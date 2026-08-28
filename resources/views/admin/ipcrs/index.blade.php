<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('All IPCRs') }}</h2>
    </x-slot>

    <x-page-container class="space-y-6">
        <x-admin.flash />
        <x-admin.live-list :action="route('admin.ipcrs.index')">
            <x-admin.filter-bar :action="route('admin.ipcrs.index')" placeholder="Search by employee name or number">
                <label class="block">
                    <span class="sr-only">Status</span>
                    <select name="status" class="w-40 rounded-lg border-gray-300 text-sm">
                        <option value="">Any status</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                                {{ $status->label() }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="sr-only">Period</span>
                    <select name="period" class="w-40 rounded-lg border-gray-300 text-sm">
                        <option value="">All periods</option>
                        @foreach ($periods as $period)
                            <option value="{{ $period->id }}" @selected(request('period') == $period->id)>{{ $period->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="sr-only">Division</span>
                    <select name="division" class="w-40 rounded-lg border-gray-300 text-sm">
                        <option value="">All divisions</option>
                        @foreach ($divisions as $division)
                            <option value="{{ $division->id }}" @selected(request('division') == $division->id)>
                                {{ $division->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="sr-only">Section</span>
                    <select name="section" class="w-40 rounded-lg border-gray-300 text-sm">
                        <option value="">All sections</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}" @selected(request('section') == $section->id)>
                                {{ $section->name }}</option>
                        @endforeach
                    </select>
                </label>
            </x-admin.filter-bar>

            <x-admin.live-results>
                @include('admin.ipcrs.rows')
            </x-admin.live-results>
        </x-admin.live-list>
    </x-page-container>
</x-app-layout>
