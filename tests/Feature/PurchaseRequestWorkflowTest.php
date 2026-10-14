<?php

use App\Http\Requests\Procurement\StorePurchaseRequestApprovalRequest;
use App\Http\Requests\Procurement\StorePurchaseRequestItemRequest;
use App\Http\Requests\Procurement\StorePurchaseRequestRequest;
use App\Models\Material;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\PurchaseRequestApprovalDecision;
use App\PurchaseRequestStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

test('purchase requests expose requester, item, and approval relationships with a draft default', function () {
    $purchaseRequest = PurchaseRequest::factory()->create();
    $item = PurchaseRequestItem::factory()->for($purchaseRequest)->create();

    expect($purchaseRequest->status)->toBe(PurchaseRequestStatus::Draft)
        ->and($purchaseRequest->requester)->toBeInstanceOf(User::class)
        ->and($purchaseRequest->items->first()->is($item))->toBeTrue()
        ->and($item->purchaseRequest->is($purchaseRequest))->toBeTrue()
        ->and($item->material)->toBeInstanceOf(Material::class)
        ->and($item->unitOfMeasure)->toBeInstanceOf(UnitOfMeasure::class)
        ->and($item->requested_qty)->toBe('1.000')
        ->and($purchaseRequest->approvals)->toBeEmpty()
        ->and($purchaseRequest->isFillable('status'))->toBeFalse()
        ->and($purchaseRequest->isFillable('requested_by_user_id'))->toBeFalse();
});

test('purchase request submission and approval decisions follow the persisted status workflow', function () {
    $purchaseRequest = PurchaseRequest::factory()->create();
    PurchaseRequestItem::factory()->for($purchaseRequest)->create();
    $approver = User::factory()->create();

    $purchaseRequest->submit();
    $approval = $purchaseRequest->recordDecision(
        $approver,
        PurchaseRequestApprovalDecision::Approved,
        'Approved for purchasing.',
    );

    expect($purchaseRequest->fresh()->status)->toBe(PurchaseRequestStatus::Approved)
        ->and($approval->purchase_request_id)->toBe($purchaseRequest->id)
        ->and($approval->approver->is($approver))->toBeTrue()
        ->and($approval->decision)->toBe(PurchaseRequestApprovalDecision::Approved)
        ->and($approval->decided_at)->not->toBeNull()
        ->and($purchaseRequest->approvals)->toHaveCount(1);

    expect(fn () => $purchaseRequest->recordDecision($approver, PurchaseRequestApprovalDecision::Rejected))
        ->toThrow(DomainException::class);

    $rejectedRequest = PurchaseRequest::factory()->create();
    PurchaseRequestItem::factory()->for($rejectedRequest)->create();
    $rejectedRequest->submit();
    $rejectedRequest->recordDecision($approver, PurchaseRequestApprovalDecision::Rejected);

    expect($rejectedRequest->fresh()->status)->toBe(PurchaseRequestStatus::Rejected);
});

test('purchase request decisions append audit rows and approval history cannot be updated or deleted', function () {
    $purchaseRequest = PurchaseRequest::factory()->create();
    PurchaseRequestItem::factory()->for($purchaseRequest)->create();
    $firstApprover = User::factory()->create();
    $secondApprover = User::factory()->create();
    $purchaseRequest->submit();

    $firstDecision = $purchaseRequest->approvals()->create([
        'approver_user_id' => $firstApprover->id,
        'decision' => PurchaseRequestApprovalDecision::Rejected,
        'comments' => 'Additional details required.',
    ]);
    $secondDecision = $purchaseRequest->approvals()->create([
        'approver_user_id' => $secondApprover->id,
        'decision' => PurchaseRequestApprovalDecision::Approved,
        'comments' => 'Approved after review.',
    ]);

    expect($purchaseRequest->approvals()->count())->toBe(2)
        ->and($firstDecision->fresh()->decision)->toBe(PurchaseRequestApprovalDecision::Rejected)
        ->and($secondDecision->fresh()->decision)->toBe(PurchaseRequestApprovalDecision::Approved)
        ->and(fn () => $firstDecision->update(['comments' => 'Overwritten']))
        ->toThrow(LogicException::class)
        ->and(fn () => $secondDecision->delete())->toThrow(LogicException::class);
});

test('purchase request items prevent duplicate material lines and restrict referenced record deletion', function () {
    $purchaseRequest = PurchaseRequest::factory()->create();
    $item = PurchaseRequestItem::factory()->for($purchaseRequest)->create();

    expect(fn () => PurchaseRequestItem::factory()
        ->for($purchaseRequest)
        ->for($item->material)
        ->create())
        ->toThrow(QueryException::class)
        ->and(fn () => $purchaseRequest->delete())->toThrow(QueryException::class)
        ->and(fn () => $item->material->delete())->toThrow(QueryException::class)
        ->and(fn () => $item->unitOfMeasure->delete())->toThrow(QueryException::class);
});

