<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Sign in &mdash; {{ config('agency.name') }} IPCR System</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap"
        rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

{{--
    The way in: what the system is on the left, the form on the right.

    The left is the same navy the sidebar is, so signing in is stepping through
    a door into a room you can already see rather than passing a gate in front
    of one. On a phone it collapses to the badge and the form; the copy is for
    somebody deciding they are in the right place, and on a phone they have
    already decided.
--}}

<body class="min-h-screen bg-white font-sans text-gray-800 antialiased">
    <div class="grid min-h-screen lg:grid-cols-2">
        <div class="relative hidden overflow-hidden bg-nav-900 px-10 py-12 lg:flex lg:flex-col lg:justify-between">
            {{-- The arc. One shape, off the corner, so the panel is not a flat
                 rectangle of navy - and nothing else in the composition has to
                 work to carry it. --}}
            <div aria-hidden="true"
                class="pointer-events-none absolute -inset-e-40 -top-40 h-136 w-136 rounded-full bg-brand-600/15">
            </div>
            <div aria-hidden="true"
                class="pointer-events-none absolute -bottom-52 -inset-s-28 h-112 w-md rounded-full bg-mint-500/5">
            </div>

            <div class="relative flex items-center gap-3">
                <img src="{{ asset('images/dtrc-logo.png') }}" alt=""
                    class="h-12 w-12 shrink-0 rounded-full bg-white/95 object-contain p-1 ring-1 ring-white/20">
                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-white">{{ config('agency.short_name') }}</span>
                    <span class="block text-xs leading-snug text-nav-300">{{ config('agency.name') }}</span>
                </span>
            </div>

            <div class="relative max-w-lg">
                <div class="h-1 w-14 rounded-full bg-accent-bright"></div>

                <p class="mt-6 text-4xl font-semibold leading-[1.15] tracking-tight text-white">
                    Performance.<br>Commitment.<br><em class="font-normal italic text-accent-bright">Results.</em>
                </p>

                <p class="mt-5 text-sm leading-relaxed text-nav-100">
                    The IPCR System carries individual performance from setting targets through to final approval,
                    for every employee at {{ config('agency.short_name') }}.
                </p>

                <ul class="mt-8 space-y-3.5">
                    @foreach ([
        ['mint', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'Set and track', 'performance targets per period'],
        ['brand', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'Multi-level review', 'from section head to Chief of Hospital'],
        ['amber', 'M11.05 2.93c.3-.92 1.6-.92 1.9 0l1.52 4.67a1 1 0 00.95.69h4.91c.97 0 1.38 1.24.59 1.81l-3.97 2.89a1 1 0 00-.37 1.12l1.52 4.67c.3.92-.75 1.69-1.54 1.12l-3.97-2.89a1 1 0 00-1.18 0l-3.97 2.89c-.79.57-1.84-.2-1.54-1.12l1.52-4.67a1 1 0 00-.37-1.12L3.08 10.1c-.78-.57-.38-1.81.59-1.81h4.91a1 1 0 00.95-.69l1.52-4.67z', 'A 1 to 5 scale', 'weighted automatically into one rating'],
    ] as [$tone, $path, $lead, $rest])
                        <li class="flex items-start gap-3">
                            <span @class([
                                'mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg ring-1 ring-inset',
                                'bg-mint-500/15 text-mint-300 ring-mint-500/25' => $tone === 'mint',
                                'bg-brand-600/20 text-brand-300 ring-brand-500/25' => $tone === 'brand',
                                'bg-amber-500/15 text-amber-300 ring-amber-500/25' => $tone === 'amber',
                            ])>
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
                                </svg>
                            </span>
                            <span class="text-sm leading-snug text-nav-100">
                                <strong class="font-semibold text-white">{{ $lead }}</strong> {{ $rest }}
                            </span>
                        </li>
                    @endforeach
                </ul>

                {{-- Live, not a boast. The three pills a marketing page would
                     put here would say the same thing on the day the system is
                     switched off. --}}
                <x-period-slip tone="dark" class="mt-8 max-w-xs border-t border-white/10 pt-6" />
            </div>

            <p class="relative text-[0.6875rem] leading-relaxed text-nav-300">
                &copy; {{ now()->year }} {{ config('agency.name') }}<br>
                All rights reserved. Authorised personnel only.
            </p>
        </div>

        <div class="flex items-center justify-center px-5 py-10 sm:px-10">
            <div class="w-full max-w-md">
                {{ $slot }}
            </div>
        </div>
    </div>
</body>

</html>
