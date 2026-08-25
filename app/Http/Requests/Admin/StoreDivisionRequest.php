<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreDivisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The admin route group already enforces role:admin.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20', 'unique:divisions,code'],
            'division_head_employee_id' => ['nullable', 'exists:employees,id'],
        ];
    }
}
