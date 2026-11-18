<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWebsiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],

            'url' => [
                'required',
                'string',
                'url:http,https',
                'max:2048',
                'unique:websites,url',
            ],

            'description' => ['nullable', 'string'],

            'is_active' => ['sometimes', 'boolean'],

            'monitoring_enabled' => ['sometimes', 'boolean'],

            // Monitoring minimum satu jam.
            'monitoring_interval_minutes' => [
                'sometimes',
                'integer',
                'min:60',
                'max:1440',
            ],
        ];
    }
}
