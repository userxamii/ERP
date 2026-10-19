<?php

use App\Http\Requests\Procurement\StorePurchaseOrderAllocationRequest;
use App\Http\Requests\Procurement\StorePurchaseOrderItemRequest;
use App\Http\Requests\Procurement\StorePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderItemAllocation;
use App\Models\PurchaseOrderStatusHistory;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Supplier;
use App\Models\SupplierMaterial;
use App\Models\User;
use App\PurchaseOrderStatus;
use App\PurchaseRequestApprovalDecision;
use App\PurchaseRequestStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

function approvedRequestItem(string $quantity = '10.000'): PurchaseRequestItem
{
    $purchaseRequest = PurchaseRequest::factory()->create();
    $requestItem = PurchaseRequestItem::factory()
        ->for($purchaseRequest)
        ->create(['requested_qty' => $quantity]);
    $purchaseRequest->submit();
    $purchaseRequest->recordDecision(
        User::factory()->create(),
        PurchaseRequestApprovalDecision::Approved,
    );

    return $requestItem->fresh();
}

function purchaseOrderItemFor(PurchaseRequestItem $requestItem, array $attributes = []): PurchaseOrderItem
{
    $purchaseOrder = PurchaseOrder::factory()->create();

    return PurchaseOrderItem::factory()
        ->for($purchaseOrder)
        ->for($requestItem->material)
        ->create([
            'unit_of_measure_id' => $requestItem->unit_of_measure_id,
            'ordered_qty' => $requestItem->requested_qty,
            ...$attributes,
        ]);
}

test('purchase orders expose supplier creator items allocations and status history', function () {
    $purchaseOrder = PurchaseOrder::factory()->create();

    expect($purchaseOrder->status)->toBe(PurchaseOrderStatus::Draft)
        ->and($purchaseOrder->supplier)->toBeInstanceOf(Supplier::class)
        ->and($purchaseOrder->creator)->toBeInstanceOf(User::class)
        ->and($purchaseOrder->items)->toBeEmpty()
        ->and($purchaseOrder->allocations)->toBeEmpty()
        ->and($purchaseOrder->statusHistory)->toHaveCount(1)
        ->and($purchaseOrder->statusHistory->first()->to_status)->toBe(PurchaseOrderStatus::Draft)
        ->and($purchaseOrder->isFillable('status'))->toBeFalse()
        ->and($purchaseOrder->isFillable('created_by_user_id'))->toBeFalse();
});

test('purchase order allocation and status history factories create valid related records', function () {
    $allocation = PurchaseOrderItemAllocation::factory()->create();
    $statusHistory = PurchaseOrderStatusHistory::factory()->create();

    expect($allocation->purchaseOrderItem->material_id)->toBe($allocation->purchaseRequestItem->material_id)
        ->and($allocation->purchaseRequestItem->purchaseRequest->status)->toBe(PurchaseRequestStatus::Approved)
        ->and($statusHistory->purchaseOrder)->toBeInstanceOf(PurchaseOrder::class)
        ->and($statusHistory->changedBy)->toBeInstanceOf(User::class);
});

test('purchase order line totals are calculated exactly from quantity and unit price', function () {
    $requestItem = approvedRequestItem('12.500');
    $purchaseOrderItem = purchaseOrderItemFor($requestItem, [
        'ordered_qty' => '12.500',
        'unit_price' => '4.1250',
    ]);

    expect($purchaseOrderItem->line_total)->toBe('51.5625000');
});

test('purchase order items require matching units and active supplier material terms', function () {
    $requestItem = approvedRequestItem();
    $supplier = Supplier::factory()->create();
    $purchaseOrder = PurchaseOrder::factory()->create(['supplier_id' => $supplier->id]);
    $foreignSupplierTerms = SupplierMaterial::factory()->create([
        'material_id' => $requestItem->material_id,
    ]);

    expect(fn () => PurchaseOrderItem::factory()
        ->for($purchaseOrder)
        ->for($requestItem->material)
        ->create([
            'unit_of_measure_id' => $requestItem->unit_of_measure_id + 1,
            'supplier_material_id' => $foreignSupplierTerms->id,
        ]))
        ->toThrow(DomainException::class);

    $matchingTerms = SupplierMaterial::factory()->create([
        'supplier_id' => $supplier->id,
        'material_id' => $requestItem->material_id,
        'minimum_order_qty' => '5.000',
        'order_multiple_qty' => '2.500',
        'quoted_unit_price' => '4.0000',
        'currency_code' => 'USD',
    ]);
    $purchaseOrderItem = PurchaseOrderItem::factory()
        ->for($purchaseOrder)
        ->for($requestItem->material)
        ->create([
            'unit_of_measure_id' => $requestItem->unit_of_measure_id,
            'supplier_material_id' => $matchingTerms->id,
            'ordered_qty' => '10.000',
        ]);

    expect($purchaseOrderItem->supplierMaterial->is($matchingTerms))->toBeTrue();
});

