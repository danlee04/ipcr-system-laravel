@props(['designation' => null, 'divisions', 'sections'])

@php
    $division = old('division_id', $designation?->division_id ?? $designation?->section?->division_id);
    $section = old('section_id', $designation?->section_id);
@endphp

{{--
    Where a designation posts whoever holds it.

    A designation used to be a title and nothing else, so somebody made OIC of
    a unit stayed, as far as the system could tell, in the section of their
    plantilla position - their IPCR went to a section head with no sight of the
    work, and the division actually running them never saw their name.

    Both may be left blank. Plenty of designations are a title and no more.
--}}
<fieldset class="space-y-3 rounded-md bg-gray-50 p-3 ring-1 ring-inset ring-gray-200"
    x-data="{ division: '{{ $division }}', section: '{{ $section }}' }">
    <legend class="text-sm font-medium text-gray-700">Where it posts them</legend>

    <p class="text-xs text-gray-500">
        Whoever holds this answers to the office named here for as long as they hold it, whatever their
        plantilla position says. Leave both blank for a title that moves nobody.
    </p>

    <div class="grid gap-3 sm:grid-cols-2">
        <label class="block">
            <span class="mb-1 block text-xs font-medium text-gray-600">Division</span>
            <select name="division_id" x-model="division" x-on:change="section = ''"
                class="w-full rounded-md border-gray-300 text-sm">
                <option value="">Nowhere in particular</option>
                @foreach ($divisions as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                @endforeach
            </select>
        </label>

        {{-- Narrowing clears what sat below it: a section from another
             division would stay selected, hidden but still submitted. --}}
        <label class="block">
            <span class="mb-1 block text-xs font-medium text-gray-600">Section</span>
            <select name="section_id" x-model="section" class="w-full rounded-md border-gray-300 text-sm">
                <option value="">The division itself</option>
                @foreach ($sections as $option)
                    <option value="{{ $option->id }}"
                        x-show="division === '' || division === '{{ $option->division_id }}'">
                        {{ $option->name }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <p class="text-xs text-gray-500">
        An officer-in-charge usually names the division and leaves the section blank: they run a unit rather
        than sit in one, so their IPCR goes straight to the division head.
    </p>
</fieldset>
