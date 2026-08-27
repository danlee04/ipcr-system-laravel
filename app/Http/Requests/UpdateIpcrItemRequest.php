<?php

namespace App\Http\Requests;

use App\Enums\RatingMeasure;
use App\Models\Ipcr;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Owner-editable fields only. Quality/Efficiency/Timeliness ratings are
 * NOT here - those belong to the approval workflow, which is handled by a
 * separate controller.
 *
 * The exception is `reported`: the figures behind a graded function. They are
 * not marks, they are what the employee did - 11 of 12, 95% - and the rubric
 * on the catalog function turns them into marks afterwards.
 *
 * Whether `actual_accomplishment` and `reported` are accepted depends on the
 * parent IPCR's mode. When the owner chose "targets only" we strip them from
 * the input before validation rather than rejecting them. The fields are
 * hidden on that form, and a validation error about something you cannot see
 * is a nasty surprise.
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
            $this->request->remove('reported');
        }
    }

    public function rules(): array
    {
        return [
            'output'                => ['required', 'string'],
            'success_indicator'     => ['nullable', 'string'],
            'weight'                => ['nullable', 'numeric', 'min:0', 'max:100'],
            'actual_accomplishment' => ['nullable', 'string'],

            // Every level of this has to be spelled out. validated() returns
            // only the keys that were named in a rule, so a nested array left
            // unmentioned is dropped without a word.
            'reported'              => ['nullable', 'array'],
            'reported.*'            => ['array'],
            'reported.*.value'      => ['nullable', 'numeric'],
            'reported.*.count'      => ['nullable', 'numeric', 'min:0'],
            'reported.*.total'      => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** Only the three CSC measures can be reported on. */
    public function after(): array
    {
        return [
            function (\Illuminate\Validation\Validator $validator): void {
                foreach (array_keys((array) $this->input('reported', [])) as $key) {
                    if (! in_array($key, RatingMeasure::values(), true)) {
                        $validator->errors()->add('reported', 'That is not a measure this form rates on.');
                    }
                }
            },
        ];
    }

    public function attributes(): array
    {
        return collect(RatingMeasure::cases())
            ->flatMap(fn (RatingMeasure $measure): array => [
                "reported.{$measure->value}.value" => strtolower($measure->label()),
                "reported.{$measure->value}.count" => strtolower($measure->label()) . ' count',
                "reported.{$measure->value}.total" => strtolower($measure->label()) . ' total',
            ])
            ->all();
    }
}
