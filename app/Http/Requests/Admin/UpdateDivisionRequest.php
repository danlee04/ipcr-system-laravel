<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDivisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // The record keeps its own code: without ignore() every save of an
        // unchanged form would fail its own uniqueness check.
        $divisionId = $this->route('division')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('divisions', 'code')->ignore($divisionId)],
            'division_head_employee_id' => ['nullable', 'exists:employees,id'],
        ];
    }
}
