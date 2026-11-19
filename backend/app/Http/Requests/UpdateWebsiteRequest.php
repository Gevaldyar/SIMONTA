<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWebsiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $website = $this->route('website');

        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],

            'url' => [
                'sometimes',
                'required',
                'string',
                'url:http,https',
                'max:2048',
                Rule::unique('websites', 'url')->ignore($website?->id),
            ],

            'description' => ['sometimes', 'nullable', 'string'],

            'is_active' => ['sometimes', 'boolean'],

            'monitoring_enabled' => ['sometimes', 'boolean'],

            'monitoring_interval_minutes' => [
                'sometimes',
                'integer',
                Rule::in([1, 5, 10, 30]),
            ],
        ];
    }
}
