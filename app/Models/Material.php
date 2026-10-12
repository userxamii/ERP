<?php

namespace App\Models;

use App\InventoryBatchQualityStatus;
use App\MaterialStockReservationStatus;
use App\ProcurementQuantity;
use Carbon\CarbonImmutable;
use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'material_category_id',
        'unit_of_measure_id',
        'minimum_stock_qty',
        'reorder_to_qty',
        'shelf_life_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'minimum_stock_qty' => 'decimal:3',
            'reorder_to_qty' => 'decimal:3',
            'shelf_life_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'material_category_id');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    public function inventoryBatches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    public function stockReservations(): HasMany
    {
        return $this->hasMany(MaterialStockReservation::class);
    }

    public function supplierMaterials(): HasMany
    {
        return $this->hasMany(SupplierMaterial::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * @return array{physical_on_hand: string, usable_on_hand: string, reserved: string, available_to_use: string, low_stock: bool, suggested_replenishment: string}
     */
    public function stockPosition(): array
    {
        $today = CarbonImmutable::today();
        $batches = $this->relationLoaded('inventoryBatches')
            ? $this->inventoryBatches
            : $this->inventoryBatches()->get();
        $reservations = $this->relationLoaded('stockReservations')
            ? $this->stockReservations
            : $this->stockReservations()->get();

        $physicalOnHandMilli = ProcurementQuantity::sumMilliUnits(
            $batches->pluck('quantity_on_hand'),
        );
        $usableOnHandMilli = ProcurementQuantity::sumMilliUnits(
            $batches
                ->filter(fn (InventoryBatch $batch): bool => $batch->quality_status === InventoryBatchQualityStatus::Available
                    && ($batch->expires_at === null || $batch->expires_at->greaterThanOrEqualTo($today)))
                ->pluck('quantity_on_hand'),
        );
        $reservedMilli = ProcurementQuantity::sumMilliUnits(
            $reservations
                ->filter(fn (MaterialStockReservation $reservation): bool => $reservation->status === MaterialStockReservationStatus::Active)
                ->pluck('quantity'),
        );
        $availableToUseMilli = max(0, $usableOnHandMilli - $reservedMilli);
        $minimumStockMilli = ProcurementQuantity::toMilliUnits((string) $this->minimum_stock_qty);
        $reorderToMilli = ProcurementQuantity::toMilliUnits((string) $this->reorder_to_qty);

        return [
            'physical_on_hand' => ProcurementQuantity::fromMilliUnits($physicalOnHandMilli),
            'usable_on_hand' => ProcurementQuantity::fromMilliUnits($usableOnHandMilli),
            'reserved' => ProcurementQuantity::fromMilliUnits($reservedMilli),
            'available_to_use' => ProcurementQuantity::fromMilliUnits($availableToUseMilli),
            'low_stock' => $availableToUseMilli <= $minimumStockMilli,
            'suggested_replenishment' => ProcurementQuantity::fromMilliUnits(
                max(0, $reorderToMilli - $availableToUseMilli),
            ),
        ];
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_materials')
            ->withPivot([
                'id',
                'supplier_sku',
                'lead_time_days',
                'minimum_order_qty',
                'order_multiple_qty',
                'quoted_unit_price',
                'currency_code',
                'is_preferred',
                'is_active',
            ])
            ->withTimestamps();
    }
}
