<?php

namespace App\Models;

use App\PurchaseOrderStatus;
use Database\Factories\PurchaseOrderStatusHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PurchaseOrderStatusHistory extends Model
{
    /** @use HasFactory<PurchaseOrderStatusHistoryFactory> */
    use HasFactory;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'from_status' => PurchaseOrderStatus::class,
            'to_status' => PurchaseOrderStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Purchase order status history is append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Purchase order status history is append-only.');
        });
    }

    public static function record(
        PurchaseOrder $purchaseOrder,
        ?PurchaseOrderStatus $fromStatus,
        PurchaseOrderStatus $toStatus,
        int $actorId,
        ?string $notes = null,
    ): self {
        $history = new self;
        $history->forceFill([
            'purchase_order_id' => $purchaseOrder->getKey(),
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by_user_id' => $actorId,
            'notes' => $notes,
        ]);
        $history->save();

        return $history;
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
