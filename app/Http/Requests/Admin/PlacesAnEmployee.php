<?php

namespace App\Http\Requests\Admin;

use App\Enums\EmploymentStatus;
use App\Enums\OrgPost;
use App\Models\Position;
use App\Models\Section;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The rules an employee record is saved under, shared by create and edit so
 * the two cannot drift apart.
 *
 * Placement narrows: Division holds Sections, a Section holds Positions. The
 * form only ever offers a combination that exists, but a form is not a
 * guarantee - so the same narrowing is checked here, where it is one.
 *
 * All three stay optional. A Division Head holds no section, and the Chief of
 * Hospital holds neither.
 */
trait PlacesAnEmployee
{
    private function employeeRules(): array
    {
        return [
            'first_name'      => ['required', 'string', 'max:255'],
            'middle_name'     => ['nullable', 'string', 'max:255'],
            'last_name'       => ['required', 'string', 'max:255'],
            'suffix'          => ['nullable', 'string', 'max:20'],

            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'section_id'  => ['nullable', 'integer', 'exists:sections,id'],

            'employment_status' => ['required', Rule::in(EmploymentStatus::values())],

            // The approving post, written straight onto the org chart on save.
            // Blank means they hold none - and stands them down from any they
            // held before, because this field states the present, not an
            // addition to a list.
            'post' => ['nullable', Rule::in(OrgPost::values())],

            // Which section or division they lead, when that is not the one
            // they sit in. An Administrative Officer on the Health Information
            // Management plantilla can be Section Head of HRD; collapsing the
            // two would hand the headship to the wrong section.
            'heads_section_id'  => ['nullable', 'integer', 'exists:sections,id'],
            'heads_division_id' => ['nullable', 'integer', 'exists:divisions,id'],

            // The posts they are designated to, wherever those sit. Nothing
            // attached these before, so no strategic or support function could
            // reach anybody.
            'designations'   => ['nullable', 'array'],
            'designations.*' => ['integer', 'exists:designations,id'],
        ];
    }

    /**
     * The login half of the form.
     *
     * The password is optional in both directions: leave it blank and one is
     * generated and shown once, which is what this screen has always done.
     * Typing one is for when HR would rather hand over something the person
     * can be told over the phone.
     */
    private function accountRules(): array
    {
        return [
            'role'     => ['nullable', Rule::in(self::ROLES)],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ];
    }

    private function validatePlacement(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['division_id', 'section_id', 'position_id'])) {
                return;
            }

            $this->sectionMustSitInTheDivision($validator);
            $this->positionMustSitInTheSection($validator);
            $this->postNeedsSomewhereToBeHeld($validator);
            $this->passwordNeedsAnEmail($validator);
        });
    }

    /**
     * A post is held somewhere. Naming someone Section Head without a section
     * would look accepted on screen and write nothing at all, which is the
     * worst of both - so it is refused here instead.
     */
    private function postNeedsSomewhereToBeHeld(Validator $validator): void
    {
        $post = OrgPost::tryFrom((string) $this->input('post'));

        // The section led, falling back to the one they sit in. Only when
        // neither is given is there nothing to write the headship onto.
        if ($post === OrgPost::SectionHead
            && ! $this->input('heads_section_id') && ! $this->input('section_id')) {
            $validator->errors()->add('post', 'Choose the section this Section Head leads.');
        }

        if ($post === OrgPost::DivisionHead
            && ! $this->input('heads_division_id') && ! $this->input('division_id')) {
            $validator->errors()->add('post', 'Choose the division this Division Head leads.');
        }
    }

    private function sectionMustSitInTheDivision(Validator $validator): void
    {
        $divisionId = $this->input('division_id');
        $sectionId = $this->input('section_id');

        if (! $divisionId || ! $sectionId) {
            return;
        }

        $section = Section::find($sectionId);

        if ($section && (int) $section->division_id !== (int) $divisionId) {
            $validator->errors()->add(
                'section_id',
                "\"{$section->name}\" does not belong to the division chosen above."
            );
        }
    }

    /**
     * A position with no section is office-wide - the Chief of Hospital's, for
     * instance - and belongs to nobody's section, so it is only available when
     * no section has been chosen.
     */
    private function positionMustSitInTheSection(Validator $validator): void
    {
        $sectionId = $this->input('section_id');
        $positionId = $this->input('position_id');

        if (! $positionId) {
            return;
        }

        $position = Position::with('section')->find($positionId);

        if ($position === null) {
            return;
        }

        if ($sectionId && (int) $position->section_id !== (int) $sectionId) {
            $validator->errors()->add(
                'position_id',
                "\"{$position->title}\" belongs to "
                    . ($position->section?->name ?? 'no section')
                    . ', not to the section chosen above.'
            );

            return;
        }

        $divisionId = $this->input('division_id');

        if (! $sectionId && $divisionId && $position->section !== null
            && (int) $position->section->division_id !== (int) $divisionId) {
            $validator->errors()->add(
                'position_id',
                "\"{$position->title}\" does not belong to the division chosen above."
            );
        }
    }

    private function passwordNeedsAnEmail(Validator $validator): void
    {
        if ($this->filled('password') && ! $this->filled('email')) {
            $validator->errors()->add(
                'password',
                'A password needs an email address - that is what the login is made from.'
            );
        }
    }

    private function employeeMessages(): array
    {
        return [
            'password.min' => 'Make the password at least 8 characters, or leave it blank to have one generated.',
        ];
    }
}
