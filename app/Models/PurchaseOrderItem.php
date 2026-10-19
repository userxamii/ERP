<?php

namespace App\Models;

use App\DeliveryStatus;
use App\GoodsReceiptStatus;
use App\ProcurementQuantity;
use App\PurchaseOrderStatus;
use App\PurchaseRequestStatus;
use Database\Factories\PurchaseOrderItemFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class PurchaseOrderItem extends Model
{
    /** @use HasFactory<PurchaseOrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'material_id',
        'unit_of_measure_id',
        'supplier_material_id',
        'ordered_qty',
        'unit_price',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'ordered_qty' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'line_total' => 'decimal:7',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PurchaseOrderItem $item): void {
            $item->assertDraftOrder();
            $item->assertValidReferencesAndTerms();
            $item->line_total = self::multiplyDecimals(
                (string) $item->ordered_qty,
                (string) $item->unit_price,
            );

            if ($item->exists && $item->isDirty([
                'purchase_order_id',
                'material_id',
                'unit_of_measure_id',
                'supplier_material_id',
            ]) && $item->allocations()->exists()) {
                throw new DomainException('Purchase order item references cannot change after demand has been allocated.');
            }

            if ($item->exists && $item->isDirty('ordered_qty')
                && ProcurementQuantity::toMilliUnits((string) $item->ordered_qty) < $item->allocatedQuantityMilli()) {
                throw new DomainException('Ordered quantity cannot be less than its allocated approved demand.');
            }
        });

        static::deleting(function (PurchaseOrderItem $item): void {
            $item->assertDraftOrder();
        });
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    public function supplierMaterial(): BelongsTo
    {
        return $this->belongsTo(SupplierMaterial::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PurchaseOrderItemAllocation::class);
    }

    public function deliveryItems(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function inventoryBatches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    public function allocatedQuantityMilli(): int
    {
        return $this->allocations()
            ->pluck('allocated_qty')
            ->sum(fn (string $quantity): int => ProcurementQuantity::toMilliUnits($quantity));
    }

    public function allocateFrom(PurchaseRequestItem $requestItem, string $quantity): PurchaseOrderItemAllocation
    {
        return DB::transaction(function () use ($requestItem, $quantity): PurchaseOrderItemAllocation {
            $purchaseOrderItem = self::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $purchaseOrder = $purchaseOrderItem->purchaseOrder()
                ->lockForUpdate()
                ->firstOrFail();
            $lockedRequestItem = PurchaseRequestItem::query()
                ->whereKey($requestItem->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $purchaseRequest = $lockedRequestItem->purchaseRequest()
                ->lockForUpdate()
                ->firstOrFail();

            if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
                throw new DomainException('Approved demand can only be allocated to a draft purchase order.');
            }

            if ($purchaseRequest->status !== PurchaseRequestStatus::Approved) {
                throw new DomainException('Only approved purchase request items can be ordered.');
            }

            if ($purchaseOrderItem->material_id !== $lockedRequestItem->material_id
                || $purchaseOrderItem->unit_of_measure_id !== $lockedRequestItem->unit_of_measure_id) {
                throw new DomainException('Purchase order items must match the approved request material and unit.');
            }

            $quantityMilli = ProcurementQuantity::toMilliUnits($quantity);

            if ($quantityMilli <= 0 || $quantityMilli > $lockedRequestItem->remainingQuantityMilli()) {
                throw new DomainException('Allocated quantity must be positive and cannot exceed unallocated approved demand.');
            }

            if ($purchaseOrderItem->allocatedQuantityMilli() + $quantityMilli
                > ProcurementQuantity::toMilliUnits((string) $purchaseOrderItem->ordered_qty)) {
                throw new DomainException('Allocated quantity cannot exceed the purchase order line quantity.');
            }

            if ($purchaseOrderItem->allocations()
                ->where('purchase_request_item_id', $lockedRequestItem->getKey())
                ->exists()) {
                throw new DomainException('A purchase order line can only allocate a request item once.');
            }

            return PurchaseOrderItemAllocation::record(
                $purchaseOrderItem,
                $lockedRequestItem,
                ProcurementQuantity::fromMilliUnits($quantityMilli),
            );
        });
    }

    public static function quantityMilli(string $quantity): int
    {
        return ProcurementQuantity::toMilliUnits($quantity);
    }

    public function dispatchedQuantityMilli(): int
    {
        $deliveryRelationsAreLoaded = $this->relationLoaded('deliveryItems')
            && $this->deliveryItems->every(fn (DeliveryItem $deliveryItem): bool => $deliveryItem->relationLoaded('delivery'));

        if ($deliveryRelationsAreLoaded) {
            return $this->deliveryItems
                ->filter(fn (DeliveryItem $deliveryItem): bool => $deliveryItem->relationLoaded('delivery')
                    && in_array($deliveryItem->delivery->status, [
                        DeliveryStatus::Dispatched,
                        DeliveryStatus::PartiallyReceived,
                        DeliveryStatus::Received,
                    ], true))
                ->sum(fn (DeliveryItem $deliveryItem): int => ProcurementQuantity::toMilliUnits(
                    (string) $deliveryItem->dispatched_qty,
                ));
        }

        $total = 0;

        foreach ($this->deliveryItems()
            ->whereHas('delivery', fn ($query) => $query->whereIn('status', [
                DeliveryStatus::Dispatched->value,
                DeliveryStatus::PartiallyReceived->value,
                DeliveryStatus::Received->value,
            ]))
            ->pluck('dispatched_qty') as $quantity) {
            $total += ProcurementQuantity::toMilliUnits((string) $quantity);
        }

        return $total;
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

        $total = 0;

        foreach ($this->goodsReceiptItems()
            ->whereHas('goodsReceipt', fn ($query) => $query->where('status', 'posted'))
            ->get(['accepted_qty', 'rejected_qty']) as $receiptItem) {
            $total += $receiptItem->receivedQuantityMilli();
        }

        return $total;
    }

    public function remainingToReceiveMilli(): int
    {
        return max(
            0,
            ProcurementQuantity::toMilliUnits((string) $this->ordered_qty) - $this->receivedQuantityMilli(),
        );
    }

    public function openInboundMilli(): int
    {
        return max(0, $this->dispatchedQuantityMilli() - $this->receivedQuantityMilli());
    }

    private function assertDraftOrder(): void
    {
        $isDraft = PurchaseOrder::query()
            ->whereKey($this->purchase_order_id)
            ->where('status', PurchaseOrderStatus::Draft->value)
            ->exists();

        if (! $isDraft) {
            throw new DomainException('Purchase order items can only be changed while the order is a draft.');
        }
    }

    private function assertValidReferencesAndTerms(): void
    {
        $material = Material::query()->find($this->material_id);
        $purchaseOrder = PurchaseOrder::query()->find($this->purchase_order_id);

        if ($material === null || ! $material->is_active || $purchaseOrder === null) {
            throw new DomainException('Purchase order items require an active material and an existing purchase order.');
        }

        if ((int) $material->unit_of_measure_id !== (int) $this->unit_of_measure_id) {
            throw new DomainException('The unit of measure must match the selected material.');
        }

        if (! preg_match('/^\d+(?:\.\d{1,3})?$/', (string) $this->ordered_qty)
            || ProcurementQuantity::toMilliUnits((string) $this->ordered_qty) <= 0) {
            throw new DomainException('Ordered quantity must be positive with at most three decimal places.');
        }

        if (! preg_match('/^\d+(?:\.\d{1,4})?$/', (string) $this->unit_price)) {
            throw new DomainException('Unit price must be nonnegative with at most four decimal places.');
        }

        if ($this->supplier_material_id === null) {
            return;
        }

        $supplierMaterial = SupplierMaterial::query()->find($this->supplier_material_id);

        if ($supplierMaterial === null
            || ! $supplierMaterial->is_active
            || (int) $supplierMaterial->supplier_id !== (int) $purchaseOrder->supplier_id
            || (int) $supplierMaterial->material_id !== (int) $this->material_id) {
            throw new DomainException('Supplier-material terms must be active and match the purchase order supplier and material.');
        }

        if ($supplierMaterial->currency_code !== null && $supplierMaterial->currency_code !== $purchaseOrder->currency_code) {
            throw new DomainException('Purchase order currency must match the supplier-material terms.');
        }

        $orderedMilli = ProcurementQuantity::toMilliUnits((string) $this->ordered_qty);
        $minimumMilli = ProcurementQuantity::toMilliUnits((string) $supplierMaterial->minimum_order_qty);

        if ($orderedMilli < $minimumMilli) {
            throw new DomainException('Ordered quantity cannot be less than the supplier minimum order quantity.');
        }

        if ($supplierMaterial->order_multiple_qty !== null) {
            $multipleMilli = ProcurementQuantity::toMilliUnits((string) $supplierMaterial->order_multiple_qty);

            if ($multipleMilli > 0 && $orderedMilli % $multipleMilli !== 0) {
                throw new DomainException('Ordered quantity must be a multiple of the supplier order quantity increment.');
            }
        }
    }

    private static function multiplyDecimals(string $left, string $right): string
    {
        $leftScale = strlen(strstr($left, '.') ?: '') - (str_contains($left, '.') ? 1 : 0);
        $rightScale = strlen(strstr($right, '.') ?: '') - (str_contains($right, '.') ? 1 : 0);
        $scale = $leftScale + $rightScale;
        $leftDigits = ltrim(str_replace('.', '', $left), '0') ?: '0';
        $rightDigits = ltrim(str_replace('.', '', $right), '0') ?: '0';
        $digits = array_fill(0, strlen($leftDigits) + strlen($rightDigits), 0);

        for ($leftIndex = strlen($leftDigits) - 1; $leftIndex >= 0; $leftIndex--) {
            for ($rightIndex = strlen($rightDigits) - 1; $rightIndex >= 0; $rightIndex--) {
                $position = $leftIndex + $rightIndex + 1;
                $product = ((int) $leftDigits[$leftIndex] * (int) $rightDigits[$rightIndex]) + $digits[$position];
                $digits[$position] = $product % 10;
                $digits[$position - 1] += intdiv($product, 10);
            }
        }

        $productDigits = ltrim(implode('', $digits), '0') ?: '0';

        if ($scale === 0) {
            return $productDigits.'.0000000';
        }

        $productDigits = str_pad($productDigits, $scale + 1, '0', STR_PAD_LEFT);
        $whole = substr($productDigits, 0, -$scale);
        $fraction = str_pad(substr($productDigits, -$scale), 7, '0');

        return $whole.'.'.$fraction;
    }
}
