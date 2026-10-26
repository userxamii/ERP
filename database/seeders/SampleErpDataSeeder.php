<?php

namespace Database\Seeders;

use App\DeliveryStatus;
use App\GoodsReceiptStatus;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryBatch;
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
use Illuminate\Support\Str;

class SampleErpDataSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, category: string, unit: string, minimum: string, reorder: string}>
     */
    private const MATERIALS = [
        'SAMPLE-STEEL-001' => [
            'name' => 'Sample Stainless Steel Coil',
            'category' => 'Sample Raw Materials',
            'unit' => 'kg',
            'minimum' => '20.000',
            'reorder' => '100.000',
        ],
        'SAMPLE-RESIN-001' => [
            'name' => 'Sample Engineering Resin',
            'category' => 'Sample Polymers',
            'unit' => 'kg',
            'minimum' => '15.000',
            'reorder' => '75.000',
        ],
        'SAMPLE-PCB-001' => [
            'name' => 'Sample Control Board',
            'category' => 'Sample Electronics',
            'unit' => 'pcs',
            'minimum' => '10.000',
            'reorder' => '50.000',
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const STORAGE_LOCATIONS = [
        'SAMPLE-STEEL-001' => 'SAMPLE-WH-01 / A-01',
        'SAMPLE-RESIN-001' => 'SAMPLE-WH-01 / B-02',
        'SAMPLE-PCB-001' => 'SAMPLE-WH-01 / C-03',
    ];

    public function run(): void
    {
        $departments = $this->seedDepartments();
        $roles = $this->seedRoles();
        $actors = $this->seedActors($departments, $roles);
        $materials = $this->seedMaterials();
        $supplier = $this->seedSupplier();
        $supplierMaterials = $this->seedSupplierMaterials($supplier, $materials);
        $this->seedWarehouse();

        $today = CarbonImmutable::today();

        try {
            foreach ([3, 2, 1, 0] as $cycle => $monthsAgo) {
                $cycleDate = $today->subMonths($monthsAgo)->setTime(10, 0);
                Date::setTestNow($cycleDate);

                $this->seedProcurementCycle(
                    $cycle + 1,
                    $cycleDate,
                    $today,
                    $actors,
                    $materials,
                    $supplier,
                    $supplierMaterials,
                );
            }

            Date::setTestNow($today->setTime(10, 0));
            $this->seedAdditionalRequests($today, $actors, $materials);
        } finally {
            Date::setTestNow();
        }
    }

    /**
     * @return array<string, int>
     */
    private function seedDepartments(): array
    {
        $records = [
            'Production' => ['code' => 'PROD', 'description' => 'Production planning and operations.'],
            'Procurement' => ['code' => 'PROC', 'description' => 'Sourcing, purchasing, and supplier coordination.'],
            'Warehouse' => ['code' => 'WH', 'description' => 'Receiving, inventory control, and material issues.'],
            'Quality Assurance' => ['code' => 'QA', 'description' => 'Incoming material quality inspections.'],
            'Management' => ['code' => 'MGMT', 'description' => 'Approvals and operational oversight.'],
            'Finance' => ['code' => 'FIN', 'description' => 'Financial operations and reporting.'],
            'Sales & Distribution' => ['code' => 'DIST', 'description' => 'Customer fulfillment and distribution.'],
        ];
        $departmentIds = [];

        foreach ($records as $name => $record) {
            $existing = DB::table('departments')
                ->where('code', $record['code'])
                ->orWhere('name', $name)
                ->first();

            $departmentIds[$name] = $existing === null
                ? (int) DB::table('departments')->insertGetId([
                    'code' => $record['code'],
                    'name' => $name,
                    'description' => $record['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                : (int) $existing->id;
        }

        return $departmentIds;
    }

    /**
     * @return array<string, int>
     */
    private function seedRoles(): array
    {
        $roleIds = [];

        foreach (User::ROLES as $role) {
            $existing = DB::table('roles')->where('name', $role)->first();

            $roleIds[$role] = $existing === null
                ? (int) DB::table('roles')->insertGetId([
                    'name' => $role,
                    'description' => 'Sample ERP role for '.$role.'.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                : (int) $existing->id;
        }

        return $roleIds;
    }

    /**
     * @param  array<string, int>  $departmentIds
     * @param  array<string, int>  $roleIds
     * @return array{production: User, procurement: User, warehouse: User, quality: User, management: User, finance: User, distribution: User}
     */
    private function seedActors(array $departmentIds, array $roleIds): array
    {
        $actorData = [
            'production' => ['Sample Production Operator', 'production', 'Production Staff', 'Production'],
            'procurement' => ['Sample Procurement Officer', 'procurement', 'Procurement Staff', 'Procurement'],
            'warehouse' => ['Sample Warehouse Operator', 'warehouse', 'Warehouse Staff', 'Warehouse'],
            'quality' => ['Sample Quality Inspector', 'quality', 'Quality Assurance (QA)', 'Quality Assurance'],
            'management' => ['Sample Manager', 'management', 'Management', 'Management'],
            'finance' => ['Sample Finance Analyst', 'finance', 'Finance Staff', 'Finance'],
            'distribution' => ['Sample Distribution Coordinator', 'distribution', 'Sales & Distribution Staff', 'Sales & Distribution'],
        ];
        $actors = [];

        foreach ($actorData as $key => [$name, $emailName, $role, $department]) {
            $email = "sample.{$emailName}@example.test";
            $actor = User::query()->where('email', $email)->first();

            if ($actor === null) {
                $actor = new User;
                $actor->forceFill([
                    'name' => $name,
                    'email' => $email,
                    'password' => Str::random(64),
                    'role' => $role,
                    'department' => $department,
                    'role_id' => $roleIds[$role],
                    'department_id' => $departmentIds[$department],
                    'is_active' => true,
                    'must_change_password' => true,
                ]);
                $actor->save();
            }

            $actors[$key] = $actor;
        }

        return $actors;
    }

    /**
     * @return array<string, Material>
     */
    private function seedMaterials(): array
    {
        $units = [
            'kg' => UnitOfMeasure::query()->firstOrCreate(
                ['code' => 'kg'],
                ['name' => 'Kilogram', 'decimal_places' => 3],
            ),
            'pcs' => UnitOfMeasure::query()->firstOrCreate(
                ['code' => 'pcs'],
                ['name' => 'Pieces', 'decimal_places' => 3],
            ),
        ];
        $materials = [];

        foreach (self::MATERIALS as $code => $attributes) {
            $category = MaterialCategory::query()->firstOrCreate(
                ['name' => $attributes['category']],
                ['is_active' => true],
            );
            $materials[$code] = Material::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $attributes['name'],
                    'material_category_id' => $category->getKey(),
                    'unit_of_measure_id' => $units[$attributes['unit']]->getKey(),
                    'minimum_stock_qty' => $attributes['minimum'],
                    'reorder_to_qty' => $attributes['reorder'],
                    'shelf_life_days' => 365,
                    'is_active' => true,
                ],
            );
        }

        return $materials;
    }

    private function seedSupplier(): Supplier
    {
        return Supplier::query()->firstOrCreate(
            ['supplier_code' => 'SAMPLE-SUP-001'],
            [
                'name' => 'Sample Components Supply Co.',
                'contact_person' => 'Jordan Lee',
                'contact_email' => 'supplier@example.test',
                'contact_phone' => '+1-555-0100',
                'is_active' => true,
                'notes' => 'Sample supplier for end-to-end ERP demonstrations.',
            ],
        );
    }

    /**
     * @param  array<string, Material>  $materials
     * @return array<string, SupplierMaterial>
     */
    private function seedSupplierMaterials(Supplier $supplier, array $materials): array
    {
        $terms = [
            'SAMPLE-STEEL-001' => ['SKU-STEEL-01', 12, '10.000', '5.000', '4.2500'],
            'SAMPLE-RESIN-001' => ['SKU-RESIN-01', 8, '10.000', '5.000', '3.1500'],
            'SAMPLE-PCB-001' => ['SKU-PCB-01', 18, '5.000', '5.000', '12.8000'],
        ];
        $supplierMaterials = [];

        foreach ($terms as $materialCode => [$sku, $leadTime, $minimum, $multiple, $price]) {
            $material = $materials[$materialCode];
            $supplierMaterials[$materialCode] = SupplierMaterial::query()->firstOrCreate(
                [
                    'supplier_id' => $supplier->getKey(),
                    'material_id' => $material->getKey(),
                ],
                [
                    'supplier_sku' => $sku,
                    'lead_time_days' => $leadTime,
                    'minimum_order_qty' => $minimum,
                    'order_multiple_qty' => $multiple,
                    'quoted_unit_price' => $price,
                    'currency_code' => 'USD',
                    'is_preferred' => true,
                    'is_active' => true,
                ],
            );
        }

        return $supplierMaterials;
    }

    private function seedWarehouse(): void
    {
        $exists = DB::table('warehouses')->where('code', 'SAMPLE-WH-01')->exists();

        if (! $exists) {
            DB::table('warehouses')->insert([
                'code' => 'SAMPLE-WH-01',
                'name' => 'Sample Central Warehouse',
                'address' => '100 Sample Industrial Way',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array{production: User, procurement: User, warehouse: User, quality: User, management: User, finance: User, distribution: User}  $actors
     * @param  array<string, Material>  $materials
     * @param  array<string, SupplierMaterial>  $supplierMaterials
     */
    private function seedProcurementCycle(
        int $cycleNumber,
        CarbonImmutable $cycleDate,
        CarbonImmutable $today,
        array $actors,
        array $materials,
        Supplier $supplier,
        array $supplierMaterials,
    ): void {
        $requestNumber = sprintf('SAMPLE-PR-%02d', $cycleNumber);
        $purchaseRequest = PurchaseRequest::query()->where('request_number', $requestNumber)->first();

        if ($purchaseRequest === null) {
            $purchaseRequest = new PurchaseRequest;
            $purchaseRequest->forceFill([
                'request_number' => $requestNumber,
                'requested_by_user_id' => $actors['production']->getKey(),
                'request_date' => $cycleDate->toDateString(),
                'required_date' => $cycleDate->addDays(14)->toDateString(),
                'purpose' => 'Sample production replenishment for integrated analytics.',
                'notes' => 'Seeded sample transaction; linked to purchasing, receiving, and production issue.',
            ]);
            $purchaseRequest->save();
        }

        $quantities = [
            'SAMPLE-STEEL-001' => '80.000',
            'SAMPLE-RESIN-001' => '50.000',
            'SAMPLE-PCB-001' => '20.000',
        ];
        $requestItems = [];

        foreach ($quantities as $materialCode => $quantity) {
            $material = $materials[$materialCode];
            $requestItem = PurchaseRequestItem::query()
                ->where('purchase_request_id', $purchaseRequest->getKey())
                ->where('material_id', $material->getKey())
                ->first();

            if ($requestItem === null) {
                if ($purchaseRequest->status !== PurchaseRequestStatus::Draft) {
                    throw new \DomainException("Sample purchase request {$requestNumber} is missing an item after submission.");
                }

                $requestItem = $purchaseRequest->items()->create([
                    'material_id' => $material->getKey(),
                    'unit_of_measure_id' => $material->unit_of_measure_id,
                    'requested_qty' => $quantity,
                    'justification' => 'Replenish sample production stock.',
                ]);
            }

            $requestItems[$materialCode] = $requestItem;
        }

        if ($purchaseRequest->status === PurchaseRequestStatus::Draft) {
            $purchaseRequest->submit();
        }

        if ($purchaseRequest->fresh()->status === PurchaseRequestStatus::Submitted) {
            $purchaseRequest->recordDecision(
                $actors['management'],
                PurchaseRequestApprovalDecision::Approved,
                'Approved for the sample production plan.',
            );
        }

        if ($purchaseRequest->fresh()->status !== PurchaseRequestStatus::Approved) {
            throw new \DomainException("Sample purchase request {$requestNumber} must be approved before ordering.");
        }

        $orderNumber = sprintf('SAMPLE-PO-%02d', $cycleNumber);
        $purchaseOrder = PurchaseOrder::query()->where('order_number', $orderNumber)->first();

        if ($purchaseOrder === null) {
            $purchaseOrder = new PurchaseOrder;
            $purchaseOrder->forceFill([
                'order_number' => $orderNumber,
                'supplier_id' => $supplier->getKey(),
                'order_date' => $cycleDate->toDateString(),
                'expected_delivery_date' => $cycleDate->addDays(7)->toDateString(),
                'currency_code' => 'USD',
                'notes' => 'Sample order linked to approved production demand.',
                'created_by_user_id' => $actors['procurement']->getKey(),
            ]);
            $purchaseOrder->save();
        }

        $orderItems = [];

        foreach ($quantities as $materialCode => $quantity) {
            $material = $materials[$materialCode];
            $terms = $supplierMaterials[$materialCode];
            $orderItem = PurchaseOrderItem::query()
                ->where('purchase_order_id', $purchaseOrder->getKey())
                ->where('material_id', $material->getKey())
                ->first();

            if ($orderItem === null) {
                if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
                    throw new \DomainException("Sample purchase order {$orderNumber} is missing an item after placement.");
                }

                $orderItem = $purchaseOrder->items()->create([
                    'material_id' => $material->getKey(),
                    'unit_of_measure_id' => $material->unit_of_measure_id,
                    'supplier_material_id' => $terms->getKey(),
                    'ordered_qty' => $quantity,
                    'unit_price' => $terms->quoted_unit_price,
                    'notes' => 'Allocated to approved production demand.',
                ]);
            }

            if (! $orderItem->allocations()
                ->where('purchase_request_item_id', $requestItems[$materialCode]->getKey())
                ->exists()) {
                $orderItem->allocateFrom($requestItems[$materialCode], $quantity);
            }

            $orderItems[$materialCode] = $orderItem;
        }

        if ($purchaseOrder->status === PurchaseOrderStatus::Draft) {
            $purchaseOrder->place($actors['procurement'], 'Sample order placed for approved production demand.');
        }

        $deliveryNumber = sprintf('SAMPLE-DL-%02d', $cycleNumber);
        $delivery = Delivery::query()->where('delivery_number', $deliveryNumber)->first();

        if ($delivery === null) {
            $delivery = new Delivery;
            $delivery->forceFill([
                'delivery_number' => $deliveryNumber,
                'purchase_order_id' => $purchaseOrder->getKey(),
                'supplier_id' => $supplier->getKey(),
                'supplier_delivery_reference' => sprintf('SUP-SAMPLE-%02d', $cycleNumber),
                'tracking_number' => sprintf('TRACK-SAMPLE-%02d', $cycleNumber),
                'dispatch_date' => $cycleDate->toDateString(),
                'expected_arrival_date' => $cycleDate->addDays(7)->toDateString(),
                'created_by_user_id' => $actors['procurement']->getKey(),
                'notes' => 'Sample supplier delivery.',
            ]);
            $delivery->save();
        }

        if ($delivery->status === DeliveryStatus::Draft) {
            foreach ($orderItems as $orderItem) {
                DeliveryItem::query()->firstOrCreate(
                    [
                        'delivery_id' => $delivery->getKey(),
                        'purchase_order_item_id' => $orderItem->getKey(),
                    ],
                    ['dispatched_qty' => $orderItem->ordered_qty],
                );
            }

            $delivery->dispatch($actors['procurement']);
        }

        $receiptNumber = sprintf('SAMPLE-GR-%02d', $cycleNumber);
        $receipt = GoodsReceipt::query()->where('receipt_number', $receiptNumber)->first();

        if ($receipt === null) {
            $receipt = new GoodsReceipt;
            $receipt->forceFill([
                'receipt_number' => $receiptNumber,
                'delivery_id' => $delivery->getKey(),
                'purchase_order_id' => $purchaseOrder->getKey(),
                'received_by_user_id' => $actors['warehouse']->getKey(),
                'notes' => 'Sample receiving inspection and stock posting.',
            ]);
            $receipt->save();
        }

        $receiptItems = [];

        if ($receipt->status === GoodsReceiptStatus::Draft) {
            foreach ($delivery->items()->with('purchaseOrderItem')->get() as $deliveryItem) {
                $materialCode = (string) $deliveryItem->purchaseOrderItem->material->code;
                $rejectedQuantity = $cycleNumber === 2 && $materialCode === 'SAMPLE-RESIN-001'
                    ? '1.000'
                    : '0.000';
                $acceptedQuantity = number_format(
                    (float) $deliveryItem->dispatched_qty - (float) $rejectedQuantity,
                    3,
                    '.',
                    '',
                );
                $expiresAt = $cycleNumber === 4 && $materialCode === 'SAMPLE-PCB-001'
                    ? $today->addDays(18)
                    : $cycleDate->addDays(180);
                $receiptItem = GoodsReceiptItem::query()->firstOrCreate(
                    [
                        'goods_receipt_id' => $receipt->getKey(),
                        'delivery_item_id' => $deliveryItem->getKey(),
                    ],
                    [
                        'purchase_order_item_id' => $deliveryItem->purchase_order_item_id,
                        'accepted_qty' => $acceptedQuantity,
                        'rejected_qty' => $rejectedQuantity,
                        'lot_code' => sprintf('SAMPLE-LOT-%02d-%s', $cycleNumber, substr($materialCode, -3)),
                        'expires_at' => $expiresAt->toDateString(),
                        'discrepancy_notes' => $rejectedQuantity === '0.000'
                            ? null
                            : 'One kilogram was damaged in transit.',
                    ],
                );
                $receiptItems[$materialCode] = $receiptItem;
            }

            Date::setTestNow($cycleDate->addDays(7)->setTime(15, 0));
            $receipt->post($actors['warehouse'], $cycleDate->addDays(7)->setTime(15, 0));
        } else {
            foreach ($receipt->items()->with('purchaseOrderItem.material')->get() as $receiptItem) {
                $receiptItems[$receiptItem->purchaseOrderItem->material->code] = $receiptItem;
            }
        }

        foreach ($receiptItems as $materialCode => $receiptItem) {
            if ((float) $receiptItem->accepted_qty <= 0) {
                continue;
            }

            $batch = InventoryBatch::query()
                ->where('goods_receipt_item_id', $receiptItem->getKey())
                ->firstOrFail();

            if ($batch->storage_location === null) {
                $batch->storage_location = self::STORAGE_LOCATIONS[$materialCode];
                $batch->save();
            }

            $inspectionExists = DB::table('quality_inspections')
                ->where('inventory_batch_id', $batch->getKey())
                ->exists();

            if (! $inspectionExists) {
                DB::table('quality_inspections')->insert([
                    'inventory_batch_id' => $batch->getKey(),
                    'inspected_by' => $actors['quality']->getKey(),
                    'inspected_at' => $cycleDate->addDays(7)->setTime(14, 0),
                    'result' => 'pass',
                    'notes' => "Sample incoming inspection passed for {$materialCode}.",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $productionMaterialCode = array_keys($quantities)[($cycleNumber - 1) % count($quantities)];
        $productionRequestNumber = sprintf('SAMPLE-PMR-%02d', $cycleNumber);
        $productionRequest = ProductionMaterialRequest::query()
            ->where('request_number', $productionRequestNumber)
            ->first();

        if ($productionRequest === null) {
            $productionRequest = new ProductionMaterialRequest;
            $productionRequest->forceFill([
                'request_number' => $productionRequestNumber,
                'material_id' => $materials[$productionMaterialCode]->getKey(),
                'requested_by_user_id' => $actors['production']->getKey(),
                'production_order' => sprintf('SAMPLE-WO-%02d', $cycleNumber),
                'purpose' => 'Sample production issue linked to received warehouse stock.',
                'required_date' => $cycleDate->addDays(10)->toDateString(),
                'requested_qty' => '10.000',
            ]);
            $productionRequest->save();
        }

        if ($productionRequest->status === ProductionMaterialRequestStatus::PendingApproval) {
            $productionRequest->recordDecision(
                $actors['management'],
                true,
                'Approved against available sample inventory.',
            );
        }

        if ($productionRequest->status === ProductionMaterialRequestStatus::Approved) {
            $batch = InventoryBatch::query()
                ->where('goods_receipt_item_id', $receiptItems[$productionMaterialCode]->getKey())
                ->firstOrFail();
            $quantityToIssue = $cycleNumber === 4 ? '6.000' : '10.000';
            $productionRequest->recordFulfillment(
                $batch,
                $quantityToIssue,
                $actors['warehouse'],
                'Sample Production Line 1',
            );
        }
    }

    /**
     * @param  array{production: User, procurement: User, warehouse: User, quality: User, management: User, finance: User, distribution: User}  $actors
     * @param  array<string, Material>  $materials
     */
    private function seedAdditionalRequests(CarbonImmutable $today, array $actors, array $materials): void
    {
        $pending = $this->findOrCreatePurchaseRequest(
            'SAMPLE-PR-PENDING',
            $actors['production'],
            $materials['SAMPLE-PCB-001'],
            $today,
            'Pending sample request for approval dashboard coverage.',
        );

        if ($pending->status === PurchaseRequestStatus::Draft) {
            $pending->items()->firstOrCreate(
                [
                    'purchase_request_id' => $pending->getKey(),
                    'material_id' => $materials['SAMPLE-PCB-001']->getKey(),
                ],
                [
                    'unit_of_measure_id' => $materials['SAMPLE-PCB-001']->unit_of_measure_id,
                    'requested_qty' => '12.000',
                    'justification' => 'Sample request awaiting management approval.',
                ],
            );
            $pending->submit();
        }

        $rejected = $this->findOrCreatePurchaseRequest(
            'SAMPLE-PR-REJECTED',
            $actors['distribution'],
            $materials['SAMPLE-STEEL-001'],
            $today->subDays(5),
            'Rejected sample request for approval-history coverage.',
        );

        if ($rejected->status === PurchaseRequestStatus::Draft) {
            $rejected->items()->firstOrCreate(
                [
                    'purchase_request_id' => $rejected->getKey(),
                    'material_id' => $materials['SAMPLE-STEEL-001']->getKey(),
                ],
                [
                    'unit_of_measure_id' => $materials['SAMPLE-STEEL-001']->unit_of_measure_id,
                    'requested_qty' => '25.000',
                    'justification' => 'Sample distribution demand.',
                ],
            );
            $rejected->submit();
        }

        if ($rejected->status === PurchaseRequestStatus::Submitted) {
            $rejected->recordDecision(
                $actors['management'],
                PurchaseRequestApprovalDecision::Rejected,
                'Sample decision: request requires a revised forecast.',
            );
        }

        $productionRequest = ProductionMaterialRequest::query()
            ->where('request_number', 'SAMPLE-PMR-PENDING')
            ->first();

        if ($productionRequest === null) {
            $productionRequest = new ProductionMaterialRequest;
            $productionRequest->forceFill([
                'request_number' => 'SAMPLE-PMR-PENDING',
                'material_id' => $materials['SAMPLE-PCB-001']->getKey(),
                'requested_by_user_id' => $actors['production']->getKey(),
                'production_order' => 'SAMPLE-WO-PENDING',
                'purpose' => 'Sample request awaiting management approval.',
                'required_date' => $today->addDays(14)->toDateString(),
                'requested_qty' => '4.000',
            ]);
            $productionRequest->save();
        }
    }

    private function findOrCreatePurchaseRequest(
        string $requestNumber,
        User $requester,
        Material $material,
        CarbonImmutable $requestDate,
        string $purpose,
    ): PurchaseRequest {
        $purchaseRequest = PurchaseRequest::query()->where('request_number', $requestNumber)->first();

        if ($purchaseRequest !== null) {
            return $purchaseRequest;
        }

        $purchaseRequest = new PurchaseRequest;
        $purchaseRequest->forceFill([
            'request_number' => $requestNumber,
            'requested_by_user_id' => $requester->getKey(),
            'request_date' => $requestDate->toDateString(),
            'required_date' => $requestDate->addDays(14)->toDateString(),
            'purpose' => $purpose,
        ]);
        $purchaseRequest->save();

        return $purchaseRequest;
    }
}
