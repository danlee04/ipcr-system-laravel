<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'IPCR System') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600|ibm-plex-mono:400,500&display=swap"
        rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

{{--
    The way in.

    Split rather than a card floating in the middle of a tinted page: the left
    is the same navy the sidebar is, so signing in is stepping through a door
    into a room you can already see, not passing a gate in front of one.

    What the left carries is the one thing everybody in the hospital wants
    twice a year - which period is open and how long is left. It is posted
    information, the kind that goes on a bulletin board, so it is here before
    anybody has signed in.
--}}

<body class="min-h-screen bg-paper font-sans text-gray-800 antialiased">
    <div class="grid min-h-screen lg:grid-cols-[26rem_minmax(0,1fr)]">
        <div class="flex flex-col justify-between gap-10 bg-nav-900 px-8 py-10 lg:px-10 lg:py-12">
            <a href="/"
                class="flex w-fit items-center gap-3 rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-bright focus-visible:ring-offset-2 focus-visible:ring-offset-nav-900">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-nav-800 ring-1 ring-white/10">
                    <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M10 3h4v5h5v4h-5v5h-4v-5H5V8h5V3Z" />
                    </svg>
                </span>
                <span>
                    <span
                        class="block font-data text-[0.625rem] uppercase tracking-[0.18em] text-nav-300">DTRC</span>
                    <span class="block text-base font-semibold text-white">IPCR System</span>
                </span>
            </a>

            <x-period-slip tone="dark" class="lg:max-w-xs" />

            {{-- The name the form goes by on paper. Last, quietly: it is what
                 the system is, not what anyone came here to read. --}}
            <p class="max-w-xs font-data text-[0.6875rem] uppercase leading-relaxed tracking-[0.14em] text-nav-300">
                Individual Performance Commitment and Review
            </p>
        </div>

        <div class="flex items-center justify-center px-4 py-12 sm:px-8">
            <div class="w-full max-w-sm">
                {{ $slot }}
            </div>
        </div>
    </div>
</body>

</html>
