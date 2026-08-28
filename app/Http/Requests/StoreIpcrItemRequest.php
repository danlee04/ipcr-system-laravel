<?php

namespace App\Http\Requests;

use App\Enums\FunctionCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIpcrItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership and editability are checked by the controller against
        // the parent Ipcr (needs the route-model-bound $ipcr, which form
        // requests don't cleanly have access to before route binding).
        return true;
    }

    public function rules(): array
    {
        return [
            'job_function_id'    => ['nullable', 'exists:job_functions,id'],
            'category'           => ['required', Rule::enum(FunctionCategory::class)],
            'output'             => ['required', 'string'],
            'success_indicator'  => ['nullable', 'string'],
        ];
    }
}
