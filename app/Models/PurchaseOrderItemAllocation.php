<?php

namespace App\Models;

use App\ProcurementQuantity;
use App\PurchaseOrderStatus;
use App\PurchaseRequestStatus;
use Database\Factories\PurchaseOrderItemAllocationFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PurchaseOrderItemAllocation extends Model
{
    /** @use HasFactory<PurchaseOrderItemAllocationFactory> */
    use HasFactory;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'allocated_qty' => 'decimal:3',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrderItemAllocation $allocation): void {
            $purchaseOrderItem = PurchaseOrderItem::query()->find($allocation->purchase_order_item_id);
            $purchaseRequestItem = PurchaseRequestItem::query()
                ->with('purchaseRequest')
                ->find($allocation->purchase_request_item_id);

            if ($purchaseOrderItem === null
                || $purchaseOrderItem->purchaseOrder->status !== PurchaseOrderStatus::Draft
                || $purchaseRequestItem === null
                || $purchaseRequestItem->purchaseRequest->status !== PurchaseRequestStatus::Approved) {
                throw new DomainException('Allocations require a draft purchase order and an approved purchase request item.');
            }

            if ((int) $purchaseOrderItem->material_id !== (int) $purchaseRequestItem->material_id
                || (int) $purchaseOrderItem->unit_of_measure_id !== (int) $purchaseRequestItem->unit_of_measure_id) {
                throw new DomainException('Allocated request items must match the purchase order material and unit.');
            }

            $allocatedMilli = ProcurementQuantity::toMilliUnits((string) $allocation->allocated_qty);

            if ($allocatedMilli <= 0 || $allocatedMilli > $purchaseRequestItem->remainingQuantityMilli()) {
                throw new DomainException('Allocated quantity must be positive and cannot exceed unallocated approved demand.');
            }

            if ($purchaseOrderItem->allocations()
                ->where('purchase_request_item_id', $purchaseRequestItem->getKey())
                ->exists()) {
                throw new DomainException('A purchase order line can only allocate a request item once.');
            }

            if ($purchaseOrderItem->allocatedQuantityMilli() + $allocatedMilli
                > ProcurementQuantity::toMilliUnits((string) $purchaseOrderItem->ordered_qty)) {
                throw new DomainException('Allocated quantity cannot exceed the purchase order line quantity.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('Purchase order demand allocations cannot be updated.');
        });

        static::deleting(function (PurchaseOrderItemAllocation $allocation): void {
            $isDraftOrder = PurchaseOrder::query()
                ->whereHas('items.allocations', fn ($query) => $query->whereKey($allocation->getKey()))
                ->where('status', PurchaseOrderStatus::Draft->value)
                ->exists();

            if (! $isDraftOrder) {
                throw new DomainException('Allocations can only be removed while the purchase order is a draft.');
            }
        });
    }

    public static function record(
        PurchaseOrderItem $purchaseOrderItem,
        PurchaseRequestItem $purchaseRequestItem,
        string $quantity,
    ): self {
        $allocation = new self;
        $allocation->forceFill([
            'purchase_order_item_id' => $purchaseOrderItem->getKey(),
            'purchase_request_item_id' => $purchaseRequestItem->getKey(),
            'allocated_qty' => $quantity,
        ]);
        $allocation->save();

        return $allocation;
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function purchaseRequestItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class);
    }
}
