<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'        => ['required', 'string', 'max:255'],
            // Optional: office-wide posts such as Chief of Hospital sit in no
            // section. The division is reached through the section, never stored.
            'section_id'   => ['nullable', 'integer', 'exists:sections,id'],
            'item_number'  => ['nullable', 'string', 'max:50', 'unique:positions,item_number'],
            // The Philippine salary grade scale runs 1 to 33.
            'salary_grade' => ['nullable', 'integer', 'min:1', 'max:33'],
            'description'  => ['nullable', 'string'],
        ];
    }
}
