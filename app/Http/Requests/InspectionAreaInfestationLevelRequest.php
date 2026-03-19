<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InspectionAreaInfestationLevelRequest extends FormRequest
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
            'inspection_area_id' => ['required', 'array', 'min:1'],
            'inspection_area_id.*' => ['integer', 'exists:inspection_areas,id'],
            'infestation_level_id' => ['required', 'array', 'min:1'],
            'infestation_level_id.*' => ['integer', 'exists:infestation_levels,id'],
        ];
    }
}
