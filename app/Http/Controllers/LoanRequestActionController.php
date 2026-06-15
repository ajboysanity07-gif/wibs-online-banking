<?php

namespace App\Http\Controllers;

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoanRequestActionController extends Controller
{
    public function __invoke(
        Request $request,
        string $reference,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user instanceof AppUser, 403);

        $loanRequest = LoanRequest::query()
            ->where('reference', $reference)
            ->where(function ($query) use ($user): void {
                $query
                    ->where('user_id', $user->user_id)
                    ->orWhere('acctno', $user->acctno);
            })
            ->firstOrFail();

        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;

        return match ($status) {
            LoanRequestStatus::NeedsRevision->value => redirect()->route(
                'client.loan-requests.create',
            ),
            LoanRequestStatus::AwaitingMemberInformation->value,
            LoanRequestStatus::AwaitingMemberAcceptance->value => redirect()->route(
                'client.loan-requests.show',
                [
                    'loanRequest' => $loanRequest->id,
                    'action' => 1,
                ],
            ),
            default => abort(
                403,
                'This loan request does not currently have an available member action.',
            ),
        };
    }
}
