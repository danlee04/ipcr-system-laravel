@props(['action'])

{{-- Wraps a filter form and the rows it filters.

     The form inside is a plain GET form and the page works without any of
     this; `liveList` only intercepts it, fetches the same URL, and swaps the
     rows in. Give the form `data-live-form` and the rows `data-live-results`
     - x-admin.filter-bar and x-admin.live-results already do. --}}
<div x-data="liveList('{{ $action }}')" {{ $attributes->merge(['class' => 'space-y-6']) }}>
    {{ $slot }}
</div>
