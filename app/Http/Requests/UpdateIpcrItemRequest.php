<?php

namespace App\Http\Requests;

use App\Models\Ipcr;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Owner-editable fields only. Quality/Efficiency/Timeliness ratings are
 * NOT here - those belong to the approval workflow, which is handled by a
 * separate controller.
 *
 * Whether `actual_accomplishment` is accepted depends on the parent IPCR's
 * mode. When the owner chose "targets only" we strip it from the input before
 * validation rather than rejecting it. The field is hidden on that form, and a
 * validation error about something you cannot see is a nasty surprise.
 */
class UpdateIpcrItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $ipcr = $this->route('ipcr');

        if ($ipcr instanceof Ipcr && ! $ipcr->showsAccomplishment()) {
            $this->request->remove('actual_accomplishment');
        }
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
