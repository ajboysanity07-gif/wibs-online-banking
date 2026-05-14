<?php

use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\LoanRequestCorrectionReport;
use App\Models\MemberApplicationProfile;
use App\Models\UserProfile;

function createCorrectionReportApprovedMember(string $acctno): User
{
    $member = User::factory()->create([
        'acctno' => $acctno,
    ]);

    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $member->user_id,
    ]);

    return $member;
}

function createCorrectionReportAdminUser(string $acctno): User
{
    $admin = User::factory()->create([
        'acctno' => $acctno,
    ]);

    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    return $admin;
}

test('member can report incorrect details for own approved request', function () {
    $member = createCorrectionReportApprovedMember('000901');
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Approved,
        'submitted_at' => now()->subDay(),
        'reviewed_at' => now()->subHour(),
    ]);

    $response = $this
        ->actingAs($member)
        ->postJson(
            route('client.loan-requests.correction-reports.store', $loanRequest),
            [
                'issue_description' => 'Co-maker last name is incorrect.',
                'correct_information' => 'Co-maker last name should be Santos.',
                'supporting_note' => 'Attached ID submitted to branch.',
            ],
        );

    $response
        ->assertOk()
        ->assertJsonPath('data.report.loan_request_id', $loanRequest->id)
        ->assertJsonPath(
            'data.report.status',
            LoanRequestCorrectionReport::STATUS_OPEN,
        );

    $report = LoanRequestCorrectionReport::query()
        ->where('loan_request_id', $loanRequest->id)
        ->first();

    expect($report)->not->toBeNull();
    expect($report?->user_id)->toBe($member->user_id);
    expect($report?->issue_description)->toBe(
        'Co-maker last name is incorrect.',
    );
    expect($report?->correct_information)->toBe(
        'Co-maker last name should be Santos.',
    );
});

test('member cannot report incorrect details for non-approved requests', function (LoanRequestStatus $status) {
    $member = createCorrectionReportApprovedMember('000902');
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => $status,
        'submitted_at' => now()->subDay(),
    ]);

    $this
        ->actingAs($member)
        ->postJson(
            route('client.loan-requests.correction-reports.store', $loanRequest),
            [
                'issue_description' => 'Invalid amount.',
                'correct_information' => 'Use the updated approved amount.',
            ],
        )
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');

    expect(
        LoanRequestCorrectionReport::query()
            ->where('loan_request_id', $loanRequest->id)
            ->count(),
    )->toBe(0);
})->with([
    'under review' => LoanRequestStatus::UnderReview,
    'draft' => LoanRequestStatus::Draft,
    'declined' => LoanRequestStatus::Declined,
    'cancelled' => LoanRequestStatus::Cancelled,
]);

test('member cannot report incorrect details for another members request', function () {
    $owner = createCorrectionReportApprovedMember('000903');
    $otherMember = createCorrectionReportApprovedMember('000904');
    $loanRequest = LoanRequest::factory()->forUser($owner)->create([
        'status' => LoanRequestStatus::Approved,
        'submitted_at' => now()->subDay(),
        'reviewed_at' => now()->subHour(),
    ]);

    $this
        ->actingAs($otherMember)
        ->postJson(
            route('client.loan-requests.correction-reports.store', $loanRequest),
            [
                'issue_description' => 'Request ownership mismatch.',
                'correct_information' => 'Only owner can report.',
            ],
        )
        ->assertStatus(422)
        ->assertJsonValidationErrors('loan_request');

    expect(
        LoanRequestCorrectionReport::query()
            ->where('loan_request_id', $loanRequest->id)
            ->count(),
    )->toBe(0);
});

test('duplicate open correction report for same request is blocked', function () {
    $member = createCorrectionReportApprovedMember('000905');
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Approved,
        'submitted_at' => now()->subDay(),
        'reviewed_at' => now()->subHour(),
    ]);

    LoanRequestCorrectionReport::factory()->create([
        'loan_request_id' => $loanRequest->id,
        'user_id' => $member->user_id,
        'status' => LoanRequestCorrectionReport::STATUS_OPEN,
    ]);

    $this
        ->actingAs($member)
        ->postJson(
            route('client.loan-requests.correction-reports.store', $loanRequest),
            [
                'issue_description' => 'Another issue.',
                'correct_information' => 'Another correction.',
            ],
        )
        ->assertStatus(422)
        ->assertJsonValidationErrors('report')
        ->assertJsonPath(
            'errors.report.0',
            'You already have an open correction report for this request.',
        );
});

test('admin can dismiss open correction report', function () {
    $admin = createCorrectionReportAdminUser('000906');
    $member = createCorrectionReportApprovedMember('000907');
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Approved,
        'submitted_at' => now()->subDay(),
        'reviewed_at' => now()->subHour(),
    ]);

    $report = LoanRequestCorrectionReport::factory()->create([
        'loan_request_id' => $loanRequest->id,
        'user_id' => $member->user_id,
        'status' => LoanRequestCorrectionReport::STATUS_OPEN,
    ]);

    $this
        ->actingAs($admin)
        ->patchJson(
            "/spa/admin/requests/{$loanRequest->id}/correction-reports/{$report->id}/dismiss",
            [
                'admin_notes' => 'Details already match signed documents.',
            ],
        )
        ->assertOk()
        ->assertJsonPath(
            'data.report.status',
            LoanRequestCorrectionReport::STATUS_DISMISSED,
        );

    $report->refresh();

    expect($report->status)->toBe(LoanRequestCorrectionReport::STATUS_DISMISSED);
    expect($report->dismissed_by)->toBe($admin->user_id);
    expect($report->dismissed_at)->not->toBeNull();
    expect($report->admin_notes)->toBe(
        'Details already match signed documents.',
    );
});
