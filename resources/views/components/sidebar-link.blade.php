@props([
    'href' => '#',
    'active' => false,
    'icon' => null,
    'badge' => null,
])

{{--
    One row in the sidebar.

    The `collapsed` state comes from `appShell`, the x-data on <body>. Note that
    it is only ever expressed through `lg:` variants. On small screens the
    sidebar is a drawer and ALWAYS shows labels, even when the desktop view is
    collapsed. Using x-show here would hide the labels on a phone, which is
    wrong.
--}}
<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    class="group relative flex min-h-11 items-center gap-3 rounded-md py-2 pe-2 ps-3 text-sm transition-colors
        focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-seal focus-visible:ring-offset-2 focus-visible:ring-offset-nav-900
        {{ $active
            ? 'bg-nav-800 font-semibold text-white'
            : 'text-nav-300 hover:bg-nav-800/60 hover:text-white' }}"
>
    {{-- The seal rail: the only place the yellow appears. --}}
    <span
        aria-hidden="true"
        class="absolute start-0 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-e-full bg-seal {{ $active ? 'opacity-100' : 'opacity-0' }}"
    ></span>

    <span class="shrink-0 [&>svg]:h-5 [&>svg]:w-5" aria-hidden="true">{{ $icon }}</span>

    <span class="truncate" :class="collapsed ? 'lg:hidden' : ''">{{ $slot }}</span>

    {{-- A count. Stays visible when collapsed, moved onto the icon, because a
         number waiting on you is the one thing worth seeing without a label. --}}
    @isset($badge)
        <span
            class="ms-auto inline-flex min-w-5 shrink-0 items-center justify-center rounded-full bg-seal px-1.5 py-0.5 font-data text-[0.625rem] font-semibold text-nav-900"
            :class="collapsed ? 'lg:absolute lg:end-1 lg:top-1 lg:ms-0' : ''"
        >{{ $badge }}</span>
    @endisset

    {{-- Tooltip - shown only when collapsed on desktop, where no label is
         visible any more. --}}
    <span
        role="tooltip"
        class="pointer-events-none absolute start-full z-50 ms-2 hidden whitespace-nowrap rounded-md bg-nav-800 px-2 py-1 text-xs font-medium text-white shadow-lg ring-1 ring-white/10"
        :class="collapsed ? 'lg:group-hover:block lg:group-focus-visible:block' : ''"
    >{{ $slot }}</span>
</a>
