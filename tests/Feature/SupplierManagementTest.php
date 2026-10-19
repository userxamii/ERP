<?php

use App\Http\Requests\Procurement\StoreSupplierMaterialRequest;
use App\Http\Requests\Procurement\StoreSupplierRequest;
use App\Models\Material;
use App\Models\Supplier;
use App\Models\SupplierMaterial;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

test('suppliers expose supplier-material and material relationships', function () {
    $supplier = Supplier::factory()->create();
    $material = Material::factory()->create();
    $supplierMaterial = SupplierMaterial::factory()
        ->for($supplier)
        ->for($material)
        ->create([
            'supplier_sku' => 'SUP-SKU-42',
            'lead_time_days' => 0,
            'minimum_order_qty' => '12.500',
            'order_multiple_qty' => '2.500',
            'quoted_unit_price' => '4.1250',
            'currency_code' => 'USD',
            'is_preferred' => true,
        ]);

    expect($supplier->supplierMaterials->first()->is($supplierMaterial))->toBeTrue()
        ->and($supplier->materials->first()->is($material))->toBeTrue()
        ->and($material->supplierMaterials->first()->is($supplierMaterial))->toBeTrue()
        ->and($material->suppliers->first()->is($supplier))->toBeTrue()
        ->and($supplierMaterial->supplier->is($supplier))->toBeTrue()
        ->and($supplierMaterial->material->is($material))->toBeTrue()
        ->and($supplierMaterial->lead_time_days)->toBe(0)
        ->and($supplierMaterial->minimum_order_qty)->toBe('12.500')
        ->and($supplierMaterial->quoted_unit_price)->toBe('4.1250')
        ->and($supplierMaterial->is_preferred)->toBeTrue()
        ->and($supplier->is_active)->toBeTrue();
});

test('supplier-material records reject duplicate pairs and protect referenced records', function () {
    $supplier = Supplier::factory()->create();
    $material = Material::factory()->create();
    SupplierMaterial::factory()->for($supplier)->for($material)->create();

    expect(fn () => SupplierMaterial::factory()->for($supplier)->for($material)->create())
        ->toThrow(QueryException::class)
        ->and(fn () => $supplier->delete())->toThrow(QueryException::class)
        ->and(fn () => $material->delete())->toThrow(QueryException::class);
});

test('supplier validation rejects duplicate codes and invalid contact data', function () {
    $supplier = Supplier::factory()->create();

    $validator = Validator::make([
        'supplier_code' => $supplier->supplier_code,
        'name' => '',
        'contact_email' => 'not-an-email',
        'is_active' => 'not-a-boolean',
        'notes' => str_repeat('x', 10001),
    ], (new StoreSupplierRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('supplier_code'))->toBeTrue()
        ->and($validator->errors()->has('name'))->toBeTrue()
        ->and($validator->errors()->has('contact_email'))->toBeTrue()
        ->and($validator->errors()->has('is_active'))->toBeTrue()
        ->and($validator->errors()->has('notes'))->toBeTrue();
});

test('supplier-material validation rejects duplicate links, invalid references, and nonpositive order quantities', function () {
    $supplier = Supplier::factory()->create();
    $material = Material::factory()->create();
    SupplierMaterial::factory()->for($supplier)->for($material)->create();

    $data = [
        'supplier_id' => $supplier->id,
        'material_id' => $material->id,
        'lead_time_days' => -1,
        'minimum_order_qty' => '0.000',
        'order_multiple_qty' => '0.000',
        'quoted_unit_price' => '-0.0001',
        'currency_code' => 'ZZZ',
    ];
    $request = StoreSupplierMaterialRequest::create('/', 'POST', $data);
    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('material_id'))->toBeTrue()
        ->and($validator->errors()->has('lead_time_days'))->toBeTrue()
        ->and($validator->errors()->has('minimum_order_qty'))->toBeTrue()
        ->and($validator->errors()->has('order_multiple_qty'))->toBeTrue()
        ->and($validator->errors()->has('quoted_unit_price'))->toBeTrue()
        ->and($validator->errors()->has('currency_code'))->toBeTrue();

    $unknownReferences = Validator::make([
        ...$data,
        'supplier_id' => 999999,
        'material_id' => 999999,
        'lead_time_days' => 0,
        'minimum_order_qty' => '1.000',
        'order_multiple_qty' => null,
        'quoted_unit_price' => null,
        'currency_code' => null,
    ], $request->rules());

    expect($unknownReferences->fails())->toBeTrue()
        ->and($unknownReferences->errors()->has('supplier_id'))->toBeTrue()
        ->and($unknownReferences->errors()->has('material_id'))->toBeTrue();
});

test('supplier-material validation accepts optional pricing and valid zero-day lead time', function () {
    $supplier = Supplier::factory()->create();
    $material = Material::factory()->create();

    $withoutPrice = [
        'supplier_id' => $supplier->id,
        'material_id' => $material->id,
        'lead_time_days' => 0,
        'minimum_order_qty' => '0.001',
        'order_multiple_qty' => null,
        'quoted_unit_price' => null,
        'currency_code' => null,
    ];
    $withoutPriceRequest = StoreSupplierMaterialRequest::create('/', 'POST', $withoutPrice);
    $withoutPriceValidator = Validator::make($withoutPrice, $withoutPriceRequest->rules());

    $withPrice = [
        ...$withoutPrice,
        'quoted_unit_price' => '0.0000',
        'currency_code' => 'USD',
    ];
    $withPriceRequest = StoreSupplierMaterialRequest::create('/', 'POST', $withPrice);
    $withPriceValidator = Validator::make($withPrice, $withPriceRequest->rules());

    expect($withoutPriceValidator->passes())->toBeTrue()
        ->and($withPriceValidator->passes())->toBeTrue();
});

test('supplier-material validation requires a matching valid currency for a quoted price', function () {
    $supplier = Supplier::factory()->create();
    $material = Material::factory()->create();
    $data = [
        'supplier_id' => $supplier->id,
        'material_id' => $material->id,
        'lead_time_days' => 5,
        'minimum_order_qty' => '1.000',
        'quoted_unit_price' => '1.2500',
    ];

    $missingCurrency = StoreSupplierMaterialRequest::create('/', 'POST', $data);
    $missingCurrencyValidator = Validator::make($data, $missingCurrency->rules());

    $invalidCurrencyData = [...$data, 'currency_code' => 'usd'];
    $invalidCurrency = StoreSupplierMaterialRequest::create('/', 'POST', $invalidCurrencyData);
    $invalidCurrencyValidator = Validator::make($invalidCurrencyData, $invalidCurrency->rules());

    $currencyWithoutPriceData = [
        ...$data,
        'quoted_unit_price' => null,
        'currency_code' => 'USD',
    ];
    $currencyWithoutPrice = StoreSupplierMaterialRequest::create('/', 'POST', $currencyWithoutPriceData);
    $currencyWithoutPriceValidator = Validator::make($currencyWithoutPriceData, $currencyWithoutPrice->rules());

    expect($missingCurrencyValidator->fails())->toBeTrue()
        ->and($missingCurrencyValidator->errors()->has('currency_code'))->toBeTrue()
        ->and($invalidCurrencyValidator->fails())->toBeTrue()
        ->and($invalidCurrencyValidator->errors()->has('currency_code'))->toBeTrue()
        ->and($currencyWithoutPriceValidator->fails())->toBeTrue()
        ->and($currencyWithoutPriceValidator->errors()->has('quoted_unit_price'))->toBeTrue();
});
