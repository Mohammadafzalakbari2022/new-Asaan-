<?php

declare(strict_types=1);

namespace Cartxis\Service\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceCategoryRequest extends StoreServiceCategoryRequest
{
    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = is_object($category) ? $category->getKey() : $category;

        $rules = parent::rules();

        $rules['slug'] = [
            'nullable',
            'string',
            'max:255',
            Rule::unique('service_categories', 'slug')->ignore($categoryId),
        ];

        // The parent check needs the whole tree, so it is not narrowed to a
        // simple existence test the way it is on create.
        $rules['parent_id'] = [
            'nullable',
            'integer',
            Rule::exists('service_categories', 'id'),
        ];

        return $rules;
    }
}
