<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    protected $attributes = [
        'is_active' => true,
    ];

    public const ROLES = [
        'Warehouse Staff',
        'Procurement Staff',
        'Production Staff',
        'Sales & Distribution Staff',
        'Quality Assurance (QA)',
        'Finance Staff',
        'Management',
        'System Administrator',
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'department',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function createdMaterialStockReservations(): HasMany
    {
        return $this->hasMany(MaterialStockReservation::class, 'created_by_user_id');
    }

    public function releasedMaterialStockReservations(): HasMany
    {
        return $this->hasMany(MaterialStockReservation::class, 'released_by_user_id');
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class, 'requested_by_user_id');
    }

    public function purchaseRequestApprovals(): HasMany
    {
        return $this->hasMany(PurchaseRequestApproval::class, 'approver_user_id');
    }

    public function createdPurchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'created_by_user_id');
    }

    public function purchaseOrderStatusChanges(): HasMany
    {
        return $this->hasMany(PurchaseOrderStatusHistory::class, 'changed_by_user_id');
    }

    public function createdDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'created_by_user_id');
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class, 'received_by_user_id');
    }

    public function procurementActivities(): HasMany
    {
        return $this->hasMany(ProcurementActivity::class, 'actor_user_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'actor_user_id');
    }
}
