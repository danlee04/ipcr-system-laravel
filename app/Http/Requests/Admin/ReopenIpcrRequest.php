<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Undoing an approval.
 *
 * `target` says how far back it goes: to the assessor, who re-enters the
 * marks, or all the way to the employee, who edits the IPCR itself.
 */
class ReopenIpcrRequest extends FormRequest
{
    /** Back to the assessor. */
    public const TARGET_ASSESSMENT = 'assessment';

    /** Back to the employee for revision. */
    public const TARGET_EMPLOYEE = 'employee';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target' => ['required', Rule::in([self::TARGET_ASSESSMENT, self::TARGET_EMPLOYEE])],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'target.in'       => 'Choose whether it goes back for assessment or to the employee.',
            'reason.required' => 'Say why an approved IPCR is being reopened. It goes on the record.',
        ];
    }
}
