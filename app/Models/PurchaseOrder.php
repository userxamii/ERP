<?php

namespace App\Models;

use App\ProcurementActivityType;
use App\PurchaseOrderStatus;
use Database\Factories\PurchaseOrderFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;

class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_number',
        'supplier_id',
        'order_date',
        'expected_delivery_date',
        'currency_code',
        'notes',
    ];

    protected $attributes = [
        'status' => PurchaseOrderStatus::Draft->value,
    ];

    private bool $statusTransitionAuthorized = false;

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrder $purchaseOrder): void {
            if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
                throw new DomainException('Purchase orders must be created as drafts.');
            }
        });

        static::created(function (PurchaseOrder $purchaseOrder): void {
            PurchaseOrderStatusHistory::record(
                $purchaseOrder,
                null,
                PurchaseOrderStatus::Draft,
                (int) $purchaseOrder->created_by_user_id,
            );
            ProcurementActivity::record(
                'purchase-order-created-'.$purchaseOrder->getKey(),
                ProcurementActivityType::PurchaseOrderCreated,
                'Purchase order '.$purchaseOrder->order_number.' was created.',
                (int) $purchaseOrder->created_by_user_id,
                purchaseOrderId: (int) $purchaseOrder->getKey(),
            );
        });

        static::updating(function (PurchaseOrder $purchaseOrder): void {
            if ($purchaseOrder->isDirty('status') && ! $purchaseOrder->statusTransitionAuthorized) {
                throw new DomainException('Use the purchase order workflow to change its status.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'status' => PurchaseOrderStatus::class,
        ];
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
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function allocations(): HasManyThrough
    {
        return $this->hasManyThrough(PurchaseOrderItemAllocation::class, PurchaseOrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(PurchaseOrderStatusHistory::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function procurementActivities(): HasMany
    {
        return $this->hasMany(ProcurementActivity::class);
    }

    public function place(User $actor, ?string $notes = null): void
    {
        DB::transaction(function () use ($actor, $notes): void {
            $purchaseOrder = self::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
                throw new DomainException('Only draft purchase orders can be placed.');
            }

            if (! $purchaseOrder->items()->exists()) {
                throw new DomainException('Purchase orders need at least one item before being placed.');
            }

            if (! $purchaseOrder->supplier->is_active) {
                throw new DomainException('Inactive suppliers cannot receive purchase orders.');
            }

            foreach ($purchaseOrder->items()->with('supplierMaterial')->get() as $item) {
                if ($item->supplierMaterial !== null && ! $item->supplierMaterial->is_active) {
                    throw new DomainException('Inactive supplier-material terms cannot be ordered.');
                }

                if ($item->allocatedQuantityMilli() !== PurchaseOrderItem::quantityMilli($item->ordered_qty)) {
                    throw new DomainException('Every purchase order item must be fully allocated to approved request items before being placed.');
                }
            }

            $purchaseOrder->transitionTo(PurchaseOrderStatus::Ordered, $actor, $notes);
            ProcurementActivity::record(
                'purchase-order-placed-'.$purchaseOrder->getKey(),
                ProcurementActivityType::PurchaseOrderPlaced,
                'Purchase order '.$purchaseOrder->order_number.' was placed.',
                (int) $actor->getKey(),
                purchaseOrderId: (int) $purchaseOrder->getKey(),
            );
            $this->setRawAttributes($purchaseOrder->getAttributes(), true);
        });
    }

    public function cancel(User $actor, ?string $notes = null): void
    {
        DB::transaction(function () use ($actor, $notes): void {
            $purchaseOrder = self::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($purchaseOrder->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Ordered], true)) {
                throw new DomainException('Only draft or ordered purchase orders can be cancelled.');
            }

            $purchaseOrder->transitionTo(PurchaseOrderStatus::Cancelled, $actor, $notes);
            ProcurementActivity::record(
                'purchase-order-cancelled-'.$purchaseOrder->getKey(),
                ProcurementActivityType::PurchaseOrderCancelled,
                'Purchase order '.$purchaseOrder->order_number.' was cancelled.',
                (int) $actor->getKey(),
                purchaseOrderId: (int) $purchaseOrder->getKey(),
            );
            $this->setRawAttributes($purchaseOrder->getAttributes(), true);
        });
    }

    private function transitionTo(
        PurchaseOrderStatus $status,
        User $actor,
        ?string $notes,
    ): void {
        $previousStatus = $this->status;
        $this->statusTransitionAuthorized = true;
        $this->status = $status;

        try {
            $this->save();
            PurchaseOrderStatusHistory::record($this, $previousStatus, $status, (int) $actor->getKey(), $notes);
        } finally {
            $this->statusTransitionAuthorized = false;
        }
    }
}
