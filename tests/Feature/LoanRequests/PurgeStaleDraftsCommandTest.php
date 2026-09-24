<?php

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();

    if (! Schema::hasTable('wmaster')) {
        Schema::create('wmaster', function (Blueprint $table): void {
            $table->string('acctno')->primary();
            $table->string('lname')->nullable();
            $table->string('fname')->nullable();
        });
    }
});

function createPurgeDraftMember(string $acctno): AppUser
{
    $member = AppUser::factory()->create([
        'acctno' => $acctno,
        'email_verified_at' => now(),
    ]);

    $member->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);
    MemberApplicationProfile::factory()->completed()->create(['user_id' => $member->user_id]);

    return $member->fresh(['roles.permissions', 'userProfile']);
}

test('purge deletes drafts older than the configured days', function (): void {
    $member = createPurgeDraftMember('004001');

    $stale = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);
    DB::table('loan_requests')->where('id', $stale->id)->update(['updated_at' => now()->subDays(45)]);

    $this->artisan('loan-requests:purge-stale-drafts', ['--days' => 30])
        ->assertSuccessful();

    expect(LoanRequest::query()->whereKey($stale->id)->exists())->toBeFalse();
});

test('purge leaves recently updated drafts untouched', function (): void {
    $member = createPurgeDraftMember('004002');

    $recent = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->artisan('loan-requests:purge-stale-drafts', ['--days' => 30])
        ->assertSuccessful();

    expect(LoanRequest::query()->whereKey($recent->id)->exists())->toBeTrue();
});

test('purge never touches non-draft statuses regardless of age', function (): void {
    $member = createPurgeDraftMember('004003');

    $submitted = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'acctno' => $member->acctno,
        'submitted_at' => now()->subDays(45),
    ]);
    DB::table('loan_requests')->where('id', $submitted->id)->update(['updated_at' => now()->subDays(45)]);

    $this->artisan('loan-requests:purge-stale-drafts', ['--days' => 30])
        ->assertSuccessful();

    expect(LoanRequest::query()->whereKey($submitted->id)->exists())->toBeTrue();
});

test('dry run makes no deletions', function (): void {
    $member = createPurgeDraftMember('004004');

    $stale = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);
    DB::table('loan_requests')->where('id', $stale->id)->update(['updated_at' => now()->subDays(45)]);

    $this->artisan('loan-requests:purge-stale-drafts', ['--days' => 30, '--dry-run' => true])
        ->assertSuccessful();

    expect(LoanRequest::query()->whereKey($stale->id)->exists())->toBeTrue();
});
