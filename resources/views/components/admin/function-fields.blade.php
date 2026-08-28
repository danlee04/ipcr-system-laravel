@props(['function' => null, 'positions', 'designations', 'divisions' => null, 'sections' => null])

@php
    use App\Enums\FunctionCategory;

    $current = old('category', $function?->category?->value ?? FunctionCategory::Core->value);

    // Where the position being edited sits, so the two narrowing selects open
    // already pointing at it instead of resetting to "all".
    $currentSection = $function?->position?->section_id ?? '';
    $currentDivision = $function?->position?->section?->division_id ?? '';

    // Which of the three routes this function takes. Not stored anywhere: the
    // link it carries is the route it took, so it is read back off the record.
    // A saved function carrying neither link reaches everyone.
    $appliesTo = old('applies_to', match (true) {
        $function?->designation_id !== null => 'designation',
        $function?->position_id !== null    => 'position',
        $function !== null                  => 'everyone',
        default                             => 'position',
    });
@endphp

{{-- Shared by the create and edit modals so the two forms cannot drift apart.

     Two questions: what kind of work this is, and who it reaches. The link
     fields are shown and hidden by Alpine rather than all at once. --}}
<div class="space-y-4">
    {{-- No weight here. The category split is worked out from what an
         employee actually holds, and the weight of a line inside a category
         fills itself in from what that category has not spent - so a number
         typed on the catalog entry would only ever be overruled. --}}
    <label class="block sm:max-w-xs">
        <span class="mb-1 block text-sm font-medium text-gray-700">Category</span>
        {{-- No x-model. There is no Alpine scope here holding a category -
             there was one when this select decided which link fields showed,
             and that question moved out. What was left bound the value to
             nothing, so Alpine emptied the select on load and the browser fell
             back to the first option: every function looked Strategic, new or
             saved. The server picks it, as it does everywhere else. --}}
        <select name="category" required class="w-full rounded-md border-gray-300 text-sm">
            @foreach (FunctionCategory::cases() as $category)
                <option value="{{ $category->value }}" @selected($current === $category->value)>
                    {{ $category->label() }}
                </option>
            @endforeach
        </select>
    </label>

    {{-- Who the function reaches. A separate question from what kind of work
         it is: a Section Head's strategic commitments belong to their post, an
         OIC's core duties to their designation, and the flag ceremony to
         everybody - all three of those are still Core, Support or Strategic. --}}
    <div x-data="{
        via: '{{ $appliesTo }}',
        division: '{{ $currentDivision }}',
        section: '{{ $currentSection }}',
        position: '{{ old('position_id', $function?->position_id) }}',
    }"
        class="space-y-4">

        <fieldset class="flex flex-wrap gap-4 rounded-md bg-gray-50 p-3 ring-1 ring-inset ring-gray-200">
            <legend class="mb-2 w-full text-sm font-medium text-gray-700">Applies to</legend>

            <label class="flex items-center gap-2 text-sm">
                <input type="radio" name="applies_to" value="everyone" x-model="via">
                <span><strong>Everyone</strong></span>
            </label>

            <label class="flex items-center gap-2 text-sm">
                <input type="radio" name="applies_to" value="position" x-model="via">
                <span>Whoever holds a <strong>position</strong></span>
            </label>

            <label class="flex items-center gap-2 text-sm">
                <input type="radio" name="applies_to" value="designation" x-model="via">
                <span>Whoever holds a <strong>designation</strong></span>
            </label>
        </fieldset>

        {{-- Disabled, not merely hidden. A hidden field is still submitted, so
             an inactive branch would send a stale link and quietly win. --}}
        <label class="block" x-show="via === 'designation'" x-cloak>
            <span class="mb-1 block text-sm font-medium text-gray-700">Designation</span>
            <select name="designation_id" class="w-full rounded-md border-gray-300 text-sm"
                :disabled="via !== 'designation'">
                <option value="">— Choose a designation —</option>
                @foreach ($designations as $designation)
                    <option value="{{ $designation->id }}" @selected(old('designation_id', $function?->designation_id) == $designation->id)>
                        {{ $designation->title }}
                    </option>
                @endforeach
            </select>
        </label>

        <div x-show="via === 'position'" x-cloak class="space-y-4">
        @if ($divisions && $sections)
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Division</span>
                    <select x-model="division" x-on:change="section = ''; position = ''"
                        class="w-full rounded-md border-gray-300 text-sm">
                        <option value="">All divisions</option>
                        @foreach ($divisions as $division)
                            <option value="{{ $division->id }}">{{ $division->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Section</span>
                    <select x-model="section" x-on:change="position = ''"
                        class="w-full rounded-md border-gray-300 text-sm">
                        <option value="">All sections</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}" data-division="{{ $section->division_id }}"
                                x-show="division === '' || division === '{{ $section->division_id }}'">
                                {{ $section->name }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>
        @endif

        <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700">Position</span>
            <select name="position_id" x-model="position" class="w-full rounded-md border-gray-300 text-sm"
                :disabled="via !== 'position'"
                x-on:change="
                    const chosen = $event.target.selectedOptions[0];
                    division = chosen?.dataset.division ?? '';
                    section = chosen?.dataset.section ?? '';
                ">
                <option value="">— Choose a position —</option>
                @foreach ($positions as $position)
                    <option value="{{ $position->id }}" data-section="{{ $position->section_id }}"
                        data-division="{{ $position->section?->division_id }}"
                        x-show="(section === '' || section === '{{ $position->section_id }}')
                            && (division === '' || division === '{{ $position->section?->division_id }}')">
                        {{ $position->title }}@if ($position->section)
                            — {{ $position->section->name }}
                        @endif
                    </option>
                @endforeach
            </select>
        </label>
        </div>
    </div>

    <label class="block">
        <span class="mb-1 block text-sm font-medium text-gray-700">Output / objective</span>
        <textarea name="title" rows="2" required class="w-full rounded-md border-gray-300 text-sm">{{ old('title', $function?->title) }}</textarea>
    </label>

    <label class="block">
        <span class="mb-1 block text-sm font-medium text-gray-700">
            Success indicator
            <span class="font-normal text-gray-500">— target and measure</span>
        </span>
        <textarea name="success_indicator" rows="2" class="w-full rounded-md border-gray-300 text-sm">{{ old('success_indicator', $function?->success_indicator) }}</textarea>
    </label>

    <div class="border-t border-gray-200 pt-4">
        <x-admin.function-rubric :function="$function" />
    </div>
</div>
