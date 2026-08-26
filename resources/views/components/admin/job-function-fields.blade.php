@props(['function' => null, 'positions', 'designations'])

@php
    use App\Enums\FunctionCategory;

    $current = old('category', $function?->category?->value ?? FunctionCategory::Core->value);
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

    {{-- Core functions reach an employee through their plantilla position. --}}
    <label class="block" x-show="category === 'core'" x-cloak>
        <span class="mb-1 block text-sm font-medium text-gray-700">Position</span>
        <select name="position_id" class="w-full rounded-md border-gray-300 text-sm">
            <option value="">— Choose a position —</option>
            @foreach ($positions as $position)
                <option value="{{ $position->id }}" @selected(old('position_id', $function?->position_id) == $position->id)>
                    {{ $position->title }}
                </option>
            @endforeach
        </select>
        <span class="mt-1 block text-xs text-gray-500">Everyone holding this position will see this function.</span>
    </label>

    {{-- Strategic and support reach them through their designations. --}}
    <label class="block" x-show="category === 'strategic' || category === 'support'" x-cloak>
        <span class="mb-1 block text-sm font-medium text-gray-700">Designation</span>
        <select name="designation_id" class="w-full rounded-md border-gray-300 text-sm">
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
        <select name="rating_category" class="w-full rounded-md border-gray-300 text-sm">
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
