<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEmployeeRequest extends FormRequest
{
    use PlacesAnEmployee;

    /** Roles an administrator may hand out from this screen. */
    public const ROLES = ['employee', 'hr', 'admin'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->employeeRules() + $this->accountRules() + [
            'employee_number' => ['required', 'string', 'max:50', 'unique:employees,employee_number'],

            // Optional: not every employee needs a login. One can be added
            // later by editing the record and supplying an email.
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
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
