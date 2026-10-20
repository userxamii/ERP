<?php

namespace App\Http\Controllers;

use App\InventoryBatchQualityStatus;
use App\InventoryMovementType;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\ProductionMaterialRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\ProductionMaterialRequestStatus;
use App\PurchaseRequestStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManagementWorkspaceController extends Controller
{
    private const ACCESS_ROLES = ['Management', 'System Administrator'];

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorizeRole($request);
        $filters = $this->filters($request);
        $materials = Material::query()
            ->where('is_active', true)
            ->when($filters['categoryId'] !== null, fn (Builder $query) => $query->where('material_category_id', $filters['categoryId']))
            ->with([
                'category:id,name',
                'unitOfMeasure:id,code',
                'inventoryBatches.purchaseOrderItem.purchaseOrder:id,currency_code',
                'inventoryBatches.material:id,code',
                'stockReservations',
            ])
            ->orderBy('code')
            ->get();
        $batches = $materials
            ->flatMap(fn (Material $material) => $material->inventoryBatches)
            ->filter(fn (InventoryBatch $batch): bool => $filters['location'] === null
                || $batch->storage_location === $filters['location'])
            ->values();

        $inventoryRows = $materials
            ->map(fn (Material $material): array => $this->inventoryRow(
                $material,
                $batches->where('material_id', $material->getKey())->values(),
            ))
            ->filter(fn (array $row): bool => $filters['location'] === null || $row['batchCount'] > 0)
            ->values();
        $movements = $this->movementQuery($filters)->with([
            'material:id,code,name,material_category_id',
            'material.category:id,name',
            'inventoryBatch:id,storage_location',
            'actor:id,name',
        ])->orderBy('occurred_at')->get();
        $inspections = $this->inspectionRows($filters);
        $purchaseRequests = PurchaseRequest::query()
            ->whereBetween('request_date', [$filters['from'], $filters['to']])
            ->when($filters['categoryId'] !== null, fn (Builder $query) => $query->whereHas('items.material', fn (Builder $materialQuery) => $materialQuery->where('material_category_id', $filters['categoryId'])))
            ->get();
        $purchaseOrders = PurchaseOrder::query()
            ->whereBetween('order_date', [$filters['from'], $filters['to']])
            ->when($filters['categoryId'] !== null, fn (Builder $query) => $query->whereHas('items.material', fn (Builder $materialQuery) => $materialQuery->where('material_category_id', $filters['categoryId'])))
            ->get();
        $productionRequests = ProductionMaterialRequest::query()
            ->whereBetween('created_at', [
                CarbonImmutable::parse($filters['from'])->startOfDay(),
                CarbonImmutable::parse($filters['to'])->endOfDay(),
            ])
            ->when($filters['categoryId'] !== null, fn (Builder $query) => $query->whereHas('material', fn (Builder $materialQuery) => $materialQuery->where('material_category_id', $filters['categoryId'])))
            ->with('material:id,code,name')
            ->latest()
            ->get();
        $pendingPurchaseApprovalCount = PurchaseRequest::query()
            ->where('status', PurchaseRequestStatus::Submitted->value)
            ->count();
        $pendingProductionApprovalCount = ProductionMaterialRequest::query()
            ->where('status', ProductionMaterialRequestStatus::PendingApproval->value)
            ->count();

        return response()->json([
            'filters' => $filters,
            'categories' => DB::table('material_categories')->orderBy('name')->get(['id', 'name']),
            'locations' => InventoryBatch::query()
                ->whereNotNull('storage_location')
                ->where('storage_location', '!=', '')
                ->distinct()
                ->orderBy('storage_location')
                ->pluck('storage_location'),
            'summary' => $this->summary(
                $inventoryRows,
                $batches,
                $movements,
                $pendingPurchaseApprovalCount,
                $pendingProductionApprovalCount,
            ),
            'inventoryByCategory' => $this->inventoryByCategory($inventoryRows),
            'agingBuckets' => $this->agingBuckets($batches),
            'expiryTrend' => $this->expiryTrend($batches),
            'movementTrend' => $this->movementTrend($movements, $filters),
            'topMaterials' => $this->topMaterials($movements),
            'topUsers' => $this->topUsers($movements),
            'topWorkCenters' => $this->topWorkCenters($movements),
            'departments' => $this->departmentReports($movements, $purchaseRequests, $purchaseOrders, $inspections, $productionRequests),
            'alerts' => $this->alerts($inventoryRows, $batches),
            'nearExpiryCostExposure' => $this->nearExpiryCostExposure($batches),
            'inventoryReport' => $inventoryRows,
            'movementReport' => $movements->map(fn (InventoryMovement $movement): array => [
                'date' => $movement->occurred_at?->toDateTimeString(),
                'materialCode' => $movement->material->code,
                'material' => $movement->material->name,
                'category' => $movement->material->category?->name ?? 'Uncategorized',
                'type' => $movement->movement_type->value,
                'quantity' => $movement->quantity,
                'location' => $movement->inventoryBatch?->storage_location
                    ?? $movement->destination_location
                    ?? $movement->source_location
                    ?? '—',
                'user' => $movement->actor?->name ?? '—',
            ])->values(),
            'inspectionReport' => $inspections,
            'productionReport' => $productionRequests->map(fn (ProductionMaterialRequest $materialRequest): array => [
                'requestNumber' => $materialRequest->request_number,
                'productionOrder' => $materialRequest->production_order ?? '—',
                'materialCode' => $materialRequest->material->code,
                'material' => $materialRequest->material->name,
                'requestedQuantity' => $materialRequest->requested_qty,
                'fulfilledQuantity' => $materialRequest->fulfilled_qty,
                'status' => $materialRequest->status->value,
                'requestedAt' => $materialRequest->created_at?->toDateTimeString(),
            ])->values(),
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * @return array{from: string, to: string, categoryId: ?int, location: ?string}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'category_id' => ['nullable', 'integer', 'exists:material_categories,id'],
            'location' => ['nullable', 'string', 'max:120'],
        ]);
        $today = CarbonImmutable::today();
        $from = isset($validated['from'])
            ? CarbonImmutable::parse($validated['from'])->startOfDay()
            : $today->subMonths(5)->startOfMonth();
        $to = isset($validated['to'])
            ? CarbonImmutable::parse($validated['to'])->endOfDay()
            : $today->endOfDay();

        if ($from->diffInDays($to) > 366) {
            throw ValidationException::withMessages([
                'from' => 'The selected report period cannot exceed 366 days.',
            ]);
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'categoryId' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            'location' => isset($validated['location']) && $validated['location'] !== ''
                ? $validated['location']
                : null,
        ];
    }

    /**
     * @param  Collection<int, InventoryBatch>  $batches
     * @return array<string, mixed>
     */
    private function inventoryRow(Material $material, Collection $batches): array
    {
        $usableBatches = $batches->filter(fn (InventoryBatch $batch): bool => $batch->quality_status === InventoryBatchQualityStatus::Available
            && ($batch->expires_at === null || $batch->expires_at->greaterThanOrEqualTo(today())));
        $physicalQuantity = $batches->sum(fn (InventoryBatch $batch): float => (float) $batch->quantity_on_hand);
        $usableQuantity = $usableBatches->sum(fn (InventoryBatch $batch): float => (float) $batch->quantity_on_hand);
        $reservedQuantity = (float) $material->stockPosition()['reserved'];
        $quantityByCurrency = [];

        foreach ($batches as $batch) {
            $purchaseOrderItem = $batch->purchaseOrderItem;
            $currency = $purchaseOrderItem?->purchaseOrder?->currency_code;

            if ($purchaseOrderItem === null || $currency === null) {
                continue;
            }

            $quantityByCurrency[$currency] = ($quantityByCurrency[$currency] ?? 0)
                + ((float) $batch->quantity_on_hand * (float) $purchaseOrderItem->unit_price);
        }

        return [
            'materialId' => (int) $material->getKey(),
            'code' => $material->code,
            'name' => $material->name,
            'category' => $material->category?->name ?? 'Uncategorized',
            'unit' => $material->unitOfMeasure?->code ?? '',
            'onHandQuantity' => number_format($physicalQuantity, 3, '.', ''),
            'usableQuantity' => number_format($usableQuantity, 3, '.', ''),
            'availableQuantity' => number_format(max(0, $usableQuantity - $reservedQuantity), 3, '.', ''),
            'minimumQuantity' => $material->minimum_stock_qty,
            'lowStock' => $usableQuantity - $reservedQuantity <= (float) $material->minimum_stock_qty,
            'batchCount' => $batches->count(),
            'valueByCurrency' => collect($quantityByCurrency)->map(fn (float $value): string => number_format($value, 2, '.', ''))->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $inventoryRows
     * @param  Collection<int, InventoryBatch>  $batches
     * @param  Collection<int, InventoryMovement>  $movements
     * @return array<string, mixed>
     */
    private function summary(
        Collection $inventoryRows,
        Collection $batches,
        Collection $movements,
        int $pendingPurchaseApprovalCount,
        int $pendingProductionApprovalCount,
    ): array {
        $values = [];

        foreach ($inventoryRows as $row) {
            foreach ($row['valueByCurrency'] as $currency => $value) {
                $values[$currency] = ($values[$currency] ?? 0) + (float) $value;
            }
        }

        $now = CarbonImmutable::today();

        return [
            'materialCount' => $inventoryRows->count(),
            'onHandQuantity' => number_format($inventoryRows->sum(fn (array $row): float => (float) $row['onHandQuantity']), 3, '.', ''),
            'lowStockCount' => $inventoryRows->where('lowStock', true)->count(),
            'expiredBatchCount' => $batches->filter(fn (InventoryBatch $batch): bool => $batch->quantity_on_hand > 0
                && $batch->expires_at !== null && $batch->expires_at->lt($now))->count(),
            'expiringBatchCount' => $batches->filter(fn (InventoryBatch $batch): bool => $batch->quantity_on_hand > 0
                && $batch->quality_status === InventoryBatchQualityStatus::Available
                && $batch->expires_at !== null
                && $batch->expires_at->betweenIncluded($now, $now->addDays(30)))->count(),
            'heldBatchCount' => $batches->filter(fn (InventoryBatch $batch): bool => $batch->quality_status !== InventoryBatchQualityStatus::Available)->count(),
            'pendingPurchaseApprovals' => $pendingPurchaseApprovalCount,
            'pendingProductionApprovals' => $pendingProductionApprovalCount,
            'issuedQuantity' => number_format($movements
                ->where('movement_type', InventoryMovementType::StockOut)
                ->sum(fn (InventoryMovement $movement): float => (float) $movement->quantity), 3, '.', ''),
            'inventoryValueByCurrency' => collect($values)->map(fn (float $value): string => number_format($value, 2, '.', ''))->all(),
            'unvaluedBatchCount' => $batches->filter(fn (InventoryBatch $batch): bool => $batch->quantity_on_hand > 0
                && $batch->purchaseOrderItem === null)->count(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $inventoryRows
     * @return Collection<int, array{name: string, quantity: string}>
     */
    private function inventoryByCategory(Collection $inventoryRows): Collection
    {
        return $inventoryRows
            ->groupBy('category')
            ->map(fn (Collection $rows, string $category): array => [
                'name' => $category,
                'quantity' => number_format($rows->sum(fn (array $row): float => (float) $row['onHandQuantity']), 3, '.', ''),
            ])
            ->sortByDesc(fn (array $row): float => (float) $row['quantity'])
            ->values();
    }

    /**
     * @param  Collection<int, InventoryBatch>  $batches
     * @return Collection<int, array{label: string, quantity: string}>
     */
    private function agingBuckets(Collection $batches): Collection
    {
        $buckets = ['0-30 days' => 0.0, '31-60 days' => 0.0, '61-90 days' => 0.0, '91+ days' => 0.0];

        foreach ($batches as $batch) {
            if ($batch->received_at === null || (float) $batch->quantity_on_hand <= 0) {
                continue;
            }

            $days = $batch->received_at->diffInDays(today());
            $label = match (true) {
                $days <= 30 => '0-30 days',
                $days <= 60 => '31-60 days',
                $days <= 90 => '61-90 days',
                default => '91+ days',
            };
            $buckets[$label] += (float) $batch->quantity_on_hand;
        }

        return collect($buckets)->map(fn (float $quantity, string $label): array => [
            'label' => $label,
            'quantity' => number_format($quantity, 3, '.', ''),
        ])->values();
    }

    /**
     * @param  Collection<int, InventoryBatch>  $batches
     * @return Collection<int, array{month: string, batchCount: int, quantity: string}>
     */
    private function expiryTrend(Collection $batches): Collection
    {
        $months = collect(range(0, 5))->mapWithKeys(fn (int $offset): array => [
            today()->startOfMonth()->addMonths($offset)->format('Y-m') => ['batchCount' => 0, 'quantity' => 0.0],
        ]);

        foreach ($batches as $batch) {
            if ($batch->expires_at === null || (float) $batch->quantity_on_hand <= 0) {
                continue;
            }

            $month = $batch->expires_at->format('Y-m');
            if ($months->has($month)) {
                $monthData = $months->get($month);
                $monthData['batchCount']++;
                $monthData['quantity'] += (float) $batch->quantity_on_hand;
                $months->put($month, $monthData);
            }
        }

        return $months->map(fn (array $row, string $month): array => [
            'month' => $month,
            'batchCount' => $row['batchCount'],
            'quantity' => number_format($row['quantity'], 3, '.', ''),
        ])->values();
    }

    /**
     * @param  Collection<int, InventoryMovement>  $movements
     * @param  array{from: string, to: string, categoryId: ?int, location: ?string}  $filters
     * @return Collection<int, array{month: string, received: string, issued: string, rejected: string}>
     */
    private function movementTrend(Collection $movements, array $filters): Collection
    {
        $period = CarbonImmutable::parse($filters['from'])->startOfMonth();
        $lastMonth = CarbonImmutable::parse($filters['to'])->startOfMonth();
        $months = collect();

        while ($period->lessThanOrEqualTo($lastMonth)) {
            $months->put($period->format('Y-m'), [
                'received' => 0.0,
                'issued' => 0.0,
                'rejected' => 0.0,
            ]);
            $period = $period->addMonth();
        }

        foreach ($movements as $movement) {
            $month = $movement->occurred_at->format('Y-m');
            $column = match ($movement->movement_type) {
                InventoryMovementType::GoodsReceiptAccepted,
                InventoryMovementType::WarehouseReceipt => 'received',
                InventoryMovementType::StockOut => 'issued',
                InventoryMovementType::GoodsReceiptRejected => 'rejected',
                default => null,
            };

            if ($column !== null && $months->has($month)) {
                $monthData = $months->get($month);
                $monthData[$column] += (float) $movement->quantity;
                $months->put($month, $monthData);
            }
        }

        return $months->map(fn (array $row, string $month): array => [
            'month' => $month,
            'received' => number_format($row['received'], 3, '.', ''),
            'issued' => number_format($row['issued'], 3, '.', ''),
            'rejected' => number_format($row['rejected'], 3, '.', ''),
        ])->values();
    }

    /**
     * @param  Collection<int, InventoryMovement>  $movements
     * @return Collection<int, array{code: string, name: string, quantity: string}>
     */
    private function topMaterials(Collection $movements): Collection
    {
        return $movements
            ->where('movement_type', InventoryMovementType::StockOut)
            ->groupBy('material_id')
            ->map(fn (Collection $rows): array => [
                'code' => $rows->first()->material->code,
                'name' => $rows->first()->material->name,
                'quantity' => number_format($rows->sum(fn (InventoryMovement $movement): float => (float) $movement->quantity), 3, '.', ''),
            ])
            ->sortByDesc(fn (array $row): float => (float) $row['quantity'])
            ->take(5)
            ->values();
    }

    /**
     * @param  Collection<int, InventoryMovement>  $movements
     * @return Collection<int, array{name: string, movementCount: int}>
     */
    private function topUsers(Collection $movements): Collection
    {
        $counts = $movements
            ->whereNotNull('actor_user_id')
            ->countBy('actor_user_id')
            ->sortDesc()
            ->take(5);
        $users = User::query()->whereIn('id', $counts->keys())->pluck('name', 'id');

        return $counts->map(fn (int $count, int|string $userId): array => [
            'name' => $users[$userId] ?? 'Unknown user',
            'movementCount' => $count,
        ])->values();
    }

    /**
     * @param  Collection<int, InventoryMovement>  $movements
     * @return Collection<int, array{name: string, issuedQuantity: string}>
     */
    private function topWorkCenters(Collection $movements): Collection
    {
        return $movements
            ->filter(fn (InventoryMovement $movement): bool => $movement->movement_type === InventoryMovementType::StockOut
                && $movement->destination_location !== null
                && $movement->destination_location !== '')
            ->groupBy('destination_location')
            ->map(fn (Collection $rows, string $location): array => [
                'name' => $location,
                'issuedQuantity' => number_format($rows->sum(fn (InventoryMovement $movement): float => (float) $movement->quantity), 3, '.', ''),
            ])
            ->sortByDesc(fn (array $row): float => (float) $row['issuedQuantity'])
            ->take(5)
            ->values();
    }

    /**
     * @param  Collection<int, InventoryMovement>  $movements
     * @param  Collection<int, PurchaseRequest>  $purchaseRequests
     * @param  Collection<int, PurchaseOrder>  $purchaseOrders
     * @param  Collection<int, array<string, mixed>>  $inspections
     * @param  Collection<int, ProductionMaterialRequest>  $productionRequests
     * @return array<string, mixed>
     */
    private function departmentReports(
        Collection $movements,
        Collection $purchaseRequests,
        Collection $purchaseOrders,
        Collection $inspections,
        Collection $productionRequests,
    ): array {
        return [
            'warehouse' => [
                'movementCount' => $movements->count(),
                'receivedQuantity' => number_format($movements
                    ->whereIn('movement_type', [InventoryMovementType::GoodsReceiptAccepted, InventoryMovementType::WarehouseReceipt])
                    ->sum(fn (InventoryMovement $movement): float => (float) $movement->quantity), 3, '.', ''),
                'postedReceipts' => $movements
                    ->where('movement_type', InventoryMovementType::GoodsReceiptAccepted)
                    ->count(),
            ],
            'procurement' => [
                'requestCount' => $purchaseRequests->count(),
                'pendingApprovals' => $purchaseRequests->where('status', PurchaseRequestStatus::Submitted)->count(),
                'orderCount' => $purchaseOrders->count(),
            ],
            'quality' => [
                'inspectionCount' => $inspections->count(),
                'rejectedInspectionCount' => $inspections->where('result', 'rejected')->count(),
                'rejectedReceiptQuantity' => number_format($movements
                    ->where('movement_type', InventoryMovementType::GoodsReceiptRejected)
                    ->sum(fn (InventoryMovement $movement): float => (float) $movement->quantity), 3, '.', ''),
            ],
            'production' => [
                'requestCount' => $productionRequests->count(),
                'pendingApprovals' => $productionRequests->where('status', ProductionMaterialRequestStatus::PendingApproval)->count(),
                'fulfilledQuantity' => number_format($movements
                    ->filter(fn (InventoryMovement $movement): bool => $movement->movement_type === InventoryMovementType::StockOut
                        && $movement->reason === 'Approved production material request')
                    ->sum(fn (InventoryMovement $movement): float => (float) $movement->quantity), 3, '.', ''),
            ],
        ];
    }

    /**
     * @param  array{from: string, to: string, categoryId: ?int, location: ?string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function inspectionRows(array $filters): Collection
    {
        return DB::table('quality_inspections')
            ->join('inventory_batches', 'quality_inspections.inventory_batch_id', '=', 'inventory_batches.id')
            ->join('materials', 'inventory_batches.material_id', '=', 'materials.id')
            ->leftJoin('material_categories', 'materials.material_category_id', '=', 'material_categories.id')
            ->leftJoin('users', 'quality_inspections.inspected_by', '=', 'users.id')
            ->whereBetween('quality_inspections.inspected_at', [
                CarbonImmutable::parse($filters['from'])->startOfDay(),
                CarbonImmutable::parse($filters['to'])->endOfDay(),
            ])
            ->when($filters['categoryId'] !== null, fn ($query) => $query->where('materials.material_category_id', $filters['categoryId']))
            ->when($filters['location'] !== null, fn ($query) => $query->where('inventory_batches.storage_location', $filters['location']))
            ->orderByDesc('quality_inspections.inspected_at')
            ->get([
                'quality_inspections.inspected_at as inspectedAt',
                'quality_inspections.result',
                'quality_inspections.notes',
                'materials.code as materialCode',
                'materials.name as material',
                'material_categories.name as category',
                'inventory_batches.lot_code as lotCode',
                'inventory_batches.storage_location as location',
                'users.name as inspector',
            ]);
    }

    /**
     * @param  array{from: string, to: string, categoryId: ?int, location: ?string}  $filters
     * @return Builder<InventoryMovement>
     */
    private function movementQuery(array $filters): Builder
    {
        return InventoryMovement::query()
            ->whereBetween('occurred_at', [
                CarbonImmutable::parse($filters['from'])->startOfDay(),
                CarbonImmutable::parse($filters['to'])->endOfDay(),
            ])
            ->when($filters['categoryId'] !== null, fn (Builder $query) => $query->whereHas('material', fn (Builder $materialQuery) => $materialQuery->where('material_category_id', $filters['categoryId'])))
            ->when($filters['location'] !== null, fn (Builder $query) => $query->where(function (Builder $locationQuery) use ($filters): void {
                $locationQuery
                    ->where('source_location', $filters['location'])
                    ->orWhere('destination_location', $filters['location'])
                    ->orWhereHas('inventoryBatch', fn (Builder $batchQuery) => $batchQuery->where('storage_location', $filters['location']));
            }));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $inventoryRows
     * @param  Collection<int, InventoryBatch>  $batches
     * @return Collection<int, array<string, mixed>>
     */
    private function alerts(Collection $inventoryRows, Collection $batches): Collection
    {
        $alerts = $inventoryRows
            ->where('lowStock', true)
            ->map(fn (array $row): array => [
                'severity' => 'high',
                'title' => "{$row['code']} · {$row['name']} is below minimum stock",
                'detail' => "{$row['availableQuantity']} {$row['unit']} available; minimum {$row['minimumQuantity']}.",
            ]);

        $batchAlerts = $batches
            ->filter(fn (InventoryBatch $batch): bool => (float) $batch->quantity_on_hand > 0
                && ($batch->expires_at !== null && $batch->expires_at->lt(today())
                    || $batch->quality_status !== InventoryBatchQualityStatus::Available
                    || ($batch->expires_at !== null && $batch->expires_at->lte(today()->addDays(30)))))
            ->sortBy(fn (InventoryBatch $batch): string => $batch->expires_at?->toDateString() ?? '9999-12-31')
            ->take(10)
            ->map(fn (InventoryBatch $batch): array => [
                'severity' => $batch->expires_at !== null && $batch->expires_at->lt(today()) ? 'critical' : 'medium',
                'title' => "{$batch->material->code} · {$batch->lot_code}",
                'detail' => $batch->quality_status->value !== InventoryBatchQualityStatus::Available->value
                    ? "QA status: {$batch->quality_status->value}; {$batch->quantity_on_hand} units on hand."
                    : ($batch->expires_at !== null && $batch->expires_at->lt(today())
                        ? "Expired {$batch->expires_at->toDateString()}; quarantine and review."
                        : "Expires {$batch->expires_at?->toDateString()}; {$batch->quantity_on_hand} units on hand."),
            ]);

        return $alerts->concat($batchAlerts)->take(12)->values();
    }

    /**
     * @param  Collection<int, InventoryBatch>  $batches
     * @return Collection<int, array<string, mixed>>
     */
    private function nearExpiryCostExposure(Collection $batches): Collection
    {
        return $batches
            ->filter(fn (InventoryBatch $batch): bool => $batch->quality_status === InventoryBatchQualityStatus::Available
                && (float) $batch->quantity_on_hand > 0
                && $batch->expires_at !== null
                && $batch->expires_at->betweenIncluded(today(), today()->addDays(30))
                && $batch->purchaseOrderItem?->purchaseOrder?->currency_code !== null)
            ->sortBy(fn (InventoryBatch $batch): string => $batch->expires_at->toDateString())
            ->map(function (InventoryBatch $batch): array {
                $purchaseOrderItem = $batch->purchaseOrderItem;

                return [
                    'materialCode' => $batch->material->code,
                    'material' => $batch->material->name,
                    'lotCode' => $batch->lot_code,
                    'expiresAt' => $batch->expires_at->toDateString(),
                    'quantity' => $batch->quantity_on_hand,
                    'currency' => $purchaseOrderItem->purchaseOrder->currency_code,
                    'estimatedValue' => number_format(
                        (float) $batch->quantity_on_hand * (float) $purchaseOrderItem->unit_price,
                        2,
                        '.',
                        '',
                    ),
                ];
            })
            ->values();
    }

    private function authorizeRole(Request $request): void
    {
        abort_unless(
            $request->user() !== null && in_array($request->user()->role, self::ACCESS_ROLES, true),
            403,
        );
    }
}
