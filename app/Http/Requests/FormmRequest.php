<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FormmRequest extends FormRequest
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
            'checklist_id' => 'required|exists:checklists,id',
            'forms' => 'required|array',
            'forms.*.name' => 'required|string|max:255',
            'forms.*.item' => 'required|array',
            'forms.*.item.*.name' => 'required|string|max:255',
            'forms.*.item.*.type' => 'nullable|string|max:255',
        ];
    }

    public function attributes()
    {
        return [
            'checklist_id' => 'checklist',
        ];
    }
}