test('only approved request demand can be allocated and partial allocations cannot exceed demand', function () {
    $requestItem = approvedRequestItem('10.000');
    $firstPurchaseOrderItem = purchaseOrderItemFor($requestItem, ['ordered_qty' => '4.000']);
    $secondPurchaseOrderItem = purchaseOrderItemFor($requestItem, ['ordered_qty' => '6.000']);

    $firstAllocation = $requestItem->allocateTo($firstPurchaseOrderItem, '4.000');
    $secondAllocation = $requestItem->allocateTo($secondPurchaseOrderItem, '6.000');

    expect($firstPurchaseOrderItem->allocations)->toHaveCount(1)
        ->and($secondPurchaseOrderItem->allocations)->toHaveCount(1)
        ->and($firstAllocation->purchaseRequestItem->is($requestItem))->toBeTrue()
        ->and($requestItem->fresh()->remainingQuantityMilli())->toBe(0);

    expect(fn () => $requestItem->allocateTo($firstPurchaseOrderItem, '1.000'))
        ->toThrow(DomainException::class);

    $thirdPurchaseOrderItem = purchaseOrderItemFor($requestItem, ['ordered_qty' => '0.001']);
    expect(fn () => $requestItem->allocateTo($thirdPurchaseOrderItem, '0.001'))
        ->toThrow(DomainException::class);

    $unapprovedRequest = PurchaseRequest::factory()->create();
    $unapprovedItem = PurchaseRequestItem::factory()->for($unapprovedRequest)->create();
    $otherOrderItem = purchaseOrderItemFor($unapprovedItem);

    expect(fn () => $unapprovedItem->allocateTo($otherOrderItem, '1.000'))
        ->toThrow(DomainException::class);
});

test('cancelled purchase orders release request allocation and preserve status history', function () {
    $requestItem = approvedRequestItem('10.000');
    $purchaseOrderItem = purchaseOrderItemFor($requestItem, ['ordered_qty' => '10.000']);
    $requestItem->allocateTo($purchaseOrderItem, '10.000');
    $actor = User::factory()->create();

    $purchaseOrderItem->purchaseOrder->cancel($actor, 'Order withdrawn.');

    expect($requestItem->fresh()->remainingQuantityMilli())->toBe(10000)
        ->and($purchaseOrderItem->purchaseOrder->fresh()->status)->toBe(PurchaseOrderStatus::Cancelled)
        ->and($purchaseOrderItem->purchaseOrder->statusHistory()->count())->toBe(2)
        ->and($purchaseOrderItem->purchaseOrder->statusHistory()->latest()->first()->changed_by_user_id)
        ->toBe($actor->id);
});

test('purchase orders can only be placed when all lines are allocated to approved demand', function () {
    $requestItem = approvedRequestItem('10.000');
    $purchaseOrderItem = purchaseOrderItemFor($requestItem, ['ordered_qty' => '10.000']);
    $purchaseOrder = $purchaseOrderItem->purchaseOrder;
    $actor = User::factory()->create();

    expect(fn () => $purchaseOrder->place($actor))->toThrow(DomainException::class);

    $requestItem->allocateTo($purchaseOrderItem, '10.000');
    $purchaseOrder->place($actor, 'Released to supplier.');

    expect($purchaseOrder->fresh()->status)->toBe(PurchaseOrderStatus::Ordered)
        ->and($purchaseOrder->statusHistory()->count())->toBe(2)
        ->and($purchaseOrder->isFillable('status'))->toBeFalse()
        ->and(function () use ($purchaseOrder): void {
            $purchaseOrder->status = PurchaseOrderStatus::Draft;
            $purchaseOrder->save();
        })
        ->toThrow(DomainException::class)
        ->and(fn () => $purchaseOrderItem->refresh()->update(['ordered_qty' => '11.000']))
        ->toThrow(DomainException::class);
});

