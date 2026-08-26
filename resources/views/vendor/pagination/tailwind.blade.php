{{--
    Overrides Laravel's shipped Tailwind paginator.

    The default is written for Tailwind v3 and this app is on v4; rather than
    hope every utility still means the same thing, the markup lives here and
    uses the same tokens as the rest of the admin screens.
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}"
        class="flex flex-wrap items-center justify-between gap-3">
        <p class="font-data text-xs text-gray-500">
            {{ __('Showing') }} <span class="font-semibold text-gray-700">{{ $paginator->firstItem() }}</span>–<span
                class="font-semibold text-gray-700">{{ $paginator->lastItem() }}</span>
            {{ __('of') }} <span class="font-semibold text-gray-700">{{ $paginator->total() }}</span>
        </p>

        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true"
                    class="cursor-not-allowed rounded-md px-3 py-1.5 text-sm font-medium text-gray-300">{{ __('Previous') }}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                    class="rounded-md px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100">{{ __('Previous') }}</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-1.5 font-data text-sm text-gray-400">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page"
                                class="rounded-md bg-nav-900 px-3 py-1.5 font-data text-sm font-semibold text-white">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}"
                                class="rounded-md px-3 py-1.5 font-data text-sm text-gray-700 transition-colors hover:bg-gray-100">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                    class="rounded-md px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100">{{ __('Next') }}</a>
            @else
                <span aria-disabled="true"
                    class="cursor-not-allowed rounded-md px-3 py-1.5 text-sm font-medium text-gray-300">{{ __('Next') }}</span>
            @endif
        </div>
    </nav>
@endif
