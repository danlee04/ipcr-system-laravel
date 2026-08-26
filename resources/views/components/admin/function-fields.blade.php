@props(['function' => null, 'positions', 'designations', 'divisions' => null, 'sections' => null])

@php
    use App\Enums\FunctionCategory;

    $current = old('category', $function?->category?->value ?? FunctionCategory::Core->value);

    // Where the position being edited sits, so the two narrowing selects open
    // already pointing at it instead of resetting to "all".
    $currentSection = $function?->position?->section_id ?? '';
    $currentDivision = $function?->position?->section?->division_id ?? '';

    // A core function reaches people through one of two routes. Which one is
    // in use is read off the record rather than stored: whichever link it
    // carries is the route it took.
    $coreVia = old('core_via', $function?->designation_id && $function?->category === FunctionCategory::Core
        ? 'designation'
        : 'position');
@endphp

{{-- Shared by the create and edit modals so the two forms cannot drift apart.
     Which link applies depends on the category, so the three link fields are
     shown and hidden by Alpine rather than all at once. --}}
<div class="space-y-4" x-data="{ category: '{{ $current }}' }">
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700">Category</span>
            <select name="category" x-model="category" required class="w-full rounded-md border-gray-300 text-sm">
                @foreach (FunctionCategory::cases() as $category)
                    <option value="{{ $category->value }}" @selected($current === $category->value)>
                        {{ $category->label() }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700">
                Suggested weight
                <span class="font-normal text-gray-500">— optional</span>
            </span>
            <input type="number" step="0.01" min="0" max="100" name="default_weight"
                value="{{ old('default_weight', $function?->default_weight) }}"
                class="w-full rounded-md border-gray-300 font-data text-sm">
        </label>
    </div>

    {{-- Core functions take one of two routes to an employee: their plantilla
         position, or a designation they hold. A designation is not a category
         of work - an Infection Control Officer has core duties as one - so
         forcing those into "support" would file them under the wrong heading
         and weight them at 20% instead of 80%. --}}
    <div x-show="category === 'core'" x-cloak
        x-data="{ via: '{{ $coreVia }}', division: '{{ $currentDivision }}', section: '{{ $currentSection }}' }"
        class="space-y-4">

        <fieldset class="flex flex-wrap gap-4 rounded-md bg-gray-50 p-3 ring-1 ring-inset ring-gray-200">
            <legend class="sr-only">How this core function reaches people</legend>

            <label class="flex items-center gap-2 text-sm">
                <input type="radio" name="core_via" value="position" x-model="via">
                <span>Through a <strong>position</strong></span>
            </label>

            <label class="flex items-center gap-2 text-sm">
                <input type="radio" name="core_via" value="designation" x-model="via">
                <span>Through a <strong>designation</strong></span>
            </label>
        </fieldset>

        {{-- Disabled, not merely hidden. A hidden field is still submitted,
             and there is a second designation_id in the strategic/support
             branch below: two selects of the same name would fight, and the
             loser's value would silently win. --}}
        <label class="block" x-show="via === 'designation'" x-cloak>
            <span class="mb-1 block text-sm font-medium text-gray-700">Designation</span>
            <select name="designation_id" class="w-full rounded-md border-gray-300 text-sm"
                :disabled="category !== 'core' || via !== 'designation'">
                <option value="">— Choose a designation —</option>
                @foreach ($designations as $designation)
                    <option value="{{ $designation->id }}" @selected(old('designation_id', $function?->designation_id) == $designation->id)>
                        {{ $designation->title }}
                    </option>
                @endforeach
            </select>
            <span class="mt-1 block text-xs text-gray-500">Everyone currently holding this designation will see it as
                a core function.</span>
        </label>

        <div x-show="via === 'position'" x-cloak class="space-y-4">
        @if ($divisions && $sections)
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Division</span>
                    <select x-model="division" x-on:change="section = ''"
                        class="w-full rounded-md border-gray-300 text-sm">
                        <option value="">All divisions</option>
                        @foreach ($divisions as $division)
                            <option value="{{ $division->id }}">{{ $division->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Section</span>
                    <select x-model="section" class="w-full rounded-md border-gray-300 text-sm">
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
            <select name="position_id" class="w-full rounded-md border-gray-300 text-sm"
                :disabled="category !== 'core' || via !== 'position'">
                <option value="">— Choose a position —</option>
                @foreach ($positions as $position)
                    <option value="{{ $position->id }}" data-section="{{ $position->section_id }}"
                        data-division="{{ $position->section?->division_id }}"
                        x-show="(section === '' || section === '{{ $position->section_id }}')
                            && (division === '' || division === '{{ $position->section?->division_id }}')"
                        @selected(old('position_id', $function?->position_id) == $position->id)>
                        {{ $position->title }}@if ($position->section)
                            — {{ $position->section->name }}
                        @endif
                    </option>
                @endforeach
            </select>
            <span class="mt-1 block text-xs text-gray-500">Everyone holding this position will see this
                function.</span>
        </label>
        </div>
    </div>

    {{-- Strategic and support reach them through their designations. --}}
    <label class="block" x-show="category === 'strategic' || category === 'support'" x-cloak>
        <span class="mb-1 block text-sm font-medium text-gray-700">Designation</span>
        <select name="designation_id" class="w-full rounded-md border-gray-300 text-sm"
            :disabled="category !== 'strategic' && category !== 'support'">
            <option value="">— Choose a designation —</option>
            @foreach ($designations as $designation)
                <option value="{{ $designation->id }}" @selected(old('designation_id', $function?->designation_id) == $designation->id)>
                    {{ $designation->title }}
                </option>
            @endforeach
        </select>
        <span class="mt-1 block text-xs text-gray-500">Everyone currently holding this designation will see
            it.</span>
    </label>

    {{-- Common is a pool, not a rating bucket, so it needs to say which of the
         three rated categories a line built from it counts towards. --}}
    <label class="block" x-show="category === 'common'" x-cloak>
        <span class="mb-1 block text-sm font-medium text-gray-700">Counts towards</span>
        <select name="rating_category" class="w-full rounded-md border-gray-300 text-sm"
            :disabled="category !== 'common'">
            <option value="">— Not set —</option>
            @foreach ([FunctionCategory::Strategic, FunctionCategory::Core, FunctionCategory::Support] as $rated)
                <option value="{{ $rated->value }}" @selected(old('rating_category', $function?->rating_category?->value) === $rated->value)>
                    {{ $rated->label() }}
                </option>
            @endforeach
        </select>
        <span class="mt-1 block text-xs text-gray-500">
            Open to everyone, but the rating only knows Strategic, Core and Support. Until this is set, nobody can
            add the function to an IPCR.
        </span>
    </label>

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
</div>
