<?php

declare(strict_types=1);

namespace Cartxis\Service\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'booking_enabled' => ['nullable', 'boolean'],
            'lead_time_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'booking_window_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'same_day_allowed' => ['nullable', 'boolean'],
            'capacity_per_slot' => ['required', 'integer', 'min:1', 'max:1000'],
            'coverage_note' => ['nullable', 'string', 'max:1000'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_whatsapp' => ['nullable', 'string', 'max:40'],
            'require_login_to_book' => ['nullable', 'boolean'],
            'auto_assign' => ['nullable', 'boolean'],
            'reference_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9-]+$/'],

            'time_slots' => ['nullable', 'array', 'max:12'],
            'time_slots.*.label' => ['nullable', 'string', 'max:60'],
            'time_slots.*.start' => ['required_with:time_slots.*.end', 'nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'time_slots.*.end' => ['required_with:time_slots.*.start', 'nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'lead_time_hours.required' => 'Say how many hours ahead customers must book.',
            'lead_time_hours.min' => 'The notice period cannot be negative.',
            'booking_window_hours.required' => 'Say how far ahead customers may book.',
            'booking_window_hours.min' => 'Customers must be able to book at least one day ahead.',
            'capacity_per_slot.required' => 'Say how many jobs fit in one time slot.',
            'capacity_per_slot.min' => 'A time slot must allow at least one job.',
            'reference_prefix.required' => 'Give the booking reference a prefix, for example SRV-.',
            'reference_prefix.regex' => 'The prefix may only use letters, numbers and dashes.',
            'time_slots.*.start.regex' => 'A slot start time must look like 09:00.',
            'time_slots.*.end.regex' => 'A slot end time must look like 12:00.',
            'time_slots.*.start.required_with' => 'Give the start time for this slot, or remove the slot.',
            'time_slots.*.end.required_with' => 'Give the end time for this slot, or remove the slot.',
        ];
    }

    /**
     * A slot that starts before it ends is impossible to honour, so it is
     * refused rather than quietly offered to a customer.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ((array) $this->input('time_slots', []) as $index => $slot) {
                if (! is_array($slot)) {
                    continue;
                }

                $start = (string) ($slot['start'] ?? '');
                $end = (string) ($slot['end'] ?? '');

                if ($start === '' || $end === '') {
                    continue;
                }

                if (strtotime($end) <= strtotime($start)) {
                    $validator->errors()->add(
                        "time_slots.{$index}.end",
                        'A time slot has to end after it starts.'
                    );
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'booking_enabled' => $this->boolean('booking_enabled'),
            'same_day_allowed' => $this->boolean('same_day_allowed'),
            'require_login_to_book' => $this->boolean('require_login_to_book'),
            'auto_assign' => $this->boolean('auto_assign'),
        ]);
    }
}
