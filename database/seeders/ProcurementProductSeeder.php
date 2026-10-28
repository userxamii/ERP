<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

class ProcurementProductSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const PRODUCTS = [
        'A' => 'Piattos Cheese 40g',
        'B' => 'C2 Apple 335mL',
        'C' => 'C2 Lemon 355mL',
        'D' => 'Great Taste Premium 25g',
        'E' => 'Great Taste White Twin Pack',
        'F' => 'Cream-O Vanilla 30g',
        'G' => 'Presto Creams Peanut Butter 30g',
        'H' => 'Magic Flakes Cheese 28g',
        'I' => 'Nissin Cup Noodles Mini Bulalo 40g',
        'J' => 'Nissin Cup Noodles Mini Seafood 40g',
    ];

    public function run(): void
    {
        $unit = UnitOfMeasure::query()->firstOrCreate(
            ['code' => 'unit'],
            ['name' => 'Unit', 'decimal_places' => 3],
        );

        foreach (self::PRODUCTS as $letter => $name) {
            $material = Material::query()->firstOrCreate(
                ['code' => "PROD-{$letter}"],
                [
                    'name' => $name,
                    'unit_of_measure_id' => $unit->getKey(),
                    'minimum_stock_qty' => '0.000',
                    'reorder_to_qty' => '0.000',
                    'is_active' => true,
                ],
            );
            $material->update(['name' => $name]);
        }
    }
}
