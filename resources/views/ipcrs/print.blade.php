@php
    use App\Enums\FunctionCategory;

    $blank = '<span class="blank"></span>';
    $mark = fn($value) => $value === null ? '' : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
@endphp

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>IPCR — {{ $ipcr->employee?->full_name }} — {{ $ipcr->period?->name }}</title>

    {{-- Deliberately not the app layout. This is the sheet that gets signed
         and filed; a sidebar and a Log out link have no place on it. Styles
         are inline because the page must print identically wherever it is
         opened, including from a machine with no access to the build. --}}
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Times New Roman", Times, serif;
            font-size: 10.5pt;
            line-height: 1.35;
            color: #000;
            background: #fff;
        }

        .sheet {
            max-width: 190mm;
            margin: 0 auto;
            padding: 8mm 0;
        }

        .toolbar {
            margin-bottom: 8mm;
            display: flex;
            gap: 8px;
            font-family: system-ui, sans-serif;
        }

        .toolbar button,
        .toolbar a {
            font: inherit;
            font-size: 10pt;
            padding: 6px 14px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #0f172a;
            text-decoration: none;
            cursor: pointer;
        }

        .toolbar button {
            background: #0d2233;
            border-color: #0d2233;
            color: #fff;
        }

        header {
            text-align: center;
            margin-bottom: 5mm;
        }

        header .agency {
            font-size: 10pt;
        }

        /* The hospital carries the letterhead - the lines around it are the
           frame, not the name. */
        header .agency-name {
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .02em;
        }

        header h1 {
            font-size: 13pt;
            font-weight: bold;
            margin: 3mm 0 1mm;
            letter-spacing: .02em;
        }

        header .period {
            font-size: 10.5pt;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4mm;
        }

        .meta td {
            padding: 1.5mm 0;
            vertical-align: bottom;
        }

        .meta .label {
            width: 26mm;
            font-size: 9.5pt;
        }

        .meta .value {
            border-bottom: 1px solid #000;
            padding-inline: 2mm;
            font-weight: bold;
        }

        table.form {
            width: 100%;
            border-collapse: collapse;
        }

        table.form th,
        table.form td {
            border: 1px solid #000;
            padding: 1.5mm 2mm;
            vertical-align: top;
        }

        table.form thead th {
            font-size: 9pt;
            text-align: center;
            background: #f1f1f1;
        }

        table.form tbody tr {
            page-break-inside: avoid;
        }

        .category-row td {
            background: #e8e8e8;
            font-weight: bold;
            font-size: 10pt;
        }

        .num {
            text-align: center;
            width: 11mm;
        }

        .weight {
            text-align: center;
            width: 14mm;
        }

        .indicator {
            font-size: 9.5pt;
        }

        .totals {
            margin-top: 4mm;
            width: 100%;
            border-collapse: collapse;
        }

        .totals td {
            border: 1px solid #000;
            padding: 2mm;
        }

        .totals .label {
            font-weight: bold;
        }

        .totals .figure {
            text-align: center;
            width: 30mm;
            font-weight: bold;
            font-size: 12pt;
        }

        .signatures {
            margin-top: 8mm;
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        .signatures td {
            width: 33.33%;
            padding: 0 4mm;
            vertical-align: top;
            text-align: center;
        }

        .signatures .role {
            font-size: 9pt;
            text-align: left;
            margin-bottom: 12mm;
        }

        .signatures .name {
            border-top: 1px solid #000;
            padding-top: 1.5mm;
            font-weight: bold;
            font-size: 10pt;
            min-height: 5mm;
        }

        .signatures .caption {
            font-size: 8.5pt;
        }

        .blank {
            display: inline-block;
            min-width: 8mm;
        }

        .note {
            margin-top: 4mm;
            font-size: 8.5pt;
            font-style: italic;
        }

        @media print {
            .toolbar {
                display: none;
            }

            .sheet {
                padding: 0;
                max-width: none;
            }
        }
    </style>
</head>

<body>
    <div class="sheet">
        <div class="toolbar">
            <button type="button" onclick="window.print()">Print</button>
            <a href="{{ route('ipcrs.show', $ipcr) }}">Back to the IPCR</a>
        </div>

        <header>
            <div class="agency">Republic of the Philippines</div>
            <div class="agency agency-name">{{ config('agency.name') }}</div>
            @if (config('agency.address'))
                <div class="agency">{{ config('agency.address') }}</div>
            @endif
            <h1>INDIVIDUAL PERFORMANCE COMMITMENT AND REVIEW</h1>
            <div class="period">{{ $ipcr->period?->name ?? 'No rating period' }}</div>
        </header>

        <table class="meta">
            <tr>
                <td class="label">Name</td>
                <td class="value">{{ $ipcr->employee?->full_name }}</td>
                <td class="label" style="padding-inline-start: 6mm;">Office</td>
                <td class="value">{{ $ipcr->office_name ?? $ipcr->employee?->section?->name }}</td>
            </tr>
            <tr>
                <td class="label">Position</td>
                <td class="value">{{ $ipcr->position_title ?? $ipcr->employee?->position?->title }}</td>
                <td class="label" style="padding-inline-start: 6mm;">Period covered</td>
                <td class="value">
                    @if ($ipcr->period?->start_date && $ipcr->period?->end_date)
                        {{ $ipcr->period->start_date->format('d M Y') }} –
                        {{ $ipcr->period->end_date->format('d M Y') }}
                    @endif
                </td>
            </tr>
        </table>

        <table class="form">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 34%;">Output / Major Final Output</th>
                    <th rowspan="2" style="width: 26%;">Success Indicator<br>(Target + Measure)</th>
                    <th rowspan="2" style="width: 20%;">Actual Accomplishment</th>
                    <th rowspan="2" class="weight">Weight</th>
                    <th colspan="4">Rating</th>
                </tr>
                <tr>
                    <th class="num">Q</th>
                    <th class="num">E</th>
                    <th class="num">T</th>
                    <th class="num">A</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($grouped as $categoryValue => $items)
                    @php $category = FunctionCategory::from($categoryValue); @endphp
                    <tr class="category-row">
                        <td colspan="8">{{ $category->label() }}</td>
                    </tr>

                    @foreach ($items as $item)
                        <tr>
                            <td>{{ $item->output }}</td>
                            <td class="indicator">{{ $item->success_indicator }}</td>
                            <td class="indicator">{{ $item->actual_accomplishment }}</td>
                            <td class="weight">{{ $item->weight !== null ? $mark($item->weight) . '%' : '' }}</td>
                            <td class="num">{{ $mark($item->quality_rating) }}</td>
                            <td class="num">{{ $mark($item->efficiency_rating) }}</td>
                            <td class="num">{{ $mark($item->timeliness_rating) }}</td>
                            <td class="num">{{ $mark($item->average_rating) }}</td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 8mm;">
                            No functions have been added to this IPCR.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Only what has actually been rated. A number nobody has given has
             no business appearing above a signature line. --}}
        <table class="totals">
            @foreach ($grouped as $categoryValue => $items)
                @php
                    $category = FunctionCategory::from($categoryValue);
                    $categoryRating = $breakdown->{$categoryValue} ?? null;
                    $categoryWeight = $breakdown->weights[$categoryValue] ?? null;
                @endphp
                <tr>
                    <td class="label">
                        {{ $category->label() }}
                        @if ($categoryWeight !== null)
                            <span style="font-weight: normal;">({{ $mark($categoryWeight) }}% of the final
                                rating)</span>
                        @endif
                    </td>
                    <td class="figure">{{ $categoryRating !== null ? number_format($categoryRating, 3) : '' }}</td>
                </tr>
            @endforeach

            <tr>
                <td class="label">Final Numerical Rating</td>
                <td class="figure">
                    {{ $ipcr->final_numerical_rating !== null ? number_format((float) $ipcr->final_numerical_rating, 3) : '' }}
                </td>
            </tr>
            <tr>
                <td class="label">Final Adjectival Rating</td>
                <td class="figure">{{ $ipcr->final_adjectival_rating }}</td>
            </tr>
        </table>

        @unless ($breakdown->complete)
            <p class="note">
                This IPCR is not fully rated. The rating rows are left blank on purpose.
            </p>
        @endunless

        <table class="signatures">
            <tr>
                <td>
                    <div class="role">Ratee</div>
                    <div class="name">{{ $ipcr->employee?->full_name }}</div>
                    <div class="caption">Signature over printed name</div>
                </td>
                <td>
                    <div class="role">Assessed by</div>
                    <div class="name">{{ $ipcr->assessor?->full_name }}</div>
                    <div class="caption">
                        {{ $ipcr->assessor?->postTitle() ?? 'Immediate Supervisor' }}
                        @if ($ipcr->assessed_at)
                            · {{ $ipcr->assessed_at->format('d M Y') }}
                        @endif
                    </div>
                </td>
                <td>
                    <div class="role">Final approval by</div>
                    <div class="name">{{ $ipcr->finalApprover?->full_name }}</div>
                    <div class="caption">
                        {{ $ipcr->finalApprover?->postTitle() ?? 'Approving Authority' }}
                        @if ($ipcr->approved_at)
                            · {{ $ipcr->approved_at->format('d M Y') }}
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>

</html>
