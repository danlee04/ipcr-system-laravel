<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\IpcrPeriod;
use App\Models\Section;
use App\Services\PeriodSummaryService;
use App\Support\SummaryRow;
use App\Support\SummaryTally;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The sheet HR hands in at the end of a rating period.
 *
 * Read-only, like the IPCR list beside it. The difference is what it is built
 * from: this one starts at the roll of employees, so the people who never
 * started an IPCR appear on it. They are the reason it exists.
 */
class SummaryController extends Controller
{
    public function __construct(private readonly PeriodSummaryService $summary) {}

    public function index(Request $request): View
    {
        $period = $this->periodFor($request);

        $rows = $period === null
            ? collect()
            : $this->summary->rows($period, $request->integer('division') ?: null, $request->integer('section') ?: null);

        return view('admin.summary.index', [
            'period'    => $period,
            'periods'   => IpcrPeriod::query()->orderByDesc('start_date')->get(),
            'divisions' => Division::query()->orderBy('name')->get(),
            'sections'  => Section::query()->orderBy('name')->get(),
            'gathered'  => $this->summary->gather($rows),
            'overall'   => SummaryTally::of($rows),
        ]);
    }

    /**
     * The same sheet as a file.
     *
     * Streamed rather than built in memory: a hospital-wide roll is thousands
     * of rows, and there is no reason to hold all of them at once.
     */
    public function export(Request $request): StreamedResponse
    {
        $period = $this->periodFor($request);
        abort_if($period === null, 404, 'There is no rating period to report on.');

        $rows = $this->summary->rows(
            $period,
            $request->integer('division') ?: null,
            $request->integer('section') ?: null,
        );

        $name = 'ipcr-summary-' . Str::slug($period->name) . '.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            // Excel reads a bare UTF-8 CSV as Windows-1252 and mangles the
            // accented names. The byte order mark is what tells it otherwise.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Employee No', 'Last Name', 'First Name', 'Middle Name', 'Position',
                'Division', 'Section', 'Status', 'Final Rating', 'Adjectival Rating',
                'Submitted', 'Approved',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, $this->csvRow($row));
            }

            fclose($handle);
        }, $name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return list<string> */
    private function csvRow(SummaryRow $row): array
    {
        $ipcr = $row->ipcr;

        return [
            (string) $row->employee->employee_number,
            (string) $row->employee->last_name,
            (string) $row->employee->first_name,
            (string) $row->employee->middle_name,
            (string) $row->employee->position?->title,
            $row->divisionName(),
            $row->sectionName(),
            $row->statusLabel(),

            // Blank, not zero, while it is unapproved: a rating on this sheet
            // is one that has been signed off.
            $row->approvedRating() === null ? '' : number_format($row->approvedRating(), 2, '.', ''),
            $row->isApproved() ? (string) $ipcr->final_adjectival_rating : '',
            $ipcr?->submitted_at?->format('Y-m-d') ?? '',
            $ipcr?->approved_at?->format('Y-m-d') ?? '',
        ];
    }

    /** The period asked for, or the one currently open. */
    private function periodFor(Request $request): ?IpcrPeriod
    {
        $asked = $request->integer('period');

        return ($asked ? IpcrPeriod::find($asked) : null) ?? IpcrPeriod::active() ?? IpcrPeriod::query()
            ->orderByDesc('start_date')
            ->first();
    }
}
