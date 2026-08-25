<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDesignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Same rules as the store request: there is no unique column to ignore.
        return [
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
