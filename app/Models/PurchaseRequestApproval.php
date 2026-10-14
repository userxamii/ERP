<?php

namespace App\Models;

use App\PurchaseRequestApprovalDecision;
use App\PurchaseRequestStatus;
use Database\Factories\PurchaseRequestApprovalFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PurchaseRequestApproval extends Model
{
    /** @use HasFactory<PurchaseRequestApprovalFactory> */
    use HasFactory;

    protected $fillable = [
        'purchase_request_id',
        'approver_user_id',
        'decision',
        'comments',
    ];

    protected function casts(): array
    {
        return [
            'decision' => PurchaseRequestApprovalDecision::class,
            'decided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseRequestApproval $approval): void {
            $isSubmitted = PurchaseRequest::query()
                ->whereKey($approval->purchase_request_id)
                ->where('status', PurchaseRequestStatus::Submitted->value)
                ->exists();

            if (! $isSubmitted) {
                throw new DomainException('Approval decisions can only be recorded for submitted purchase requests.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('Purchase request approval history is append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Purchase request approval history is append-only.');
        });
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
