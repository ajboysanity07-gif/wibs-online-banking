<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\ConfirmWibsReleaseRequest;
use App\Http\Requests\Workflow\MarkForWibsEncodingRequest;
use App\Http\Requests\Workflow\RecordWibsReferenceRequest;
use App\Http\Requests\Workflow\ScheduleWibsReleaseRequest;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Services\LoanRequests\WibsTrackingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;

class WibsTrackingController extends Controller
{
    public function markForEncoding(
        MarkForWibsEncodingRequest $request,
        LoanRequest $loanRequest,
        WibsTrackingService $service,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof AppUser, 403);

        $service->markForEncoding($loanRequest, $actor);

        return redirect()->route('staff.loan-requests.show', $loanRequest)
            ->with('success', 'Loan request marked for WIBS encoding.');
    }

    public function recordReference(
        RecordWibsReferenceRequest $request,
        LoanRequest $loanRequest,
        WibsTrackingService $service,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof AppUser, 403);

        $service->recordWibsReference(
            $loanRequest,
            $actor,
            (string) $request->validated('wibs_loan_reference'),
        );

        return redirect()->route('staff.loan-requests.show', $loanRequest)
            ->with('success', 'WIBS loan reference recorded.');
    }

    public function scheduleRelease(
        ScheduleWibsReleaseRequest $request,
        LoanRequest $loanRequest,
        WibsTrackingService $service,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof AppUser, 403);

        $service->scheduleRelease(
            $loanRequest,
            $actor,
            Carbon::parse((string) $request->validated('wibs_release_date')),
        );

        return redirect()->route('staff.loan-requests.show', $loanRequest)
            ->with('success', 'WIBS release date scheduled.');
    }

    public function confirmRelease(
        ConfirmWibsReleaseRequest $request,
        LoanRequest $loanRequest,
        WibsTrackingService $service,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof AppUser, 403);

        $service->confirmRelease($loanRequest, $actor);

        return redirect()->route('staff.loan-requests.show', $loanRequest)
            ->with('success', 'Loan release confirmed.');
    }
}
