<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600|ibm-plex-mono:400,500&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-800 antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center bg-paper px-4 py-10">
            {{-- Parehong brand mark ng sidebar, para iisang app ang dating. --}}
            <a href="/" class="flex items-center gap-3 rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-nav-700 focus-visible:ring-offset-2">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-nav-900">
                    <svg class="h-6 w-6 text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M10 3h4v5h5v4h-5v5h-4v-5H5V8h5V3Z" />
                    </svg>
                </span>
                <span>
                    <span class="block font-data text-[0.625rem] uppercase tracking-[0.18em] text-slate-500">DTRC</span>
                    <span class="block text-base font-semibold text-nav-900">IPCR System</span>
                </span>
            </a>

            <div class="mt-8 w-full max-w-md rounded-xl border border-slate-200 bg-white px-6 py-7 shadow-sm">
                {{ $slot }}
            </div>

            <p class="mt-6 font-data text-[0.6875rem] uppercase tracking-[0.14em] text-slate-400">
                Individual Performance Commitment and Review
            </p>
        </div>
    </body>
</html>
