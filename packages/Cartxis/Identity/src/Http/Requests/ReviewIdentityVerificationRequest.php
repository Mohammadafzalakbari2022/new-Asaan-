<?php

namespace Cartxis\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A reviewer's decision.
 *
 * Authorisation is the second lock on the door: the admin guard already turns a
 * customer away at the route, so a route added later that forgets the middleware
 * still cannot approve anybody's identity.
 */
class ReviewIdentityVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            // Required to reject. A customer whose document is turned down is
            // entitled to be told why, in words they can act on.
            'reason' => ['nullable', 'required_if:decision,rejected', 'string', 'min:10', 'max:1000'],
            'note' => ['nullable', 'string', 'max:255'],
            'decision' => ['nullable', 'in:approved,rejected'],
        ];
    }

    public function attributes(): array
    {
        return [
            'reason' => 'rejection reason',
            'note' => 'reviewer note',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required_if' => __('Tell the customer why the document was not accepted.'),
            'reason.min' => __('Give the customer a reason they can act on, not one word.'),
        ];
    }
}