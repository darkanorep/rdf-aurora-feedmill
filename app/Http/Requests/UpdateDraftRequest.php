<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDraftRequest extends FormRequest
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
            'start_at'             => ['sometimes', 'date'],
            'good_points'          => ['nullable', 'string'],
            'remarks'              => ['nullable', 'string'],
            'notes'                => ['nullable', 'string'],
            'temporal_audit'       => ['nullable'],
            'responses'            => ['sometimes', 'array', 'max:200'],
            'responses.*.id'       => ['nullable', 'integer'],   // present = update, absent = new row
            'responses.*.response' => ['required'],
        ];
    }
}
