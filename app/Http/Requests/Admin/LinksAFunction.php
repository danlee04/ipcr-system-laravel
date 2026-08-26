<?php

namespace App\Http\Requests\Admin;

use App\Enums\FunctionCategory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The rules a catalog function is saved under, shared by create and edit so
 * the two cannot drift apart.
 *
 * How a function reaches an employee, per FunctionCatalogService:
 *
 *   core      -> their plantilla position, OR one of their designations
 *   strategic -> one of their designations
 *   support   -> one of their designations
 *   common    -> everyone, and it says which rated category it counts towards
 *
 * Core takes either link because a designation is not a category of work. An
 * Infection Control Officer has core duties as one; filing those under
 * "support" would put them in a category they do not belong to, and weight
 * them at 20% instead of 80%.
 */
trait LinksAFunction
{
    private function category(): ?FunctionCategory
    {
        return FunctionCategory::tryFrom((string) $this->input('category'));
    }

    private function functionRules(): array
    {
        $category = $this->category();

        return [
            'category' => ['required', Rule::enum(FunctionCategory::class)],

            // Neither link is required here. Which one is needed depends on
            // the other, so the requirement is checked in one place below,
            // where a single clear message can be given.
            'position_id'    => ['nullable', 'integer', 'exists:positions,id'],
            'designation_id' => ['nullable', 'integer', 'exists:designations,id'],

            /*
             * Deliberately not `prohibited` outside the common pool.
             *
             * The category branches are shown and hidden by Alpine, and a
             * hidden field is still submitted: someone who looks at Common
             * first and then picks Core sends a leftover rating category. That
             * used to fail the save and point at a field they could no longer
             * see. The controller already discards the value, so refusing it
             * only punishes the user for a quirk of the form.
             */
            'rating_category' => [
                'nullable',
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

    /**
     * Exactly one link, and the right kind for the category.
     *
     * Done after the field rules rather than inside them so the message can
     * say what is actually wrong, instead of "the position field is required"
     * on a form where a designation would have done just as well.
     */
    private function validateTheLink(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['category', 'position_id', 'designation_id'])) {
                return;
            }

            $category = $this->category();
            $position = $this->input('position_id');
            $designation = $this->input('designation_id');

            if ($category === FunctionCategory::Common) {
                return;   // Open to everyone; any link submitted is discarded.
            }

            if ($category === FunctionCategory::Core) {
                if ($position && $designation) {
                    $validator->errors()->add(
                        'position_id',
                        'A core function belongs to a position or to a designation, not to both.'
                    );
                } elseif (! $position && ! $designation) {
                    $validator->errors()->add(
                        'position_id',
                        'Choose the position or the designation this core function belongs to, or nobody will see it.'
                    );
                }

                return;
            }

            // Strategic and support reach people through designations only.
            if (! $designation) {
                $validator->errors()->add(
                    'designation_id',
                    'This function must belong to a designation, or nobody will see it.'
                );
            }
        });
    }

    private function functionMessages(): array
    {
        return [
            'rating_category.in' => 'Choose Strategic, Core or Support.',
        ];
    }
}
