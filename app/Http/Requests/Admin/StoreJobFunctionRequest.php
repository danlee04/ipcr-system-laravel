<?php

namespace App\Http\Requests\Admin;

use App\Enums\FunctionCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobFunctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = FunctionCategory::tryFrom((string) $this->input('category'));

        return [
            'category' => ['required', Rule::enum(FunctionCategory::class)],

            // FunctionCatalogService finds core functions through the
            // employee's position and strategic/support through their
            // designations. A function missing its link is in the catalog but
            // reaches nobody, so the link is required rather than optional.
            'position_id' => [
                $category === FunctionCategory::Core ? 'required' : 'nullable',
                'integer', 'exists:positions,id',
            ],
            'designation_id' => [
                in_array($category, [FunctionCategory::Strategic, FunctionCategory::Support], true)
                    ? 'required' : 'nullable',
                'integer', 'exists:designations,id',
            ],

            // Only meaningful for the common pool: which rated category a line
            // built from this function counts towards.
            'rating_category' => [
                $category === FunctionCategory::Common ? 'nullable' : 'prohibited',
                Rule::in([
                    FunctionCategory::Strategic->value,
                    FunctionCategory::Core->value,
                    FunctionCategory::Support->value,
                ]),
            ],

            'title'             => ['required', 'string', 'max:2000'],
            'success_indicator' => ['nullable', 'string', 'max:2000'],
            'default_weight'    => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'position_id.required'    => 'A core function must belong to a position, or nobody will see it.',
            'designation_id.required' => 'This function must belong to a designation, or nobody will see it.',
            'rating_category.in'      => 'Choose Strategic, Core or Support.',
        ];
    }
}
