<?php

namespace Database\Factories;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestApproval;
use App\Models\User;
use App\PurchaseRequestApprovalDecision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequestApproval>
 */
class PurchaseRequestApprovalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_request_id' => PurchaseRequest::factory()->submitted(),
            'approver_user_id' => User::factory(),
            'decision' => PurchaseRequestApprovalDecision::Approved,
            'comments' => fake()->optional()->sentence(),
        ];
    }
}
