<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The admin route group already enforces role:admin.
        return true;
    }

    public function rules(): array
    {
        return [
            'division_id' => ['required', 'exists:divisions,id'],
            'name'        => ['required', 'string', 'max:255'],
            'code'        => ['nullable', 'string', 'max:20', 'unique:sections,code'],
            'section_head_employee_id' => ['nullable', 'exists:employees,id'],
        ];
    }
}