test('purchase order schema prevents duplicate order numbers lines and allocations while restricting deletes', function () {
    $requestItem = approvedRequestItem();
    $purchaseOrderItem = purchaseOrderItemFor($requestItem);

    expect(fn () => PurchaseOrder::factory()->create([
        'order_number' => $purchaseOrderItem->purchaseOrder->order_number,
    ]))->toThrow(QueryException::class)
        ->and(fn () => PurchaseOrderItem::factory()
            ->for($purchaseOrderItem->purchaseOrder)
            ->for($purchaseOrderItem->material)
            ->create(['unit_of_measure_id' => $purchaseOrderItem->unit_of_measure_id]))
        ->toThrow(QueryException::class)
        ->and(fn () => $purchaseOrderItem->purchaseOrder->delete())->toThrow(QueryException::class)
        ->and(fn () => $requestItem->material->delete())->toThrow(QueryException::class);
});

test('purchase order requests reject invalid currency dates and client controlled fields', function () {
    $supplier = Supplier::factory()->create();
    $data = [
        'order_number' => 'PO-INVALID',
        'supplier_id' => $supplier->id,
        'order_date' => '2026-10-12',
        'expected_delivery_date' => '2026-10-11',
        'currency_code' => 'usd',
        'status' => PurchaseOrderStatus::Ordered->value,
        'created_by_user_id' => User::factory()->create()->id,
    ];
    $request = StorePurchaseOrderRequest::create('/', 'POST', $data);
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('expected_delivery_date'))->toBeTrue()
        ->and($validator->errors()->has('currency_code'))->toBeTrue()
        ->and($validator->errors()->has('status'))->toBeTrue()
        ->and($validator->errors()->has('created_by_user_id'))->toBeTrue();
});

test('purchase order item validation rejects nonpositive quantities prices and mismatched references', function () {
    $requestItem = approvedRequestItem();
    $supplier = Supplier::factory()->create();
    $purchaseOrder = PurchaseOrder::factory()->create(['supplier_id' => $supplier->id]);
    $data = [
        'purchase_order_id' => $purchaseOrder->id,
        'material_id' => $requestItem->material_id,
        'unit_of_measure_id' => $requestItem->unit_of_measure_id + 1,
        'ordered_qty' => '0.000',
        'unit_price' => '-0.0001',
        'line_total' => '1.00',
    ];
    $request = StorePurchaseOrderItemRequest::create('/', 'POST', $data);
    $validator = Validator::make($data, $request->rules());
    $validator->fails();
    foreach ($request->after() as $after) {
        $after($validator);
    }

    expect($validator->errors()->has('unit_of_measure_id'))->toBeTrue()
        ->and($validator->errors()->has('ordered_qty'))->toBeTrue()
        ->and($validator->errors()->has('unit_price'))->toBeTrue()
        ->and($validator->errors()->has('line_total'))->toBeTrue();
});

test('allocation validation rejects unapproved requests and server controlled quantities', function () {
    $request = PurchaseRequest::factory()->create();
    $requestItem = PurchaseRequestItem::factory()->for($request)->create();
    $purchaseOrderItem = purchaseOrderItemFor($requestItem);
    $data = [
        'purchase_order_item_id' => $purchaseOrderItem->id,
        'purchase_request_item_id' => $requestItem->id,
        'allocated_qty' => '1.000',
        'purchase_order_id' => $purchaseOrderItem->purchase_order_id,
    ];
    $data['status'] = PurchaseOrderStatus::Ordered->value;
    $formRequest = StorePurchaseOrderAllocationRequest::create('/', 'POST', $data);
    $validator = Validator::make($data, $formRequest->rules());
    $validator->fails();
    foreach ($formRequest->after() as $after) {
        $after($validator);
    }

    expect($validator->errors()->has('purchase_request_item_id'))->toBeTrue()
        ->and($validator->errors()->has('purchase_order_id'))->toBeTrue()
        ->and($validator->errors()->has('status'))->toBeTrue();
});
