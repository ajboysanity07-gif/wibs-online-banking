<?php

use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\LoanRequestCorrectionReport;
use App\Services\Admin\RequestsService;
use Illuminate\Support\Facades\Schema;

function createRequestsApiAdmin(string $acctno): User
{
    $admin = User::factory()->create([
        'acctno' => $acctno,
    ]);

    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    return $admin;
}

test('admin requests api returns correction report metadata when table exists', function () {
    $admin = createRequestsApiAdmin('009001');

    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'submitted_at' => now(),
    ]);

    LoanRequestCorrectionReport::factory()->create([
        'loan_request_id' => $loanRequest->id,
        'user_id' => $loanRequest->user_id,
        'issue_description' => 'Member name has a typo.',
        'correct_information' => 'Use official member profile name.',
        'status' => LoanRequestCorrectionReport::STATUS_OPEN,
    ]);

    $this
        ->actingAs($admin)
        ->get('/spa/admin/requests?perPage=10&page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.meta.available', true)
        ->assertJsonPath('data.meta.message', null)
        ->assertJsonPath('data.meta.openCorrectionReports', 1)
        ->assertJsonPath('data.items.0.id', $loanRequest->id)
        ->assertJsonPath('data.items.0.has_open_correction_report', true)
        ->assertJsonPath(
            'data.items.0.latest_correction_report_issue',
            'Member name has a typo.',
        );
});

test('admin requests api does not crash when correction report table is missing', function () {
    Schema::dropIfExists('loan_request_correction_reports');

    $admin = createRequestsApiAdmin('009002');
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->get('/spa/admin/requests?perPage=10&page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.meta.available', true)
        ->assertJsonPath('data.meta.message', null)
        ->assertJsonPath('data.meta.openCorrectionReports', 0)
        ->assertJsonPath('data.items.0.id', $loanRequest->id)
        ->assertJsonPath('data.items.0.has_open_correction_report', false)
        ->assertJsonPath('data.items.0.latest_correction_report_id', null);
});

test('admin requests api reported filter is safely unavailable when correction report table is missing', function () {
    Schema::dropIfExists('loan_request_correction_reports');

    $admin = createRequestsApiAdmin('009003');

    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->get('/spa/admin/requests?reported=1')
        ->assertOk()
        ->assertJsonCount(0, 'data.items')
        ->assertJsonPath('data.meta.available', false)
        ->assertJsonPath(
            'data.meta.message',
            RequestsService::REPORTED_REQUESTS_UNAVAILABLE_MESSAGE,
        )
        ->assertJsonPath('data.meta.openCorrectionReports', 0);
});

test('admin reported requests api returns safe unavailable response when correction report table is missing', function () {
    Schema::dropIfExists('loan_request_correction_reports');

    $admin = createRequestsApiAdmin('009004');

    $this
        ->actingAs($admin)
        ->get('/spa/admin/requests/reported')
        ->assertOk()
        ->assertJsonCount(0, 'data.items')
        ->assertJsonPath('data.meta.available', false)
        ->assertJsonPath(
            'data.meta.message',
            RequestsService::REPORTED_REQUESTS_UNAVAILABLE_MESSAGE,
        )
        ->assertJsonPath('data.meta.openCorrectionReports', 0);
});

test('admin reported requests api still returns open reported requests when table exists', function () {
    $admin = createRequestsApiAdmin('009005');

    $openReported = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'submitted_at' => now(),
    ]);

    LoanRequestCorrectionReport::factory()->create([
        'loan_request_id' => $openReported->id,
        'user_id' => $openReported->user_id,
        'issue_description' => 'Address field is outdated.',
        'correct_information' => 'Use updated branch address.',
        'status' => LoanRequestCorrectionReport::STATUS_OPEN,
    ]);

    $resolvedReported = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'submitted_at' => now(),
    ]);

    LoanRequestCorrectionReport::factory()->create([
        'loan_request_id' => $resolvedReported->id,
        'user_id' => $resolvedReported->user_id,
        'status' => LoanRequestCorrectionReport::STATUS_RESOLVED,
    ]);

    $this
        ->actingAs($admin)
        ->get('/spa/admin/requests/reported')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.meta.available', true)
        ->assertJsonPath('data.meta.message', null)
        ->assertJsonPath('data.meta.openCorrectionReports', 1)
        ->assertJsonPath('data.items.0.id', $openReported->id)
        ->assertJsonPath('data.items.0.has_open_correction_report', true);
});

test('loan request correction reports migration keeps appusers foreign keys on no action', function () {
    $contents = file_get_contents(
        database_path(
            'migrations/2026_05_14_114929_create_loan_request_correction_reports_table.php',
        ),
    );

    expect($contents)->not->toBeFalse();

    foreach (['user_id', 'resolved_by', 'dismissed_by'] as $column) {
        $pattern = "/foreign\\('{$column}'\\).*?;/s";

        expect(preg_match($pattern, $contents, $matches))->toBe(1);

        $foreignKeyDefinition = $matches[0];

        expect($foreignKeyDefinition)->toContain("->on('appusers')");
        expect($foreignKeyDefinition)->toContain("->onUpdate('no action')");
        expect($foreignKeyDefinition)->toContain("->onDelete('no action')");
        expect($foreignKeyDefinition)->not->toContain('cascadeOnUpdate');
        expect($foreignKeyDefinition)->not->toContain('cascadeOnDelete');
    }
});
