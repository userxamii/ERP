<?php

namespace App\Models;

use App\ProcurementQuantity;
use App\ProductionMaterialRequestStatus;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ProductionMaterialRequest extends Model
{
    protected $fillable = [
        'request_number',
        'material_id',
        'requested_by_user_id',
        'production_order',
        'purpose',
        'required_date',
        'requested_qty',
    ];

    protected $attributes = [
        'fulfilled_qty' => '0.000',
        'status' => ProductionMaterialRequestStatus::PendingApproval->value,
    ];

    private bool $statusTransitionAuthorized = false;

    protected function casts(): array
    {
        return [
            'required_date' => 'date',
            'requested_qty' => 'decimal:3',
            'fulfilled_qty' => 'decimal:3',
            'status' => ProductionMaterialRequestStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProductionMaterialRequest $request): void {
            if ($request->status !== ProductionMaterialRequestStatus::PendingApproval) {
                throw new DomainException('Production material requests must start pending approval.');
            }
        });

        static::updating(function (ProductionMaterialRequest $request): void {
            if ($request->isDirty('status') && ! $request->statusTransitionAuthorized) {
                throw new DomainException('Use the production material request workflow to change its status.');
            }
        });
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function recordDecision(User $approver, bool $approved, ?string $comments = null): void
    {
        DB::transaction(function () use ($approver, $approved, $comments): void {
            $request = self::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if ($request->status !== ProductionMaterialRequestStatus::PendingApproval) {
                throw new DomainException('Only pending production material requests can receive an approval decision.');
            }

            $request->status = $approved
                ? ProductionMaterialRequestStatus::Approved
                : ProductionMaterialRequestStatus::Rejected;
            $request->approved_by_user_id = $approver->getKey();
            $request->decided_at = now();
            $request->decision_notes = $comments;
            $request->saveWithAuthorizedStatusTransition();
            $this->setRawAttributes($request->getAttributes(), true);
        });
    }

    public function recordFulfillment(
        InventoryBatch $batch,
        string $quantity,
        User $actor,
        string $destinationLocation,
    ): void {
        DB::transaction(function () use ($batch, $quantity, $actor, $destinationLocation): void {
            $request = self::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($request->status, [
                ProductionMaterialRequestStatus::Approved,
                ProductionMaterialRequestStatus::PartiallyFulfilled,
            ], true)) {
                throw new DomainException('Only approved, unfulfilled production material requests can be issued.');
            }

            if ((int) $batch->material_id !== (int) $request->material_id) {
                throw new DomainException('The selected batch does not belong to the requested material.');
            }

            $quantityMilli = ProcurementQuantity::toMilliUnits($quantity);
            $requestedMilli = ProcurementQuantity::toMilliUnits((string) $request->requested_qty);
            $fulfilledMilli = ProcurementQuantity::toMilliUnits((string) $request->fulfilled_qty);

            if ($quantityMilli <= 0 || $quantityMilli > $requestedMilli - $fulfilledMilli) {
                throw new DomainException('The issue quantity must be positive and cannot exceed the remaining requested quantity.');
            }

            $material = Material::query()
                ->whereKey($request->material_id)
                ->lockForUpdate()
                ->with(['inventoryBatches', 'stockReservations'])
                ->firstOrFail();
            $availableMilli = ProcurementQuantity::toMilliUnits($material->stockPosition()['available_to_use']);

            if ($quantityMilli > $availableMilli) {
                throw new DomainException('The issue quantity exceeds unreserved usable stock.');
            }

            $batch->recordStockOut(
                $quantity,
                $actor,
                $destinationLocation,
                'Approved production material request',
                $request->request_number,
            );

            $newFulfilledMilli = $fulfilledMilli + $quantityMilli;
            $request->fulfilled_qty = ProcurementQuantity::fromMilliUnits($newFulfilledMilli);
            $request->status = $newFulfilledMilli === $requestedMilli
                ? ProductionMaterialRequestStatus::Fulfilled
                : ProductionMaterialRequestStatus::PartiallyFulfilled;
            $request->saveWithAuthorizedStatusTransition();
            $this->setRawAttributes($request->getAttributes(), true);
        });
    }

    private function saveWithAuthorizedStatusTransition(): void
    {
        $this->statusTransitionAuthorized = true;

        try {
            $this->save();
        } finally {
            $this->statusTransitionAuthorized = false;
        }
    }
}
