<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreResponseImagesRequest extends FormRequest
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
            'image'   => ['required', 'array', 'min:1', 'max:3'],
            'image.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.*.max'   => 'Each image must not exceed 5 MB.',
            'image.*.mimes' => 'Images must be JPG, PNG or WebP.',
            'image.*.file'  => 'An image failed to upload. It may exceed the server upload limit.',
        ];
    }
}
