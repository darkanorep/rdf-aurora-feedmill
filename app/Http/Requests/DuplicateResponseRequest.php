<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DuplicateResponseRequest extends FormRequest
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
            'batch_no' => ['required', 'integer', 'min:1'],
            'duplicate_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'batch_no.required' => 'Batch number is required.',
            'duplicate_reason.required' => 'Please explain why this response is being duplicated.',
            'duplicate_reason.min' => 'The reason must be at least :min characters.',
        ];
    }
}
