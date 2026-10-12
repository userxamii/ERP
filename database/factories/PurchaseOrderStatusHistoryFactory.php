<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderStatusHistory;
use App\Models\User;
use App\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderStatusHistory>
 */
class PurchaseOrderStatusHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'from_status' => null,
            'to_status' => PurchaseOrderStatus::Draft,
            'changed_by_user_id' => User::factory(),
            'notes' => null,
        ];
    }
}