test('purchase requests reject empty submission, direct status changes, and post-submission item changes', function () {
    $emptyRequest = PurchaseRequest::factory()->create();

    expect(fn () => $emptyRequest->submit())->toThrow(DomainException::class);

    $purchaseRequest = PurchaseRequest::factory()->create();
    $item = PurchaseRequestItem::factory()->for($purchaseRequest)->create();

    $purchaseRequest->status = PurchaseRequestStatus::Approved;
    expect(fn () => $purchaseRequest->save())->toThrow(DomainException::class);

    $purchaseRequest->refresh();
    $purchaseRequest->submit();
    expect(fn () => $item->update(['requested_qty' => '2.000']))->toThrow(DomainException::class)
        ->and(fn () => PurchaseRequestItem::factory()->for($purchaseRequest)->create())
        ->toThrow(DomainException::class);
});

test('purchase requests may be cancelled before approval but terminal requests cannot change status', function () {
    $draftRequest = PurchaseRequest::factory()->create();
    $draftRequest->cancel();

    expect($draftRequest->fresh()->status)->toBe(PurchaseRequestStatus::Cancelled)
        ->and(fn () => $draftRequest->cancel())->toThrow(DomainException::class);

    $approvedRequest = PurchaseRequest::factory()->create();
    PurchaseRequestItem::factory()->for($approvedRequest)->create();
    $approvedRequest->submit();
    $approvedRequest->recordDecision(User::factory()->create(), PurchaseRequestApprovalDecision::Approved);

    expect(fn () => $approvedRequest->cancel())->toThrow(DomainException::class);
});

test('purchase request validation rejects client-controlled status and invalid dates or duplicate request numbers', function () {
    $existingRequest = PurchaseRequest::factory()->create();
    $data = [
        'request_number' => $existingRequest->request_number,
        'request_date' => '2026-10-10',
        'required_date' => '2026-10-09',
        'purpose' => '',
        'status' => PurchaseRequestStatus::Approved->value,
        'requested_by_user_id' => User::factory()->create()->id,
    ];

    $validator = Validator::make($data, (new StorePurchaseRequestRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('request_number'))->toBeTrue()
        ->and($validator->errors()->has('required_date'))->toBeTrue()
        ->and($validator->errors()->has('purpose'))->toBeTrue()
        ->and($validator->errors()->has('status'))->toBeTrue()
        ->and($validator->errors()->has('requested_by_user_id'))->toBeTrue();
});

test('purchase request item validation requires a draft request, matching unit, and positive unique material line', function () {
    $purchaseRequest = PurchaseRequest::factory()->create();
    $material = Material::factory()->create();
    $itemData = [
        'purchase_request_id' => $purchaseRequest->id,
        'material_id' => $material->id,
        'unit_of_measure_id' => $material->unit_of_measure_id,
        'requested_qty' => '1.000',
    ];
    PurchaseRequestItem::factory()->create($itemData);

    $duplicateRequest = StorePurchaseRequestItemRequest::create('/', 'POST', $itemData);
    $duplicateValidator = Validator::make($itemData, $duplicateRequest->rules());

    $wrongUnitData = [
        ...$itemData,
        'material_id' => Material::factory()->create()->id,
        'unit_of_measure_id' => UnitOfMeasure::factory()->create()->id,
        'requested_qty' => '0.000',
    ];
    $wrongUnitRequest = StorePurchaseRequestItemRequest::create('/', 'POST', $wrongUnitData);
    $wrongUnitValidator = Validator::make($wrongUnitData, $wrongUnitRequest->rules());
    foreach ($wrongUnitRequest->after() as $after) {
        $after($wrongUnitValidator);
    }

    $submittedRequest = PurchaseRequest::factory()->create();
    PurchaseRequestItem::factory()->for($submittedRequest)->create();
    $submittedRequest->submit();
    $closedRequestData = [...$itemData, 'purchase_request_id' => $submittedRequest->id];
    $closedRequest = StorePurchaseRequestItemRequest::create('/', 'POST', $closedRequestData);
    $closedRequestValidator = Validator::make($closedRequestData, $closedRequest->rules());

    expect($duplicateValidator->fails())->toBeTrue()
        ->and($duplicateValidator->errors()->has('material_id'))->toBeTrue()
        ->and($wrongUnitValidator->errors()->has('unit_of_measure_id'))->toBeTrue()
        ->and($wrongUnitValidator->errors()->has('requested_qty'))->toBeTrue()
        ->and($closedRequestValidator->fails())->toBeTrue()
        ->and($closedRequestValidator->errors()->has('purchase_request_id'))->toBeTrue();
});

test('approval validation only accepts decisions for submitted requests and protects server-owned history fields', function () {
    $draftRequest = PurchaseRequest::factory()->create();
    $data = [
        'purchase_request_id' => $draftRequest->id,
        'decision' => 'unknown',
        'approver_user_id' => User::factory()->create()->id,
        'decided_at' => now()->toDateTimeString(),
        'status' => PurchaseRequestStatus::Approved->value,
    ];
    $request = StorePurchaseRequestApprovalRequest::create('/', 'POST', $data);
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('purchase_request_id'))->toBeTrue()
        ->and($validator->errors()->has('decision'))->toBeTrue()
        ->and($validator->errors()->has('approver_user_id'))->toBeTrue()
        ->and($validator->errors()->has('decided_at'))->toBeTrue()
        ->and($validator->errors()->has('status'))->toBeTrue();
});
