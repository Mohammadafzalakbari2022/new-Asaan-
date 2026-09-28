<?php

namespace Cartxis\Referral\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReferralCreditRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'type' => ['required', Rule::in(['admin_credit', 'admin_debit'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'reason' => ['required', 'string', 'min:4', 'max:500'],
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'customer',
            'amount' => 'amount',
            'reason' => 'reason',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Write why this credit is being given or taken. It is kept permanently.',
            'amount.min' => 'Enter an amount greater than zero.',
        ];
    }
}
