<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreStockOutRequest;
use App\Http\Requests\Inventory\StoreWarehouseReceiptRequest;
use App\InventoryBatchQualityStatus;
use App\InventoryMovementType;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Supplier;
use App\Models\User;
use App\ProcurementQuantity;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    private const QUALITY_STATUSES = [
        'available',
        'held',
        'rejected',
        'damaged',
    ];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'tab' => ['nullable', 'string', Rule::in(['all', ...self::QUALITY_STATUSES])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $tab = $filters['tab'] ?? 'all';
        $search = trim($filters['search'] ?? '');
        $activeMaterials = Material::query()
            ->where('is_active', true)
            ->with(['inventoryBatches', 'stockReservations'])
            ->get();

        $stats = [
            'active_materials' => $activeMaterials->count(),
            'low_stock_materials' => $activeMaterials
                ->filter(fn (Material $material): bool => $material->stockPosition()['low_stock'])
                ->count(),
            'expiring_batches' => InventoryBatch::query()
                ->where('quality_status', InventoryBatchQualityStatus::Available->value)
                ->whereBetween('expires_at', [today(), today()->addDays(30)])
                ->count(),
            'review_batches' => InventoryBatch::query()
                ->whereIn('quality_status', [
                    InventoryBatchQualityStatus::Held->value,
                    InventoryBatchQualityStatus::Rejected->value,
                    InventoryBatchQualityStatus::Damaged->value,
                ])
                ->count(),
        ];

        $categoryCounts = Material::query()
            ->where('is_active', true)
            ->selectRaw('material_category_id, count(*) as material_count')
            ->groupBy('material_category_id')
            ->get()
            ->keyBy('material_category_id');
        $categoryNames = MaterialCategory::query()
            ->whereIn('id', $categoryCounts->keys()->filter())
            ->pluck('name', 'id');
        $categories = $categoryCounts
            ->map(fn ($category): array => [
                'name' => $category->material_category_id === null
                    ? 'Uncategorized'
                    : ($categoryNames[$category->material_category_id] ?? 'Uncategorized'),
                'material_count' => (int) $category->material_count,
            ])
            ->sortBy('name')
            ->values();

        $inventory = InventoryBatch::query()
            ->with([
                'material:id,code,name,material_category_id,unit_of_measure_id',
                'material.category:id,name',
                'material.unitOfMeasure:id,code,name',
                'supplier:id,name',
            ])
            ->when($tab !== 'all', fn (Builder $query) => $query->where('quality_status', $tab))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('lot_code', 'like', "%{$search}%")
                        ->orWhereHas('material', function (Builder $materialQuery) use ($search): void {
                            $materialQuery->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('received_at')
            ->paginate(10)
            ->withQueryString()
            ->through(function (InventoryBatch $batch): array {
                $expiresAt = $batch->expires_at;
                $qualityStatus = $batch->quality_status->value;
                $priority = match (true) {
                    $expiresAt !== null && $expiresAt->isBefore(today()) => 'Expired',
                    $expiresAt !== null && $expiresAt->lessThanOrEqualTo(today()->addDays(7)) => 'Urgent',
                    $expiresAt !== null && $expiresAt->lessThanOrEqualTo(today()->addDays(30)) => 'High',
                    $qualityStatus !== InventoryBatchQualityStatus::Available->value => 'Review',
                    default => 'Normal',
                };

                return [
                    'id' => $batch->getKey(),
                    'batch_number' => $batch->lot_code,
                    'material_code' => $batch->material->code,
                    'material_name' => $batch->material->name,
                    'quantity_on_hand' => $batch->quantity_on_hand,
                    'unit_of_measure' => $batch->material->unitOfMeasure->code,
                    'category' => $batch->material->category?->name ?? 'Uncategorized',
                    'expires_at' => $expiresAt?->toDateString(),
                    'storage_location' => $batch->storage_location,
                    'priority' => $priority,
                    'quality_status' => $qualityStatus,
                ];
            });

        $recentMovements = InventoryMovement::query()
            ->with(['material:id,code,name', 'inventoryBatch:id,lot_code', 'actor:id,name'])
            ->latest('occurred_at')
            ->limit(8)
            ->get()
            ->map(fn (InventoryMovement $movement): array => $this->presentMovement($movement));

        return Inertia::render('Warehouse/Dashboard', [
            'stats' => $stats,
            'inventory' => $inventory,
            'categories' => $categories,
            'recent_movements' => $recentMovements,
            'filters' => [
                'search' => $search,
                'tab' => $tab,
            ],
        ]);
    }

    public function inventory(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer', 'exists:material_categories,id'],
            'stock' => ['nullable', Rule::in(['all', 'in_stock', 'low_stock', 'out_of_stock', 'review_required'])],
            'sort' => ['nullable', Rule::in(['code', 'name', 'category', 'available', 'batches'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $search = trim($filters['search'] ?? '');
        $stockFilter = $filters['stock'] ?? 'all';
        $sort = $filters['sort'] ?? 'name';
        $direction = $filters['direction'] ?? 'asc';

        $materials = Material::query()
            ->where('is_active', true)
            ->with([
                'category:id,name',
                'unitOfMeasure:id,code,name',
                'inventoryBatches',
                'stockReservations',
            ])
            ->get()
            ->map(function (Material $material): array {
                $position = $material->stockPosition();
                $batches = $material->inventoryBatches
                    ->filter(fn (InventoryBatch $batch): bool => $batch->quantityMilli() > 0);
                $hasReviewBatch = $batches->contains(fn (InventoryBatch $batch): bool => $batch->quality_status !== InventoryBatchQualityStatus::Available
                    || ($batch->expires_at !== null && $batch->expires_at->isBefore(today())));
                $status = match (true) {
                    $position['available_to_use'] === '0.000' && $hasReviewBatch => 'review_required',
                    $position['available_to_use'] === '0.000' => 'out_of_stock',
                    $position['low_stock'] => 'low_stock',
                    default => 'in_stock',
                };

                return [
                    'id' => (int) $material->getKey(),
                    'code' => $material->code,
                    'name' => $material->name,
                    'category' => $material->category?->name ?? 'Uncategorized',
                    'category_id' => $material->material_category_id,
                    'unit_of_measure' => $material->unitOfMeasure->code,
                    'available_quantity' => $position['available_to_use'],
                    'physical_on_hand' => $position['physical_on_hand'],
                    'total_batches' => $material->inventoryBatches->count(),
                    'storage_locations' => $batches->pluck('storage_location')->filter()->unique()->values(),
                    'next_expiration' => $batches
                        ->pluck('expires_at')
                        ->filter(fn ($date): bool => $date !== null && ! $date->isBefore(today()))
                        ->sort()
                        ->first()?->toDateString(),
                    'stock_status' => $status,
                ];
            })
            ->filter(function (array $material) use ($search, $filters, $stockFilter): bool {
                $matchesSearch = $search === ''
                    || str_contains(mb_strtolower($material['code']), mb_strtolower($search))
                    || str_contains(mb_strtolower($material['name']), mb_strtolower($search));
                $matchesCategory = ! isset($filters['category'])
                    || (int) $material['category_id'] === (int) $filters['category'];
                $matchesStock = $stockFilter === 'all' || $material['stock_status'] === $stockFilter;

                return $matchesSearch && $matchesCategory && $matchesStock;
            })
            ->sortBy(fn (array $material): string|int => match ($sort) {
                'code' => $material['code'],
                'name' => $material['name'],
                'category' => $material['category'],
                'available' => ProcurementQuantity::toMilliUnits($material['available_quantity']),
                'batches' => $material['total_batches'],
            }, SORT_REGULAR, $direction === 'desc')
            ->values();

        $perPage = 15;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $inventory = new LengthAwarePaginator(
            $materials->forPage($page, $perPage)->values(),
            $materials->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Warehouse/Inventory', [
            'inventory' => $inventory,
            'categories' => MaterialCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'category' => $filters['category'] ?? '',
                'stock' => $stockFilter,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function showMaterial(Material $material): Response
    {
        $material->load([
            'category:id,name',
            'unitOfMeasure:id,code,name',
            'inventoryBatches' => fn ($query) => $query->with(['supplier:id,name', 'receivedBy:id,name'])->latest('received_at'),
            'stockReservations',
        ]);
        $position = $material->stockPosition();
        $batches = $material->inventoryBatches->map(function (InventoryBatch $batch) use ($material): array {
            $expiresAt = $batch->expires_at;

            return [
                'id' => (int) $batch->getKey(),
                'lot_code' => $batch->lot_code,
                'supplier' => $batch->supplier?->name,
                'received_at' => $batch->received_at->toDateString(),
                'manufactured_at' => $batch->manufactured_at?->toDateString(),
                'expires_at' => $expiresAt?->toDateString(),
                'age_days' => max(0, $batch->received_at->startOfDay()->diffInDays(today(), false)),
                'days_remaining' => $expiresAt === null
                    ? null
                    : today()->diffInDays($expiresAt->startOfDay(), false),
                'quantity_on_hand' => $batch->quantity_on_hand,
                'unit_of_measure' => $material->unitOfMeasure->code,
                'storage_location' => $batch->storage_location,
                'quality_status' => $batch->quality_status->value,
                'received_by' => $batch->receivedBy?->name,
            ];
        });
        $movements = InventoryMovement::query()
            ->where('material_id', $material->getKey())
            ->with(['material:id,code,name', 'inventoryBatch:id,lot_code', 'actor:id,name'])
            ->latest('occurred_at')
            ->limit(25)
            ->get()
            ->map(fn (InventoryMovement $movement): array => $this->presentMovement($movement));

        return Inertia::render('Warehouse/MaterialDetails', [
            'material' => [
                'id' => (int) $material->getKey(),
                'code' => $material->code,
                'name' => $material->name,
                'category' => $material->category?->name ?? 'Uncategorized',
                'unit_of_measure' => $material->unitOfMeasure->code,
                ...$position,
            ],
            'batches' => $batches,
            'movements' => $movements,
        ]);
    }

    public function receive(): Response
    {
        return Inertia::render('Warehouse/Receive', [
            'materials' => Material::query()
                ->where('is_active', true)
                ->with('unitOfMeasure:id,code')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'unit_of_measure_id']),
            'suppliers' => Supplier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function storeReceipt(StoreWarehouseReceiptRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        DB::transaction(function () use ($validated, $actor): void {
            $material = Material::query()->findOrFail($validated['material_id']);
            $supplier = Supplier::query()->findOrFail($validated['supplier_id']);
            $batch = new InventoryBatch;
            $batch->forceFill([
                'material_id' => $material->getKey(),
                'supplier_id' => $supplier->getKey(),
                'received_by_user_id' => $actor->getKey(),
                'lot_code' => $validated['lot_code'],
                'received_at' => $validated['received_at'],
                'manufactured_at' => $validated['manufactured_at'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
                'storage_location' => $validated['storage_location'],
                'quantity_received' => $validated['quantity_received'],
                'quantity_on_hand' => $validated['quantity_received'],
                'quality_status' => InventoryBatchQualityStatus::Held,
            ]);
            $batch->save();

            InventoryMovement::record(
                InventoryMovementType::WarehouseReceipt,
                $material,
                $validated['quantity_received'],
                $validated['quantity_received'],
                '0.000',
                $actor,
                $batch,
                sourceReference: $validated['reference'] ?? null,
                sourceLocation: $supplier->name,
                destinationLocation: $validated['storage_location'],
                reason: 'Warehouse receipt',
            );
        });

        return redirect()
            ->route('warehouse.materials.inventory')
            ->with('success', 'Material receipt recorded and inventory updated.');
    }

    public function movements(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['all', ...array_column(InventoryMovementType::cases(), 'value')])],
        ]);
        $search = trim($filters['search'] ?? '');
        $type = $filters['type'] ?? 'all';
        $movements = InventoryMovement::query()
            ->with(['material:id,code,name', 'inventoryBatch:id,lot_code', 'actor:id,name'])
            ->when($type !== 'all', fn (Builder $query) => $query->where('movement_type', $type))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('source_reference', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhereHas('material', function (Builder $materialQuery) use ($search): void {
                            $materialQuery->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('inventoryBatch', fn (Builder $batchQuery) => $batchQuery->where('lot_code', 'like', "%{$search}%"));
                });
            })
            ->latest('occurred_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (InventoryMovement $movement): array => $this->presentMovement($movement));

        return Inertia::render('Warehouse/Movements', [
            'movements' => $movements,
            'filters' => ['search' => $search, 'type' => $type],
            'stock_out_batches' => InventoryBatch::query()
                ->where('quantity_on_hand', '>', 0)
                ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))
                ->with(['material:id,code,name,unit_of_measure_id', 'material.unitOfMeasure:id,code'])
                ->orderBy('lot_code')
                ->get()
                ->map(fn (InventoryBatch $batch): array => [
                    'id' => (int) $batch->getKey(),
                    'lot_code' => $batch->lot_code,
                    'material_code' => $batch->material->code,
                    'material_name' => $batch->material->name,
                    'quantity_on_hand' => $batch->quantity_on_hand,
                    'unit_of_measure' => $batch->material->unitOfMeasure->code,
                    'storage_location' => $batch->storage_location,
                ]),
            'types' => array_map(
                fn (InventoryMovementType $movementType): array => [
                    'value' => $movementType->value,
                    'label' => str($movementType->value)->replace('_', ' ')->title()->toString(),
                ],
                InventoryMovementType::cases(),
            ),
        ]);
    }

    public function storeStockOut(StoreStockOutRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $batch = InventoryBatch::query()->findOrFail($validated['inventory_batch_id']);
        try {
            $batch->recordStockOut(
                $validated['quantity'],
                $actor,
                $validated['destination_location'],
                $validated['reason'],
                $validated['reference'] ?? null,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['quantity' => $exception->getMessage()]);
        }

        return redirect()
            ->route('warehouse.movements')
            ->with('success', 'Stock-out recorded and batch quantity updated.');
    }

    public function alerts(): Response
    {
        $batches = InventoryBatch::query()
            ->where('quantity_on_hand', '>', 0)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', today()->addDays(30))
            ->with(['material:id,code,name,unit_of_measure_id', 'material.unitOfMeasure:id,code', 'supplier:id,name'])
            ->orderBy('expires_at')
            ->paginate(20)
            ->through(function (InventoryBatch $batch): array {
                $daysRemaining = today()->diffInDays($batch->expires_at->startOfDay(), false);
                $qualityStatus = $batch->quality_status->value;
                [$escalationStatus, $recommendedFollowUp] = match (true) {
                    $daysRemaining < 0 => ['Escalate now', 'Quarantine expired stock and request a quality review.'],
                    $qualityStatus !== InventoryBatchQualityStatus::Available->value => [
                        'QA review required',
                        'Keep this batch out of use and request an independent quality review.',
                    ],
                    $daysRemaining <= 7 => ['Urgent', 'Prioritize approved use or transfer before expiration.'],
                    default => ['Monitor', 'Apply first-expiring, first-out picking and review weekly.'],
                };

                return [
                    'id' => (int) $batch->getKey(),
                    'material_id' => (int) $batch->material->getKey(),
                    'batch_number' => $batch->lot_code,
                    'material_code' => $batch->material->code,
                    'material_name' => $batch->material->name,
                    'quantity_on_hand' => $batch->quantity_on_hand,
                    'unit_of_measure' => $batch->material->unitOfMeasure->code,
                    'storage_location' => $batch->storage_location,
                    'expires_at' => $batch->expires_at->toDateString(),
                    'days_remaining' => $daysRemaining,
                    'quality_status' => $qualityStatus,
                    'escalation_status' => $escalationStatus,
                    'recommended_follow_up' => $recommendedFollowUp,
                ];
            });

        return Inertia::render('Warehouse/Alerts', ['batches' => $batches]);
    }

    /**
     * @return array{id: int, movement_type: string, quantity: string, on_hand_delta: string, material_code: string, material_name: string, batch_number: ?string, source_location: ?string, destination_location: ?string, reference: ?string, reason: ?string, actor: ?string, occurred_at: string}
     */
    private function presentMovement(InventoryMovement $movement): array
    {
        return [
            'id' => (int) $movement->getKey(),
            'movement_type' => $movement->movement_type->value,
            'quantity' => $movement->quantity,
            'on_hand_delta' => $movement->on_hand_delta,
            'material_code' => $movement->material->code,
            'material_name' => $movement->material->name,
            'batch_number' => $movement->inventoryBatch?->lot_code,
            'source_location' => $movement->source_location,
            'destination_location' => $movement->destination_location,
            'reference' => $movement->source_reference,
            'reason' => $movement->reason,
            'actor' => $movement->actor?->name,
            'occurred_at' => $movement->occurred_at->toDateTimeString(),
        ];
    }
}
