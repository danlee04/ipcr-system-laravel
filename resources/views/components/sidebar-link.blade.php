@props([
    'href' => '#',
    'active' => false,
    'icon' => null,
])

{{--
    Isang linya sa sidebar.

    Ang `collapsed` na state ay galing sa `appShell` na naka-x-data sa <body>.
    Pansinin: ipinapahayag lang natin siya sa pamamagitan ng `lg:` variants.
    Sa maliit na screen ay drawer ang sidebar at LAGING may label - kahit
    naka-collapse ang desktop view. Kung gagamit tayo ng x-show dito,
    mawawala ang mga label sa cellphone, na mali.
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
    {{-- Ang seal rail: ito lang ang lugar kung saan lumalabas ang dilaw. --}}
    <span
        aria-hidden="true"
        class="absolute start-0 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-e-full bg-seal {{ $active ? 'opacity-100' : 'opacity-0' }}"
    ></span>

    <span class="shrink-0 [&>svg]:h-5 [&>svg]:w-5" aria-hidden="true">{{ $icon }}</span>

    <span class="truncate" :class="collapsed ? 'lg:hidden' : ''">{{ $slot }}</span>

    {{-- Tooltip - lumalabas lang kapag naka-collapse sa desktop, kung saan
         wala nang nakikitang label. --}}
    <span
        role="tooltip"
        class="pointer-events-none absolute start-full z-50 ms-2 hidden whitespace-nowrap rounded-md bg-nav-800 px-2 py-1 text-xs font-medium text-white shadow-lg ring-1 ring-white/10"
        :class="collapsed ? 'lg:group-hover:block lg:group-focus-visible:block' : ''"
    >{{ $slot }}</span>
</a>
