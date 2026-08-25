<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employee = $this->route('employee');

        return [
            'first_name'      => ['required', 'string', 'max:255'],
            'middle_name'     => ['nullable', 'string', 'max:255'],
            'last_name'       => ['required', 'string', 'max:255'],
            'suffix'          => ['nullable', 'string', 'max:20'],
            'employee_number' => [
                'required', 'string', 'max:50',
                Rule::unique('employees', 'employee_number')->ignore($employee->id),
            ],

            // Ignores the account already linked to this employee, so saving
            // the form without changing the email is not a uniqueness clash.
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($employee->user_id),
            ],
            'role' => ['nullable', Rule::in(StoreEmployeeRequest::ROLES)],

            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'section_id'  => ['nullable', 'integer', 'exists:sections,id'],

            'employment_status'    => ['required', Rule::in(StoreEmployeeRequest::EMPLOYMENT_STATUSES)],
            'date_hired'           => ['nullable', 'date'],
            'is_chief_of_hospital' => ['nullable', 'boolean'],
        ];
    }
}
