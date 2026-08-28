<?php

namespace App\Http\Controllers;

use App\Exceptions\IpcrRoutingException;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\IpcrRoutingService;
use App\Support\ApprovalChain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * The profile page: the record, the chain, then the two forms.
     *
     * Everything above the forms is read only. The employee record is HR's to
     * keep, and the chain is read off the org chart rather than stored - but
     * both decide how this person's IPCR behaves, and neither was visible
     * anywhere they could reach. The first time anyone learned who assessed
     * their sheet, it was because the sheet had already gone there.
     */
    public function edit(Request $request, IpcrRoutingService $routing): View
    {
        $user = $request->user();

        $employee = $user->employee?->loadMissing([
            'position', 'section.division', 'division', 'activeDesignations',
        ]);

        [$chain, $chainProblem] = $this->chainFor($routing, $employee);

        return view('profile.edit', compact('user', 'employee', 'chain', 'chainProblem'));
    }

    /**
     * Who assesses and who approves - or why nobody does yet.
     *
     * The routing exception already names the gap and who fixes it, so it is
     * shown rather than swallowed. Learning that no section head is assigned
     * is worth more here than at the moment the Submit button refuses.
     *
     * @return array{0: ?ApprovalChain, 1: ?string}
     */
    private function chainFor(IpcrRoutingService $routing, ?\App\Models\Employee $employee): array
    {
        if ($employee === null) {
            return [null, null];
        }

        try {
            return [$routing->resolve($employee), null];
        } catch (IpcrRoutingException $exception) {
            return [null, $exception->getMessage()];
        }
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

}
