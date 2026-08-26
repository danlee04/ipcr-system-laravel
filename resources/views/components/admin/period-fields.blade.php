@props(['period' => null])

@php
    use App\Enums\IpcrPeriodType;
@endphp

{{-- Shared by the create and edit modals so the two forms cannot drift apart.
     $period is null when creating. --}}
<div class="space-y-4">
    <div class="grid gap-4 sm:grid-cols-3">
        <label class="block sm:col-span-2">
            <span class="mb-1 block text-sm font-medium text-gray-700">Name</span>
            <input type="text" name="name" value="{{ old('name', $period?->name) }}" required
                placeholder="January - June {{ now()->year }}" class="w-full rounded-md border-gray-300 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700">Year</span>
            <input type="number" name="year" value="{{ old('year', $period?->year ?? now()->year) }}" required
                min="2000" max="2100" class="w-full rounded-md border-gray-300 font-data text-sm">
        </label>
    </div>

    <label class="block">
        <span class="mb-1 block text-sm font-medium text-gray-700">Type</span>
        <select name="type" required class="w-full rounded-md border-gray-300 text-sm">
            @foreach (IpcrPeriodType::cases() as $type)
                <option value="{{ $type->value }}" @selected(old('type', $period?->type) === $type->value)>
                    {{ $type->label() }}
                </option>
            @endforeach
        </select>
        <span class="mt-1 block text-xs text-gray-500">One period of each type per year.</span>
    </label>

    <div class="grid gap-4 sm:grid-cols-3">
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700">Starts</span>
            <input type="date" name="start_date"
                value="{{ old('start_date', $period?->start_date?->format('Y-m-d')) }}" required
                class="w-full rounded-md border-gray-300 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700">Ends</span>
            <input type="date" name="end_date" value="{{ old('end_date', $period?->end_date?->format('Y-m-d')) }}"
                required class="w-full rounded-md border-gray-300 text-sm">
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700">
                Submission deadline
                <span class="font-normal text-gray-500">— optional</span>
            </span>
            <input type="date" name="submission_deadline"
                value="{{ old('submission_deadline', $period?->submission_deadline?->format('Y-m-d')) }}"
                class="w-full rounded-md border-gray-300 text-sm">
        </label>
    </div>
</div>
