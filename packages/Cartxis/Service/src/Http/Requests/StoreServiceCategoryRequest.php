<?php

declare(strict_types=1);

namespace Cartxis\Service\Http\Requests;

use Cartxis\Service\Models\ServiceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('service_categories', 'slug'),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('service_categories', 'id'),
            ],
            'description' => ['nullable', 'string', 'max:20000'],
            'icon' => ['nullable', 'string', 'max:60'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'status' => ['required', Rule::in(['enabled', 'disabled'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'show_in_menu' => ['nullable', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Give the category a name.',
            'slug.unique' => 'That web address is already used by another category.',
            'parent_id.exists' => 'The parent category no longer exists.',
            'status.in' => 'Choose either published or hidden.',
            'image.max' => 'The category photo must not be larger than 5 MB.',
        ];
    }

    /**
     * A category cannot be filed under itself, and never under one of its own
     * descendants, which would make the tree impossible to walk.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $parentId = $this->input('parent_id');
            $category = $this->route('category');

            if (! $parentId || ! $category) {
                return;
            }

            if ((int) $parentId === (int) $category->getKey()) {
                $validator->errors()->add('parent_id', 'A category cannot be filed under itself.');

                return;
            }

            if (in_array((int) $parentId, $category->descendantIds(), true)) {
                $validator->errors()->add(
                    'parent_id',
                    'A category cannot be filed under one of its own sub-categories.'
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'show_in_menu' => $this->boolean('show_in_menu'),
        ]);
    }
}
