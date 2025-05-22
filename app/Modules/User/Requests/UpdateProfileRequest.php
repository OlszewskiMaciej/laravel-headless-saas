<?php

namespace App\Modules\User\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
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
        $user = $this->user();
        
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required', 
                'string', 
                'email', 
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'password' => [
                'sometimes', 
                'required', 
                'string', 
                Password::defaults(), 
                'confirmed'
            ],
            'current_password' => ['required_with:password', 'current_password'],
        ];
    }
}
