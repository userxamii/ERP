<?php

namespace Database\Factories;

use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderItemAllocation;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\PurchaseRequestApprovalDecision;
use App\PurchaseRequestStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItemAllocation>
 */
class PurchaseOrderItemAllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'purchase_request_item_id' => PurchaseRequestItem::factory(),
            'allocated_qty' => '1.000',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PurchaseOrderItemAllocation $allocation): void {
            $purchaseOrderItem = $allocation->purchaseOrderItem;
            $requestItem = $allocation->purchaseRequestItem;

            if ($purchaseOrderItem->exists) {
                $purchaseOrderItem->material_id = $requestItem->material_id;
                $purchaseOrderItem->unit_of_measure_id = $requestItem->unit_of_measure_id;
                $purchaseOrderItem->save();
            }

            if ($requestItem->exists
                && $requestItem->purchaseRequest->status !== PurchaseRequestStatus::Approved) {
                $requestItem->purchaseRequest->submit();
                $requestItem->purchaseRequest->recordDecision(
                    User::factory()->create(),
                    PurchaseRequestApprovalDecision::Approved,
                );
            }
        });
    }
}
