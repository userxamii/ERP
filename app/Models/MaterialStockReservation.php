<?php

namespace App\Models;

use App\InventoryMovementType;
use App\MaterialStockReservationStatus;
use App\ProcurementActivityType;
use App\ProcurementQuantity;
use Database\Factories\MaterialStockReservationFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class MaterialStockReservation extends Model
{
    /** @use HasFactory<MaterialStockReservationFactory> */
    use HasFactory;

    protected $fillable = [
        'material_id',
        'quantity',
        'source_reference',
    ];

    protected $attributes = [
        'status' => MaterialStockReservationStatus::Active->value,
    ];

    private bool $releaseAuthorized = false;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'status' => MaterialStockReservationStatus::class,
            'released_at' => 'datetime',
        ];
    }

    public function save(array $options = []): bool
    {
        return DB::transaction(fn (): bool => parent::save($options));
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_user_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    protected static function booted(): void
    {
        static::creating(function (MaterialStockReservation $reservation): void {
            if ($reservation->status !== MaterialStockReservationStatus::Active
                || ProcurementQuantity::toMilliUnits((string) $reservation->quantity) <= 0) {
                throw new DomainException('Stock reservations must start active with a positive quantity.');
            }
        });

        static::created(function (MaterialStockReservation $reservation): void {
            InventoryMovement::record(
                InventoryMovementType::ReservationCreated,
                $reservation->material,
                (string) $reservation->quantity,
                '0.000',
                (string) $reservation->quantity,
                $reservation->createdBy,
                reservation: $reservation,
                sourceReference: $reservation->source_reference,
            );
            ProcurementActivity::record(
                'stock-reservation-created-'.$reservation->getKey(),
                ProcurementActivityType::StockReservationCreated,
                'Stock reservation '.$reservation->getKey().' was created.',
                $reservation->created_by_user_id === null ? null : (int) $reservation->created_by_user_id,
                metadata: [
                    'material_id' => (int) $reservation->material_id,
                    'quantity' => (string) $reservation->quantity,
                ],
            );
        });

        static::updating(function (MaterialStockReservation $reservation): void {
            if (! $reservation->releaseAuthorized && $reservation->isDirty([
                'material_id',
                'quantity',
                'status',
                'released_by_user_id',
                'released_at',
            ])) {
                throw new DomainException('Reservation lifecycle and quantities can only be changed through the reservation workflow.');
            }
        });
    }

    public function release(User $actor): void
    {
        DB::transaction(function () use ($actor): void {
            $reservation = self::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if ($reservation->status !== MaterialStockReservationStatus::Active) {
                throw new DomainException('Only active stock reservations can be released.');
            }

            $reservation->releaseAuthorized = true;
            $reservation->status = MaterialStockReservationStatus::Released;
            $reservation->released_by_user_id = $actor->getKey();
            $reservation->released_at = now();

            try {
                $reservation->save();
            } finally {
                $reservation->releaseAuthorized = false;
            }

            InventoryMovement::record(
                InventoryMovementType::ReservationReleased,
                $reservation->material,
                (string) $reservation->quantity,
                '0.000',
                '-'.(string) $reservation->quantity,
                $actor,
                reservation: $reservation,
                sourceReference: $reservation->source_reference,
            );
            ProcurementActivity::record(
                'stock-reservation-released-'.$reservation->getKey(),
                ProcurementActivityType::StockReservationReleased,
                'Stock reservation '.$reservation->getKey().' was released.',
                (int) $actor->getKey(),
                metadata: [
                    'material_id' => (int) $reservation->material_id,
                    'quantity' => (string) $reservation->quantity,
                ],
            );

            $this->setRawAttributes($reservation->getAttributes(), true);
        });
    }
}
