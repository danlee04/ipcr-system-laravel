@props(['tone' => 'light'])

@php
    use App\Models\IpcrPeriod;

    /*
     * The open period, wherever this is dropped.
     *
     * It looks the period up itself rather than being handed one, because it
     * appears on a page nobody has signed in to as well as on one they have,
     * and a component that needs a different controller to feed it on each is
     * two components pretending to be one.
     */
    $period = IpcrPeriod::active();

    $daysLeft = $period?->submission_deadline
        ? (int) now()->startOfDay()->diffInDays($period->submission_deadline, false)
        : null;

    // How much of the period has gone. The rule underneath is the only thing
    // here that is not a word, and this is what it measures.
    $elapsed = null;

    if ($period?->start_date && $period?->end_date) {
        $whole = $period->start_date->diffInDays($period->end_date) ?: 1;
        $gone = $period->start_date->diffInDays(now(), false);
        $elapsed = (int) max(0, min(100, round($gone / $whole * 100)));
    }

    // Two grounds, one object: navy behind the sign-in form, white on the
    // dashboard. Only the colours differ.
    $dark = $tone === 'dark';

    $eyebrow = $dark ? 'text-nav-300' : 'text-gray-400';
    $name = $dark ? 'text-white' : 'text-gray-900';
    $detail = $dark ? 'text-nav-100' : 'text-gray-600';
    $rule = $dark ? 'bg-white/15' : 'bg-gray-200';
    $fill = $dark ? 'bg-accent-bright' : 'bg-brand-600';

    $urgent = $daysLeft !== null && $daysLeft <= 7;
@endphp

<div data-period-slip {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <p class="font-data text-[0.625rem] uppercase tracking-[0.18em] {{ $eyebrow }}">
        {{ $period ? 'Open period' : 'Rating period' }}
    </p>

    @if (! $period)
        <p class="mt-1 text-sm {{ $detail }}">
            No rating period is open. Nobody can start an IPCR until one is.
        </p>
    @else
        <p class="mt-1 truncate text-lg font-semibold leading-tight {{ $name }}">{{ $period->name }}</p>

        @if ($daysLeft !== null)
            {{-- The deadline reads as a date and as a distance, because those
                 are two different questions and people ask both. --}}
            <p class="mt-1 font-data text-xs {{ $urgent && ! $dark ? 'text-amber-800' : $detail }}">
                Closes {{ $period->submission_deadline->format('d M Y') }}
                <span aria-hidden="true" class="{{ $dark ? 'text-nav-300' : 'text-gray-300' }}">&middot;</span>
                @if ($daysLeft < 0)
                    <strong>{{ abs($daysLeft) }} day{{ abs($daysLeft) === 1 ? '' : 's' }} overdue</strong>
                @elseif ($daysLeft === 0)
                    <strong>closes today</strong>
                @else
                    {{ $daysLeft }} day{{ $daysLeft === 1 ? '' : 's' }} left
                @endif
            </p>
        @endif

        @if ($elapsed !== null)
            <div class="mt-3 h-px w-full {{ $rule }}" role="presentation">
                <div class="h-px {{ $fill }}" style="width: {{ $elapsed }}%"></div>
            </div>
        @endif
    @endif
</div>
