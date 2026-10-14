<?php

namespace App\Models;

use App\ProcurementActivityType;
use App\PurchaseRequestApprovalDecision;
use App\PurchaseRequestStatus;
use Database\Factories\PurchaseRequestFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;

class PurchaseRequest extends Model
{
    /** @use HasFactory<PurchaseRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'request_number',
        'request_date',
        'required_date',
        'purpose',
        'notes',
    ];

    protected $attributes = [
        'status' => PurchaseRequestStatus::Draft->value,
    ];

    private bool $statusTransitionAuthorized = false;

    protected static function booted(): void
    {
        static::creating(function (PurchaseRequest $purchaseRequest): void {
            if ($purchaseRequest->status !== PurchaseRequestStatus::Draft) {
                throw new DomainException('Purchase requests must be created as drafts.');
            }
        });

        static::created(function (PurchaseRequest $purchaseRequest): void {
            ProcurementActivity::record(
                'purchase-request-created-'.$purchaseRequest->getKey(),
                ProcurementActivityType::PurchaseRequestCreated,
                'Purchase request '.$purchaseRequest->request_number.' was created.',
                (int) $purchaseRequest->requested_by_user_id,
                purchaseRequestId: (int) $purchaseRequest->getKey(),
            );
        });

        static::updating(function (PurchaseRequest $purchaseRequest): void {
            if ($purchaseRequest->isDirty('status') && ! $purchaseRequest->statusTransitionAuthorized) {
                throw new DomainException('Use the purchase request workflow to change its status.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'required_date' => 'date',
            'status' => PurchaseRequestStatus::class,
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PurchaseRequestApproval::class);
    }

    public function purchaseOrderAllocations(): HasManyThrough
    {
        return $this->hasManyThrough(PurchaseOrderItemAllocation::class, PurchaseRequestItem::class);
    }

    public function submit(): void
    {
        DB::transaction(function (): void {
            $purchaseRequest = self::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if ($purchaseRequest->status !== PurchaseRequestStatus::Draft) {
                throw new DomainException('Only draft purchase requests can be submitted.');
            }

            if (! $purchaseRequest->items()->exists()) {
                throw new DomainException('Purchase requests need at least one item before submission.');
            }

            $purchaseRequest->transitionTo(
                PurchaseRequestStatus::Submitted,
                $purchaseRequest->requester,
            );
            $this->setRawAttributes($purchaseRequest->getAttributes(), true);
        });
    }

    public function cancel(): void
    {
        DB::transaction(function (): void {
            $purchaseRequest = self::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($purchaseRequest->status, [PurchaseRequestStatus::Draft, PurchaseRequestStatus::Submitted], true)) {
                throw new DomainException('Only draft or submitted purchase requests can be cancelled.');
            }

            $purchaseRequest->transitionTo(
                PurchaseRequestStatus::Cancelled,
                $purchaseRequest->requester,
            );
            $this->setRawAttributes($purchaseRequest->getAttributes(), true);
        });
    }

    public function recordDecision(
        User $approver,
        PurchaseRequestApprovalDecision $decision,
        ?string $comments = null,
    ): PurchaseRequestApproval {
        return DB::transaction(function () use ($approver, $decision, $comments): PurchaseRequestApproval {
            $purchaseRequest = self::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($purchaseRequest->status !== PurchaseRequestStatus::Submitted) {
                throw new DomainException('Only submitted purchase requests can receive approval decisions.');
            }

            $approval = $purchaseRequest->approvals()->create([
                'approver_user_id' => $approver->getKey(),
                'decision' => $decision,
                'comments' => $comments,
            ]);

            $nextStatus = $decision === PurchaseRequestApprovalDecision::Approved
                ? PurchaseRequestStatus::Approved
                : PurchaseRequestStatus::Rejected;

            $purchaseRequest->transitionTo($nextStatus, $approver);
            $this->setRawAttributes($purchaseRequest->getAttributes(), true);

            return $approval->refresh();
        });
    }

    private function transitionTo(PurchaseRequestStatus $status, ?User $actor): void
    {
        $this->statusTransitionAuthorized = true;
        $this->status = $status;

        try {
            $this->save();
        } finally {
            $this->statusTransitionAuthorized = false;
        }

        $activityType = match ($status) {
            PurchaseRequestStatus::Submitted => ProcurementActivityType::PurchaseRequestSubmitted,
            PurchaseRequestStatus::Approved => ProcurementActivityType::PurchaseRequestApproved,
            PurchaseRequestStatus::Rejected => ProcurementActivityType::PurchaseRequestRejected,
            PurchaseRequestStatus::Cancelled => ProcurementActivityType::PurchaseRequestCancelled,
            default => throw new DomainException('Unsupported purchase request activity status.'),
        };

        ProcurementActivity::record(
            'purchase-request-status-'.$this->getKey().'-'.$status->value,
            $activityType,
            'Purchase request '.$this->request_number.' was '.$status->value.'.',
            $actor?->getKey(),
            purchaseRequestId: (int) $this->getKey(),
        );
    }
}
