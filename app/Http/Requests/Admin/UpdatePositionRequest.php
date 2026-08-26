<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $positionId = $this->route('position')->id;

        return [
            'title'        => ['required', 'string', 'max:255'],
            // Optional: office-wide posts such as Chief of Hospital sit in no
            // section. The division is reached through the section, never stored.
            'section_id'   => ['nullable', 'integer', 'exists:sections,id'],
            'item_number'  => ['nullable', 'string', 'max:50', Rule::unique('positions', 'item_number')->ignore($positionId)],
            'salary_grade' => ['nullable', 'integer', 'min:1', 'max:33'],
            'description'  => ['nullable', 'string'],
        ];
    }
}
