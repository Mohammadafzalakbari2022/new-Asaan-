<?php

declare(strict_types=1);

namespace Cartxis\Service\Http\Requests;

use Cartxis\Service\Services\ServiceSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreServiceBookingRequest extends FormRequest
{
    public function __construct(
        protected ServiceSettings $settings,
    ) {
        parent::__construct();
    }

    public function authorize(): bool
    {
        // The owner can require an account before anyone books. The check is
        // here so the reason is a clear message rather than a redirect loop.
        if ($this->settings->requiresLoginToBook() && ! Auth::check()) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30', 'min:6'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:1000', 'min:10'],
            'city' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],

            // The date and slot are checked properly by the slot service, which
            // knows the owner's lead time and working hours. This only keeps a
            // nonsense value out before it gets there.
            'scheduled_date' => ['required', 'date_format:Y-m-d'],
            'scheduled_slot' => ['required', 'string', 'max:60', Rule::in($this->settings->timeSlotLabels())],

            // One key per filled-in form. A second submit of the same form is
            // recognised as the same request instead of a second job.
            'request_token' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_name.required' => 'Tell us your name so we know who to ask for.',
            'customer_phone.required' => 'A phone number is needed so the worker can call you.',
            'customer_phone.min' => 'That phone number looks too short.',
            'customer_email.email' => 'That email address is not valid.',
            'address.required' => 'Tell us the address where the work is needed.',
            'address.min' => 'Please give the full address, including the area.',
            'scheduled_date.required' => 'Choose the day you would like the work done.',
            'scheduled_date.date_format' => 'Choose a date from the calendar.',
            'scheduled_slot.required' => 'Choose a time of day.',
            'scheduled_slot.in' => 'Choose one of the offered time slots.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'customer_name' => trim((string) $this->input('customer_name', '')),
            'customer_phone' => trim((string) $this->input('customer_phone', '')),
            'address' => trim((string) $this->input('address', '')),
        ]);
    }
}
