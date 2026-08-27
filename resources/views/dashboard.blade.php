@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $firstName = $employee?->first_name ?: strtok((string) auth()->user()->name, ' ');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ $greeting }}, {{ $firstName }}
            </h2>
            <p class="mt-0.5 text-sm text-gray-500">{{ now()->format('l, j F Y') }}</p>
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

        {{-- Nothing at all when neither part applies, rather than an empty
             grid or a card explaining what is not here. An administrator
             account has no IPCR of its own, and saying so on every visit tells
             them something they already know. --}}
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

        {{-- Everything below is the hospital-wide picture, for HR and
             administrators only. --}}
        @if ($admin)
            <x-dashboard.admin-overview :admin="$admin" />
        @endif
    </x-page-container>
</x-app-layout>
