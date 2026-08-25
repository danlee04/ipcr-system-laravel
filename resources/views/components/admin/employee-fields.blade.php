@props(['employee' => null, 'divisions', 'sections', 'positions'])

@php
    use App\Http\Requests\Admin\StoreEmployeeRequest;

    $currentRole = $employee?->user?->getRoleNames()->first() ?? 'employee';
@endphp

{{-- Shared by the create and edit modals so the two forms cannot drift apart.
     $employee is null when creating. --}}
<div class="space-y-4">
    <div class="grid gap-4 sm:grid-cols-4">
        <label class="block sm:col-span-1">
            <span class="mb-1 block text-sm font-medium text-gray-700">First name</span>
            <input type="text" name="first_name" value="{{ old('first_name', $employee?->first_name) }}" required
                class="w-full rounded-md border-gray-300 text-sm">
        </label>

        <label class="block sm:col-span-1">
            <span class="mb-1 block text-sm font-medium text-gray-700">Middle name</span>
            <input type="text" name="middle_name" value="{{ old('middle_name', $employee?->middle_name) }}"
                class="w-full rounded-md border-gray-300 text-sm">
        </label>

        <label class="block sm:col-span-1">
            <span class="mb-1 block text-sm font-medium text-gray-700">Last name</span>
            <input type="text" name="last_name" value="{{ old('last_name', $employee?->last_name) }}" required
                class="w-full rounded-md border-gray-300 text-sm">
        </label>

        <label class="block sm:col-span-1">
            <span class="mb-1 block text-sm font-medium text-gray-700">Suffix</span>
            <input type="text" name="suffix" value="{{ old('suffix', $employee?->suffix) }}" maxlength="20"
                placeholder="Jr." class="w-full rounded-md border-gray-300 text-sm">
        </label>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700">Employee number</span>
            <input type="text" name="employee_number"
                value="{{ old('employee_number', $employee?->employee_number) }}" required maxlength="50"
                placeholder="DTRC-1001" class="w-full rounded-md border-gray-300 font-data text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700">
                Email
                <span class="font-normal text-gray-500">— creates the login</span>
            </span>
            <input type="email" name="email" value="{{ old('email', $employee?->user?->email) }}"
                class="w-full rounded-md border-gray-300 text-sm">
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

    <div class="grid gap-4 sm:grid-cols-3">
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700">Position</span>
            <select name="position_id" class="w-full rounded-md border-gray-300 text-sm">
                <option value="">— None —</option>
                @foreach ($positions as $position)
                    <option value="{{ $position->id }}" @selected(old('position_id', $employee?->position_id) == $position->id)>
                        {{ $position->title }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700">Division</span>
            <select name="division_id" class="w-full rounded-md border-gray-300 text-sm">
                <option value="">— None —</option>
                @foreach ($divisions as $division)
                    <option value="{{ $division->id }}" @selected(old('division_id', $employee?->division_id) == $division->id)>
                        {{ $division->name }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700">Section</span>
            <select name="section_id" class="w-full rounded-md border-gray-300 text-sm">
                <option value="">— None —</option>
                @foreach ($sections as $section)
                    <option value="{{ $section->id }}" @selected(old('section_id', $employee?->section_id) == $section->id)>
                        {{ $section->name }}{{ $section->division ? ' (' . $section->division->name . ')' : '' }}
                    </option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700">Employment status</span>
            <select name="employment_status" required class="w-full rounded-md border-gray-300 text-sm">
                @foreach (StoreEmployeeRequest::EMPLOYMENT_STATUSES as $status)
                    <option value="{{ $status }}" @selected(old('employment_status', $employee?->employment_status ?? 'permanent') === $status)>
                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700">Date hired</span>
            <input type="date" name="date_hired"
                value="{{ old('date_hired', $employee?->date_hired?->format('Y-m-d')) }}"
                class="w-full rounded-md border-gray-300 text-sm">
        </label>
    </div>

    {{-- There is exactly one. Ticking this demotes whoever holds it now. --}}
    <label class="flex items-start gap-3 rounded-md bg-gray-50 p-3 ring-1 ring-inset ring-gray-200">
        <input type="hidden" name="is_chief_of_hospital" value="0">
        <input type="checkbox" name="is_chief_of_hospital" value="1"
            @checked(old('is_chief_of_hospital', $employee?->is_chief_of_hospital)) class="mt-0.5 rounded border-gray-300 text-nav-900 focus:ring-nav-700">
        <span class="text-sm">
            <span class="block font-medium text-gray-900">Chief of Hospital</span>
            <span class="block text-gray-600">
                Approves every Division Head's IPCR. Only one employee holds this — ticking it removes it from
                whoever holds it now.
            </span>
        </span>
    </label>
</div>
