@props([
    'selected' => null,
])

{{--
    The choice between "targets only" and "with accomplishments".

    Only one place uses this now: the "Start a new IPCR" modal on the list. It
    stays a separate component to keep the index view readable - the markup for
    these two cards is bulky.

    NOTE: this is the LAST chance to pick the mode. There is no switcher on the
    show page, and the create page is always Targets only. That is why the
    warning below is blunt - do not remove it without bringing back a way to
    change the mode.

    Each card shows a real radio dot. That matters: the selected card used to be
    marked by border colour alone, which is not a strong enough signal - and if
    the browser is holding a stale CSS bundle, literally nothing changes when
    you click. The dot is not decoration; it is what says "there is a choice
    here."
--}}
@php
    $selected = old('mode', $selected ?? \App\Enums\IpcrMode::TargetsOnly->value);
@endphp

<fieldset {{ $attributes }}>
    <legend class="text-sm font-semibold text-gray-900">What are you filling in right now?</legend>
    <p class="mt-1 text-sm text-gray-600">
        Choose carefully — this is set when the IPCR is created and cannot be changed afterwards.
    </p>

    <div class="mt-3 grid gap-3 sm:grid-cols-2">
        @foreach (\App\Enums\IpcrMode::cases() as $case)
            <label
                class="group relative flex cursor-pointer items-start gap-3 rounded-lg border-2 border-gray-200 bg-white p-4 transition-colors hover:border-gray-300 hover:bg-gray-50 has-checked:border-nav-900 has-checked:bg-nav-900/5 has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-seal">
                <input type="radio" name="mode" value="{{ $case->value }}" class="sr-only"
                    @checked($selected === $case->value) required>

                {{-- The visible radio, driven by the checked state of the input above. --}}
                <span aria-hidden="true"
                    class="mt-0.5 grid h-4.5 w-4.5 shrink-0 place-items-center rounded-full border-2 border-gray-400 bg-white transition-colors group-has-checked:border-nav-900">
                    <span
                        class="h-2 w-2 rounded-full bg-transparent transition-colors group-has-checked:bg-nav-900"></span>
                </span>

                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-gray-900">{{ $case->label() }}</span>
                    <span class="mt-1 block text-xs text-gray-600">{{ $case->description() }}</span>
                </span>
            </label>
        @endforeach
    </div>

    @error('mode')
        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror
</fieldset>
