<?php

use App\Console\Commands\BackfillCycleFieldSensitivityCommand;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestDataEntry;
use App\Models\Role;
use App\Services\LoanRequests\LoanRequestDataService;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

/**
 * Dependent/spouse cycle_status and cycle_number are staff-verified against
 * the member's insurance history (see LoanRequestCycleStateService's
 * docblock), not a member declaration -- unlike applicant_pep_status in
 * LoanRequestApplyStaffUpdatesLegacyBackfillTest, an edit to an existing
 * dependent cycle entry must NOT require member reconfirmation, since there
 * is no member-facing UI for staff-only processing fields.
 */
test('a staff edit to an existing dependent cycle field does not require member reconfirmation', function (): void {
    $actor = AppUser::factory()->create(['email_verified_at' => now()]);
    $loanRequest = LoanRequest::factory()->create();

    $dataService = app(LoanRequestDataService::class);

    $dataService->backfillField(
        $loanRequest,
        'dependent_child_1_cycle_status',
        'New',
        confirmedByMember: true,
    );

    $dataService->applyStaffUpdates(
        $loanRequest,
        $actor,
        ['dependent_child_1_cycle_status' => 'Old'],
        'Corrected after checking loan history.',
    );

    $entry = LoanRequestDataEntry::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('field_key', 'dependent_child_1_cycle_status')
        ->sole();

    $loanRequest->unsetRelation('dataEntries');

    expect($entry->is_sensitive)->toBeFalse()
        ->and($dataService->unconfirmedSensitiveFields($loanRequest))->not->toContain('dependent_child_1_cycle_status');
});

test('a never-captured dependent cycle field written by staff is not sensitive either', function (): void {
    $actor = AppUser::factory()->create(['email_verified_at' => now()]);
    $loanRequest = LoanRequest::factory()->create();

    app(LoanRequestDataService::class)->applyStaffUpdates(
        $loanRequest,
        $actor,
        ['dependent_spouse_cycle_status' => 'New'],
        'Entered from the physical form.',
    );

    $entry = LoanRequestDataEntry::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('field_key', 'dependent_spouse_cycle_status')
        ->sole();

    expect($entry->is_sensitive)->toBeFalse()
        ->and($entry->owner_type)->toBe('staff');
});

test('applicant_pep_status is unaffected and still requires reconfirmation on edit', function (): void {
    $actor = AppUser::factory()->create(['email_verified_at' => now()]);
    $loanRequest = LoanRequest::factory()->create();

    $dataService = app(LoanRequestDataService::class);

    $dataService->backfillField($loanRequest, 'applicant_pep_status', true, confirmedByMember: true);
    $dataService->applyStaffUpdates($loanRequest, $actor, ['applicant_pep_status' => false], 'Correction.');

    $loanRequest->unsetRelation('dataEntries');

    expect($dataService->unconfirmedSensitiveFields($loanRequest))->toContain('applicant_pep_status');
});

function cycleSensitivityCreateEntry(
    LoanRequest $loanRequest,
    string $fieldKey,
    mixed $value,
    bool $isSensitive = true,
): LoanRequestDataEntry {
    return LoanRequestDataEntry::query()->create([
        'loan_request_id' => $loanRequest->id,
        'section_key' => 'dependents',
        'field_key' => $fieldKey,
        'owner_type' => 'member',
        'is_sensitive' => $isSensitive,
        'confirmed_by_member' => false,
        'confirmed_by_member_at' => null,
        'value_json' => ['value' => $value],
    ]);
}

test('backfill dry run reports stale dependent cycle entries without writing', function (): void {
    $loanRequest = LoanRequest::factory()->create();
    cycleSensitivityCreateEntry($loanRequest, 'dependent_child_1_cycle_status', 'New');
    cycleSensitivityCreateEntry($loanRequest, 'dependent_spouse_cycle_number', 2);

    $this->artisan('loan-requests:backfill-cycle-field-sensitivity')
        ->expectsOutputToContain('Stale entries found: 2')
        ->assertExitCode(0);

    expect(LoanRequestDataEntry::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('is_sensitive', true)
        ->count())->toBe(2);
});

test('backfill fix mode clears is_sensitive on stale dependent cycle entries only', function (): void {
    $loanRequest = LoanRequest::factory()->create();
    cycleSensitivityCreateEntry($loanRequest, 'dependent_child_1_cycle_status', 'New');
    cycleSensitivityCreateEntry($loanRequest, 'dependent_extended_3_cycle_number', 1);
    cycleSensitivityCreateEntry($loanRequest, 'applicant_pep_status', true);

    $this->artisan('loan-requests:backfill-cycle-field-sensitivity --fix')
        ->expectsOutputToContain('Stale entries found: 2')
        ->expectsOutputToContain('Updated: 2')
        ->assertExitCode(0);

    expect(LoanRequestDataEntry::query()
        ->where('loan_request_id', $loanRequest->id)
        ->whereIn('field_key', ['dependent_child_1_cycle_status', 'dependent_extended_3_cycle_number'])
        ->where('is_sensitive', false)
        ->count())->toBe(2);

    expect(LoanRequestDataEntry::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('field_key', 'applicant_pep_status')
        ->value('is_sensitive'))->toBeTrue();
});

test('dependent cycle field key list covers every category slot plus spouse and applicant', function (): void {
    $keys = BackfillCycleFieldSensitivityCommand::dependentCycleFieldKeys();

    expect($keys)->toHaveCount(26)
        ->and($keys)->toContain('dependent_child_3_cycle_number')
        ->and($keys)->toContain('dependent_extended_1_cycle_status')
        ->and($keys)->toContain('dependent_parent_2_cycle_status')
        ->and($keys)->toContain('dependent_sibling_2_cycle_number')
        ->and($keys)->toContain('dependent_spouse_cycle_status')
        ->and($keys)->toContain('applicant_cycle_status')
        ->and($keys)->toContain('applicant_cycle_number');
});

test('a never-captured applicant cycle field written by staff is not sensitive', function (): void {
    $actor = AppUser::factory()->create(['email_verified_at' => now()]);
    $loanRequest = LoanRequest::factory()->create();

    app(LoanRequestDataService::class)->applyStaffUpdates(
        $loanRequest,
        $actor,
        ['applicant_cycle_status' => 'New'],
        'Entered from the physical form.',
    );

    $entry = LoanRequestDataEntry::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('field_key', 'applicant_cycle_status')
        ->sole();

    expect($entry->is_sensitive)->toBeFalse()
        ->and($entry->owner_type)->toBe('staff');
});

test('backfill fix mode also clears stale applicant cycle entries', function (): void {
    $loanRequest = LoanRequest::factory()->create();
    cycleSensitivityCreateEntry($loanRequest, 'applicant_cycle_status', 'New');
    cycleSensitivityCreateEntry($loanRequest, 'applicant_cycle_number', 3);

    $this->artisan('loan-requests:backfill-cycle-field-sensitivity --fix')
        ->expectsOutputToContain('Stale entries found: 2')
        ->expectsOutputToContain('Updated: 2')
        ->assertExitCode(0);

    expect(LoanRequestDataEntry::query()
        ->where('loan_request_id', $loanRequest->id)
        ->whereIn('field_key', ['applicant_cycle_status', 'applicant_cycle_number'])
        ->where('is_sensitive', false)
        ->count())->toBe(2);
});
