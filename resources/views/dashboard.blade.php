@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $firstName = $employee?->first_name ?: strtok((string) auth()->user()->name, ' ');
@endphp

<x-app-layout>
    {{-- The greeting says who is here; the slip says what everyone is working
         against. The same slip is on the login page, so the deadline is the
         first thing seen on the way in and it is still there on landing. --}}
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-x-8 gap-y-4">
            {{-- For a head the page is about the unit rather than about them,
                 so the unit is what the masthead says. Everyone else is
                 greeted: there is no unit to name, and their own sheet is the
                 whole of what follows. --}}
            <div class="min-w-0">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ $unit ? $unit['name'] : "{$greeting}, {$firstName}" }}
                </h2>
                <p class="mt-0.5 font-data text-xs uppercase tracking-wider text-gray-400">
                    @if ($unit)
                        {{ $unit['kind'] }} IPCR overview &middot;
                    @endif
                    {{ now()->format('l, j F Y') }}
                </p>
            </div>

            <x-period-slip class="w-full border-t border-gray-200 pt-3 sm:w-64 sm:border-0 sm:pt-0" />
        </div>
    </x-slot>

    <x-page-container class="space-y-6">
        <x-admin.flash />

        {{-- Setup problems first. Each one blocks somebody from finishing an
             IPCR, and each fails late and quietly without this. --}}
        @if ($admin && $admin['problems'] !== [])
            <div class="rounded-lg bg-amber-50 p-5 ring-1 ring-amber-500/20">
                <h3 class="text-sm font-semibold text-amber-900">
                    {{ count($admin['problems']) }}
                    thing{{ count($admin['problems']) === 1 ? '' : 's' }} still need attention
                </h3>
                <ul class="mt-3 space-y-2">
                    @foreach ($admin['problems'] as $problem)
                        <li class="flex flex-wrap items-baseline gap-x-2 text-sm text-amber-900">
                            <span>{{ $problem['message'] }}</span>
                            <a href="{{ $problem['route'] }}"
                                class="font-medium underline underline-offset-2 hover:no-underline">Fix</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Nothing at all when neither part applies, rather than an empty grid
             or a card explaining what is not here. An administrator account has
             no IPCR of its own, and saying so on every visit tells them
             something they already know. --}}
        @if ($employee || $pending['total'] > 0)
            <div class="grid gap-6 lg:grid-cols-3">
                {{-- The employee's own IPCR. --}}
                @if ($employee)
                    <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-950/5 lg:col-span-2">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">My IPCR</h3>
                                <p class="mt-0.5 text-sm text-gray-500">
                                    {{ $period?->name ?? 'No rating period is open right now.' }}
                                </p>
                            </div>

                            @if ($myIpcr)
                                <x-status-badge :status="$myIpcr->status" />
                            @elseif ($period)
                                <span
                                    class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-500/20">
                                    Not started
                                </span>
                            @endif
                        </div>

                        @if ($period?->submission_deadline)
                            @php
                                $daysLeft = (int) now()->startOfDay()->diffInDays($period->submission_deadline, false);
                            @endphp
                            <p
                                class="mt-4 text-sm {{ $daysLeft < 0 ? 'text-red-700' : ($daysLeft <= 7 ? 'text-amber-800' : 'text-gray-600') }}">
                                Deadline {{ $period->submission_deadline->format('d M Y') }}
                                @if ($daysLeft < 0)
                                    — <strong>{{ abs($daysLeft) }} day{{ abs($daysLeft) === 1 ? '' : 's' }}
                                        overdue</strong>
                                @elseif ($daysLeft === 0)
                                    — <strong>today</strong>
                                @else
                                    — {{ $daysLeft }} day{{ $daysLeft === 1 ? '' : 's' }} left
                                @endif
                            </p>
                        @endif

                        <div class="mt-5">
                            @if ($myIpcr)
                                <a href="{{ route('ipcrs.show', $myIpcr) }}"
                                    class="inline-flex items-center rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-nav-800">
                                    Open my IPCR
                                </a>
                            @else
                                <a href="{{ route('ipcrs.index') }}"
                                    class="inline-flex items-center rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-nav-800">
                                    {{ $period ? 'Start my IPCR' : 'Go to my IPCRs' }}
                                </a>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Only for people something is actually routed to. --}}
                @if ($pending['total'] > 0)
                    <a href="{{ route('approvals.inbox') }}"
                        class="block rounded-lg bg-nav-900 p-6 text-white shadow-sm transition-colors hover:bg-nav-800">
                        <h3 class="text-sm font-semibold">Waiting for you</h3>
                        <p class="mt-2 font-data text-4xl font-semibold">{{ $pending['total'] }}</p>
                        <p class="mt-2 text-sm text-nav-300">
                            @if ($pending['assessment'] > 0)
                                {{ $pending['assessment'] }} to assess
                            @endif
                            @if ($pending['assessment'] > 0 && $pending['final'] > 0)
                                ·
                            @endif
                            @if ($pending['final'] > 0)
                                {{ $pending['final'] }} awaiting your final approval
                            @endif
                        </p>
                    </a>
                @endif
            </div>
        @endif

        {{-- The unit this head runs: how far it has got, what has been sent in,
             and who is still to send anything. Above the hospital-wide figures
             because it is the part they can actually do something about. --}}
        @if ($team->isNotEmpty())
            <x-dashboard.head-overview :team="$team" :period="$period" :head="$employee" :unit="$unit" />
        @endif

        {{-- The hospital-wide picture and the rail beside it, for HR and
             administrators only.

             The rail starts here rather than level with the cards above. Those
             are the short answer to "what should I do next" and they read
             across the page; this is the long stretch that wants a companion,
             and the rail is about other people's work rather than your own. --}}
        @if ($admin)
            <x-dashboard.admin-overview :admin="$admin">
                {{-- Handed over rather than placed here: the overview owns its
                     own layout, and the rail starts where its breakdowns do -
                     under the strip of figures, not level with it.

                     Stuck to the top on a wide screen, because the column
                     beside it runs to several screens and a rail that scrolls
                     away is a rail you have to go back up for. --}}
                <x-slot:rail>
                    <x-dashboard.side-rail class="xl:sticky xl:top-6" :admin="$admin" />
                </x-slot:rail>
            </x-dashboard.admin-overview>
        @endif
    </x-page-container>
</x-app-layout>
