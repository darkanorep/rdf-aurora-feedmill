<?php

namespace App\Http\Requests;

use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChecklistRequest extends FormRequest
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
            'section_id' => 'required|exists:sections,id',
            'checklist_name' => 'required|string|unique:checklists,checklist_name,' . $this->route('checklist'),
            'items' => 'nullable',
            'unit_ids' => [
                Rule::requiredIf(function () {
                    $section = Section::find($this->input('section_id'));
                    return $section && $section->name === Section::COBS;
                }),
                'array',
                'exists:units,id'
            ],
//            'inspection_area_ids' => [
//                Rule::requiredIf(fn() => in_array(
//                    Section::find($this->input('section_id'))?->name,
//                    [Section::PESTS, Section::BIRDS]
//                )),
//            ],
//            'infestation_level_ids' => [
//                Rule::requiredIf(fn() => in_array(
//                    Section::find($this->input('section_id'))?->name,
//                    [Section::BIRDS]
//                )),
//            ]
        ];
    }

    public function attributes()
    {
        return [
            'section_id' => 'section',
            'unit_ids' => 'units',
            'inspection_area_ids' => 'inspection areas',
        ];
    }
}
