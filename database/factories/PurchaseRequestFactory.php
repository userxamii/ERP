<?php

namespace Database\Factories;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\PurchaseRequestStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequest>
 */
class PurchaseRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_number' => fake()->unique()->bothify('PR-########'),
            'requested_by_user_id' => User::factory(),
            'request_date' => now()->toDateString(),
            'required_date' => now()->addDays(14)->toDateString(),
            'purpose' => fake()->sentence(),
            'status' => PurchaseRequestStatus::Draft,
            'notes' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->afterCreating(function (PurchaseRequest $purchaseRequest): void {
            PurchaseRequestItem::factory()->for($purchaseRequest)->create();
            $purchaseRequest->submit();
        });
    }
}
