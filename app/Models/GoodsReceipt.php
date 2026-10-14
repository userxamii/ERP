<?php

namespace App\Models;

use App\DeliveryStatus;
use App\GoodsReceiptStatus;
use App\InventoryBatchQualityStatus;
use App\InventoryMovementType;
use App\ProcurementActivityType;
use App\ProcurementQuantity;
use App\PurchaseOrderStatus;
use Carbon\CarbonImmutable;
use Database\Factories\GoodsReceiptFactory;
use DateTimeInterface;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class GoodsReceipt extends Model
{
    /** @use HasFactory<GoodsReceiptFactory> */
    use HasFactory;

    protected $fillable = [
        'receipt_number',
        'delivery_id',
        'purchase_order_id',
        'notes',
    ];

    protected $attributes = [
        'status' => GoodsReceiptStatus::Draft->value,
    ];

    private bool $postingAuthorized = false;

    protected static function booted(): void
    {
        static::creating(function (GoodsReceipt $receipt): void {
            $delivery = Delivery::query()->find($receipt->delivery_id);

            if ($delivery !== null && $receipt->purchase_order_id === null) {
                $receipt->purchase_order_id = $delivery->purchase_order_id;
            }

            $receipt->received_by_user_id ??= auth()->id();

            if ($receipt->status !== GoodsReceiptStatus::Draft
                || $delivery === null
                || ! in_array($delivery->status, [
                    DeliveryStatus::Dispatched,
                    DeliveryStatus::PartiallyReceived,
                ], true)
                || (int) $delivery->purchase_order_id !== (int) $receipt->purchase_order_id) {
                throw new DomainException('Goods receipts require a dispatched delivery and its purchase order.');
            }
        });

        static::updating(function (GoodsReceipt $receipt): void {
            if ($receipt->isDirty('status') && ! $receipt->postingAuthorized) {
                throw new DomainException('Goods receipt status can only change through posting.');
            }

            if ($receipt->getRawOriginal('status') === GoodsReceiptStatus::Posted->value
                && ! $receipt->postingAuthorized
                && $receipt->isDirty()) {
                throw new DomainException('Posted goods receipt history is immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => GoodsReceiptStatus::class,
            'received_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function procurementActivities(): HasMany
    {
        return $this->hasMany(ProcurementActivity::class);
    }

    public function post(User $actor, ?DateTimeInterface $actualReceivedAt = null): void
    {
        DB::transaction(function () use ($actor, $actualReceivedAt): void {
            $receipt = self::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($receipt->status === GoodsReceiptStatus::Posted) {
                $this->setRawAttributes($receipt->getAttributes(), true);

                return;
            }

            $delivery = Delivery::query()
                ->whereKey($receipt->delivery_id)
                ->lockForUpdate()
                ->firstOrFail();
            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($receipt->purchase_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($delivery->status, [
                DeliveryStatus::Dispatched,
                DeliveryStatus::PartiallyReceived,
            ], true) || $purchaseOrder->status !== PurchaseOrderStatus::Ordered) {
                throw new DomainException('Only an active dispatched delivery on an ordered purchase order can be received.');
            }

            $receiptItems = $receipt->items()->with(['deliveryItem', 'purchaseOrderItem'])->get();

            if ($receiptItems->isEmpty()) {
                throw new DomainException('A goods receipt must contain at least one received item before posting.');
            }

            $receivedAt = $actualReceivedAt === null
                ? CarbonImmutable::now()
                : CarbonImmutable::instance(\DateTime::createFromInterface($actualReceivedAt));

            foreach ($receiptItems as $receiptItem) {
                $deliveryItem = DeliveryItem::query()
                    ->whereKey($receiptItem->delivery_item_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $purchaseOrderItem = PurchaseOrderItem::query()
                    ->whereKey($receiptItem->purchase_order_item_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $deliveryItem->delivery_id !== (int) $delivery->getKey()
                    || (int) $deliveryItem->purchase_order_item_id !== (int) $purchaseOrderItem->getKey()
                    || (int) $purchaseOrderItem->purchase_order_id !== (int) $purchaseOrder->getKey()) {
                    throw new DomainException('Receipt items must belong to the receipt delivery and purchase order.');
                }

                $receivedMilli = $receiptItem->receivedQuantityMilli();

                if ($receivedMilli <= 0
                    || $receivedMilli > $deliveryItem->remainingToReceiveMilli()
                    || $receivedMilli > $purchaseOrderItem->remainingToReceiveMilli()) {
                    throw new DomainException('Accepted plus rejected quantity cannot exceed remaining dispatched or ordered quantity.');
                }

                $acceptedMilli = ProcurementQuantity::toMilliUnits((string) $receiptItem->accepted_qty);
                $rejectedMilli = ProcurementQuantity::toMilliUnits((string) $receiptItem->rejected_qty);
                $material = $purchaseOrderItem->material;
                $batch = null;

                if ($acceptedMilli > 0) {
                    $batch = InventoryBatch::recordGoodsReceipt(
                        $receiptItem,
                        $purchaseOrderItem,
                        $delivery->supplier,
                        $actor,
                        $receivedAt,
                        InventoryBatchQualityStatus::Available,
                    );

                    InventoryMovement::record(
                        InventoryMovementType::GoodsReceiptAccepted,
                        $material,
                        ProcurementQuantity::fromMilliUnits($acceptedMilli),
                        ProcurementQuantity::fromMilliUnits($acceptedMilli),
                        '0.000',
                        $actor,
                        $batch,
                        $receiptItem,
                        sourceReference: $receipt->receipt_number,
                    );
                }

                if ($rejectedMilli > 0) {
                    InventoryMovement::record(
                        InventoryMovementType::GoodsReceiptRejected,
                        $material,
                        ProcurementQuantity::fromMilliUnits($rejectedMilli),
                        '0.000',
                        '0.000',
                        $actor,
                        receiptItem: $receiptItem,
                        sourceReference: $receipt->receipt_number,
                    );
                }
            }

            $receipt->postingAuthorized = true;
            $receipt->status = GoodsReceiptStatus::Posted;
            $receipt->received_by_user_id = $actor->getKey();
            $receipt->received_at = $receivedAt;
            $receipt->posted_at = now();

            try {
                $receipt->save();
            } finally {
                $receipt->postingAuthorized = false;
            }

            $delivery->refreshReceivingStatus($receivedAt);

            ProcurementActivity::record(
                'goods-receipt-posted-'.$receipt->getKey(),
                ProcurementActivityType::GoodsReceiptPosted,
                'Goods receipt '.$receipt->receipt_number.' was posted.',
                (int) $actor->getKey(),
                purchaseOrderId: (int) $purchaseOrder->getKey(),
                deliveryId: (int) $delivery->getKey(),
                goodsReceiptId: (int) $receipt->getKey(),
            );

            $this->setRawAttributes($receipt->getAttributes(), true);
        });
    }
}
