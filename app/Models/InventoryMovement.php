<?php

namespace App\Models;

use App\InventoryMovementType;
use App\ProcurementActivityType;
use App\ProcurementQuantity;
use Database\Factories\InventoryMovementFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InventoryMovement extends Model
{
    /** @use HasFactory<InventoryMovementFactory> */
    use HasFactory;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'movement_type' => InventoryMovementType::class,
            'quantity' => 'decimal:3',
            'on_hand_delta' => 'decimal:3',
            'reserved_delta' => 'decimal:3',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (InventoryMovement $movement): void {
            $quantityMilli = ProcurementQuantity::toMilliUnits((string) $movement->quantity);

            if ($quantityMilli <= 0) {
                throw new DomainException('Inventory movement quantities must be positive.');
            }

            $type = $movement->movement_type;
            $expectedOnHandDelta = match ($type) {
                InventoryMovementType::GoodsReceiptAccepted,
                InventoryMovementType::WarehouseReceipt => $quantityMilli,
                InventoryMovementType::StockOut => -$quantityMilli,
                default => 0,
            };
            $expectedReservedDelta = match ($type) {
                InventoryMovementType::ReservationCreated => $quantityMilli,
                InventoryMovementType::ReservationReleased => -$quantityMilli,
                default => 0,
            };

            if (ProcurementQuantity::toSignedMilliUnits((string) $movement->on_hand_delta) !== $expectedOnHandDelta
                || ProcurementQuantity::toSignedMilliUnits((string) $movement->reserved_delta) !== $expectedReservedDelta) {
                throw new DomainException('Inventory movement deltas do not match the movement type.');
            }

            if ($type === InventoryMovementType::GoodsReceiptAccepted
                && ($movement->goods_receipt_item_id === null || $movement->inventory_batch_id === null)) {
                throw new DomainException('Accepted receipt movements require a batch and receipt item.');
            }

            if (in_array($type, [
                InventoryMovementType::WarehouseReceipt,
                InventoryMovementType::StockOut,
            ], true) && $movement->inventory_batch_id === null) {
                throw new DomainException('Warehouse stock movements require an inventory batch.');
            }

            if ($type === InventoryMovementType::GoodsReceiptRejected && $movement->goods_receipt_item_id === null) {
                throw new DomainException('Rejected receipt movements require a receipt item.');
            }

            if (in_array($type, [
                InventoryMovementType::ReservationCreated,
                InventoryMovementType::ReservationReleased,
            ], true) && $movement->material_stock_reservation_id === null) {
                throw new DomainException('Reservation movements require their source reservation.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('Inventory movement history is append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Inventory movement history is append-only.');
        });
    }

    public static function record(
        InventoryMovementType $type,
        Material $material,
        string $quantity,
        string $onHandDelta,
        string $reservedDelta,
        ?User $actor,
        ?InventoryBatch $batch = null,
        ?GoodsReceiptItem $receiptItem = null,
        ?MaterialStockReservation $reservation = null,
        ?string $sourceReference = null,
        ?string $sourceLocation = null,
        ?string $destinationLocation = null,
        ?string $reason = null,
    ): self {
        $movement = new self;
        $movement->forceFill([
            'material_id' => $material->getKey(),
            'inventory_batch_id' => $batch?->getKey(),
            'goods_receipt_item_id' => $receiptItem?->getKey(),
            'material_stock_reservation_id' => $reservation?->getKey(),
            'actor_user_id' => $actor?->getKey(),
            'movement_type' => $type,
            'quantity' => $quantity,
            'on_hand_delta' => $onHandDelta,
            'reserved_delta' => $reservedDelta,
            'source_reference' => $sourceReference,
            'source_location' => $sourceLocation,
            'destination_location' => $destinationLocation,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
        $movement->save();

        ProcurementActivity::record(
            'inventory-movement-'.$movement->getKey(),
            ProcurementActivityType::InventoryMovementRecorded,
            'Inventory movement '.$type->value.' recorded for material '.$material->code.'.',
            $actor?->getKey(),
            purchaseOrderId: $receiptItem?->purchaseOrderItem->purchase_order_id,
            goodsReceiptId: $receiptItem?->goods_receipt_id,
            inventoryMovementId: (int) $movement->getKey(),
            metadata: [
                'material_id' => (int) $material->getKey(),
                'quantity' => $quantity,
                'on_hand_delta' => $onHandDelta,
                'reserved_delta' => $reservedDelta,
            ],
        );

        return $movement;
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }

    public function goodsReceiptItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class);
    }

    public function materialStockReservation(): BelongsTo
    {
        return $this->belongsTo(MaterialStockReservation::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
