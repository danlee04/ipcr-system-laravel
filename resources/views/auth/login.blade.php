<x-guest-layout>
    {{-- The badge repeats the agency for anyone who never sees the left panel:
         on a phone it is hidden, and this is the only thing saying whose
         system this is. --}}
    <div class="flex items-center gap-3 rounded-xl bg-gray-50 p-3 ring-1 ring-inset ring-gray-200">
        <img src="{{ asset('images/dtrc-logo.png') }}" alt=""
            class="h-10 w-10 shrink-0 rounded-full bg-white object-contain p-0.5 ring-1 ring-gray-200">
        <span class="min-w-0 text-xs font-semibold leading-snug text-gray-800">
            {{ config('agency.name') }}
        </span>
    </div>

    <h1 class="mt-7 text-2xl font-semibold tracking-tight text-gray-900">Welcome back</h1>
    <p class="mt-1 text-sm text-gray-500">Sign in to your IPCR account to continue.</p>

    <x-auth-session-status class="mt-5" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-7 space-y-5">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">
                Email address <span class="text-red-600">*</span>
            </label>

            <div class="relative mt-1.5">
                <span class="pointer-events-none absolute inset-y-0 inset-s-0 grid w-10 place-items-center text-gray-400"
                    aria-hidden="true">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </span>

                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                    autocomplete="username" placeholder="you@example.com"
                    class="block w-full rounded-md border-gray-300 ps-10 shadow-xs focus:border-brand-500 focus:ring-brand-500">
            </div>

            <x-input-error class="mt-2" :messages="$errors->get('email')" />
        </div>

        <div>
            <div class="flex items-baseline justify-between gap-3">
                <label for="password" class="block text-sm font-medium text-gray-700">
                    Password <span class="text-red-600">*</span>
                </label>

                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}"
                        class="text-xs font-medium text-gray-500 underline underline-offset-2 hover:text-gray-900">
                        Forgot it?
                    </a>
                @endif
            </div>

            {{-- The same field as the profile screen, eye and all. A password
                 typed blind on a shared machine is how people lock themselves
                 out of an account they have only just been given. --}}
            <x-password-input name="password" class="mt-1.5 ps-10" placeholder="••••••••" required>
                <x-slot:icon>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" />
                        <path stroke-linecap="round" d="M7 11V7a5 5 0 0110 0v4" />
                    </svg>
                </x-slot:icon>
            </x-password-input>

            <x-input-error class="mt-2" :messages="$errors->get('password')" />
        </div>

        <label class="flex items-center gap-2">
            <input id="remember_me" type="checkbox" name="remember"
                class="rounded border-gray-300 text-nav-900 focus:ring-brand-500">
            <span class="text-sm text-gray-600">Remember me</span>
        </label>

        <button type="submit"
            class="flex w-full items-center justify-center gap-2 rounded-md bg-nav-900 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-nav-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
            </svg>
            Sign in to IPCR System
        </button>
    </form>

    {{-- There is no way to make an account here: HR creates every employee,
         and saying so beats a sign-up link that leads nowhere. --}}
    <p class="mt-8 border-t border-gray-100 pt-5 text-[0.6875rem] leading-relaxed text-gray-500">
        Accounts are created by HR along with your employee record.<br>
        &copy; {{ now()->year }} {{ config('agency.short_name') }} &mdash; Department of Health, Philippines
    </p>
</x-guest-layout>
