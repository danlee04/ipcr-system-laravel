<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreDesignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Designations have no code column, unlike divisions and sections.
        return [
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
