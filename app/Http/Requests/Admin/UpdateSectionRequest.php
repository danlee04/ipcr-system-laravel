<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // The record keeps its own code: without ignore() every save of an
        // unchanged form would fail its own uniqueness check.
        $sectionId = $this->route('section')->id;

        return [
            'division_id' => ['required', 'exists:divisions,id'],
            'name'        => ['required', 'string', 'max:255'],
            'code'        => ['nullable', 'string', 'max:20', Rule::unique('sections', 'code')->ignore($sectionId)],
            'section_head_employee_id' => ['nullable', 'exists:employees,id'],
        ];
    }
}
