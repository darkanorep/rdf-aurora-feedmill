<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password'     => ['required', 'string', 'current_password'],
            'new_password'         => ['required', 'string', 'min:6', 'same:new_confirm_password'],
            'new_confirm_password' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'current_password'     => 'current password',
            'new_password'         => 'new password',
            'new_confirm_password' => 'confirm password',
        ];
    }
}
