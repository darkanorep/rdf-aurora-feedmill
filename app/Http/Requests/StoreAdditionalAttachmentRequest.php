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
            'batch_no' => ['required', 'integer', 'exists:responses,batch_no', 'min:1'],
            'image' => ['required'],
            'image.*' => ['image', 'mimes:jpeg,jpg,png', 'max:5120'],
        ];
    }
}
