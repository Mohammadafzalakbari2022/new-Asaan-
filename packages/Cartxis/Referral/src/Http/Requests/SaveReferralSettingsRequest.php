<?php

namespace Cartxis\Referral\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveReferralSettingsRequest extends FormRequest
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
            'enabled' => 'required|boolean',
            'reward_amount' => 'required|numeric|min:0|max:100000',
            'threshold_amount' => 'required|numeric|min:0|max:10000000',
            'lock_days' => 'required|integer|min:0|max:3650',
            'level_shares' => 'required|array|min:1|max:2',
            'level_shares.*' => 'required|numeric|min:0|max:100',
            'reward_mode' => 'required|in:once_per_person',
            'allow_admin_credit' => 'required|boolean',
            'block_account_deletion' => 'required|boolean',
            'credit_max_percent_of_order' => 'required|integer|min:1|max:100',
        ];
    }

    /**
     * The levels must add up to the whole reward, or customers would be paid
     * more or less than the shop promised.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $shares = $this->input('level_shares');

            if (! is_array($shares) || $shares === []) {
                return;
            }

            $total = array_sum(array_map('floatval', $shares));

            if (abs($total - 100) > 0.001) {
                $validator->errors()->add(
                    'level_shares',
                    'The level shares must add up to exactly 100%. They currently add up to '.rtrim(rtrim(number_format($total, 2), '0'), '.').'%.'
                );
            }
        });
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'reward_amount' => 'referral credit amount',
            'threshold_amount' => 'lifetime order threshold',
            'lock_days' => 'lock period',
            'level_shares' => 'level shares',
            'credit_max_percent_of_order' => 'maximum credit per order',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'reward_amount.required' => 'Enter the credit you want to award a referrer.',
            'threshold_amount.required' => 'Enter the lifetime spend a referred customer must reach.',
            'lock_days.required' => 'Enter how many days the credit stays locked.',
        ];
    }
}
