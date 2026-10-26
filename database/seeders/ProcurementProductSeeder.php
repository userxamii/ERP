<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

class ProcurementProductSeeder extends Seeder
{
    public function run(): void
    {
        $unit = UnitOfMeasure::query()->firstOrCreate(
            ['code' => 'unit'],
            ['name' => 'Unit', 'decimal_places' => 3],
        );

        foreach (range('A', 'J') as $letter) {
            Material::query()->firstOrCreate(
                ['code' => "PROD-{$letter}"],
                [
                    'name' => "Product {$letter}",
                    'unit_of_measure_id' => $unit->getKey(),
                    'minimum_stock_qty' => '0.000',
                    'reorder_to_qty' => '0.000',
                    'is_active' => true,
                ],
            );
        }
    }
}
