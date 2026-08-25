<?php

namespace App\Http\Controllers;

use App\Enums\IpcrStatus;
use App\Exceptions\IpcrRoutingException;
use App\Models\Ipcr;
use App\Models\IpcrPeriod;
use App\Services\FunctionCatalogService;
use App\Services\IpcrRoutingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IpcrController extends Controller
{
    public function __construct(
        private readonly FunctionCatalogService $functionCatalog,
        private readonly IpcrRoutingService $routing,
    ) {}

    /** List of the current user's own IPCRs. */
    public function index(Request $request): View
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'No employee record is linked to your account.');

        $ipcrs = $employee->ipcrs()->with('period')->latest('id')->paginate(10);

        return view('ipcrs.index', compact('ipcrs'));
    }

    /** Show the form to start a new IPCR for the current open rating period. */
    public function create(Request $request): View|RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'No employee record is linked to your account.');

        $period = IpcrPeriod::open()->latest('start_date')->first();

        if ($period === null) {
            return back()->with('error', 'There is no open rating period right now. Contact HR/Admin.');
        }

        if ($employee->ipcrs()->where('ipcr_period_id', $period->id)->exists()) {
            return redirect()->route('ipcrs.index')->with('error', 'You already have an IPCR for the current period.');
        }

        $catalog = $this->functionCatalog->availableFor($employee);

        return view('ipcrs.create', compact('period', 'catalog'));
    }

    /**
     * Create the draft IPCR header. No items yet - those are added
     * afterward through IpcrItemController on the "show" screen.
     */
    public function store(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'No employee record is linked to your account.');

        $period = IpcrPeriod::open()->latest('start_date')->first();
        abort_unless($period, 404, 'No open rating period found.');

        $ipcr = Ipcr::firstOrCreate(
            ['employee_id' => $employee->id, 'ipcr_period_id' => $period->id],
            [
                'position_title' => $employee->position?->title,
                'office_name'    => $employee->section?->name ?? $employee->division?->name,
                'status'         => IpcrStatus::Draft,
            ]
        );

        return redirect()->route('ipcrs.show', $ipcr)
            ->with('status', 'Draft IPCR created. Add your functions below.');
    }

    /** The main working screen: view items, add/edit them, and submit. */
    public function show(Request $request, Ipcr $ipcr): View
    {
        $this->authorize('view', $ipcr);

        $ipcr->load(['items', 'period', 'employee', 'assessor', 'finalApprover', 'approvals.approver']);
        $catalog = $this->functionCatalog->availableFor($ipcr->employee);

        return view('ipcrs.show', compact('ipcr', 'catalog'));
    }

    /**
     * Submit the IPCR: resolve the approval chain via IpcrRoutingService,
     * stamp the assessor/final approver on the record, and move the
     * status to "submitted" (shown to users as "For Assessment").
     */
    public function submit(Request $request, Ipcr $ipcr): RedirectResponse
    {
        $this->authorize('update', $ipcr);

        if (! $ipcr->isEditableByOwner()) {
            return back()->with('error', 'This IPCR can no longer be submitted from its current status.');
        }

        if ($ipcr->items()->doesntExist()) {
            return back()->with('error', 'Add at least one function before submitting.');
        }

        try {
            $chain = $this->routing->resolve($ipcr->employee);
        } catch (IpcrRoutingException $e) {
            return back()->with('error', $e->getMessage());
        }

        $ipcr->update([
            'assessor_employee_id'       => $chain->assessor->id,
            'final_approver_employee_id' => $chain->finalApprover->id,
            'status'                     => IpcrStatus::Submitted,
            'submitted_at'               => now(),
        ]);

        return redirect()->route('ipcrs.show', $ipcr)
            ->with('status', "Submitted for assessment to {$chain->assessor->full_name}.");
    }
}
