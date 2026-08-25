<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    /** The employment statuses a Philippine government plantilla recognises. */
    public const EMPLOYMENT_STATUSES = ['permanent', 'casual', 'contractual', 'job_order', 'coterminous'];

    /** Roles an administrator may hand out from this screen. */
    public const ROLES = ['employee', 'hr', 'admin'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'      => ['required', 'string', 'max:255'],
            'middle_name'     => ['nullable', 'string', 'max:255'],
            'last_name'       => ['required', 'string', 'max:255'],
            'suffix'          => ['nullable', 'string', 'max:20'],
            'employee_number' => ['required', 'string', 'max:50', 'unique:employees,employee_number'],

            // Optional: not every employee needs a login. One can be added
            // later by editing the record and supplying an email.
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'role'  => ['nullable', Rule::in(self::ROLES)],

            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'section_id'  => ['nullable', 'integer', 'exists:sections,id'],

            'employment_status'    => ['required', Rule::in(self::EMPLOYMENT_STATUSES)],
            'date_hired'           => ['nullable', 'date'],
            'is_chief_of_hospital' => ['nullable', 'boolean'],
        ];
    }
}
