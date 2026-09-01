@props(['admin'])

@php
    use App\Enums\IpcrStatus;

    $period = $admin['period'] ?? null;
    $totals = $admin['totals'];
    $recent = $admin['recent'];

    // Sent for assessment, whatever happened to it after: a draft is the only
    // status that has never left the employee's hands.
    $sent = $totals['total'] - $totals['draft'];

    $daysLeft = $period?->submission_deadline
        ? (int) now()->startOfDay()->diffInDays($period->submission_deadline, false)
        : null;

    /*
     * The screens an administrator lives in.
     *
     * The sidebar has these too, and it can be collapsed to icons; this is
     * the same handful spelled out where the work is being read.
     */
    $links = [
        [
            'label' => 'All IPCRs',
            'href' => route('admin.ipcrs.index'),
            'count' => null,
            'path' => 'M4 5.5h16M4 12h16M4 18.5h10',
        ],
        [
            'label' => 'Period Summary',
            'href' => route('admin.summary.index'),
            'count' => null,
            'path' => 'M4.5 19.5V13m5 6.5V7.5m5 12v-9m5 9V5M3 20.5h18',
        ],
        [
            'label' => 'Employees',
            'href' => route('admin.employees.index'),
            'count' => null,
            'path' =>
                'M9 11a3.25 3.25 0 1 0 0-6.5A3.25 3.25 0 0 0 9 11Zm7.5.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM3 19.5a6 6 0 0 1 12 0M16 14a5 5 0 0 1 5 5.5',
        ],
        [
            'label' => 'Rating Periods',
            'href' => route('admin.periods.index'),
            'count' => null,
            'path' => 'M7 3.5V6m10-2.5V6M4.5 8.5h15M6 6h12a1.5 1.5 0 0 1 1.5 1.5V19A1.5 1.5 0 0 1 18 20.5H6A1.5 1.5 0 0 1 4.5 19V7.5A1.5 1.5 0 0 1 6 6Z',
        ],
        [
            'label' => 'Functions',
            'href' => route('admin.functions.index'),
            'count' => null,
            'path' => 'M5 5.5h14M5 10h14M5 14.5h9M5 19h6',
        ],
        [
            'label' => 'Notifications',
            'href' => route('notifications.index'),
            'count' => ($admin['unread'] ?? 0) > 0 ? $admin['unread'] : null,
            'path' => 'M12 4a5 5 0 0 0-5 5v3.5L5.5 16h13L17 12.5V9a5 5 0 0 0-5-5Zm-2 12a2 2 0 1 0 4 0',
        ],
    ];
@endphp

<aside data-dashboard-rail {{ $attributes->merge(['class' => 'space-y-4']) }}>
    {{-- Where the period stands. Everything else on the page is a count of
         something; this is the one that has a date attached to it. --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
        <h3 class="text-sm font-semibold text-gray-900">This period</h3>

        @if ($period)
            <p class="mt-1 text-sm text-gray-600">{{ $period->name }}</p>

            @if ($daysLeft !== null)
                <p
                    class="mt-2 text-xs {{ $daysLeft < 0 ? 'text-red-700' : ($daysLeft <= 7 ? 'text-amber-800' : 'text-gray-500') }}">
                    Deadline {{ $period->submission_deadline->format('d M Y') }} —
                    @if ($daysLeft < 0)
                        <strong>{{ abs($daysLeft) }} day{{ abs($daysLeft) === 1 ? '' : 's' }} overdue</strong>
                    @elseif ($daysLeft === 0)
                        <strong>today</strong>
                    @else
                        {{ $daysLeft }} day{{ $daysLeft === 1 ? '' : 's' }} left
                    @endif
                </p>
            @endif

            <div class="mt-4">
                <div class="flex items-baseline justify-between text-xs text-gray-500">
                    <span>Sent for assessment</span>
                    <span class="font-data text-gray-900">{{ $sent }} / {{ $totals['total'] }}</span>
                </div>

                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full rounded-full bg-nav-900"
                        style="width: {{ $totals['total'] > 0 ? round($sent / $totals['total'] * 100) : 0 }}%"></div>
                </div>
            </div>
        @else
            <p class="mt-2 text-xs text-gray-500">
                No rating period is open. Nobody can start an IPCR until one is.
            </p>
        @endif
    </div>

    {{-- The sheets that most recently left an employee's hands.

         Ordered by when they were sent, not when they were last touched: an
         assessor typing a mark moves updated_at and says nothing about who is
         getting their work in. --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
        <div class="flex items-baseline justify-between gap-3">
            <h3 class="text-sm font-semibold text-gray-900">Recent submissions</h3>

            @if ($recent->isNotEmpty())
                <a href="{{ route('admin.ipcrs.index') }}"
                    class="text-xs font-medium text-gray-500 underline underline-offset-2 hover:text-gray-900">See
                    all</a>
            @endif
        </div>

        @forelse ($recent as $ipcr)
            @php
                $name = $ipcr->employee?->full_name ?? 'Unknown';
                $initials = collect(preg_split('/\s+/', trim($name)))
                    ->filter()
                    ->take(2)
                    ->map(fn(string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
                    ->implode('');
            @endphp

            <a href="{{ route('ipcrs.show', $ipcr) }}"
                class="-mx-2 flex items-center gap-2.5 rounded-md px-2 py-2 transition-colors hover:bg-gray-50">
                <span
                    class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-nav-900/5 font-data text-[0.625rem] font-semibold text-nav-900">{{ $initials }}</span>

                <span class="min-w-0 flex-1">
                    <span class="block truncate text-xs font-medium text-gray-900">{{ $name }}</span>
                    <span class="block truncate font-data text-[0.6875rem] text-gray-400">
                        {{ $ipcr->submitted_at?->diffForHumans() }}
                    </span>
                </span>

                <span
                    class="shrink-0 rounded-full px-2 py-0.5 text-[0.625rem] font-medium ring-1 ring-inset {{ $ipcr->status->badgeClasses() }}">
                    {{ $ipcr->status->label() }}
                </span>
            </a>
        @empty
            <p class="mt-3 text-sm font-medium text-gray-900">Nothing submitted yet</p>
            <p class="mt-1 text-xs text-gray-500">
                Sheets appear here the moment an employee sends one for assessment.
            </p>
        @endforelse
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
        <h3 class="text-sm font-semibold text-gray-900">Quick access</h3>

        <nav class="mt-3 space-y-0.5">
            @foreach ($links as $link)
                <a href="{{ $link['href'] }}"
                    class="group flex items-center gap-2.5 rounded-md px-2 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-50 hover:text-gray-900">
                    <svg class="h-4.5 w-4.5 shrink-0 text-gray-400 transition-colors group-hover:text-nav-900"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $link['path'] }}" />
                    </svg>

                    <span class="min-w-0 flex-1 truncate">{{ $link['label'] }}</span>

                    @if ($link['count'])
                        <span
                            class="inline-flex min-w-5 shrink-0 items-center justify-center rounded-full bg-nav-900 px-1.5 py-0.5 font-data text-[0.625rem] font-semibold text-white">{{ $link['count'] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>
    </div>
</aside>
