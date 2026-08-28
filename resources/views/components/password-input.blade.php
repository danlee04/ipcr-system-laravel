@props(['name', 'autocomplete' => 'current-password'])

{{--
    A password field with an eye on it.

    Typing a password you cannot see is how people end up locked out of an
    account they just set the password on - and this one is typed three times
    in a row on the same screen.

    `type="password"` is on the element itself as well as bound: with no
    JavaScript the field is still a password field, and Alpine only takes over
    once it has booted.
--}}
<div class="relative" x-data="{ show: false }">
    <input
        {{ $attributes->merge([
            'id' => $name,
            'name' => $name,
            'type' => 'password',
            'autocomplete' => $autocomplete,
            'class' => 'block w-full rounded-md border-gray-300 pe-11 shadow-xs focus:border-brand-500 focus:ring-brand-500',
        ]) }}
        x-bind:type="show ? 'text' : 'password'">

    <button type="button" x-on:click="show = !show"
        class="absolute inset-y-0 end-0 grid w-11 place-items-center rounded-e-md text-gray-400 transition-colors hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
        <span class="sr-only" x-text="show ? 'Hide password' : 'Show password'">Show password</span>

        <svg x-show="!show" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" />
            <circle cx="12" cy="12" r="2.75" />
        </svg>

        <svg x-show="show" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M4 4l16 16M9.9 5.9A9.6 9.6 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a17 17 0 0 1-3.3 4.1M6.6 8A17 17 0 0 0 2.5 12S6 18.5 12 18.5a9.4 9.4 0 0 0 2.6-.36M10.2 10.3a2.75 2.75 0 0 0 3.6 3.9" />
        </svg>
    </button>
</div>
