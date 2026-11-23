<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

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
            'hierarchy'   => ['required', 'array', 'min:1'],
            'hierarchy.*' => ['required', function ($attribute, $value, $fail) {
                $ids = is_array($value) ? $value : [$value];
                foreach ($ids as $id) {
                    if (! is_numeric($id) || ! \App\Models\User::whereKey($id)->exists()) {
                        $fail('Each approval level must be a user id or a list of user ids.');
                        return;
                    }
                }
            }],
            'evaluator_ids'   => ['nullable', 'array'],
            'evaluator_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'section_id' => 'required|integer|exists:sections,id',
        ];
    }

    public function attributes() {
        return [
            'section_id' =>  'Section'
        ];
    }
}
