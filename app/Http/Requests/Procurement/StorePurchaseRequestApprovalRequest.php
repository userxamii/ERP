<?php

namespace App\Http\Requests\Procurement;

use App\PurchaseRequestApprovalDecision;
use App\PurchaseRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequestApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'purchase_request_id' => [
                'required',
                'integer',
                Rule::exists('purchase_requests', 'id')
                    ->where('status', PurchaseRequestStatus::Submitted->value),
            ],
            'decision' => ['required', Rule::enum(PurchaseRequestApprovalDecision::class)],
            'comments' => ['nullable', 'string', 'max:10000'],
            'status' => ['prohibited'],
            'approver_user_id' => ['prohibited'],
            'requested_by_user_id' => ['prohibited'],
            'decided_at' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }
}
