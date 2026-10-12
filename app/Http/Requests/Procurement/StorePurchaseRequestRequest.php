<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'request_number' => ['required', 'string', 'max:64', 'unique:purchase_requests,request_number'],
            'request_date' => ['required', 'date'],
            'required_date' => ['nullable', 'date', 'after_or_equal:request_date'],
            'purpose' => ['required', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'status' => ['prohibited'],
            'requested_by_user_id' => ['prohibited'],
            'approver_user_id' => ['prohibited'],
            'decided_at' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }
}
