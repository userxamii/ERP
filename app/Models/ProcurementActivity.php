<?php

namespace App\Models;

use App\ProcurementActivityType;
use Database\Factories\ProcurementActivityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ProcurementActivity extends Model
{
    /** @use HasFactory<ProcurementActivityFactory> */
    use HasFactory;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'activity_type' => ProcurementActivityType::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Procurement activity history is append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Procurement activity history is append-only.');
        });
    }

    public static function record(
        string $idempotencyKey,
        ProcurementActivityType $type,
        string $description,
        ?int $actorId = null,
        ?int $purchaseRequestId = null,
        ?int $purchaseOrderId = null,
        ?int $deliveryId = null,
        ?int $goodsReceiptId = null,
        ?int $inventoryMovementId = null,
        ?array $metadata = null,
    ): self {
        $existing = self::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null) {
            return $existing;
        }

        $activity = new self;
        $activity->forceFill([
            'idempotency_key' => $idempotencyKey,
            'activity_type' => $type,
            'actor_user_id' => $actorId,
            'purchase_request_id' => $purchaseRequestId,
            'purchase_order_id' => $purchaseOrderId,
            'delivery_id' => $deliveryId,
            'goods_receipt_id' => $goodsReceiptId,
            'inventory_movement_id' => $inventoryMovementId,
            'description' => $description,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
        $activity->save();

        return $activity;
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function inventoryMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class);
    }
}
