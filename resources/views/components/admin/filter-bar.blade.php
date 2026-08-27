@props(['action', 'placeholder' => 'Search…', 'hidden' => []])

{{-- Search and filters for an admin list.
     A plain GET form: the query string is the only state, so a filtered list
     can be bookmarked, shared, and survives paging without any JavaScript.

     Inside an x-admin.live-list the same form answers as you type - see
     `data-live-form`, which is the only thing that marks it as such. --}}
<form method="GET" action="{{ $action }}" data-live-form class="flex flex-wrap items-end gap-2">
    @foreach ($hidden as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach

    <label class="block min-w-56 flex-1">
        <span class="sr-only">{{ $placeholder }}</span>
        <input type="search" name="search" value="{{ request('search') }}" placeholder="{{ $placeholder }}"
            class="w-full rounded-lg border-gray-300 text-sm">
    </label>

    {{ $slot }}

    <button type="submit"
        class="rounded-lg bg-nav-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-nav-800">
        Search
    </button>

    @if (collect(request()->except(array_merge(['page'], array_keys($hidden))))->filter()->isNotEmpty())
        <a href="{{ $action }}{{ $hidden === [] ? '' : '?' . http_build_query($hidden) }}"
            class="rounded-lg px-3 py-2 text-sm font-medium text-gray-600 underline underline-offset-2 hover:text-gray-900">
            Clear
        </a>
    @endif
</form>
