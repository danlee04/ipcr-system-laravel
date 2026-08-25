<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Owner-editable fields only. Quality/Efficiency/Timeliness ratings are
 * NOT here on purpose - those are set by the assessor during the approval
 * workflow (a separate controller, coming in the next step), not by the
 * IPCR owner.
 */
class UpdateIpcrItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'output'                => ['required', 'string'],
            'success_indicator'     => ['nullable', 'string'],
            'weight'                => ['nullable', 'numeric', 'min:0', 'max:100'],
            'actual_accomplishment' => ['nullable', 'string'],
        ];
    }
}
