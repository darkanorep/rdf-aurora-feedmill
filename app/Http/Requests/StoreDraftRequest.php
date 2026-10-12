<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDraftRequest extends FormRequest
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
            'checklist_id'         => ['nullable', 'integer', 'exists:checklists,id'],
            'unit_id'              => ['nullable', 'integer', 'exists:units,id'],
            'start_at'             => ['nullable', 'date'],
            'evaluator_id'         => ['nullable', 'integer', 'exists:users,id'],
            'approver_id'          => ['nullable', 'integer', 'exists:users,id'],
            'assessor_id'          => ['nullable', 'integer', 'exists:users,id'],
            'good_points'          => ['nullable', 'string'],
            'remarks'              => ['nullable', 'string'],
            'notes'                => ['nullable', 'string'],
            'temporal_audit'       => ['nullable'],
            'responses'            => ['nullable', 'array', 'min:1', 'max:200'],
            'responses.*.response' => ['nullable'],   // object or JSON string; parseData() handles both
        ];
    }
}
