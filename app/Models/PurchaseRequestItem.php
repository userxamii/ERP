<?php

namespace App\Models;

use App\ProcurementQuantity;
use App\PurchaseOrderStatus;
use App\PurchaseRequestStatus;
use Database\Factories\PurchaseRequestItemFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequestItem extends Model
{
    /** @use HasFactory<PurchaseRequestItemFactory> */
    use HasFactory;

    protected $fillable = [
        'purchase_request_id',
        'material_id',
        'unit_of_measure_id',
        'requested_qty',
        'justification',
    ];

    protected static function booted(): void
    {
        $ensureDraftRequest = function (PurchaseRequestItem $item): void {
            $isDraft = PurchaseRequest::query()
                ->whereKey($item->purchase_request_id)
                ->where('status', PurchaseRequestStatus::Draft->value)
                ->exists();

            if (! $isDraft) {
                throw new DomainException('Purchase request items can only be changed while the request is a draft.');
            }
        };

        static::creating($ensureDraftRequest);
        static::updating($ensureDraftRequest);
    }

    protected function casts(): array
    {
        return [
            'requested_qty' => 'decimal:3',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    public function purchaseOrderAllocations(): HasMany
    {
        return $this->hasMany(PurchaseOrderItemAllocation::class);
    }

    public function remainingQuantityMilli(): int
    {
        $allocationsAreLoaded = $this->relationLoaded('purchaseOrderAllocations')
            && $this->purchaseOrderAllocations->every(fn (PurchaseOrderItemAllocation $allocation): bool => $allocation->relationLoaded('purchaseOrderItem')
                && $allocation->purchaseOrderItem->relationLoaded('purchaseOrder'));

        if ($allocationsAreLoaded) {
            $allocatedMilli = $this->purchaseOrderAllocations
                ->filter(fn (PurchaseOrderItemAllocation $allocation): bool => $allocation->relationLoaded('purchaseOrderItem')
                    && $allocation->purchaseOrderItem->relationLoaded('purchaseOrder')
                    && $allocation->purchaseOrderItem->purchaseOrder->status !== PurchaseOrderStatus::Cancelled)
                ->sum(fn (PurchaseOrderItemAllocation $allocation): int => ProcurementQuantity::toMilliUnits(
                    (string) $allocation->allocated_qty,
                ));
        } else {
            $allocatedMilli = $this->purchaseOrderAllocations()
                ->whereHas('purchaseOrderItem.purchaseOrder', fn ($query) => $query
                    ->where('status', '!=', PurchaseOrderStatus::Cancelled->value))
                ->pluck('allocated_qty')
                ->sum(fn (string $quantity): int => ProcurementQuantity::toMilliUnits($quantity));
        }

        return max(
            0,
            ProcurementQuantity::toMilliUnits((string) $this->requested_qty) - $allocatedMilli,
        );
    }

    public function unpurchasedQuantityMilli(): int
    {
        $allocationsAreLoaded = $this->relationLoaded('purchaseOrderAllocations')
            && $this->purchaseOrderAllocations->every(fn (PurchaseOrderItemAllocation $allocation): bool => $allocation->relationLoaded('purchaseOrderItem')
                && $allocation->purchaseOrderItem->relationLoaded('purchaseOrder'));
        $orderedAllocatedMilli = $allocationsAreLoaded
            ? $this->purchaseOrderAllocations
                ->filter(fn (PurchaseOrderItemAllocation $allocation): bool => $allocation->purchaseOrderItem->purchaseOrder->status === PurchaseOrderStatus::Ordered)
                ->sum(fn (PurchaseOrderItemAllocation $allocation): int => ProcurementQuantity::toMilliUnits((string) $allocation->allocated_qty))
            : $this->purchaseOrderAllocations()
                ->whereHas('purchaseOrderItem.purchaseOrder', fn ($query) => $query
                    ->where('status', PurchaseOrderStatus::Ordered->value))
                ->pluck('allocated_qty')
                ->sum(fn (string $quantity): int => ProcurementQuantity::toMilliUnits($quantity));

        return max(
            0,
            ProcurementQuantity::toMilliUnits((string) $this->requested_qty) - $orderedAllocatedMilli,
        );
    }

    public function allocateTo(PurchaseOrderItem $purchaseOrderItem, string $quantity): PurchaseOrderItemAllocation
    {
        return $purchaseOrderItem->allocateFrom($this, $quantity);
    }
}
