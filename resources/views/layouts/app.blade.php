<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body
        x-data="appShell()"
        @keydown.escape.window="closeDrawer()"
        class="min-h-screen bg-paper font-sans text-slate-800 antialiased"
        :class="drawerOpen ? 'overflow-hidden lg:overflow-auto' : ''"
    >
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-nav-900 focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white"
        >
            Skip to content
        </a>

        {{-- Drawer backdrop - small screens only. --}}
        <div
            x-show="drawerOpen"
            x-transition.opacity.duration.200ms
            @click="closeDrawer()"
            class="fixed inset-0 z-30 bg-nav-900/60 lg:hidden"
            aria-hidden="true"
            x-cloak
        ></div>

        @include('layouts.sidebar')

        {{-- Content column. Only the padding moves when the sidebar collapses -
             the pages' own layouts are never touched. --}}
        <div
            class="flex min-h-screen flex-col transition-[padding] duration-200 ease-out"
            :class="collapsed ? 'lg:ps-[4.5rem]' : 'lg:ps-64'"
        >
            {{-- Topbar - appears only where the sidebar is hidden. --}}
            <div class="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-2 border-b border-slate-200 bg-white/90 px-2 backdrop-blur lg:hidden">
                <button
                    type="button"
                    @click="drawerOpen = true"
                    class="grid h-11 w-11 place-items-center rounded-md text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-nav-700"
                    aria-controls="app-sidebar"
                    :aria-expanded="drawerOpen.toString()"
                >
                    <span class="sr-only">Open menu</span>
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16" />
                    </svg>
                </button>

                <span class="font-data text-[0.625rem] uppercase tracking-[0.18em] text-slate-500">DTRC</span>
                <span class="text-sm font-semibold text-slate-900">IPCR System</span>
            </div>

            @isset($header)
                <header class="border-b border-slate-200 bg-white">
                    {{-- Same width and padding as x-page-container, so the page
                         heading lines up with the content below it. --}}
                    <div class="mx-auto w-full max-w-[110rem] px-4 py-6 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main id="main-content" class="flex-1">
                {{ $slot }}
            </main>
        </div>

        {{-- For components that need a script of their own. Pushed once,
             after Alpine has been loaded by the bundle in the head. --}}
        @stack('scripts')
    </body>
</html>
