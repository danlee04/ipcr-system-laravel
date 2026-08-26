<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEmployeeRequest extends FormRequest
{
    use PlacesAnEmployee;

    /** The same list the create form hands out. */
    public const ROLES = StoreEmployeeRequest::ROLES;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employee = $this->route('employee');

        return $this->employeeRules() + $this->accountRules() + [
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
        ];
    }

    public function messages(): array
    {
        return $this->employeeMessages();
    }

    public function withValidator(Validator $validator): void
    {
        $this->validatePlacement($validator);
    }
}
