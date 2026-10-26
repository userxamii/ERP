<?php

namespace App;

use App\Models\Delivery;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryBatch;
use App\Models\Material;
use App\Models\ProcurementActivity;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequestItem;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;

final class ProcurementDashboardReport
{
    /**
     * @return array{
     *     as_of: string,
     *     kpis: array{
     *         low_stock_material_count: int,
     *         approved_unpurchased_request_item_count: int,
     *         approved_unpurchased_quantity: string,
     *         open_inbound_quantity: string,
     *         overdue_delivery_count: int,
     *         expiring_batch_count: int,
     *         expired_batch_count: int,
     *         rejected_quantity: string
     *     },
     *     low_stock_materials: Collection<int, array{material: Material, stock: array}>,
     *     approved_unpurchased_request_items: Collection<int, array{item: PurchaseRequestItem, remaining_quantity: string}>,
     *     expected_deliveries: \Illuminate\Database\Eloquent\Collection<int, Delivery>,
     *     overdue_deliveries: \Illuminate\Database\Eloquent\Collection<int, Delivery>,
     *     expiring_batches: \Illuminate\Database\Eloquent\Collection<int, InventoryBatch>,
     *     expired_batches: \Illuminate\Database\Eloquent\Collection<int, InventoryBatch>,
     *     recent_activities: \Illuminate\Database\Eloquent\Collection<int, ProcurementActivity>
     * }
     */
    public function snapshot(
        ?DateTimeInterface $asOf = null,
        int $expiringWithinDays = 30,
        int $activityLimit = 10,
    ): array {
        if ($expiringWithinDays < 0 || $activityLimit < 1) {
            throw new \InvalidArgumentException('Expiry window must be nonnegative and activity limit must be positive.');
        }

        $today = $asOf === null
            ? CarbonImmutable::today()
            : CarbonImmutable::instance(\DateTime::createFromInterface($asOf))->startOfDay();

        $materials = Material::query()
            ->where('is_active', true)
            ->with([
                'unitOfMeasure:id,code',
                'inventoryBatches:id,material_id,quantity_on_hand,quality_status,expires_at',
                'stockReservations:id,material_id,quantity,status',
            ])
            ->orderBy('code')
            ->get();
        $stockPositions = $materials->map(fn (Material $material): array => [
            'material' => $material,
            'stock' => $material->stockPosition(),
        ]);
        $lowStockMaterials = $stockPositions->filter(
            fn (array $position): bool => $position['stock']['low_stock'],
        )->values();

        $approvedUnallocatedItems = PurchaseRequestItem::query()
            ->whereHas('purchaseRequest', fn ($query) => $query->where('status', PurchaseRequestStatus::Approved->value))
            ->with('purchaseOrderAllocations.purchaseOrderItem.purchaseOrder', 'material')
            ->orderBy('id')
            ->get()
            ->map(function (PurchaseRequestItem $item): array {
                return [
                    'item' => $item,
                    'remaining_quantity' => ProcurementQuantity::fromMilliUnits($item->unpurchasedQuantityMilli()),
                ];
            })
            ->filter(fn (array $row): bool => ProcurementQuantity::toMilliUnits($row['remaining_quantity']) > 0)
            ->values();

        $orderedItems = PurchaseOrderItem::query()
            ->whereHas('purchaseOrder', fn ($query) => $query->where('status', PurchaseOrderStatus::Ordered->value))
            ->with([
                'deliveryItems.delivery',
                'goodsReceiptItems.goodsReceipt',
            ])
            ->get();
        $openInboundMilli = $orderedItems->sum(fn (PurchaseOrderItem $item): int => $item->openInboundMilli());

        $expectedDeliveries = Delivery::query()
            ->whereIn('status', [DeliveryStatus::Dispatched->value, DeliveryStatus::PartiallyReceived->value])
            ->whereHas('purchaseOrder', fn ($query) => $query->where('status', PurchaseOrderStatus::Ordered->value))
            ->with(['purchaseOrder', 'supplier', 'items.purchaseOrderItem'])
            ->orderBy('expected_arrival_date')
            ->orderBy('id')
            ->get();
        $overdueDeliveries = $expectedDeliveries
            ->filter(fn (Delivery $delivery): bool => $delivery->expected_arrival_date !== null
                && $delivery->expected_arrival_date->lt($today))
            ->values();

        $expiringBatches = InventoryBatch::query()
            ->where('quality_status', InventoryBatchQualityStatus::Available->value)
            ->where('quantity_on_hand', '>', 0)
            ->whereBetween('expires_at', [
                $today->toDateString(),
                $today->addDays($expiringWithinDays)->toDateString(),
            ])
            ->with('material')
            ->orderBy('expires_at')
            ->orderBy('id')
            ->get();
        $expiredBatches = InventoryBatch::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $today->toDateString())
            ->where('quantity_on_hand', '>', 0)
            ->with('material')
            ->orderBy('expires_at')
            ->orderBy('id')
            ->get();

        $rejectedQuantityMilli = GoodsReceiptItem::query()
            ->where('rejected_qty', '>', 0)
            ->whereHas('goodsReceipt', fn ($query) => $query->where('status', GoodsReceiptStatus::Posted->value))
            ->pluck('rejected_qty')
            ->sum(fn (string $quantity): int => ProcurementQuantity::toMilliUnits($quantity));

        return [
            'as_of' => $today->toDateString(),
            'kpis' => [
                'low_stock_material_count' => $lowStockMaterials->count(),
                'approved_unpurchased_request_item_count' => $approvedUnallocatedItems->count(),
                'approved_unpurchased_quantity' => ProcurementQuantity::fromMilliUnits(
                    $approvedUnallocatedItems->sum(fn (array $row): int => ProcurementQuantity::toMilliUnits($row['remaining_quantity'])),
                ),
                'open_inbound_quantity' => ProcurementQuantity::fromMilliUnits($openInboundMilli),
                'overdue_delivery_count' => $overdueDeliveries->count(),
                'expiring_batch_count' => $expiringBatches->count(),
                'expired_batch_count' => $expiredBatches->count(),
                'rejected_quantity' => ProcurementQuantity::fromMilliUnits($rejectedQuantityMilli),
            ],
            'low_stock_materials' => $lowStockMaterials,
            'approved_unpurchased_request_items' => $approvedUnallocatedItems,
            'expected_deliveries' => $expectedDeliveries,
            'overdue_deliveries' => $overdueDeliveries,
            'expiring_batches' => $expiringBatches,
            'expired_batches' => $expiredBatches,
            'recent_activities' => ProcurementActivity::query()
                ->with(['actor', 'purchaseRequest', 'purchaseOrder', 'delivery', 'goodsReceipt'])
                ->recent()
                ->limit($activityLimit)
                ->get(),
        ];
    }
}
