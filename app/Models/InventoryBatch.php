<?php

namespace App\Models;

use App\InventoryBatchQualityStatus;
use App\ProcurementQuantity;
use Database\Factories\InventoryBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryBatch extends Model
{
    /** @use HasFactory<InventoryBatchFactory> */
    use HasFactory;

    protected $fillable = [
        'material_id',
        'lot_code',
        'received_at',
        'expires_at',
        'quantity_received',
        'quantity_on_hand',
        'quality_status',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'expires_at' => 'date',
            'quantity_received' => 'decimal:3',
            'quantity_on_hand' => 'decimal:3',
            'quality_status' => InventoryBatchQualityStatus::class,
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function goodsReceiptItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public static function recordGoodsReceipt(
        GoodsReceiptItem $receiptItem,
        PurchaseOrderItem $purchaseOrderItem,
        Supplier $supplier,
        User $actor,
        \DateTimeInterface $receivedAt,
        InventoryBatchQualityStatus $qualityStatus,
    ): self {
        $batch = new self;
        $batch->forceFill([
            'material_id' => $purchaseOrderItem->material_id,
            'supplier_id' => $supplier->getKey(),
            'purchase_order_item_id' => $purchaseOrderItem->getKey(),
            'goods_receipt_item_id' => $receiptItem->getKey(),
            'received_by_user_id' => $actor->getKey(),
            'lot_code' => $receiptItem->lot_code,
            'received_at' => $receivedAt,
            'expires_at' => $receiptItem->expires_at,
            'quantity_received' => $receiptItem->accepted_qty,
            'quantity_on_hand' => $receiptItem->accepted_qty,
            'quality_status' => $qualityStatus,
        ]);
        $batch->save();

        return $batch;
    }

    public function quantityMilli(): int
    {
        return ProcurementQuantity::toMilliUnits((string) $this->quantity_on_hand);
    }
}
