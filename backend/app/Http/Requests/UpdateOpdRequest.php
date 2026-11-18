<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOpdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $opd = $this->route('opd');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('opds', 'name')->ignore($opd?->id),
            ],

            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('opds', 'code')->ignore($opd?->id),
            ],

            'description' => ['sometimes', 'nullable', 'string'],
            'address' => ['sometimes', 'nullable', 'string'],

            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],

            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
