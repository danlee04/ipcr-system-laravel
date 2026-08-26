@props(['employee' => null, 'divisions', 'sections', 'positions'])

@php
    use App\Enums\EmploymentStatus;
    use App\Enums\OrgPost;
    use App\Http\Requests\Admin\StoreEmployeeRequest;

    $currentRole = $employee?->user?->getRoleNames()->first() ?? 'employee';

    // The placement opens already pointing at where the person sits, rather
    // than resetting to "all" and hiding the section they are actually in.
    $currentDivision = old('division_id', $employee?->division_id) ?? '';
    $currentSection = old('section_id', $employee?->section_id) ?? '';
@endphp

{{-- Shared by the create and edit modals so the two forms cannot drift apart.
     $employee is null when creating. --}}
<div class="space-y-6">

    {{-- ---------------------------------------------------------------
         The person
    --------------------------------------------------------------- --}}
    {{-- One 12-column grid rather than a stack of narrower ones: the fields
         are different widths - a suffix is three characters, a name is not -
         and giving each the room it needs is what fills the row. --}}
    <fieldset>
        <legend class="mb-2 text-sm font-semibold text-gray-900">Personal details</legend>

        <div class="grid gap-4 sm:grid-cols-12">
            <label class="block sm:col-span-4">
                <span class="mb-1 block text-sm font-medium text-gray-700">First name</span>
                <input type="text" name="first_name" value="{{ old('first_name', $employee?->first_name) }}" required
                    class="w-full rounded-md border-gray-300 text-sm">
            </label>

            <label class="block sm:col-span-3">
                <span class="mb-1 block text-sm font-medium text-gray-700">Middle name</span>
                <input type="text" name="middle_name" value="{{ old('middle_name', $employee?->middle_name) }}"
                    class="w-full rounded-md border-gray-300 text-sm">
            </label>

            <label class="block sm:col-span-3">
                <span class="mb-1 block text-sm font-medium text-gray-700">Last name</span>
                <input type="text" name="last_name" value="{{ old('last_name', $employee?->last_name) }}" required
                    class="w-full rounded-md border-gray-300 text-sm">
            </label>

            <label class="block sm:col-span-2">
                <span class="mb-1 block text-sm font-medium text-gray-700">Suffix</span>
                <input type="text" name="suffix" value="{{ old('suffix', $employee?->suffix) }}" maxlength="20"
                    placeholder="Jr." class="w-full rounded-md border-gray-300 text-sm">
            </label>

            <label class="block sm:col-span-5">
                <span class="mb-1 block text-sm font-medium text-gray-700">Employee number</span>
                <input type="text" name="employee_number"
                    value="{{ old('employee_number', $employee?->employee_number) }}" required maxlength="50"
                    placeholder="DTRC-1001" class="w-full rounded-md border-gray-300 font-data text-sm">
            </label>

            <label class="block sm:col-span-7">
                <span class="mb-1 block text-sm font-medium text-gray-700">Employment status</span>
                <select name="employment_status" required class="w-full rounded-md border-gray-300 text-sm">
                    @foreach (EmploymentStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(old('employment_status', $employee?->employment_status ?? EmploymentStatus::Permanent->value) === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </label>
        </div>
    </fieldset>

    {{-- ---------------------------------------------------------------
         Where they sit. Division holds Sections, a Section holds Positions,
         so each select narrows the one after it: the three can never describe
         a placement that does not exist.
    --------------------------------------------------------------- --}}
    <fieldset class="space-y-3 rounded-lg bg-gray-50 p-4 ring-1 ring-inset ring-gray-200"
        x-data="{ division: '{{ $currentDivision }}', section: '{{ $currentSection }}', post: '{{ old('post', $employee?->post()?->value) }}' }">
        <legend class="px-1 text-sm font-semibold text-gray-900">Placement</legend>

        {{-- Four across: where they sit, and what they lead there. One row,
             read left to right, each select narrowing the next. --}}
        <div class="grid gap-4 sm:grid-cols-4">
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">Division</span>
                <select name="division_id" x-model="division" x-on:change="section = ''"
                    class="w-full rounded-md border-gray-300 text-sm">
                    <option value="">— None —</option>
                    @foreach ($divisions as $division)
                        <option value="{{ $division->id }}">{{ $division->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">Section</span>
                <select name="section_id" x-model="section" class="w-full rounded-md border-gray-300 text-sm">
                    <option value="">— None —</option>
                    @foreach ($sections as $section)
                        <option value="{{ $section->id }}" data-division="{{ $section->division_id }}"
                            x-show="division === '' || division === '{{ $section->division_id }}'">
                            {{ $section->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">Position</span>
                <select name="position_id" class="w-full rounded-md border-gray-300 text-sm">
                    <option value="">— None —</option>
                    @foreach ($positions as $position)
                        {{-- Office-wide positions carry no section, so they
                             show only while no section is chosen. Nobody in a
                             section holds one. --}}
                        <option value="{{ $position->id }}" data-section="{{ $position->section_id }}"
                            data-division="{{ $position->section?->division_id }}"
                            x-show="(section === '' || section === '{{ $position->section_id }}')
                                && (division === '' || division === '{{ $position->section?->division_id }}')"
                            @selected(old('position_id', $employee?->position_id) == $position->id)>
                            {{-- The title alone. The section is already chosen
                                 in the select before this one, so repeating it
                                 on every line says nothing. --}}
                            {{ $position->title }}
                        </option>
                    @endforeach
                </select>
            </label>

            {{-- The approving post. Saving writes it straight onto the org
                 chart - the section's head column, the division's, or the
                 chief flag - which is what IpcrRoutingService reads when an
                 IPCR is submitted.

                 It states the present: leaving it blank stands them down from
                 whatever they held, and there is one head per section, per
                 division, and one Chief of Hospital in the whole hospital. --}}
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">Approving post</span>

                <select name="post" x-model="post" class="w-full rounded-md border-gray-300 text-sm">
                    <option value="">— None —</option>
                    @foreach (OrgPost::cases() as $orgPost)
                        {{-- A post is held somewhere: Section Head needs a
                             section, Division Head a division. Offering one
                             that cannot be saved is a trap, so it greys out
                             until the placement beside it can carry it. --}}
                        <option value="{{ $orgPost->value }}" @selected(old('post', $employee?->post()?->value) === $orgPost->value)
                            @if ($orgPost === OrgPost::SectionHead) :disabled="section === ''"
                            @elseif ($orgPost === OrgPost::DivisionHead) :disabled="division === ''" @endif>
                            {{ $orgPost->label() }}
                        </option>
                    @endforeach
                </select>
            </label>
        </div>

        {{-- Under the row, not squeezed into a quarter of it: the sentence
             changes with the choice and needs the width to stay one line. --}}
        <p class="text-sm text-gray-600">
            @foreach (OrgPost::cases() as $orgPost)
                <span x-show="post === '{{ $orgPost->value }}'" x-cloak>{{ $orgPost->description() }}</span>
            @endforeach
            <span x-show="post === ''" x-cloak>
                Holds no approving post. Their own IPCR goes to their Section Head.
            </span>
        </p>
    </fieldset>

    {{-- ---------------------------------------------------------------
         The login. Its own card because it is its own thing: an employee
         record can exist without one, and this is the only place an account
         comes into being.
    --------------------------------------------------------------- --}}
    <fieldset class="space-y-4 rounded-lg bg-white p-4 ring-1 ring-inset ring-gray-200">
        <legend class="px-1 text-sm font-semibold text-gray-900">Login account</legend>

        <p class="text-xs text-gray-600">
            @if ($employee?->user)
                This employee already signs in as
                <span class="font-data">{{ $employee->user->email }}</span>.
                Leave the password blank to keep the current one.
            @else
                Optional. Leave the email blank and the employee has a record but no way to sign in — you can add one
                later by editing them.
            @endif
        </p>

        <div class="grid gap-4 sm:grid-cols-3">
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">Email</span>
                <input type="email" name="email" value="{{ old('email', $employee?->user?->email) }}"
                    autocomplete="off" class="w-full rounded-md border-gray-300 text-sm">
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">
                    Password
                    <span class="font-normal text-gray-500">— optional</span>
                </span>
                <input type="text" name="password" value="" autocomplete="new-password" minlength="8"
                    placeholder="Leave blank to generate one" class="w-full rounded-md border-gray-300 text-sm">
                <span class="mt-1 block text-xs text-gray-500">
                    Shown in plain text so it can be handed over. A generated one is shown once, after saving.
                </span>
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">Role</span>
                <select name="role" class="w-full rounded-md border-gray-300 text-sm">
                    @foreach (StoreEmployeeRequest::ROLES as $role)
                        <option value="{{ $role }}" @selected(old('role', $currentRole) === $role)>
                            {{ ucfirst($role) }}
                        </option>
                    @endforeach
                </select>
            </label>
        </div>
    </fieldset>
</div>
