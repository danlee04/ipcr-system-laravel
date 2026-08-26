@props(['admin'])

@php
    use App\Enums\IpcrStatus;

    $scope = $admin['scope'];
    $t = $admin['totals'];
    $divisor = $t['total'] > 0 ? $t['total'] : 1;
    $pct = fn(int $n): int => (int) round($n / $divisor * 100);

    $approvalRate = $t['total'] > 0 ? (int) round($t['approved'] / $t['total'] * 100) : 0;

    $activeDivision = $scope->divisionId ? $admin['divisionMap'][$scope->divisionId] ?? null : null;

    // Only the four states worth charting; Returned is an exception path and
    // would flatten the other slices.
    $donut = [
        'labels' => ['Draft', 'For Assessment', 'For Final Approval', 'Approved'],
        'data' => [$t['draft'], $t['review'], $t['final'], $t['approved']],
        'colors' => ['#EF9F27', '#378ADD', '#5DCAA5', '#639922'],
    ];

    // Built here rather than inline in the attribute: Blade's @json argument
    // parser truncates a multi-line array literal containing quoted keys.
    $periodChart = [
        'labels' => array_column($admin['periodStats'], 'label'),
        'total' => array_column($admin['periodStats'], 'count'),
        'approved' => array_column($admin['periodStats'], 'approved'),
    ];
@endphp

