<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Supplier;
use App\Models\User;
use App\PurchaseOrderStatus;
use App\PurchaseRequestApprovalDecision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => fake()->unique()->bothify('PO-########'),
            'supplier_id' => Supplier::factory(),
            'order_date' => now()->toDateString(),
            'expected_delivery_date' => now()->addDays(14)->toDateString(),
            'status' => PurchaseOrderStatus::Draft,
            'currency_code' => 'PHP',
            'notes' => null,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function ordered(): static
    {
        return $this->afterCreating(function (PurchaseOrder $purchaseOrder): void {
            $request = PurchaseRequest::factory()->create();
            $requestItem = PurchaseRequestItem::factory()->for($request)->create([
                'requested_qty' => '10.000',
            ]);
            $request->submit();
            $request->recordDecision(
                User::factory()->create(),
                PurchaseRequestApprovalDecision::Approved,
            );

            $purchaseOrderItem = PurchaseOrderItem::factory()
                ->for($purchaseOrder)
                ->for($requestItem->material)
                ->create([
                    'unit_of_measure_id' => $requestItem->unit_of_measure_id,
                    'ordered_qty' => '10.000',
                ]);
            $requestItem->allocateTo($purchaseOrderItem, '10.000');
            $purchaseOrder->place($purchaseOrder->creator);
        });
    }
}
