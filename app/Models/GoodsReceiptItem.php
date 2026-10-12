<?php

namespace App\Models;

use App\GoodsReceiptStatus;
use App\ProcurementQuantity;
use Database\Factories\GoodsReceiptItemFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GoodsReceiptItem extends Model
{
    /** @use HasFactory<GoodsReceiptItemFactory> */
    use HasFactory;

    protected $fillable = [
        'goods_receipt_id',
        'delivery_item_id',
        'purchase_order_item_id',
        'accepted_qty',
        'rejected_qty',
        'lot_code',
        'expires_at',
        'discrepancy_notes',
    ];

    protected function casts(): array
    {
        return [
            'accepted_qty' => 'decimal:3',
            'rejected_qty' => 'decimal:3',
            'expires_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (GoodsReceiptItem $item): void {
            $receipt = GoodsReceipt::query()->find($item->goods_receipt_id);
            $deliveryItem = DeliveryItem::query()->find($item->delivery_item_id);

            if ($receipt === null
                || $receipt->status !== GoodsReceiptStatus::Draft
                || $deliveryItem === null
                || (int) $deliveryItem->delivery_id !== (int) $receipt->delivery_id
                || (int) $deliveryItem->purchase_order_item_id !== (int) $item->purchase_order_item_id) {
                throw new DomainException('Receipt items must reference a delivery item from the same draft receipt.');
            }

            $acceptedMilli = ProcurementQuantity::toMilliUnits((string) $item->accepted_qty);
            $rejectedMilli = ProcurementQuantity::toMilliUnits((string) $item->rejected_qty);

            if ($acceptedMilli < 0 || $rejectedMilli < 0 || $acceptedMilli + $rejectedMilli <= 0) {
                throw new DomainException('Receipt quantity must contain a positive accepted or rejected amount.');
            }

            if ($rejectedMilli > 0 && trim((string) $item->discrepancy_notes) === '') {
                throw new DomainException('Rejected quantities require discrepancy or damage notes.');
            }

            if ($item->exists && $item->isDirty([
                'goods_receipt_id',
                'delivery_item_id',
                'purchase_order_item_id',
            ])) {
                throw new DomainException('Receipt items cannot be reassigned.');
            }
        });

        static::deleting(function (GoodsReceiptItem $item): void {
            if ($item->goodsReceipt->status !== GoodsReceiptStatus::Draft) {
                throw new DomainException('Posted goods receipt items are immutable.');
            }
        });
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function deliveryItem(): BelongsTo
    {
        return $this->belongsTo(DeliveryItem::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function inventoryBatch(): HasOne
    {
        return $this->hasOne(InventoryBatch::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function receivedQuantityMilli(): int
    {
        return ProcurementQuantity::toMilliUnits((string) $this->accepted_qty)
            + ProcurementQuantity::toMilliUnits((string) $this->rejected_qty);
    }
}
