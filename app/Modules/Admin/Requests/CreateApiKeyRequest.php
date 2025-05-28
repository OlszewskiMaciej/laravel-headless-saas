<?php

namespace App\Modules\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateApiKeyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'service' => 'required|string|max:255',
            'environment' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'expires_days' => 'nullable|integer|min:1|max:3650', // Max 10 years
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'API key name is required.',
            'service.required' => 'Service name is required.',
            'environment.required' => 'Environment is required.',
            'expires_days.min' => 'Expiration days must be at least 1.',
            'expires_days.max' => 'Expiration days cannot exceed 3650 (10 years).',
        ];
    }
}
