<?php

namespace Database\Seeders;

use App\DeliveryStatus;
use App\GoodsReceiptStatus;
use App\InventoryBatchQualityStatus;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductionMaterialRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Supplier;
use App\Models\SupplierMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\ProductionMaterialRequestStatus;
use App\PurchaseOrderStatus;
use App\PurchaseRequestApprovalDecision;
use App\PurchaseRequestStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class AnalyticsDemoSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, category: string, unit: string, minimum: string, reorder: string, price: string, received: string, issued: string, location: string}>
     */
    private const MATERIALS = [
        'RM-SUGAR-001' => ['name' => 'Refined Cane Sugar', 'category' => 'Raw Materials', 'unit' => 'kg', 'minimum' => '600.000', 'reorder' => '1800.000', 'price' => '48.5000', 'received' => '1800.000', 'issued' => '1320.000', 'location' => 'WH-MNL-01 / A-01'],
        'RM-COCOA-001' => ['name' => 'Alkalized Cocoa Powder', 'category' => 'Raw Materials', 'unit' => 'kg', 'minimum' => '300.000', 'reorder' => '900.000', 'price' => '295.0000', 'received' => '360.000', 'issued' => '250.000', 'location' => 'WH-MNL-01 / A-02'],
        'RM-MILK-001' => ['name' => 'Full Cream Milk Powder', 'category' => 'Raw Materials', 'unit' => 'kg', 'minimum' => '350.000', 'reorder' => '1200.000', 'price' => '225.0000', 'received' => '480.000', 'issued' => '320.000', 'location' => 'WH-MNL-01 / A-03'],
        'RM-OIL-001' => ['name' => 'Refined Palm Oil', 'category' => 'Raw Materials', 'unit' => 'L', 'minimum' => '500.000', 'reorder' => '1800.000', 'price' => '90.0000', 'received' => '700.000', 'issued' => '500.000', 'location' => 'WH-MNL-01 / B-01'],
        'PKG-BOTTLE-001' => ['name' => '500 mL PET Bottle', 'category' => 'Packaging Materials', 'unit' => 'pcs', 'minimum' => '15000.000', 'reorder' => '50000.000', 'price' => '2.8500', 'received' => '24000.000', 'issued' => '19000.000', 'location' => 'WH-MNL-01 / C-01'],
        'PKG-CAP-001' => ['name' => '28 mm Tamper-Evident Bottle Cap', 'category' => 'Packaging Materials', 'unit' => 'pcs', 'minimum' => '18000.000', 'reorder' => '60000.000', 'price' => '0.4200', 'received' => '24000.000', 'issued' => '20000.000', 'location' => 'WH-MNL-01 / C-02'],
        'PKG-LABEL-001' => ['name' => 'Printed Product Label', 'category' => 'Packaging Materials', 'unit' => 'pcs', 'minimum' => '18000.000', 'reorder' => '65000.000', 'price' => '0.3000', 'received' => '26000.000', 'issued' => '21000.000', 'location' => 'WH-MNL-01 / C-03'],
    ];

    /**
     * Seed four months of receipt and production-issue activity for the management analytics workspace.
     */
    public function run(): void
    {
        $users = User::query()->orderBy('id')->get();

        if ($users->isEmpty()) {
            $this->command?->warn('Analytics demo data requires an existing user.');

            return;
        }

        $actors = [
            'procurement' => $users->firstWhere('role', 'Procurement Staff') ?? $users->first(),
            'production' => $users->firstWhere('role', 'Production Staff') ?? $users->first(),
            'warehouse' => $users->firstWhere('role', 'Warehouse Staff') ?? $users->first(),
            'management' => $users->firstWhere('role', 'Management')
                ?? $users->firstWhere('role', 'System Administrator')
                ?? $users->first(),
        ];

        $materials = $this->seedMaterials();
        $supplier = Supplier::query()->updateOrCreate(
            ['supplier_code' => 'ANL-SUP-PH-001'],
            [
                'name' => 'Luzon Food Ingredients Supply Corporation',
                'contact_person' => 'Marisol Santos',
                'contact_email' => 'orders@luzon-ingredients.example.test',
                'contact_phone' => '+63 2 8555 0147',
                'is_active' => true,
                'notes' => 'Philippine peso reference terms for analytics demonstrations.',
            ],
        );
        $supplierMaterials = $this->seedSupplierMaterials($supplier, $materials);
        $today = CarbonImmutable::today();
        $previousTestNow = Date::getTestNow();

        try {
            foreach ([3, 2, 1, 0] as $monthsAgo) {
                $receiptDate = $today->startOfMonth()
                    ->subMonths($monthsAgo)
                    ->addDays($monthsAgo === 0 ? min(7, $today->day - 1) : 7)
                    ->setTime(9, 0);

                DB::transaction(fn () => $this->seedMonthlyActivity(
                    $receiptDate,
                    $today,
                    $monthsAgo,
                    $actors,
                    $supplier,
                    $materials,
                    $supplierMaterials,
                ));
            }
        } finally {
            Date::setTestNow($previousTestNow);
        }
    }

    /**
     * @return array<string, Material>
     */
    private function seedMaterials(): array
    {
        $units = [
            'kg' => UnitOfMeasure::query()->firstOrCreate(['code' => 'kg'], ['name' => 'Kilogram', 'decimal_places' => 3]),
            'L' => UnitOfMeasure::query()->firstOrCreate(['code' => 'L'], ['name' => 'Liter', 'decimal_places' => 3]),
            'pcs' => UnitOfMeasure::query()->firstOrCreate(['code' => 'pcs'], ['name' => 'Pieces', 'decimal_places' => 0]),
        ];
        $materials = [];

        foreach (self::MATERIALS as $code => $attributes) {
            $category = MaterialCategory::query()->firstOrCreate(
                ['name' => $attributes['category']],
                ['is_active' => true],
            );
            $materials[$code] = Material::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $attributes['name'],
                    'material_category_id' => $category->getKey(),
                    'unit_of_measure_id' => $units[$attributes['unit']]->getKey(),
                    'minimum_stock_qty' => $attributes['minimum'],
                    'reorder_to_qty' => $attributes['reorder'],
                    'shelf_life_days' => in_array($code, ['RM-SUGAR-001', 'RM-COCOA-001', 'RM-MILK-001'], true) ? 365 : null,
                    'is_active' => true,
                ],
            );
        }

        return $materials;
    }

    /**
     * @param  array<string, Material>  $materials
     * @return array<string, SupplierMaterial>
     */
    private function seedSupplierMaterials(Supplier $supplier, array $materials): array
    {
        $supplierMaterials = [];

        foreach (self::MATERIALS as $code => $attributes) {
            $supplierMaterials[$code] = SupplierMaterial::query()->updateOrCreate(
                ['supplier_id' => $supplier->getKey(), 'material_id' => $materials[$code]->getKey()],
                [
                    'supplier_sku' => 'ANL-'.str_replace('-', '', $code),
                    'lead_time_days' => $attributes['category'] === 'Packaging Materials' ? 12 : 9,
                    'minimum_order_qty' => $attributes['received'],
                    'order_multiple_qty' => $attributes['received'],
                    'quoted_unit_price' => $attributes['price'],
                    'currency_code' => 'PHP',
                    'is_preferred' => true,
                    'is_active' => true,
                ],
            );
        }

        return $supplierMaterials;
    }

    /**
     * @param  array{procurement: User, production: User, warehouse: User, management: User}  $actors
     * @param  array<string, Material>  $materials
     * @param  array<string, SupplierMaterial>  $supplierMaterials
     */
    private function seedMonthlyActivity(
        CarbonImmutable $receiptDate,
        CarbonImmutable $today,
        int $monthsAgo,
        array $actors,
        Supplier $supplier,
        array $materials,
        array $supplierMaterials,
    ): void {
        $monthKey = $receiptDate->format('Ym');
        $orderDate = $receiptDate->subDays(9);
        Date::setTestNow($orderDate->subDay());
        $purchaseRequest = PurchaseRequest::query()->where('request_number', "ANL-PR-{$monthKey}")->first();

        if ($purchaseRequest === null) {
            $purchaseRequest = new PurchaseRequest;
            $purchaseRequest->forceFill([
                'request_number' => "ANL-PR-{$monthKey}",
                'requested_by_user_id' => $actors['production']->getKey(),
                'request_date' => $orderDate->subDay()->toDateString(),
                'required_date' => $receiptDate->toDateString(),
                'purpose' => 'Monthly ingredient and packaging replenishment for scheduled production.',
            ]);
            $purchaseRequest->save();
        }

        $requestItems = [];

        foreach (self::MATERIALS as $code => $attributes) {
            $requestItems[$code] = PurchaseRequestItem::query()->firstOrCreate(
                ['purchase_request_id' => $purchaseRequest->getKey(), 'material_id' => $materials[$code]->getKey()],
                [
                    'unit_of_measure_id' => $materials[$code]->unit_of_measure_id,
                    'requested_qty' => $attributes['received'],
                    'justification' => 'Replenish stock against the monthly production schedule.',
                ],
            );
        }

        if ($purchaseRequest->status === PurchaseRequestStatus::Draft) {
            $purchaseRequest->submit();
        }

        Date::setTestNow($orderDate);

        if ($purchaseRequest->status === PurchaseRequestStatus::Submitted) {
            $purchaseRequest->recordDecision(
                $actors['management'],
                PurchaseRequestApprovalDecision::Approved,
                'Approved for the monthly production plan.',
            );
        }

        $purchaseOrder = PurchaseOrder::query()->where('order_number', "ANL-PO-{$monthKey}")->first();

        if ($purchaseOrder === null) {
            $purchaseOrder = new PurchaseOrder;
            $purchaseOrder->forceFill([
                'order_number' => "ANL-PO-{$monthKey}",
                'supplier_id' => $supplier->getKey(),
                'order_date' => $orderDate->toDateString(),
                'expected_delivery_date' => $receiptDate->toDateString(),
                'currency_code' => 'PHP',
                'notes' => 'Analytics demonstration replenishment order.',
                'created_by_user_id' => $actors['procurement']->getKey(),
            ]);
            $purchaseOrder->save();
        }

        $orderItems = [];

        foreach (self::MATERIALS as $code => $attributes) {
            $orderItems[$code] = PurchaseOrderItem::query()->firstOrCreate(
                ['purchase_order_id' => $purchaseOrder->getKey(), 'material_id' => $materials[$code]->getKey()],
                [
                    'unit_of_measure_id' => $materials[$code]->unit_of_measure_id,
                    'supplier_material_id' => $supplierMaterials[$code]->getKey(),
                    'ordered_qty' => $attributes['received'],
                    'unit_price' => $attributes['price'],
                    'notes' => 'Monthly replenishment quantity for dashboard analytics.',
                ],
            );

            if (! $orderItems[$code]->allocations()
                ->where('purchase_request_item_id', $requestItems[$code]->getKey())
                ->exists()) {
                $orderItems[$code]->allocateFrom($requestItems[$code], $attributes['received']);
            }
        }

        if ($purchaseOrder->status === PurchaseOrderStatus::Draft) {
            $purchaseOrder->place($actors['procurement'], 'Approved monthly replenishment order.');
        }

        Date::setTestNow($receiptDate->subDay());
        $delivery = Delivery::query()->where('delivery_number', "ANL-DL-{$monthKey}")->first();

        if ($delivery === null) {
            $delivery = new Delivery;
            $delivery->forceFill([
                'delivery_number' => "ANL-DL-{$monthKey}",
                'purchase_order_id' => $purchaseOrder->getKey(),
                'supplier_id' => $supplier->getKey(),
                'supplier_delivery_reference' => "ANL-SUP-{$monthKey}",
                'dispatch_date' => $receiptDate->subDay()->toDateString(),
                'expected_arrival_date' => $receiptDate->toDateString(),
                'created_by_user_id' => $actors['procurement']->getKey(),
            ]);
            $delivery->save();
        }

        if ($delivery->status === DeliveryStatus::Draft) {
            foreach ($orderItems as $orderItem) {
                DeliveryItem::query()->firstOrCreate(
                    ['delivery_id' => $delivery->getKey(), 'purchase_order_item_id' => $orderItem->getKey()],
                    ['dispatched_qty' => $orderItem->ordered_qty],
                );
            }

            $delivery->dispatch($actors['procurement']);
        }

        Date::setTestNow($receiptDate);
        $receipt = GoodsReceipt::query()->where('receipt_number', "ANL-REC-{$monthKey}")->first();

        if ($receipt === null) {
            $receipt = new GoodsReceipt;
            $receipt->forceFill([
                'receipt_number' => "ANL-REC-{$monthKey}",
                'delivery_id' => $delivery->getKey(),
                'purchase_order_id' => $purchaseOrder->getKey(),
                'received_by_user_id' => $actors['warehouse']->getKey(),
                'notes' => 'Incoming ingredient and packaging inspection completed.',
            ]);
            $receipt->save();
        }

        if ($receipt->status === GoodsReceiptStatus::Draft) {
            foreach ($delivery->items()->with('purchaseOrderItem.material')->get() as $deliveryItem) {
                $code = $deliveryItem->purchaseOrderItem->material->code;
                GoodsReceiptItem::query()->firstOrCreate(
                    ['goods_receipt_id' => $receipt->getKey(), 'delivery_item_id' => $deliveryItem->getKey()],
                    [
                        'purchase_order_item_id' => $deliveryItem->purchase_order_item_id,
                        'accepted_qty' => $deliveryItem->dispatched_qty,
                        'rejected_qty' => '0.000',
                        'lot_code' => "ANL-{$monthKey}-{$code}",
                        'expires_at' => $this->expiryDate($code, $receiptDate, $today, $monthsAgo),
                    ],
                );
            }

            $receipt->post($actors['warehouse'], $receiptDate);
        }

        foreach ($receipt->items()->with('inventoryBatch.material')->get() as $receiptItem) {
            $batch = $receiptItem->inventoryBatch;
            $code = $batch->material->code;
            $batch->fill([
                'storage_location' => self::MATERIALS[$code]['location'],
                'manufactured_at' => $receiptDate->subDays(14)->toDateString(),
            ]);
            $batch->save();

            if ($monthsAgo === 0 && $code === 'RM-COCOA-001') {
                $batch->quality_status = InventoryBatchQualityStatus::Held;
                $batch->save();

                continue;
            }

            $issueDate = $receiptDate->addDays(12)->min($today->setTime(15, 0));
            Date::setTestNow($issueDate);
            $productionRequest = ProductionMaterialRequest::query()
                ->firstOrCreate([
                    'request_number' => "ANL-PMR-{$monthKey}-{$code}",
                ], [
                    'material_id' => $materials[$code]->getKey(),
                    'requested_by_user_id' => $actors['production']->getKey(),
                    'production_order' => "ANL-WO-{$monthKey}",
                    'purpose' => 'Scheduled monthly food and beverage production.',
                    'required_date' => $issueDate->toDateString(),
                    'requested_qty' => self::MATERIALS[$code]['issued'],
                ]);

            if ($productionRequest->status === ProductionMaterialRequestStatus::PendingApproval) {
                $productionRequest->recordDecision($actors['management'], true, 'Approved against inspected stock.');
            }

            if ($productionRequest->status === ProductionMaterialRequestStatus::Approved) {
                $productionRequest->recordFulfillment(
                    $batch,
                    self::MATERIALS[$code]['issued'],
                    $actors['warehouse'],
                    str_starts_with($code, 'PKG-') ? 'Packaging Line 2' : 'Production Line 1',
                );
            }
        }
    }

    private function expiryDate(string $code, CarbonImmutable $receiptDate, CarbonImmutable $today, int $monthsAgo): ?string
    {
        return match ($code) {
            'RM-MILK-001' => $monthsAgo >= 2
                ? $receiptDate->addDays(45)->toDateString()
                : ($monthsAgo === 0 ? $today->addDays(18)->toDateString() : $receiptDate->addDays(90)->toDateString()),
            'RM-SUGAR-001' => $receiptDate->addDays(270)->toDateString(),
            'RM-COCOA-001' => $receiptDate->addDays(210)->toDateString(),
            default => null,
        };
    }
}
