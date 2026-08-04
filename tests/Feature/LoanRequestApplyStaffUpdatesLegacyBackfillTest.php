<?php

use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestDataEntry;
use App\Models\Role;
use App\Services\LoanRequests\LoanRequestDataService;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

/**
 * Fields like applicant_pep_status/applicant_cycle_status were added after
 * many loan requests were already submitted, so those requests have no
 * LoanRequestDataEntry for them at all -- the member never had a chance to
 * provide (and therefore confirm) a value. Staff relaying the member's
 * verbal answer for a field that was genuinely never captured should count
 * as resolved, without forcing the request back through the member portal.
 * An edit to a field the member DID already provide must still require
 * member reconfirmation.
 */
test('staff update to a never-captured sensitive field is treated as confirmed', function (): void {
    $actor = AppUser::factory()->create(['email_verified_at' => now()]);
    $loanRequest = LoanRequest::factory()->create();

    app(LoanRequestDataService::class)->applyStaffUpdates(
        $loanRequest,
        $actor,
        ['applicant_pep_status' => false],
        'Member confirmed by phone: not a PEP.',
    );

    $entry = LoanRequestDataEntry::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('field_key', 'applicant_pep_status')
        ->sole();

    expect($entry->confirmed_by_member)->toBeTrue()
        ->and($entry->confirmed_by_member_at)->not->toBeNull();
});

test('staff edit to a sensitive field that already has an entry still requires member reconfirmation', function (): void {
    $actor = AppUser::factory()->create(['email_verified_at' => now()]);
    $loanRequest = LoanRequest::factory()->create();

    $dataService = app(LoanRequestDataService::class);

    // Member originally provided and confirmed this value themselves.
    $dataService->backfillField(
        $loanRequest,
        'applicant_pep_status',
        true,
        confirmedByMember: true,
    );

    $dataService->applyStaffUpdates(
        $loanRequest,
        $actor,
        ['applicant_pep_status' => false],
        'Correcting a data entry mistake.',
    );

    $entry = LoanRequestDataEntry::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('field_key', 'applicant_pep_status')
        ->sole();

    expect($entry->confirmed_by_member)->toBeFalse()
        ->and($entry->confirmed_by_member_at)->toBeNull();
});
