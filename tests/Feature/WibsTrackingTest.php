<?php

use App\LoanRequestDocumentKey;
use App\LoanRequestDocumentReadinessStatus;
use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\LoanRequestDataEntry;
use App\Models\LoanRequestDocument;
use App\Models\Role;
use App\Services\LoanRequests\LoanRequestDocumentWorkflowService;
use App\Services\LoanRequests\WibsTrackingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

function createWibsStaff(): AppUser
{
    $user = AppUser::factory()->create(['email_verified_at' => now()]);
    Role::attachNamedRole($user, Role::LOAN_MANAGER);
    $user->forceFill(['two_factor_secret' => 'fakesecret', 'two_factor_confirmed_at' => now()])->save();

    return $user->fresh(['roles.permissions', 'staffAccessControl']);
}

function createWibsMember(): AppUser
{
    $user = AppUser::factory()->create(['email_verified_at' => now()]);
    Role::attachNamedRole($user, Role::MEMBER);

    return $user->fresh(['roles.permissions', 'staffAccessControl']);
}

// ── Happy path ────────────────────────────────────────────────────────────────

test('markForEncoding transitions ConvertedToLoan to ForWibsEncoding and records change', function (): void {
    Notification::fake();

    $member = createWibsMember();
    $staff = createWibsStaff();
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::ConvertedToLoan,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    app(WibsTrackingService::class)->markForEncoding($loanRequest, $staff);

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::ForWibsEncoding)
        ->and($loanRequest->wibs_encoded_at)->not->toBeNull()
        ->and(
            LoanRequestChange::where('loan_request_id', $loanRequest->id)
                ->where('action', LoanRequestChange::ACTION_MARK_FOR_WIBS_ENCODING)
                ->exists(),
        )->toBeTrue();
});

test('recordWibsReference transitions ForWibsEncoding to WibsLoanCreated and stores reference', function (): void {
    Notification::fake();

    $member = createWibsMember();
    $staff = createWibsStaff();
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::ForWibsEncoding,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    app(WibsTrackingService::class)->recordWibsReference($loanRequest, $staff, 'WIBS-2026-001');

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::WibsLoanCreated)
        ->and($loanRequest->wibs_loan_reference)->toBe('WIBS-2026-001')
        ->and(
            LoanRequestChange::where('loan_request_id', $loanRequest->id)
                ->where('action', LoanRequestChange::ACTION_RECORD_WIBS_REFERENCE)
                ->exists(),
        )->toBeTrue();
});

test('scheduleRelease transitions WibsLoanCreated to ReleaseScheduled and stores date', function (): void {
    Notification::fake();

    $member = createWibsMember();
    $staff = createWibsStaff();
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::WibsLoanCreated,
        'wibs_loan_reference' => 'WIBS-2026-001',
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    app(WibsTrackingService::class)->scheduleRelease(
        $loanRequest,
        $staff,
        Carbon::parse('2026-07-01'),
    );

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::ReleaseScheduled)
        ->and($loanRequest->wibs_release_date?->toDateString())->toBe('2026-07-01')
        ->and(
            LoanRequestChange::where('loan_request_id', $loanRequest->id)
                ->where('action', LoanRequestChange::ACTION_SCHEDULE_WIBS_RELEASE)
                ->exists(),
        )->toBeTrue();
});

test('scheduleRelease marks the authority-to-deduct document stale since its start date just became computable', function (): void {
    Notification::fake();

    $member = createWibsMember();
    $staff = createWibsStaff();
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::WibsLoanCreated,
        'wibs_loan_reference' => 'WIBS-2026-001',
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    // Authority to Deduct is only applicable to BLGU/LGU/MRDINC/LDH institutional
    // payroll employees -- give this applicant an MRDINC employer so the document
    // stays applicable and this test still exercises staleness, not NotApplicable.
    \App\Models\LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(\App\LoanRequestPersonRole::Applicant)
        ->create(['employer_business_name' => 'MRDINC Head Office']);

    // Authority to Deduct also requires payment_option to be Salary Deduction,
    // separate from the institutional category above.
    LoanRequestDataEntry::query()->create([
        'loan_request_id' => $loanRequest->id,
        'field_key' => 'payment_option',
        'section_key' => 'banking',
        'owner_type' => 'member',
        'value_type' => 'string',
        'value_json' => ['value' => \App\LoanPaymentOption::SalaryDeduction->value],
        'is_sensitive' => true,
        'confirmed_by_member' => false,
        'confirmed_by_member_at' => null,
    ]);

    // Seed a real source_hash/readiness row via the normal checklist evaluation first, so
    // that scheduling release below is the only thing that changes -- otherwise an
    // incidental hash mismatch (not the wibs_release_date registration) could produce the
    // same GeneratedStale outcome and the test wouldn't prove what it claims to.
    app(LoanRequestDocumentWorkflowService::class)->refreshChecklist($loanRequest);

    $document = LoanRequestDocument::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('document_key', LoanRequestDocumentKey::AuthorityToDeduct->value)
        ->firstOrFail();

    $relativePath = sprintf('loan-request-documents/%d/authority-to-deduct/test-preview.pdf', $loanRequest->id);
    $absolutePath = Storage::disk('local')->path($relativePath);
    File::ensureDirectoryExists(dirname($absolutePath));
    File::put($absolutePath, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R>>endobj\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n0\n%%EOF");

    $document->fill([
        'readiness_status' => LoanRequestDocumentReadinessStatus::GeneratedCurrent,
        'generated_disk' => 'local',
        'generated_path' => $relativePath,
        'generated_version' => 1,
        'generated_filename' => 'authority-to-deduct.pdf',
        'generated_mime_type' => 'application/pdf',
        'generated_by' => $staff->user_id,
        'generated_at' => now(),
    ])->save();

    // The earlier refreshChecklist() call cached the (now stale) documents relation on
    // this LoanRequest instance -- reload it so scheduleRelease() below sees the
    // GeneratedCurrent row just saved, not the pre-generation snapshot.
    $loanRequest->refresh();

    app(WibsTrackingService::class)->scheduleRelease(
        $loanRequest,
        $staff,
        Carbon::parse('2026-07-01'),
    );

    expect($document->refresh()->readiness_status)
        ->toBe(LoanRequestDocumentReadinessStatus::GeneratedStale);
});

test('confirmRelease transitions ReleaseScheduled to Released and sets wibs_released_at', function (): void {
    Notification::fake();

    $member = createWibsMember();
    $staff = createWibsStaff();
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::ReleaseScheduled,
        'wibs_loan_reference' => 'WIBS-2026-001',
        'wibs_release_date' => now()->addDay(),
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    app(WibsTrackingService::class)->confirmRelease($loanRequest, $staff);

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::Released)
        ->and($loanRequest->wibs_released_at)->not->toBeNull()
        ->and(
            LoanRequestChange::where('loan_request_id', $loanRequest->id)
                ->where('action', LoanRequestChange::ACTION_CONFIRM_WIBS_RELEASE)
                ->exists(),
        )->toBeTrue();
});

