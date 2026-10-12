<?php

namespace App\Models;

use App\DeliveryStatus;
use App\ProcurementActivityType;
use App\ProcurementQuantity;
use App\PurchaseOrderStatus;
use Database\Factories\DeliveryFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Delivery extends Model
{
    /** @use HasFactory<DeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'delivery_number',
        'purchase_order_id',
        'supplier_id',
        'supplier_delivery_reference',
        'tracking_number',
        'dispatch_date',
        'expected_arrival_date',
        'notes',
    ];

    protected $attributes = [
        'status' => DeliveryStatus::Draft->value,
    ];

    private bool $workflowTransitionAuthorized = false;

    protected static function booted(): void
    {
        static::creating(function (Delivery $delivery): void {
            $purchaseOrder = PurchaseOrder::query()->find($delivery->purchase_order_id);

            if ($purchaseOrder !== null && $delivery->supplier_id === null) {
                $delivery->supplier_id = $purchaseOrder->supplier_id;
            }

            $delivery->created_by_user_id ??= auth()->id();

            if ($delivery->status !== DeliveryStatus::Draft
                || $purchaseOrder === null
                || $purchaseOrder->status !== PurchaseOrderStatus::Ordered
                || (int) $purchaseOrder->supplier_id !== (int) $delivery->supplier_id) {
                throw new DomainException('Deliveries require an ordered purchase order and its supplier.');
            }
        });

        static::created(function (Delivery $delivery): void {
            ProcurementActivity::record(
                'delivery-created-'.$delivery->getKey(),
                ProcurementActivityType::DeliveryCreated,
                'Delivery '.$delivery->delivery_number.' was created.',
                $delivery->created_by_user_id === null ? null : (int) $delivery->created_by_user_id,
                purchaseOrderId: (int) $delivery->purchase_order_id,
                deliveryId: (int) $delivery->getKey(),
            );
        });

        static::updating(function (Delivery $delivery): void {
            if ($delivery->isDirty('status') && ! $delivery->workflowTransitionAuthorized) {
                throw new DomainException('Use the delivery workflow to change its status.');
            }

            if (! $delivery->workflowTransitionAuthorized && $delivery->isDirty([
                'purchase_order_id',
                'supplier_id',
                'dispatch_date',
                'expected_arrival_date',
                'dispatched_at',
                'actual_arrival_at',
                'created_by_user_id',
            ]) && $delivery->status !== DeliveryStatus::Draft) {
                throw new DomainException('Dispatched delivery details cannot be changed.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'dispatch_date' => 'date',
            'expected_arrival_date' => 'date',
            'dispatched_at' => 'datetime',
            'actual_arrival_at' => 'datetime',
            'status' => DeliveryStatus::class,
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function procurementActivities(): HasMany
    {
        return $this->hasMany(ProcurementActivity::class);
    }

    public function dispatch(User $actor): void
    {
        DB::transaction(function () use ($actor): void {
            $delivery = self::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($delivery->purchase_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($delivery->status !== DeliveryStatus::Draft
                || $purchaseOrder->status !== PurchaseOrderStatus::Ordered
                || ! $purchaseOrder->supplier->is_active) {
                throw new DomainException('Only draft deliveries for active suppliers on ordered purchase orders can be dispatched.');
            }

            $deliveryItems = $delivery->items()->with('purchaseOrderItem')->get();

            if ($deliveryItems->isEmpty()) {
                throw new DomainException('A delivery must contain at least one item before dispatch.');
            }

            foreach ($deliveryItems as $deliveryItem) {
                $previouslyDispatchedMilli = DeliveryItem::query()
                    ->where('purchase_order_item_id', $deliveryItem->purchase_order_item_id)
                    ->whereHas('delivery', fn ($query) => $query
                        ->whereKeyNot($delivery->getKey())
                        ->whereIn('status', [
                            DeliveryStatus::Dispatched->value,
                            DeliveryStatus::PartiallyReceived->value,
                            DeliveryStatus::Received->value,
                        ]))
                    ->pluck('dispatched_qty')
                    ->sum(fn (string $quantity): int => ProcurementQuantity::toMilliUnits($quantity));

                $orderedMilli = ProcurementQuantity::toMilliUnits(
                    (string) $deliveryItem->purchaseOrderItem->ordered_qty,
                );
                $thisDeliveryMilli = ProcurementQuantity::toMilliUnits((string) $deliveryItem->dispatched_qty);

                if ($thisDeliveryMilli <= 0 || $previouslyDispatchedMilli + $thisDeliveryMilli > $orderedMilli) {
                    throw new DomainException('Dispatched quantity cannot exceed the remaining ordered quantity.');
                }
            }

            $delivery->workflowTransitionAuthorized = true;
            $delivery->status = DeliveryStatus::Dispatched;
            $delivery->dispatch_date ??= now()->toDateString();
            $delivery->dispatched_at = now();

            try {
                $delivery->save();
            } finally {
                $delivery->workflowTransitionAuthorized = false;
            }

            ProcurementActivity::record(
                'delivery-dispatched-'.$delivery->getKey(),
                ProcurementActivityType::DeliveryDispatched,
                'Delivery '.$delivery->delivery_number.' was dispatched.',
                (int) $actor->getKey(),
                purchaseOrderId: (int) $purchaseOrder->getKey(),
                deliveryId: (int) $delivery->getKey(),
            );

            $this->setRawAttributes($delivery->getAttributes(), true);
        });
    }

    public function cancel(): void
    {
        DB::transaction(function (): void {
            $delivery = self::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if ($delivery->status !== DeliveryStatus::Draft) {
                throw new DomainException('Only draft deliveries can be cancelled.');
            }

            $delivery->transitionTo(DeliveryStatus::Cancelled);
            ProcurementActivity::record(
                'delivery-cancelled-'.$delivery->getKey(),
                ProcurementActivityType::DeliveryCancelled,
                'Delivery '.$delivery->delivery_number.' was cancelled.',
                $delivery->created_by_user_id === null ? null : (int) $delivery->created_by_user_id,
                purchaseOrderId: (int) $delivery->purchase_order_id,
                deliveryId: (int) $delivery->getKey(),
            );
            $this->setRawAttributes($delivery->getAttributes(), true);
        });
    }

    public function refreshReceivingStatus(\DateTimeInterface $receivedAt): void
    {
        $delivery = self::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

        if ($delivery->status === DeliveryStatus::Cancelled) {
            throw new DomainException('Cancelled deliveries cannot receive goods.');
        }

        $items = $delivery->items()->with('goodsReceiptItems.goodsReceipt')->get();
        $hasReceived = false;
        $allReceived = $items->isNotEmpty();

        foreach ($items as $item) {
            $receivedMilli = $item->receivedQuantityMilli();
            $dispatchedMilli = ProcurementQuantity::toMilliUnits((string) $item->dispatched_qty);
            $hasReceived = $hasReceived || $receivedMilli > 0;
            $allReceived = $allReceived && $receivedMilli >= $dispatchedMilli;
        }

        $nextStatus = $allReceived
            ? DeliveryStatus::Received
            : ($hasReceived ? DeliveryStatus::PartiallyReceived : DeliveryStatus::Dispatched);

        $delivery->workflowTransitionAuthorized = true;
        $delivery->status = $nextStatus;
        $delivery->actual_arrival_at ??= $receivedAt;

        try {
            $delivery->save();
        } finally {
            $delivery->workflowTransitionAuthorized = false;
        }

        $this->setRawAttributes($delivery->getAttributes(), true);
    }

    private function transitionTo(DeliveryStatus $status): void
    {
        $this->workflowTransitionAuthorized = true;
        $this->status = $status;

        try {
            $this->save();
        } finally {
            $this->workflowTransitionAuthorized = false;
        }
    }
}
