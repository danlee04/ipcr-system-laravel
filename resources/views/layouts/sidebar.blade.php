@php
    $user = Auth::user();
    $employee = $user?->employee;
    $displayName = $employee?->full_name ?: $user?->name;

    // Waiting on this user across both approval stages. Zero for anyone who
    // is not an assessor or final approver, which is most people.
    $pendingApprovals = $employee
        ? \App\Models\Ipcr::query()->awaitingAssessmentBy($employee)->count() +
            \App\Models\Ipcr::query()->awaitingFinalRatingBy($employee)->count()
        : 0;

    // Whether they are an approver at all. Kept separate from the count so the
    // link survives an empty queue - it is how they get back to the inbox.
    $isApprover = $employee ? \App\Models\Ipcr::query()->routedTo($employee)->exists() : false;
    $initials = collect(preg_split('/\s+/', trim((string) $displayName)))
        ->filter()
        ->take(2)
        ->map(fn(string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

{{--
    The sidebar. One piece of markup serving two shapes:
      - lg and up: fixed to the left, collapsible to icons only
      - below lg: an off-canvas drawer driven by `drawerOpen`

    `collapsed` is always scoped to `lg:` - on a phone the drawer is always full
    width with full labels, even when the desktop view is collapsed.
--}}
<aside id="app-sidebar"
    class="fixed inset-y-0 inset-s-0 z-40 flex w-64 -translate-x-full flex-col bg-nav-900 text-nav-100 transition-[width,transform] duration-200 ease-out lg:translate-x-0"
    :class="[
        drawerOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full',
        collapsed ? 'lg:w-18' : 'lg:w-64',
    ]">
    {{-- Brand --}}
    <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-4">
        <a href="{{ route('dashboard') }}"
            class="flex min-w-0 items-center gap-3 rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-seal focus-visible:ring-offset-2 focus-visible:ring-offset-nav-900">
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

        {{-- Close the drawer - phones only. --}}
        <button type="button" @click="closeDrawer()"
            class="ms-auto grid h-11 w-11 shrink-0 place-items-center rounded-md text-nav-300 transition-colors hover:bg-nav-800 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-seal lg:hidden">
            <span class="sr-only">Close menu</span>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Main">
        <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
            <x-slot:icon>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3.5 10.5 12 4l8.5 6.5V19a1.5 1.5 0 0 1-1.5 1.5h-3.5V14h-7v6.5H5A1.5 1.5 0 0 1 3.5 19v-8.5Z" />
                </svg>
            </x-slot:icon>
            Dashboard
        </x-sidebar-link>

        {{-- Only for people who have an Employee record. IpcrController aborts
             403 without one, so for an account like the system administrator
             this link would just be an invitation to an error page. --}}
        @if ($employee)
            <x-sidebar-link :href="route('ipcrs.index')" :active="request()->routeIs('ipcrs.*')">
                <x-slot:icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M7 3.5h7.5L19 8v12.5H7A1.5 1.5 0 0 1 5.5 19V5A1.5 1.5 0 0 1 7 3.5Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 3.5V8h5M9 12.5h6M9 16h4" />
                    </svg>
                </x-slot:icon>
                My IPCRs
            </x-sidebar-link>
        @endif

        {{-- Shown to anyone ever routed an IPCR, not only when something is
             waiting. The badge is what carries the count. --}}
        @if ($isApprover)
            <x-sidebar-link :href="route('approvals.inbox')" :active="request()->routeIs('approvals.*')">
                <x-slot:icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.5 9 17l10.5-10.5" />
                    </svg>
                </x-slot:icon>
                For My Approval
                @if ($pendingApprovals > 0)
                    <x-slot:badge>{{ $pendingApprovals }}</x-slot:badge>
                @endif
            </x-sidebar-link>
        @endif

        @if ($user?->hasAnyRole(['admin', 'hr']))
            {{-- Administration. Hidden entirely from everyone else: the routes
                 return 403 anyway, but there is no reason to advertise them.
                 HR is included because HR does the same setup work as an
                 administrator - see the admin route group in routes/web.php. --}}
            <p class="px-3 pb-1 pt-5 font-data text-[0.625rem] uppercase tracking-[0.18em] text-nav-300"
                :class="collapsed ? 'lg:hidden' : ''">
                Administration
            </p>

            <x-sidebar-link :href="route('admin.ipcrs.index')" :active="request()->routeIs('admin.ipcrs.*')">
                <x-slot:icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M4 5.5h16M4 12h16M4 18.5h10" />
                    </svg>
                </x-slot:icon>
                All IPCRs
            </x-sidebar-link>

            <x-sidebar-link :href="route('admin.divisions.index')" :active="request()->routeIs('admin.divisions.*')">
                <x-slot:icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 4v4m0 0H7.5A1.5 1.5 0 0 0 6 9.5V12m6-4h4.5A1.5 1.5 0 0 1 18 9.5V12M4 15h4v5H4v-5Zm6 0h4v5h-4v-5Zm6 0h4v5h-4v-5Z" />
                    </svg>
                </x-slot:icon>
                Divisions
            </x-sidebar-link>

            <x-sidebar-link :href="route('admin.job-titles.index')" :active="request()->routeIs('admin.job-titles.*')">
                <x-slot:icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 6.5V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5v1M4.5 6.5h15A1.5 1.5 0 0 1 21 8v10a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18V8a1.5 1.5 0 0 1 1.5-1.5ZM3 12h18" />
                    </svg>
                </x-slot:icon>
                Job Titles
            </x-sidebar-link>

            <x-sidebar-link :href="route('admin.employees.index')" :active="request()->routeIs('admin.employees.*')">
                <x-slot:icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 11a3.25 3.25 0 1 0 0-6.5A3.25 3.25 0 0 0 9 11Zm7.5.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM3 19.5a6 6 0 0 1 12 0M16 14a5 5 0 0 1 5 5.5" />
                    </svg>
                </x-slot:icon>
                Employees
            </x-sidebar-link>

            <x-sidebar-link :href="route('admin.periods.index')" :active="request()->routeIs('admin.periods.*')">
                <x-slot:icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M7 3.5V6m10-2.5V6M4.5 8.5h15M6 6h12a1.5 1.5 0 0 1 1.5 1.5V19A1.5 1.5 0 0 1 18 20.5H6A1.5 1.5 0 0 1 4.5 19V7.5A1.5 1.5 0 0 1 6 6Z" />
                    </svg>
                </x-slot:icon>
                Rating Periods
            </x-sidebar-link>

            <x-sidebar-link :href="route('admin.job-functions.index')" :active="request()->routeIs('admin.job-functions.*')">
                <x-slot:icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 5.5h14M5 10h14M5 14.5h9M5 19h6" />
                    </svg>
                </x-slot:icon>
                Job Functions
            </x-sidebar-link>
        @endif
    </nav>

    {{-- Who is signed in --}}
    <div class="shrink-0 border-t border-white/10 p-3">
        <div class="flex items-center gap-3 rounded-md px-1 py-2">
            <span
                class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-nav-700 font-data text-xs font-medium text-white ring-1 ring-white/10">
                {{ $initials !== '' ? $initials : '?' }}
            </span>
            <span class="min-w-0 flex-1" :class="collapsed ? 'lg:hidden' : ''">
                <span class="block truncate text-sm font-medium text-white">{{ $displayName }}</span>
                @if ($employee?->employee_number)
                    <span
                        class="block truncate font-data text-[0.6875rem] tracking-wide text-nav-300">{{ $employee->employee_number }}</span>
                @else
                    <span class="block truncate text-[0.6875rem] text-nav-300">{{ $user?->email }}</span>
                @endif
            </span>
        </div>

        <div class="mt-1 space-y-1">
            <x-sidebar-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')">
                <x-slot:icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 12a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5ZM4.75 20a7.25 7.25 0 0 1 14.5 0" />
                    </svg>
                </x-slot:icon>
                Profile
            </x-sidebar-link>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="group relative flex min-h-11 w-full items-center gap-3 rounded-md py-2 pe-2 ps-3 text-sm text-nav-300 transition-colors hover:bg-nav-800/60 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-seal focus-visible:ring-offset-2 focus-visible:ring-offset-nav-900">
                    <span class="shrink-0" aria-hidden="true">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 8.5V6.5A1.5 1.5 0 0 0 13.5 5h-7A1.5 1.5 0 0 0 5 6.5v11A1.5 1.5 0 0 0 6.5 19h7a1.5 1.5 0 0 0 1.5-1.5v-2M11 12h9m0 0-3-3m3 3-3 3" />
                        </svg>
                    </span>
                    <span class="truncate" :class="collapsed ? 'lg:hidden' : ''">Log out</span>
                    <span role="tooltip"
                        class="pointer-events-none absolute inset-s-full z-50 ms-2 hidden whitespace-nowrap rounded-md bg-nav-800 px-2 py-1 text-xs font-medium text-white shadow-lg ring-1 ring-white/10"
                        :class="collapsed ? 'lg:group-hover:block' : ''">Log out</span>
                </button>
            </form>
        </div>

        {{-- Collapse toggle - desktop only. --}}
        <button type="button" @click="toggleCollapsed()"
            class="mt-2 hidden min-h-11 w-full items-center gap-3 rounded-md py-2 pe-2 ps-3 text-sm text-nav-300 transition-colors hover:bg-nav-800/60 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-seal focus-visible:ring-offset-2 focus-visible:ring-offset-nav-900 lg:flex"
            :aria-expanded="(!collapsed).toString()" aria-controls="app-sidebar">
            <span class="shrink-0" aria-hidden="true">
                <svg class="h-5 w-5 transition-transform duration-200" :class="collapsed ? 'rotate-180' : ''"
                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 8l-4 4 4 4" />
                </svg>
            </span>
            <span class="truncate" :class="collapsed ? 'lg:hidden' : ''">Collapse</span>
        </button>
    </div>
</aside>
