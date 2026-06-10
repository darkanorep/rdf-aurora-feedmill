<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcknowledgementSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'user_id' => 'required|integer|exists:users,id|unique:acknowledgement_settings,user_id,' . $this->route('id'),
            'hierarchy' => 'required|array',
            'hierarchy.*' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
                function ($attribute, $value, $fail) {
                    $user = User::whereHas('role', function ($query) {
                        $query->whereIn('name', [Role::APPROVER, Role::ASSESSOR]);
                    })->where('id', $value)->exists();

                    if (!$user) {
                        $fail("The selected {$attribute} must be an Approver or Assessor.");
                    }
                }
            ],
            'section_id' => 'required|integer|exists:sections,id',
        ];
    }

    public function attributes() {
        return [
            'user_id' =>  'User',
            'section_id' =>  'Section'
        ];
    }
}
