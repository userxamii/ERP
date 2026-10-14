<?php

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    protected $fillable = [
        'supplier_code',
        'name',
        'contact_person',
        'contact_email',
        'contact_phone',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function supplierMaterials(): HasMany
    {
        return $this->hasMany(SupplierMaterial::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'supplier_materials')
            ->withPivot([
                'id',
                'supplier_sku',
                'lead_time_days',
                'minimum_order_qty',
                'order_multiple_qty',
                'quoted_unit_price',
                'currency_code',
                'is_preferred',
                'is_active',
            ])
            ->withTimestamps();
    }
}
