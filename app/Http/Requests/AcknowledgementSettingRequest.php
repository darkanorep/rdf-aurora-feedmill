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
            'user_id' => 'required|integer|exists:users,id',
            'hierarchy' => 'required|array',
            'hierarchy.*' => [
                'required',
                'integer',
                'exists:users,id'
//                Rule::exists('users', 'id'),
//                function ($attribute, $value, $fail) {
//                    $user = User::whereHas('role', function ($query) {
//                        $query->whereIn('name', array_merge(Role::APPROVER, Role::ASSESSOR));
//                    })->where('id', $value)->exists();
//
//                    if (!$user) {
//                        $fail("The selected {$attribute} must be an Approver, QA, Assessor, or QA Head.");
//                    }
//                }
            ],
            'section_id' => 'required|integer|exists:sections,id|unique:acknowledgement_settings,section_id,' . $this->route('id'),
        ];
    }

    public function attributes() {
        return [
            'user_id' =>  'User',
            'section_id' =>  'Section'
        ];
    }
}
