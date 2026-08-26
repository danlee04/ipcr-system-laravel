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
            'category'           => ['required', Rule::enum(FunctionCategory::class), $this->ratedCategoryUnlessFromCatalog()],
            'output'             => ['required', 'string'],
            'success_indicator'  => ['nullable', 'string'],
            'weight'             => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * A line typed by hand must name one of the three rated categories.
     *
     * "Common" is only meaningful when it comes from the catalog, where the
     * controller swaps it for the category HR filed that function under. Typed
     * in directly it would produce a line the rating calculator ignores.
     */
    private function ratedCategoryUnlessFromCatalog(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === FunctionCategory::Common->value && ! $this->filled('job_function_id')) {
                $fail('Choose Strategic, Core or Support.');
            }
        };
    }
}
