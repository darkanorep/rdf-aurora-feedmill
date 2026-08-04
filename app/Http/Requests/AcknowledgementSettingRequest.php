<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Models\Section;
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
            'user_id' => [
                Rule::requiredIf(fn () => $this->input('section') === Section::BIRDS),
                'integer',
                'exists:users,id',
            ],
            'hierarchy' => 'nullable|array',
            'hierarchy.*' => [
                'nullable',
                'integer',
                'exists:users,id'
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
