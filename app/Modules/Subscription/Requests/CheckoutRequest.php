<?php

namespace App\Modules\Subscription\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'plan' => 'sometimes|string',
            'mode' => 'sometimes|string|in:subscription,payment,setup',
            'success_url' => 'sometimes|string|url',
            'cancel_url' => 'sometimes|string|url',
            'trial_days' => 'sometimes|integer|min:0',
        ];
    }
}
