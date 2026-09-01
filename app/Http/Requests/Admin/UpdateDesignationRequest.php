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

            // Where it posts whoever holds it. Both optional: plenty of
            // designations are a title and move nobody.
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'section_id'  => ['nullable', 'integer', 'exists:sections,id'],
        ];
    }
}
