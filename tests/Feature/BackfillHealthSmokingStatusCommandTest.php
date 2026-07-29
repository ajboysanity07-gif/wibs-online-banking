<?php

use App\Models\LoanRequest;
use App\Models\LoanRequestDataEntry;

function backfillHealthCreateDataEntry(
    LoanRequest $loanRequest,
    string $sectionKey,
    string $fieldKey,
    mixed $value,
    bool $confirmedByMember = false,
): LoanRequestDataEntry {
    return LoanRequestDataEntry::query()->create([
        'loan_request_id' => $loanRequest->id,
        'section_key' => $sectionKey,
        'field_key' => $fieldKey,
        'owner_type' => 'member',
        'is_sensitive' => false,
        'confirmed_by_member' => $confirmedByMember,
        'confirmed_by_member_at' => $confirmedByMember ? now() : null,
        'value_json' => ['value' => $value],
    ]);
}

test('dry run reports the derived status without writing anything', function (): void {
    $loanRequest = LoanRequest::factory()->create();
    backfillHealthCreateDataEntry($loanRequest, 'health_glapi', 'gl_health_q11_smoker', true);

    $this->artisan('loan-requests:backfill-health-fields')
        ->assertExitCode(0);

    expect(
        LoanRequestDataEntry::query()
            ->where('loan_request_id', $loanRequest->id)
            ->where('field_key', 'health_smoking_status')
            ->exists(),
    )->toBeFalse();
});

test('fix mode backfills heavy when the GLAPI smoker boolean was true', function (): void {
    $loanRequest = LoanRequest::factory()->create();
    backfillHealthCreateDataEntry($loanRequest, 'health_glapi', 'gl_health_q11_smoker', true);

    $this->artisan('loan-requests:backfill-health-fields --fix')
        ->assertExitCode(0);

    $entry = LoanRequestDataEntry::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('field_key', 'health_smoking_status')
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->value_json['value'])->toBe('heavy');
});

test('fix mode backfills light when only the legacy smoker boolean was true', function (): void {
    $loanRequest = LoanRequest::factory()->create();
    backfillHealthCreateDataEntry($loanRequest, 'health', 'health_smoker', true);

    $this->artisan('loan-requests:backfill-health-fields --fix')
        ->assertExitCode(0);

    $entry = LoanRequestDataEntry::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('field_key', 'health_smoking_status')
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->value_json['value'])->toBe('light');
});

test('fix mode backfills none when neither legacy boolean was true', function (): void {
    $loanRequest = LoanRequest::factory()->create();
    backfillHealthCreateDataEntry($loanRequest, 'health', 'health_smoker', false);
    backfillHealthCreateDataEntry($loanRequest, 'health_glapi', 'gl_health_q11_smoker', false);

    $this->artisan('loan-requests:backfill-health-fields --fix')
        ->assertExitCode(0);

    $entry = LoanRequestDataEntry::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('field_key', 'health_smoking_status')
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->value_json['value'])->toBe('none');
});

test('fix mode does not overwrite a loan request that already has health_smoking_status set', function (): void {
    $loanRequest = LoanRequest::factory()->create();
    backfillHealthCreateDataEntry($loanRequest, 'health_glapi', 'gl_health_q11_smoker', true);
    backfillHealthCreateDataEntry($loanRequest, 'health', 'health_smoking_status', 'light');

    $this->artisan('loan-requests:backfill-health-fields --fix')
        ->assertExitCode(0);

    $entries = LoanRequestDataEntry::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('field_key', 'health_smoking_status')
        ->get();

    expect($entries)->toHaveCount(1);
    expect($entries->first()->value_json['value'])->toBe('light');
});

test('reports item 2e rows needing manual review without guessing an answer', function (): void {
    $loanRequest = LoanRequest::factory()->create();
    backfillHealthCreateDataEntry($loanRequest, 'health_glapi', 'gl_health_q02e_diabetes_renal', true);
    backfillHealthCreateDataEntry(
        $loanRequest,
        'health_glapi',
        'gl_health_q02e_diabetes_renal_details',
        'Type 2 diabetes since 2019.',
    );

    $this->artisan('loan-requests:backfill-health-fields --fix')
        ->expectsOutputToContain('need manual review')
        ->expectsOutputToContain((string) $loanRequest->id)
        ->assertExitCode(0);

    expect(
        LoanRequestDataEntry::query()
            ->where('loan_request_id', $loanRequest->id)
            ->whereIn('field_key', [
                'gl_health_q02e_diabetes',
                'gl_health_q02e_kidney',
                'gl_health_q02e_liver',
                'gl_health_q02e_urinary',
            ])
            ->exists(),
    )->toBeFalse();
});

test('fix mode carries forward member confirmation from the source field so the document gate actually clears', function (): void {
    $loanRequest = LoanRequest::factory()->create();
    backfillHealthCreateDataEntry(
        $loanRequest,
        'health_glapi',
        'gl_health_q11_smoker',
        true,
        confirmedByMember: true,
    );

    $this->artisan('loan-requests:backfill-health-fields --fix')
        ->assertExitCode(0);

    $entry = LoanRequestDataEntry::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('field_key', 'health_smoking_status')
        ->sole();

    expect($entry->value_json['value'])->toBe('heavy')
        ->and($entry->confirmed_by_member)->toBeTrue();
});

test('fix mode leaves the backfilled field unconfirmed when the source field was never confirmed by the member', function (): void {
    $loanRequest = LoanRequest::factory()->create();
    backfillHealthCreateDataEntry(
        $loanRequest,
        'health_glapi',
        'gl_health_q11_smoker',
        true,
        confirmedByMember: false,
    );

    $this->artisan('loan-requests:backfill-health-fields --fix')
        ->assertExitCode(0);

    $entry = LoanRequestDataEntry::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('field_key', 'health_smoking_status')
        ->sole();

    expect($entry->confirmed_by_member)->toBeFalse();
});

test('limit option caps the number of loan requests scanned', function (): void {
    $first = LoanRequest::factory()->create();
    $second = LoanRequest::factory()->create();
    backfillHealthCreateDataEntry($first, 'health_glapi', 'gl_health_q11_smoker', true);
    backfillHealthCreateDataEntry($second, 'health_glapi', 'gl_health_q11_smoker', true);

    $this->artisan('loan-requests:backfill-health-fields --fix --limit=1')
        ->assertExitCode(0);

    $backfilledCount = LoanRequestDataEntry::query()
        ->whereIn('loan_request_id', [$first->id, $second->id])
        ->where('field_key', 'health_smoking_status')
        ->count();

    expect($backfilledCount)->toBe(1);
});
