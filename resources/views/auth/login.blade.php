<x-guest-layout>
    <h1 class="text-2xl font-semibold tracking-tight text-gray-900">Sign in</h1>
    <p class="mt-1 text-sm text-gray-500">Use the account HR set up for you.</p>

    <x-auth-session-status class="mt-6" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')"
                required autofocus autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />
        </div>

        <div>
            <div class="flex items-baseline justify-between gap-3">
                <x-input-label for="password" :value="__('Password')" />

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
            <x-password-input name="password" class="mt-1" required />
            <x-input-error class="mt-2" :messages="$errors->get('password')" />
        </div>

        <label class="flex items-center gap-2">
            <input id="remember_me" type="checkbox" name="remember"
                class="rounded border-gray-300 text-nav-900 focus:ring-brand-500">
            <span class="text-sm text-gray-600">{{ __('Keep me signed in') }}</span>
        </label>

        <button type="submit"
            class="w-full rounded-md bg-nav-900 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-nav-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
            Sign in
        </button>
    </form>

    {{-- There is no way to make an account here: HR creates every employee,
         and saying so beats a "Register" link that leads nowhere. --}}
    <p class="mt-8 border-t border-gray-100 pt-5 text-xs leading-relaxed text-gray-500">
        No account? Accounts are created by HR along with your employee record. Ask them to set one up.
    </p>
</x-guest-layout>
