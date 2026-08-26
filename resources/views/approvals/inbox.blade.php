<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('For My Approval') }}</h2>
    </x-slot>

    <x-page-container class="space-y-8">
        <x-admin.flash />

        @php
            $queues = [
                [
                    'title' => 'For Assessment',
                    'blurb' => 'You rate each function on quality, efficiency and timeliness, then complete the assessment.',
                    'items' => $forAssessment,
                    'stamp' => 'submitted_at',
                    'stampLabel' => 'Submitted',
                ],
                [
                    'title' => 'For Final Approval',
                    'blurb' => 'The assessment is done. Confirm the marks to give the final approval.',
                    'items' => $forFinalRating,
                    'stamp' => 'assessed_at',
                    'stampLabel' => 'Assessed',
                ],
            ];
        @endphp

        @if ($forAssessment->isEmpty() && $forFinalRating->isEmpty())
            <div class="rounded-lg bg-white p-10 text-center shadow-sm ring-1 ring-gray-950/5">
                <p class="text-sm font-medium text-gray-900">Nothing is waiting on you.</p>
                <p class="mt-1 text-sm text-gray-500">
                    IPCRs appear here when someone you assess or approve submits one.
                </p>
            </div>
        @endif

        @foreach ($queues as $queue)
            @continue($queue['items']->isEmpty())

            <section class="space-y-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">
                        {{ $queue['title'] }}
                        <span class="ms-1 font-data text-sm font-normal text-gray-500">({{ $queue['items']->count() }})</span>
                    </h3>
                    <p class="text-sm text-gray-600">{{ $queue['blurb'] }}</p>
                </div>

                <x-admin.table>
                    <x-slot:head>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            Employee</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            Period</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            Functions</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            {{ $queue['stampLabel'] }}</th>
                        <th class="px-6 py-3"></th>
                    </x-slot:head>

                    @foreach ($queue['items'] as $ipcr)
                        <tr>
                            <td class="px-6 py-4 text-sm">
                                <span class="font-medium text-gray-900">{{ $ipcr->employee?->full_name }}</span>
                                @if ($ipcr->position_title)
                                    <span class="block text-xs text-gray-500">{{ $ipcr->position_title }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ $ipcr->period?->name ?? '—' }}</td>
                            <td class="px-6 py-4 font-data text-sm text-gray-600">{{ $ipcr->items()->count() }}</td>
                            <td class="px-6 py-4 font-data text-sm text-gray-600">
                                {{ $ipcr->{$queue['stamp']}?->format('d M Y') ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-end">
                                <a href="{{ route('ipcrs.show', $ipcr) }}"
                                    class="inline-flex items-center rounded-md bg-nav-900 px-3 py-1.5 text-sm font-semibold text-white transition-colors hover:bg-nav-800">
                                    Open
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </x-admin.table>
            </section>
        @endforeach
    </x-page-container>
</x-app-layout>
