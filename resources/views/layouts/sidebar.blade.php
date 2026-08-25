@php
    $user = Auth::user();
    $employee = $user?->employee;
    $displayName = $employee?->full_name ?: $user?->name;
    $initials = collect(preg_split('/\s+/', trim((string) $displayName)))
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

{{--
    Ang sidebar. Isang markup lang para sa dalawang anyo:
      - lg pataas: naka-fix sa kaliwa, pwedeng i-collapse sa icon-only
      - mababa sa lg: off-canvas drawer na hinihila ng `drawerOpen`

    Ang `collapsed` ay laging naka-scope sa `lg:` - sa cellphone ay buong
    lapad at buong label palagi ang drawer, kahit naka-collapse ang desktop.
--}}
<aside
    id="app-sidebar"
    class="fixed inset-y-0 start-0 z-40 flex w-64 -translate-x-full flex-col bg-nav-900 text-nav-100 transition-[width,transform] duration-200 ease-out lg:translate-x-0"
    :class="[
        drawerOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full',
        collapsed ? 'lg:w-[4.5rem]' : 'lg:w-64',
    ]"
>
    {{-- Brand --}}
    <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-4">
        <a
            href="{{ route('dashboard') }}"
            class="flex min-w-0 items-center gap-3 rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-seal focus-visible:ring-offset-2 focus-visible:ring-offset-nav-900"
        >
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-nav-800 ring-1 ring-white/10">
                <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M10 3h4v5h5v4h-5v5h-4v-5H5V8h5V3Z" />
                </svg>
            </span>
            <span class="min-w-0" :class="collapsed ? 'lg:hidden' : ''">
                <span class="block font-data text-[0.625rem] uppercase tracking-[0.18em] text-nav-300">DTRC</span>
                <span class="block truncate text-sm font-semibold text-white">IPCR System</span>
            </span>
        </a>

        {{-- Isara ang drawer - sa cellphone lang. --}}
        <button
            type="button"
            @click="closeDrawer()"
            class="ms-auto grid h-11 w-11 shrink-0 place-items-center rounded-md text-nav-300 transition-colors hover:bg-nav-800 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-seal lg:hidden"
        >
            <span class="sr-only">Close menu</span>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Main">
        <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
            <x-slot:icon>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.5 10.5 12 4l8.5 6.5V19a1.5 1.5 0 0 1-1.5 1.5h-3.5V14h-7v6.5H5A1.5 1.5 0 0 1 3.5 19v-8.5Z" />
                </svg>
            </x-slot:icon>
            Dashboard
        </x-sidebar-link>

        <x-sidebar-link :href="route('ipcrs.index')" :active="request()->routeIs('ipcrs.*')">
            <x-slot:icon>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 3.5h7.5L19 8v12.5H7A1.5 1.5 0 0 1 5.5 19V5A1.5 1.5 0 0 1 7 3.5Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 3.5V8h5M9 12.5h6M9 16h4" />
                </svg>
            </x-slot:icon>
            My IPCRs
        </x-sidebar-link>
    </nav>

    {{-- Sino ang naka-login --}}
    <div class="shrink-0 border-t border-white/10 p-3">
        <div class="flex items-center gap-3 rounded-md px-1 py-2">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-nav-700 font-data text-xs font-medium text-white ring-1 ring-white/10">
                {{ $initials !== '' ? $initials : '?' }}
            </span>
            <span class="min-w-0 flex-1" :class="collapsed ? 'lg:hidden' : ''">
                <span class="block truncate text-sm font-medium text-white">{{ $displayName }}</span>
                @if ($employee?->employee_number)
                    <span class="block truncate font-data text-[0.6875rem] tracking-wide text-nav-300">{{ $employee->employee_number }}</span>
                @else
                    <span class="block truncate text-[0.6875rem] text-nav-300">{{ $user?->email }}</span>
                @endif
            </span>
        </div>

        <div class="mt-1 space-y-1">
            <x-sidebar-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')">
                <x-slot:icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 12a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5ZM4.75 20a7.25 7.25 0 0 1 14.5 0" />
                    </svg>
                </x-slot:icon>
                Profile
            </x-sidebar-link>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    type="submit"
                    class="group relative flex min-h-11 w-full items-center gap-3 rounded-md py-2 pe-2 ps-3 text-sm text-nav-300 transition-colors hover:bg-nav-800/60 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-seal focus-visible:ring-offset-2 focus-visible:ring-offset-nav-900"
                >
                    <span class="shrink-0" aria-hidden="true">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 8.5V6.5A1.5 1.5 0 0 0 13.5 5h-7A1.5 1.5 0 0 0 5 6.5v11A1.5 1.5 0 0 0 6.5 19h7a1.5 1.5 0 0 0 1.5-1.5v-2M11 12h9m0 0-3-3m3 3-3 3" />
                        </svg>
                    </span>
                    <span class="truncate" :class="collapsed ? 'lg:hidden' : ''">Log out</span>
                    <span
                        role="tooltip"
                        class="pointer-events-none absolute start-full z-50 ms-2 hidden whitespace-nowrap rounded-md bg-nav-800 px-2 py-1 text-xs font-medium text-white shadow-lg ring-1 ring-white/10"
                        :class="collapsed ? 'lg:group-hover:block' : ''"
                    >Log out</span>
                </button>
            </form>
        </div>

        {{-- Collapse toggle - desktop lang. --}}
        <button
            type="button"
            @click="toggleCollapsed()"
            class="mt-2 hidden min-h-11 w-full items-center gap-3 rounded-md py-2 pe-2 ps-3 text-sm text-nav-300 transition-colors hover:bg-nav-800/60 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-seal focus-visible:ring-offset-2 focus-visible:ring-offset-nav-900 lg:flex"
            :aria-expanded="(! collapsed).toString()"
            aria-controls="app-sidebar"
        >
            <span class="shrink-0" aria-hidden="true">
                <svg class="h-5 w-5 transition-transform duration-200" :class="collapsed ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 8l-4 4 4 4" />
                </svg>
            </span>
            <span class="truncate" :class="collapsed ? 'lg:hidden' : ''">Collapse</span>
        </button>
    </div>
</aside>
