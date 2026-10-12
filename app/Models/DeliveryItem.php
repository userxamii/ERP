<?php

namespace App\Models;

use App\DeliveryStatus;
use App\GoodsReceiptStatus;
use App\ProcurementQuantity;
use Database\Factories\DeliveryItemFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryItem extends Model
{
    /** @use HasFactory<DeliveryItemFactory> */
    use HasFactory;

    protected $fillable = [
        'delivery_id',
        'purchase_order_item_id',
        'dispatched_qty',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_qty' => 'decimal:3',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (DeliveryItem $item): void {
            $delivery = Delivery::query()->find($item->delivery_id);
            $purchaseOrderItem = PurchaseOrderItem::query()->find($item->purchase_order_item_id);

            if ($delivery === null
                || $delivery->status !== DeliveryStatus::Draft
                || $purchaseOrderItem === null
                || (int) $purchaseOrderItem->purchase_order_id !== (int) $delivery->purchase_order_id) {
                throw new DomainException('Delivery items must reference an order line belonging to the draft delivery purchase order.');
            }

            $quantity = ProcurementQuantity::toMilliUnits((string) $item->dispatched_qty);

            if ($quantity <= 0) {
                throw new DomainException('Dispatched quantity must be positive.');
            }

            if ($item->exists && $item->isDirty(['delivery_id', 'purchase_order_item_id']) && $item->goodsReceiptItems()->exists()) {
                throw new DomainException('A delivery item cannot be reassigned after it has receipt history.');
            }
        });

        static::deleting(function (DeliveryItem $item): void {
            if ($item->delivery->status !== DeliveryStatus::Draft || $item->goodsReceiptItems()->exists()) {
                throw new DomainException('Only unreceived items from draft deliveries can be removed.');
            }
        });
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function receivedQuantityMilli(): int
    {
        $receiptRelationsAreLoaded = $this->relationLoaded('goodsReceiptItems')
            && $this->goodsReceiptItems->every(fn (GoodsReceiptItem $receiptItem): bool => $receiptItem->relationLoaded('goodsReceipt'));

        if ($receiptRelationsAreLoaded) {
            return $this->goodsReceiptItems
                ->filter(fn (GoodsReceiptItem $receiptItem): bool => $receiptItem->relationLoaded('goodsReceipt')
                    && $receiptItem->goodsReceipt->status === GoodsReceiptStatus::Posted)
                ->sum(fn (GoodsReceiptItem $receiptItem): int => $receiptItem->receivedQuantityMilli());
        }

        $receivedMilli = 0;

        foreach ($this->goodsReceiptItems()
            ->whereHas('goodsReceipt', fn ($query) => $query->where('status', 'posted'))
            ->get(['accepted_qty', 'rejected_qty']) as $item) {
            $receivedMilli += ProcurementQuantity::toMilliUnits((string) $item->accepted_qty);
            $receivedMilli += ProcurementQuantity::toMilliUnits((string) $item->rejected_qty);
        }

        return $receivedMilli;
    }

    public function remainingToReceiveMilli(): int
    {
        return max(
            0,
            ProcurementQuantity::toMilliUnits((string) $this->dispatched_qty) - $this->receivedQuantityMilli(),
        );
    }
}
