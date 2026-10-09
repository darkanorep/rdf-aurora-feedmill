<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdditionalAttachmentRequest extends FormRequest
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
            'response_id' => ['required', 'integer', 'exists:responses,id'],
            'image'       => ['required', 'array', 'min:1', 'max:10'],
            'image.*'     => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