// ── Invalid predecessor ───────────────────────────────────────────────────────

test('markForEncoding throws InvalidArgumentException when status is not ConvertedToLoan', function (): void {
    $member = createWibsMember();
    $staff = createWibsStaff();
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Approved,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    expect(fn () => app(WibsTrackingService::class)->markForEncoding($loanRequest, $staff))
        ->toThrow(InvalidArgumentException::class);
});

test('recordWibsReference throws when status is not ForWibsEncoding', function (): void {
    $member = createWibsMember();
    $staff = createWibsStaff();
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::ConvertedToLoan,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    expect(fn () => app(WibsTrackingService::class)->recordWibsReference($loanRequest, $staff, 'WIBS-001'))
        ->toThrow(InvalidArgumentException::class);
});

test('scheduleRelease throws when status is not WibsLoanCreated', function (): void {
    $member = createWibsMember();
    $staff = createWibsStaff();
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::ForWibsEncoding,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    expect(fn () => app(WibsTrackingService::class)->scheduleRelease($loanRequest, $staff, Carbon::parse('2026-07-01')))
        ->toThrow(InvalidArgumentException::class);
});

test('confirmRelease throws when status is not ReleaseScheduled', function (): void {
    $member = createWibsMember();
    $staff = createWibsStaff();
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::WibsLoanCreated,
        'wibs_loan_reference' => 'WIBS-001',
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    expect(fn () => app(WibsTrackingService::class)->confirmRelease($loanRequest, $staff))
        ->toThrow(InvalidArgumentException::class);
});

// ── HTTP auth ─────────────────────────────────────────────────────────────────

test('member gets 403 on mark-for-encoding endpoint', function (): void {
    $member = createWibsMember();
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::ConvertedToLoan,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    $this->actingAs($member)
        ->patch(route('staff.loan-requests.wibs.mark-for-encoding', $loanRequest))
        ->assertStatus(403);
});

test('loan manager can mark a loan for WIBS encoding via HTTP', function (): void {
    Notification::fake();

    $member = createWibsMember();
    $staff = createWibsStaff();
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::ConvertedToLoan,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    $this->actingAs($staff)
        ->patch(route('staff.loan-requests.wibs.mark-for-encoding', $loanRequest))
        ->assertRedirectToRoute('staff.loan-requests.show', $loanRequest);

    expect($loanRequest->fresh()->status)->toBe(LoanRequestStatus::ForWibsEncoding);
});

test('loan manager can record WIBS reference via HTTP', function (): void {
    Notification::fake();

    $member = createWibsMember();
    $staff = createWibsStaff();
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::ForWibsEncoding,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    $this->actingAs($staff)
        ->patch(route('staff.loan-requests.wibs.record-reference', $loanRequest), [
            'wibs_loan_reference' => 'WIBS-2026-999',
        ])
        ->assertRedirectToRoute('staff.loan-requests.show', $loanRequest);

    expect($loanRequest->fresh()->wibs_loan_reference)->toBe('WIBS-2026-999');
});

// ── Member visibility ─────────────────────────────────────────────────────────

test('member does not see wibs_loan_reference on client loan request page', function (): void {
    $member = createWibsMember();
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::WibsLoanCreated,
        'wibs_loan_reference' => 'SECRET-REF-12345',
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    $this->withoutMiddleware(\App\Http\Middleware\EnsureMemberProfileComplete::class)
        ->actingAs($member)
        ->get(route('client.loan-requests.show', $loanRequest))
        ->assertOk()
        ->assertDontSee('SECRET-REF-12345');
});

// ── Preflight ─────────────────────────────────────────────────────────────────

test('preflight blocks when WibsLoanCreated request has null wibs_loan_reference', function (): void {
    $member = createWibsMember();
    LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::WibsLoanCreated,
        'wibs_loan_reference' => null,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    $this->artisan('loan-workflow:preflight', ['--stage' => 'post-migration'])
        ->expectsOutputToContain('wibs_reference_missing')
        ->assertFailed();
});
