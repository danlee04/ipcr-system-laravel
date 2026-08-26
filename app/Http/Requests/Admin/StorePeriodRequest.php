<?php

namespace App\Http\Requests\Admin;

use App\Enums\IpcrPeriodType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],

            // The table has unique(year, type). Checked here so the
            // administrator gets a field error instead of a database
            // constraint violation.
            'type' => [
                'required',
                Rule::enum(IpcrPeriodType::class),
                Rule::unique('ipcr_periods', 'type')->where(
                    fn ($query) => $query->where('year', $this->integer('year'))
                ),
            ],

            'start_date'          => ['required', 'date'],
            'end_date'            => ['required', 'date', 'after:start_date'],
            'submission_deadline' => ['nullable', 'date', 'after_or_equal:end_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.unique' => 'There is already a period of this type for that year.',
            'submission_deadline.after_or_equal' => 'The submission deadline cannot fall before the period ends.',
        ];
    }
}
