<?php

namespace Cartxis\Referral\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReverseReferralCommissionRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:4', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Write why this reward is being reversed. It is kept permanently.',
        ];
    }
}
