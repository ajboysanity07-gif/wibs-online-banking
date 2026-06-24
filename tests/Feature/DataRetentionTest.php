<?php

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

test('archive command moves terminal requests older than retention period', function (): void {
    $member = AppUser::factory()->create(['acctno' => '100001']);

    $old = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Released->value,
        'created_at' => now()->subYears(6),
    ]);

    $this->artisan('loan-requests:archive', ['--years' => 5])
        ->assertSuccessful();

    expect(DB::table('archived_loan_requests')->where('original_id', $old->id)->exists())->toBeTrue();
    expect(LoanRequest::query()->where('id', $old->id)->exists())->toBeFalse();
});

test('archive command does not archive requests within retention period', function (): void {
    $member = AppUser::factory()->create(['acctno' => '100002']);

    $recent = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Released->value,
        'created_at' => now()->subYears(2),
    ]);

    $this->artisan('loan-requests:archive', ['--years' => 5])
        ->assertSuccessful();

    expect(LoanRequest::query()->where('id', $recent->id)->exists())->toBeTrue();
    expect(DB::table('archived_loan_requests')->where('original_id', $recent->id)->exists())->toBeFalse();
});

test('archive command does not archive non-terminal requests', function (): void {
    $member = AppUser::factory()->create(['acctno' => '100003']);

    $active = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview->value,
        'created_at' => now()->subYears(10),
    ]);

    $this->artisan('loan-requests:archive', ['--years' => 5])
        ->assertSuccessful();

    expect(LoanRequest::query()->where('id', $active->id)->exists())->toBeTrue();
});

test('dry-run does not delete or archive requests', function (): void {
    $member = AppUser::factory()->create(['acctno' => '100004']);

    $old = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Rejected->value,
        'created_at' => now()->subYears(6),
    ]);

    $this->artisan('loan-requests:archive', ['--dry-run' => true, '--years' => 5])
        ->assertSuccessful();

    expect(LoanRequest::query()->where('id', $old->id)->exists())->toBeTrue();
    expect(DB::table('archived_loan_requests')->where('original_id', $old->id)->exists())->toBeFalse();
});

test('archival stores full data snapshot in data_json', function (): void {
    $member = AppUser::factory()->create(['acctno' => '100005']);

    $old = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Cancelled->value,
        'created_at' => now()->subYears(6),
        'loan_purpose' => 'Test purpose',
    ]);

    $this->artisan('loan-requests:archive', ['--years' => 5])
        ->assertSuccessful();

    $archived = DB::table('archived_loan_requests')->where('original_id', $old->id)->first();
    expect($archived)->not->toBeNull();

    $data = json_decode($archived->data_json, true);
    expect($data)->toBeArray();
    expect($data['id'] ?? $data['reference'])->not->toBeNull();
});

test('already-archived requests are skipped on second run', function (): void {
    $member = AppUser::factory()->create(['acctno' => '100006']);

    $old = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Released->value,
        'created_at' => now()->subYears(6),
    ]);

    $this->artisan('loan-requests:archive', ['--years' => 5])->assertSuccessful();

    $count = DB::table('archived_loan_requests')->where('original_id', $old->id)->count();
    expect($count)->toBe(1);
});
