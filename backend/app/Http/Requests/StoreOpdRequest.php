<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOpdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:opds,name'],
            'code' => ['nullable', 'string', 'max:50', 'unique:opds,code'],

            'description' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],

            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],

            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
