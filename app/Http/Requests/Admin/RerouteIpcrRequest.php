<?php

namespace App\Http\Requests\Admin;

use App\Models\Ipcr;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Naming the two approvers on one IPCR by hand.
 *
 * Authorisation is IpcrPolicy's, applied in the controller, because it depends
 * on the IPCR's status as well as the user's role.
 */
class RerouteIpcrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ipcr = $this->route('ipcr');

        // Only an active employee can be given work, and nobody assesses
        // themselves. Both slots may name the same person: that is the
        // Division Head case, where the Chief of Hospital fills both.
        $approver = [
            'required',
            'integer',
            Rule::exists('employees', 'id')->where('is_active', true),
            Rule::notIn([$ipcr instanceof Ipcr ? $ipcr->employee_id : null]),
        ];

        return [
            'assessor_employee_id'       => $approver,
            'final_approver_employee_id' => $approver,
            'reason'                     => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'assessor_employee_id.not_in'       => 'Nobody can assess their own IPCR.',
            'final_approver_employee_id.not_in' => 'Nobody can give the final approval on their own IPCR.',
            'assessor_employee_id.exists'       => 'Choose an active employee to assess this IPCR.',
            'final_approver_employee_id.exists' => 'Choose an active employee to give the final approval.',
            'reason.required'                   => 'Say why the chain is being set by hand. It goes on the record.',
        ];
    }
}
