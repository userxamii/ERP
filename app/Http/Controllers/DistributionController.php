<?php

namespace App\Http\Controllers;

use App\DeliveryStatus;
use App\InventoryMovementType;
use App\Models\Delivery;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\ProcurementQuantity;
use App\PurchaseOrderStatus;
use App\PurchaseRequestApprovalDecision;
use App\PurchaseRequestStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DistributionController extends Controller
{
    private const ACCESSIBLE_ROLES = [
        'Sales & Distribution Staff',
        'Management',
        'System Administrator',
    ];

    public function overview(Request $request): Response
    {
        $this->authorizeDistributionAccess($request);
        $filters = $this->validatedFilters($request);
        $report = $this->availabilitySnapshot($filters);

        return Inertia::render('Distribution/Overview', [
            ...$report,
            'notices' => $this->supplyNotices($report['materials']),
        ]);
    }

    public function reports(Request $request): Response
    {
        $this->authorizeDistributionAccess($request);
        $filters = $this->validatedFilters($request);
        $report = $this->availabilitySnapshot($filters);

        return Inertia::render('Distribution/AvailabilityReports', $report);
    }

    public function notifications(Request $request): Response
    {
        $this->authorizeDistributionAccess($request);
        $filters = $this->validatedFilters($request);
        $report = $this->availabilitySnapshot($filters);

        return Inertia::render('Distribution/Notifications', [
            'notices' => $this->supplyNotices($report['materials']),
            'filters' => $report['filters'],
            'summary' => $report['summary'],
        ]);
    }

    /**
     * @return array{
     *   filters: array{from: string, to: string, category: int|string, search: string, risk: string},
     *   categories: Collection<int, array{id: int, name: string}>,
     *   summary: array{approved_quantity: string, usable_stock: string, open_inbound: string, shortage_materials: int, risk_materials: int},
     *   materials: Collection<int, array<string, mixed>>,
     *   trend: Collection<int, array{date: string, increased: string, decreased: string, net_change: string}>
     * }
     */
    private function availabilitySnapshot(array $filters): array
    {
        $from = CarbonImmutable::parse($filters['from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['to'])->endOfDay();
        $approvedRequests = PurchaseRequest::query()
            ->where('status', PurchaseRequestStatus::Approved->value)
            ->whereHas('approvals', fn (Builder $query) => $query
                ->where('decision', PurchaseRequestApprovalDecision::Approved->value)
                ->whereBetween('decided_at', [$from, $to]))
            ->with([
                'items.material:id,code,name,material_category_id,unit_of_measure_id,minimum_stock_qty',
                'items.purchaseOrderAllocations.purchaseOrderItem.purchaseOrder',
            ])
            ->get();
        $approvedItemsByMaterial = collect();
        foreach ($approvedRequests as $purchaseRequest) {
            foreach ($purchaseRequest->items as $item) {
                $approvedItemsByMaterial->push([
                    'item' => $item,
                    'request' => $purchaseRequest,
                ]);
            }
        }

        $approvedQuantityByMaterial = $approvedItemsByMaterial
            ->groupBy(fn (array $row): int => (int) $row['item']->material_id)
            ->map(fn (Collection $items): int => $items->sum(
                fn (array $row): int => ProcurementQuantity::toMilliUnits((string) $row['item']->requested_qty),
            ));
        $inboundByMaterial = PurchaseOrderItem::query()
            ->whereHas('purchaseOrder', fn (Builder $query) => $query->where('status', PurchaseOrderStatus::Ordered->value))
            ->with(['deliveryItems.delivery', 'goodsReceiptItems.goodsReceipt'])
            ->get()
            ->groupBy('material_id')
            ->map(fn (Collection $items): int => $items->sum(
                fn (PurchaseOrderItem $item): int => $item->openInboundMilli(),
            ));

        $materials = Material::query()
            ->where('is_active', true)
            ->when($filters['category'] !== '', fn (Builder $query) => $query->where('material_category_id', $filters['category']))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->with([
                'category:id,name',
                'unitOfMeasure:id,code',
                'inventoryBatches',
                'stockReservations',
            ])
            ->orderBy('code')
            ->get()
            ->map(function (Material $material) use ($approvedQuantityByMaterial, $inboundByMaterial, $approvedItemsByMaterial): array {
                $stock = $material->stockPosition();
                $approvedMilli = $approvedQuantityByMaterial->get((int) $material->getKey(), 0);
                $inboundMilli = $inboundByMaterial->get((int) $material->getKey(), 0);
                $usableMilli = ProcurementQuantity::toMilliUnits($stock['available_to_use']);
                $shortageMilli = max(0, $approvedMilli - $usableMilli - $inboundMilli);
                $risk = match (true) {
                    $shortageMilli > 0 => 'shortage',
                    $stock['low_stock'] => 'low_stock',
                    $inboundMilli > 0 => 'inbound',
                    default => 'sufficient',
                };
                $requests = $approvedItemsByMaterial
                    ->filter(fn (array $row): bool => (int) $row['item']->material_id === (int) $material->getKey())
                    ->map(fn (array $row): array => [
                        'request_number' => $row['request']->request_number,
                        'required_date' => $row['request']->required_date?->toDateString(),
                        'approved_quantity' => $row['item']->requested_qty,
                    ])
                    ->values();

                return [
                    'id' => (int) $material->getKey(),
                    'code' => $material->code,
                    'name' => $material->name,
                    'category' => $material->category?->name ?? 'Uncategorized',
                    'category_id' => $material->material_category_id,
                    'unit' => $material->unitOfMeasure->code,
                    'approved_quantity' => ProcurementQuantity::fromMilliUnits($approvedMilli),
                    'usable_stock' => $stock['available_to_use'],
                    'open_inbound' => ProcurementQuantity::fromMilliUnits($inboundMilli),
                    'shortage_quantity' => ProcurementQuantity::fromMilliUnits($shortageMilli),
                    'risk' => $risk,
                    'requests' => $requests,
                ];
            })
            ->filter(fn (array $material): bool => $filters['risk'] === 'all' || $material['risk'] === $filters['risk'])
            ->values();

        $movementRows = InventoryMovement::query()
            ->whereIn('movement_type', [
                InventoryMovementType::GoodsReceiptAccepted->value,
                InventoryMovementType::StockOut->value,
                InventoryMovementType::ReservationCreated->value,
                InventoryMovementType::ReservationReleased->value,
            ])
            ->whereBetween('occurred_at', [$from, $to])
            ->get(['occurred_at', 'on_hand_delta', 'reserved_delta']);
        $dailyMovement = $movementRows
            ->groupBy(fn (InventoryMovement $movement): string => $movement->occurred_at->toDateString())
            ->map(fn (Collection $rows): array => [
                'increased' => $rows->sum(function (InventoryMovement $movement): int {
                    $delta = ProcurementQuantity::toSignedMilliUnits((string) $movement->on_hand_delta)
                        - ProcurementQuantity::toSignedMilliUnits((string) $movement->reserved_delta);

                    return max(0, $delta);
                }),
                'decreased' => $rows->sum(function (InventoryMovement $movement): int {
                    $delta = ProcurementQuantity::toSignedMilliUnits((string) $movement->on_hand_delta)
                        - ProcurementQuantity::toSignedMilliUnits((string) $movement->reserved_delta);

                    return abs(min(0, $delta));
                }),
            ]);
        $trend = collect();
        for ($day = $from->startOfDay(); $day->lessThanOrEqualTo($to->startOfDay()); $day = $day->addDay()) {
            $quantities = $dailyMovement->get($day->toDateString(), ['increased' => 0, 'decreased' => 0]);
            $increasedMilli = $quantities['increased'];
            $decreasedMilli = $quantities['decreased'];
            $trend->push([
                'date' => $day->toDateString(),
                'increased' => ProcurementQuantity::fromMilliUnits($increasedMilli),
                'decreased' => ProcurementQuantity::fromMilliUnits($decreasedMilli),
                'net_change' => ProcurementQuantity::fromSignedMilliUnits($increasedMilli - $decreasedMilli),
            ]);
        }

        return [
            'filters' => $filters,
            'categories' => MaterialCategory::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (MaterialCategory $category): array => [
                    'id' => (int) $category->getKey(),
                    'name' => $category->name,
                ]),
            'summary' => [
                'approved_quantity' => ProcurementQuantity::fromMilliUnits(
                    $materials->sum(fn (array $material): int => ProcurementQuantity::toMilliUnits($material['approved_quantity'])),
                ),
                'usable_stock' => ProcurementQuantity::fromMilliUnits(
                    $materials->sum(fn (array $material): int => ProcurementQuantity::toMilliUnits($material['usable_stock'])),
                ),
                'open_inbound' => ProcurementQuantity::fromMilliUnits(
                    $materials->sum(fn (array $material): int => ProcurementQuantity::toMilliUnits($material['open_inbound'])),
                ),
                'shortage_materials' => $materials->where('risk', 'shortage')->count(),
                'risk_materials' => $materials->whereIn('risk', ['shortage', 'low_stock'])->count(),
            ],
            'materials' => $materials,
            'trend' => $trend,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $materials
     * @return Collection<int, array{id: string, severity: string, title: string, detail: string, occurred_at: string, href: string}>
     */
    private function supplyNotices(Collection $materials): Collection
    {
        $notices = collect();
        foreach ($materials as $material) {
            if ($material['risk'] === 'shortage') {
                $notices->push([
                    'id' => 'shortage-'.$material['id'],
                    'severity' => 'high',
                    'title' => 'Approved demand exceeds available supply: '.$material['name'],
                    'detail' => $material['shortage_quantity'].' '.$material['unit'].' shortage after usable stock and open inbound orders.',
                    'occurred_at' => now()->toDateTimeString(),
                    'href' => route('distribution.reports', ['search' => $material['code']]),
                ]);
            } elseif ($material['risk'] === 'low_stock') {
                $notices->push([
                    'id' => 'low-stock-'.$material['id'],
                    'severity' => 'medium',
                    'title' => 'Low usable stock: '.$material['name'],
                    'detail' => 'Usable stock is at or below the material minimum level.',
                    'occurred_at' => now()->toDateTimeString(),
                    'href' => route('distribution.reports', ['search' => $material['code']]),
                ]);
            }
        }

        $overdueDeliveries = Delivery::query()
            ->whereIn('status', [DeliveryStatus::Dispatched->value, DeliveryStatus::PartiallyReceived->value])
            ->whereNotNull('expected_arrival_date')
            ->whereDate('expected_arrival_date', '<', today())
            ->whereHas('purchaseOrder', fn (Builder $query) => $query->where('status', PurchaseOrderStatus::Ordered->value))
            ->with(['supplier:id,name'])
            ->orderBy('expected_arrival_date')
            ->limit(50)
            ->get();
        foreach ($overdueDeliveries as $delivery) {
            $notices->push([
                'id' => 'overdue-delivery-'.$delivery->getKey(),
                'severity' => 'high',
                'title' => 'Overdue supply delivery: '.$delivery->delivery_number,
                'detail' => $delivery->supplier->name.' expected '.$delivery->expected_arrival_date->toDateString().'.',
                'occurred_at' => $delivery->expected_arrival_date->toDateString(),
                'href' => route('distribution.reports', ['from' => today()->subDays(30)->toDateString(), 'to' => today()->toDateString()]),
            ]);
        }

        $expiringBatches = InventoryBatch::query()
            ->where('quantity_on_hand', '>', 0)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', today()->addDays(30))
            ->with('material:id,code,name')
            ->orderBy('expires_at')
            ->limit(50)
            ->get();
        foreach ($expiringBatches as $batch) {
            $expired = $batch->expires_at->isBefore(today());
            $notices->push([
                'id' => 'expiry-'.$batch->getKey(),
                'severity' => $expired ? 'high' : 'medium',
                'title' => ($expired ? 'Expired inventory: ' : 'Inventory nearing expiration: ').$batch->material->name,
                'detail' => 'Batch '.($batch->lot_code ?? 'without lot code').' expires '.$batch->expires_at->toDateString().'.',
                'occurred_at' => $batch->expires_at->toDateString(),
                'href' => route('distribution.reports', ['search' => $batch->material->code]),
            ]);
        }

        return $notices
            ->sortBy(fn (array $notice): array => [
                $notice['severity'] === 'high' ? 0 : 1,
                $notice['occurred_at'],
                $notice['title'],
            ])
            ->values();
    }

    /**
     * @return array{from: string, to: string, category: int|string, search: string, risk: string}
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'category' => ['nullable', 'integer', 'exists:material_categories,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'risk' => ['nullable', 'in:all,shortage,low_stock,inbound,sufficient'],
        ]);
        $from = $validated['from'] ?? today()->subDays(29)->toDateString();
        $to = $validated['to'] ?? today()->toDateString();

        if (CarbonImmutable::parse($from)->greaterThan(CarbonImmutable::parse($to))) {
            throw ValidationException::withMessages([
                'from' => 'The report start date must be on or before the end date.',
            ]);
        }

        if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 365) {
            throw ValidationException::withMessages([
                'to' => 'The report period cannot exceed 366 days.',
            ]);
        }

        return [
            'from' => $from,
            'to' => $to,
            'category' => $validated['category'] ?? '',
            'search' => trim($validated['search'] ?? ''),
            'risk' => $validated['risk'] ?? 'all',
        ];
    }

    private function authorizeDistributionAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User && in_array($user->role, self::ACCESSIBLE_ROLES, true),
            403,
        );
    }
}
