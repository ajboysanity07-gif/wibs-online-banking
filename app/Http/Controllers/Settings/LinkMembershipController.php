<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\LinkMembershipRequest;
use App\Models\Wmaster;
use App\Services\Admin\StaffManagementService;
use App\Support\MemberVerificationMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LinkMembershipController extends Controller
{
    public function store(
        LinkMembershipRequest $request,
        MemberVerificationMatcher $matcher,
        StaffManagementService $service,
    ): RedirectResponse {
        $validated = $request->validated();
        $accountNumber = $matcher->normalizeAccountNumber($validated['accntno']);

        $member = Wmaster::find($accountNumber);

        if ($member === null) {
            Log::warning('Staff link-membership: member not found in wmaster.', [
                'actor_user_id' => $request->user()?->user_id,
                'accntno' => $accountNumber,
            ]);

            throw ValidationException::withMessages([
                'accntno' => "We couldn't verify that account number and name against WIBS records. Please check and try again.",
            ]);
        }

        $comparison = $matcher->compare(
            $member,
            $validated['last_name'] ?? null,
            $validated['first_name'] ?? null,
            $validated['middle_initial'] ?? null,
        );

        if (! $comparison['matches']) {
            Log::warning('Staff link-membership: wmaster verification failed.', [
                'actor_user_id' => $request->user()?->user_id,
                'accntno' => $accountNumber,
                'failure' => $comparison['failure'] ?? 'unknown',
            ]);

            throw ValidationException::withMessages([
                'accntno' => "We couldn't verify that account number and name against WIBS records. Please check and try again.",
            ]);
        }

        $service->linkMembership($request->user(), $accountNumber);

        return to_route('profile.edit')->with('status', 'membership-linked');
    }
}
