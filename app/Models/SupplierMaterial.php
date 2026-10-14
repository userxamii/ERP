<?php

namespace App\Models;

use Database\Factories\SupplierMaterialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierMaterial extends Model
{
    /** @use HasFactory<SupplierMaterialFactory> */
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'material_id',
        'supplier_sku',
        'lead_time_days',
        'minimum_order_qty',
        'order_multiple_qty',
        'quoted_unit_price',
        'currency_code',
        'is_preferred',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'lead_time_days' => 'integer',
            'minimum_order_qty' => 'decimal:3',
            'order_multiple_qty' => 'decimal:3',
            'quoted_unit_price' => 'decimal:4',
            'is_preferred' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
