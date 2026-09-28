<?php

declare(strict_types=1);

namespace Cartxis\Service\Http\Requests;

use Cartxis\Service\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('services', 'slug')],
            'service_category_id' => ['nullable', 'integer', 'exists:service_categories,id'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'includes' => ['nullable', 'string', 'max:5000'],
            'excludes' => ['nullable', 'string', 'max:5000'],
            'icon' => ['nullable', 'string', 'max:60'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'icon_only' => ['nullable', 'boolean'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'price_unit' => ['required', Rule::in(Service::UNITS)],
            'price_note' => ['nullable', 'string', 'max:255'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'duration_label' => ['nullable', 'string', 'max:100'],
            'service_area' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['enabled', 'disabled'])],
            'featured' => ['nullable', 'boolean'],
            'booking_enabled' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Give the service a name.',
            'name.max' => 'The name must not be longer than 255 characters.',
            'slug.unique' => 'That web address is already used by another service.',
            'service_category_id.exists' => 'That category no longer exists.',
            'image.image' => 'The service photo must be an image file.',
            'image.mimes' => 'The service photo must be a JPEG, JPG, PNG or WEBP file.',
            'image.max' => 'The service photo must not be larger than 5 MB.',
            'price.required' => 'Enter the price for this service.',
            'price.numeric' => 'The price must be a number.',
            'price.min' => 'The price cannot be negative.',
            'price_unit.required' => 'Choose what the customer is buying one of.',
            'price_unit.in' => 'Choose one of the listed units.',
            'duration_minutes.integer' => 'The duration must be a whole number of minutes.',
            'duration_minutes.min' => 'The duration must be at least one minute.',
            'status.required' => 'Choose whether this service is published or hidden.',
            'status.in' => 'Choose either published or hidden.',
        ];
    }

    /**
     * The checkboxes arrive as "on" or absent, never as a real boolean, so they
     * are normalised here rather than in every caller.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'icon_only' => $this->boolean('icon_only'),
            'featured' => $this->boolean('featured'),
            'booking_enabled' => $this->boolean('booking_enabled'),
        ]);
    }
}
