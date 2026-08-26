@props([
    'divisions',
    'sections',
    'selected' => null,
    'label' => 'Section',
    'hint' => null,
    'blank' => '— None (office-wide) —',
    'divisionName' => null,
    'name' => 'section_id',
])

@php
    $selectedSection = $sections->firstWhere('id', (int) $selected);
    $selectedDivision = $selectedSection?->division_id ?? '';
@endphp

{{-- Division narrows Section. The division is never submitted with a position
     or a function - it is reached through the section - so it is only sent
     when a caller asks for it by name, as the list filters do. --}}
<div class="contents" x-data="{ division: '{{ $selectedDivision }}' }">
    <label class="block">
        <span class="mb-1 block text-sm font-medium text-gray-700">Division</span>
        <select @if ($divisionName) name="{{ $divisionName }}" @endif x-model="division"
            class="w-full rounded-lg border-gray-300 text-sm">
            <option value="">All divisions</option>
            @foreach ($divisions as $division)
                <option value="{{ $division->id }}">{{ $division->name }}</option>
            @endforeach
        </select>
    </label>

    <label class="block">
        <span class="mb-1 block text-sm font-medium text-gray-700">{{ $label }}</span>
        <select name="{{ $name }}" class="w-full rounded-lg border-gray-300 text-sm">
            <option value="">{{ $blank }}</option>
            @foreach ($sections as $section)
                {{-- data-division is what the cascade filters on, and what the
                     tests assert: without it the two selects can describe a
                     combination that has no rows. --}}
                <option value="{{ $section->id }}" data-division="{{ $section->division_id }}"
                    x-show="division === '' || division === '{{ $section->division_id }}'"
                    @selected((int) $selected === $section->id)>
                    {{ $section->name }}
                </option>
            @endforeach
        </select>
        @if ($hint)
            <span class="mt-1 block text-xs text-gray-500">{{ $hint }}</span>
        @endif
    </label>
</div>