<section class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h3 class="text-base font-semibold text-gray-900">Hospital-wide overview</h3>
            <p class="mt-0.5 text-sm text-gray-500">
                @if ($activeDivision)
                    Showing <strong class="text-gray-700">{{ $activeDivision['name'] }}</strong>.
                @else
                    Every division.
                @endif
                @if ($scope->isFiltered())
                    <a href="{{ route('dashboard') }}" class="ms-1 underline underline-offset-2">Clear filters</a>
                @endif
            </p>
        </div>

        {{-- Filters submit on change; the page is server-rendered from the
             query string, so there is one source of truth for what is shown.
             The scope carries through to the IPCR list, so "Medical Services
             this semester" stays selected when you drill in. --}}
        <div class="flex flex-wrap items-end gap-2">
            <a href="{{ route('admin.ipcrs.index', array_filter([
                'period' => $scope->periodId,
                'division' => $scope->divisionId,
                'section' => $scope->sectionId,
            ])) }}"
                class="rounded-lg bg-nav-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-nav-800">
                Open IPCRs
            </a>
        </div>

        <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-end gap-2"
            x-data x-on:change="$el.submit()">
            @foreach ([['filter_period_id', 'Period', 'periods', 'All periods'], ['filter_division_id', 'Division', 'divisions', 'All divisions'], ['filter_section_id', 'Section', 'sections', 'All sections']] as [$name, $label, $key, $blank])
                @php
                    $selected = $scope->toQuery()[$name] ?? '';
                @endphp
                <label class="block">
                    <span
                        class="mb-1 block font-data text-[0.625rem] font-semibold uppercase tracking-wider text-gray-500">{{ $label }}</span>
                    <select name="{{ $name }}"
                        class="w-40 rounded-lg border-gray-300 text-xs {{ $selected ? 'border-sky-500 bg-sky-50/60 font-medium text-sky-800' : '' }}">
                        <option value="">{{ $blank }}</option>
                        @foreach ($admin[$key] as $option)
                            <option value="{{ $option->id }}" @selected($selected == $option->id)>
                                {{ $option->name }}
                            </option>
                        @endforeach
                    </select>
                </label>
            @endforeach
        </form>
    </div>

    {{-- Period tabs --}}
    @if ($admin['periods']->isNotEmpty())
        <nav class="flex flex-wrap gap-2" aria-label="Rating period">
            <a href="{{ route('dashboard', $scope->toQuery(['filter_period_id' => null])) }}"
                class="inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-xs font-medium transition-colors {{ $scope->periodId === null ? 'border-nav-900 bg-nav-900 text-white' : 'border-gray-200 bg-white text-gray-600 hover:border-sky-400 hover:text-sky-700' }}">
                All periods
            </a>
            @foreach ($admin['periods'] as $p)
                @php $isCurrent = $p->id === $admin['currentPeriodId']; @endphp
                <a href="{{ route('dashboard', $scope->toQuery(['filter_period_id' => $p->id])) }}"
                    class="inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-xs font-medium transition-colors {{ $scope->periodId === $p->id ? 'border-nav-900 bg-nav-900 text-white' : 'border-gray-200 bg-white text-gray-600 hover:border-sky-400 hover:text-sky-700' }}">
                    @if ($isCurrent)
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
                    @endif
                    {{ $p->name }}
                    @if ($isCurrent)
                        <span class="opacity-70">(current)</span>
                    @endif
                </a>
            @endforeach
        </nav>
    @endif

    {{-- KPI strip --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        <x-dashboard.kpi-card label="Total IPCRs" :value="$t['total']" sub="all records" accent="blue" :percent="100">
            <x-slot:icon>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.6L19 9.4V19a2 2 0 0 1-2 2z" />
                </svg>
            </x-slot:icon>
        </x-dashboard.kpi-card>

        <x-dashboard.kpi-card label="Draft" :value="$t['draft']" sub="not yet submitted" accent="amber"
            :percent="$pct($t['draft'])">
            <x-slot:icon>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" d="M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4z" />
                </svg>
            </x-slot:icon>
        </x-dashboard.kpi-card>

        <x-dashboard.kpi-card label="For Assessment" :value="$t['review']" sub="waiting to be assessed" accent="teal"
            :percent="$pct($t['review'])">
            <x-slot:icon>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                    <path stroke-linecap="round" d="M2.5 12C3.7 7.9 7.5 5 12 5s8.3 2.9 9.5 7c-1.2 4.1-5 7-9.5 7s-8.3-2.9-9.5-7z" />
                </svg>
            </x-slot:icon>
        </x-dashboard.kpi-card>

        <x-dashboard.kpi-card label="For Final Approval" :value="$t['final']" sub="waiting for final approval"
            accent="blue" :percent="$pct($t['final'])">
            <x-slot:icon>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" d="M12 3l2.5 5.5 6 .8-4.4 4.2 1.1 6-5.2-2.9L6.8 19.5l1.1-6L3.5 9.3l6-.8z" />
                </svg>
            </x-slot:icon>
        </x-dashboard.kpi-card>

        <x-dashboard.kpi-card label="Approved" :value="$t['approved']" sub="finalized" accent="green"
            :percent="$pct($t['approved'])">
            <x-slot:icon>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                </svg>
            </x-slot:icon>
        </x-dashboard.kpi-card>
    </div>

    @if ($t['returned'] > 0)
        <p class="text-sm text-amber-800">
            <strong>{{ $t['returned'] }}</strong>
            IPCR{{ $t['returned'] === 1 ? ' has' : 's have' }} been returned for revision and
            {{ $t['returned'] === 1 ? 'is' : 'are' }} back with the employee.
        </p>
    @endif

    {{-- Status distribution + recent activity --}}
    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h4 class="text-sm font-semibold text-gray-900">Status distribution</h4>
                <span class="font-data text-xs text-gray-500">{{ $t['total'] }} total</span>
            </div>

            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600">
                @foreach ($donut['labels'] as $i => $label)
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-sm" style="background: {{ $donut['colors'][$i] }}"></span>
                        {{ $label }}
                        <span class="font-data">{{ $pct($donut['data'][$i]) }}%</span>
                    </span>
                @endforeach
            </div>

            <div class="relative mt-4 h-52">
                @if ($t['total'] > 0)
                    <canvas data-chart="doughnut" data-chart-config='@json($donut)'></canvas>
                @else
                    <p class="grid h-full place-items-center text-sm text-gray-400">No IPCRs in this scope yet.</p>
                @endif
            </div>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-semibold text-gray-900">Recent activity</h4>
            </div>

            @forelse ($admin['recent'] as $ipcr)
                @php
                    $name = $ipcr->employee?->full_name ?? 'Unknown';
                    $initials = collect(preg_split('/\s+/', trim($name)))->filter()->take(2)
                        ->map(fn(string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
                @endphp
                <div class="flex items-center gap-3 border-b border-gray-100 py-2.5 last:border-0 last:pb-0">
                    <span
                        class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-nav-900/5 font-data text-[0.6875rem] font-semibold text-nav-900">
                        {{ $initials }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-gray-900">{{ $name }}</span>
                        <span class="block truncate text-xs text-gray-400">{{ $ipcr->period?->name ?? '—' }}</span>
                    </span>
                    <x-status-badge :status="$ipcr->status" />
                    <span
                        class="shrink-0 font-data text-xs text-gray-400">{{ $ipcr->updated_at?->format('d M') }}</span>
                </div>
            @empty
                <p class="py-10 text-center text-sm text-gray-400">No activity in this scope yet.</p>
            @endforelse
        </div>
    </div>

    {{-- Submissions per period + workflow track --}}
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 lg:col-span-2">
            <h4 class="text-sm font-semibold text-gray-900">Submissions by period</h4>
            <div class="relative mt-4 h-52">
                @if ($admin['periodStats'] !== [])
                    <canvas data-chart="bar" data-chart-config='@json($periodChart)'></canvas>
                @else
                    <p class="grid h-full place-items-center text-sm text-gray-400">No rating periods yet.</p>
                @endif
            </div>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
            <h4 class="text-sm font-semibold text-gray-900">By workflow track</h4>

            <div class="mt-3 grid grid-cols-2 gap-2">
                <div class="rounded-lg bg-gray-50 p-3 text-center">
                    <p class="font-data text-2xl font-semibold text-gray-900">{{ $t['employee_track'] }}</p>
                    <p class="mt-0.5 text-[0.6875rem] leading-tight text-gray-500">Employee track</p>
                </div>
                <div class="rounded-lg bg-gray-50 p-3 text-center">
                    <p class="font-data text-2xl font-semibold text-gray-900">{{ $t['section_head_track'] }}</p>
                    <p class="mt-0.5 text-[0.6875rem] leading-tight text-gray-500">Section head track</p>
                </div>
                <div class="col-span-2 rounded-lg bg-gray-50 p-3 text-center">
                    <p class="font-data text-2xl font-semibold text-gray-900">
                        @if ($t['avg_rating'] !== null)
                            {{ number_format($t['avg_rating'], 2) }}<span
                                class="text-sm font-normal text-gray-400">/5</span>
                        @else
                            —
                        @endif
                    </p>
                    <p class="mt-0.5 text-[0.6875rem] leading-tight text-gray-500">Average final rating</p>
                </div>
            </div>

            <div class="mt-4 border-t border-gray-100 pt-4">
                <div class="flex items-center justify-between text-xs text-gray-500">
                    <span>Approval rate</span>
                    <strong class="font-data text-gray-900">{{ $approvalRate }}%</strong>
                </div>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full rounded-full bg-emerald-600" style="width: {{ $approvalRate }}%"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Division breakdown. Each row is a link that applies the filter, so
         what you see always matches the query string. --}}
    @if ($admin['divisionStats'] !== [])
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
            <div class="border-b border-gray-100 px-5 py-4">
                <h4 class="text-sm font-semibold text-gray-900">Division breakdown</h4>
                <p class="mt-0.5 text-xs text-gray-500">Select a row to narrow everything above to that division.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-[0.6875rem] uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-2.5 text-start font-medium">Division</th>
                            @foreach (['Total', 'Approved', 'For Assessment', 'For Final Approval', 'Draft', 'Avg Rating', 'Progress'] as $heading)
                                <th class="px-3 py-2.5 text-center font-medium">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($admin['divisionStats'] as $row)
                            @php
                                $isActive = $scope->divisionId === $row['id'];
                                $rowPct = $row['total'] > 0 ? (int) round($row['approved'] / $row['total'] * 100) : 0;
                                $target = $isActive
                                    ? $scope->toQuery(['filter_division_id' => null, 'filter_section_id' => null])
                                    : $scope->toQuery(['filter_division_id' => $row['id'], 'filter_section_id' => null]);
                            @endphp
                            <tr class="border-b border-gray-50 last:border-0 {{ $isActive ? 'bg-sky-50/70' : 'hover:bg-gray-50' }}">
                                <td class="px-5 py-2.5 font-medium text-gray-900 {{ $isActive ? 'border-s-2 border-sky-500 ps-4' : '' }}">
                                    <a href="{{ route('dashboard', $target) }}" class="block hover:underline">
                                        {{ $row['name'] }}
                                    </a>
                                </td>
                                <td class="px-3 py-2.5 text-center font-data text-gray-700">{{ $row['total'] }}</td>
                                <td class="px-3 py-2.5 text-center font-data font-medium text-emerald-700">
                                    {{ $row['approved'] }}</td>
                                <td class="px-3 py-2.5 text-center font-data text-sky-700">{{ $row['review'] }}</td>
                                <td class="px-3 py-2.5 text-center font-data text-teal-700">{{ $row['final'] }}</td>
                                <td class="px-3 py-2.5 text-center font-data text-gray-400">{{ $row['draft'] }}</td>
                                <td class="px-3 py-2.5 text-center font-data text-gray-700">
                                    {{ $row['avg_rating'] !== null ? number_format($row['avg_rating'], 2) : '—' }}
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center justify-center gap-2">
                                        <div class="h-1 w-14 overflow-hidden rounded-full bg-gray-100">
                                            <div class="h-full rounded-full bg-emerald-600"
                                                style="width: {{ $rowPct }}%"></div>
                                        </div>
                                        <span class="font-data text-[0.6875rem] text-gray-500">{{ $rowPct }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Who has not submitted --}}
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
        <div class="border-b border-gray-100 px-5 py-4">
            <h4 class="text-sm font-semibold text-gray-900">Who has not submitted</h4>
            <p class="mt-0.5 text-xs text-gray-500">Active employees with no IPCR sent for assessment. A draft does
                not count.</p>
        </div>

        @if (! $scope->isFiltered())
            <p class="px-5 py-10 text-center text-sm text-gray-400">
                Choose a period or division above to see who is missing.
            </p>
        @elseif ($admin['notSubmitted']->isEmpty())
            <p class="m-5 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-500/20">
                Everyone in this scope has submitted.
            </p>
        @else
            <p class="m-5 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-500/20">
                <strong>{{ $admin['notSubmitted']->count() }}</strong>
                {{ $admin['notSubmitted']->count() === 1 ? 'employee has' : 'employees have' }} not submitted in this
                scope.
            </p>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-y border-gray-100 text-[0.6875rem] uppercase tracking-wide text-gray-500">
                            @foreach (['Employee', 'Employee No.', 'Position', 'Section', 'Division'] as $heading)
                                <th class="px-5 py-2.5 text-start font-medium">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($admin['notSubmitted'] as $person)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-5 py-2.5">
                                    <span class="block font-medium text-gray-900">{{ $person->full_name }}</span>
                                    @if ($person->user)
                                        <span
                                            class="block font-data text-[0.6875rem] text-gray-400">{{ $person->user->email }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-2.5 font-data text-xs text-gray-500">
                                    {{ $person->employee_number }}</td>
                                <td class="px-5 py-2.5 text-xs text-gray-600">{{ $person->position?->title ?? '—' }}
                                </td>
                                <td class="px-5 py-2.5 text-xs text-gray-500">{{ $person->section?->name ?? '—' }}
                                </td>
                                <td class="px-5 py-2.5 text-xs text-gray-500">
                                    {{ $person->section?->division?->name ?? ($person->division?->name ?? '—') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>
