@props(['employee' => null, 'chain' => null, 'chainProblem' => null])

{{--
    What HR holds about this employee, and where their IPCR goes.

    Read only, all of it. The record is maintained on the admin screens, and
    the chain is read off the org chart rather than stored - so a page that
    let either be edited here would be lying about who decides.
--}}
@php
    $facts = $employee
        ? collect([
            'Employee number' => $employee->employee_number,
            'Name'            => $employee->full_name,
            'Position'        => $employee->position?->title,
            'Division'        => $employee->effective_division?->name,
            'Section'         => $employee->section?->name,
            'Appointment'     => $employee->employment_status
                ? ucfirst(str_replace('_', ' ', $employee->employment_status))
                : null,
            'Post held'       => $employee->postTitle(),
        ])->filter(fn($value) => filled($value))
        : collect();

    $designations = $employee?->activeDesignations ?? collect();
@endphp

<section class="space-y-4">
    <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-6">
        <header class="flex flex-wrap items-baseline justify-between gap-3">
            <h2 class="text-lg font-medium text-gray-900">Employee Record</h2>

            @if ($employee)
                <x-admin.active-badge :active="$employee->is_active" />
            @endif
        </header>

        @if (! $employee)
            {{-- An account with no employee record. It happens: the system
                 administrator is a login, not a member of staff. --}}
            <p class="mt-3 rounded-md bg-gray-50 p-3 text-sm text-gray-600 ring-1 ring-inset ring-gray-200">
                This account is not linked to an employee record, so it has no IPCR of its own. Ask HR to link it
                if that is wrong.
            </p>
        @else
            <p class="mt-1 text-sm text-gray-600">
                Kept by HR. Ask them to correct anything here that is out of date.
            </p>

            <dl class="mt-4 grid gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($facts as $label => $value)
                    <div>
                        <dt class="text-[0.625rem] font-bold uppercase tracking-wider text-gray-400">{{ $label }}</dt>
                        <dd class="mt-0.5 text-sm text-gray-900">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($designations->isNotEmpty())
                <div class="mt-4 border-t border-gray-100 pt-3">
                    <p class="text-[0.625rem] font-bold uppercase tracking-wider text-gray-400">
                        Designations held
                    </p>

                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        @foreach ($designations as $designation)
                            <span
                                class="inline-flex items-center rounded-full bg-mint-100 px-2.5 py-0.5 text-xs font-medium text-mint-800 ring-1 ring-inset ring-mint-500/25">
                                {{ $designation->title }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </div>

    @if ($employee)
        {{-- Where the sheet goes once it is submitted. Read off the org chart
             every time rather than stored: a change of head has to carry, and
             an employee should be able to see who it will reach before they
             commit to sending it. --}}
        <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-6">
            <h2 class="text-lg font-medium text-gray-900">Where My IPCR Goes</h2>
            <p class="mt-1 text-sm text-gray-600">
                Two steps, in this order, once you submit.
            </p>

            @if ($chainProblem)
                <p class="mt-4 rounded-md bg-amber-50 p-3 text-sm text-amber-900 ring-1 ring-inset ring-amber-500/30">
                    {{ $chainProblem }}
                </p>
            @else
                <ol class="mt-4 grid gap-2 sm:grid-cols-2">
                    @foreach ([
        ['step' => 1, 'label' => 'For Assessment', 'person' => $chain->assessor],
        ['step' => 2, 'label' => 'For Final Approval', 'person' => $chain->finalApprover],
    ] as $stage)
                        <li class="flex items-start gap-3 rounded-lg bg-gray-50 p-3 ring-1 ring-inset ring-gray-200">
                            <span
                                class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-nav-900 font-data text-xs font-semibold text-white">
                                {{ $stage['step'] }}
                            </span>

                            <span class="min-w-0">
                                <span
                                    class="block text-[0.625rem] font-bold uppercase tracking-wider text-gray-400">{{ $stage['label'] }}</span>
                                <span class="block text-sm font-medium text-gray-900">
                                    {{ $stage['person']->full_name }}
                                </span>
                                @if ($stage['person']->postTitle())
                                    <span class="block text-xs text-gray-500">{{ $stage['person']->postTitle() }}</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    @endif
</section>
