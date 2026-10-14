<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\InventoryBatchQualityStatus;
use App\Models\InventoryBatch;
use App\Models\Material;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        $inventory = InventoryBatch::query()
            ->with([
                'material:id,code,name,material_category_id,unit_of_measure_id',
                'material.category:id,name',
                'material.unitOfMeasure:id,code,name',
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
                    'priority' => $priority,
                    'quality_status' => $qualityStatus,
                ];
            });

        return Inertia::render('Warehouse/Dashboard', [
            'stats' => $stats,
            'inventory' => $inventory,
            'filters' => [
                'search' => $search,
                'tab' => $tab,
            ],
        ]);
    }
}
