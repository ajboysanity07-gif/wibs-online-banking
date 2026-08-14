<?php

use App\Models\AppUser;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;
use App\Services\LoanRequests\LoanRequestService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();

    if (! Schema::hasTable('wmaster')) {
        Schema::create('wmaster', function (Blueprint $table): void {
            $table->string('acctno')->primary();
            $table->string('lname')->nullable();
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();
            $table->string('bname')->nullable();
            $table->date('birthday')->nullable();
            $table->string('address')->nullable();
            $table->string('civilstat')->nullable();
            $table->string('occupation')->nullable();
        });
    }

    if (! Schema::hasTable('wlntype')) {
        Schema::create('wlntype', function (Blueprint $table): void {
            $table->string('typecode')->primary();
            $table->string('lntype');
        });
    }
});

function createApplicantSyncTestMember(string $acctno): AppUser
{
    $member = AppUser::factory()->create([
        'acctno' => $acctno,
        'email_verified_at' => now(),
    ]);

    $member->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);
    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create([
        'user_id' => $member->user_id,
        'civil_status' => null,
    ]);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $acctno],
        ['fname' => 'Applicant', 'lname' => 'Sync', 'birthday' => '1990-01-01', 'address' => 'Bank St'],
    );

    return $member->fresh(['roles.permissions', 'userProfile', 'memberApplicationProfile']);
}

test('getFormData backfills applicant civil status from the profile once a draft already exists', function (): void {
    $member = createApplicantSyncTestMember('004400');

    app(LoanRequestService::class)->saveDraft($member, [
        'applicant' => [
            'first_name' => 'Cecilia',
            'last_name' => 'De Gracia',
        ],
    ]);

    $member->memberApplicationProfile()->update(['civil_status' => 'Widowed']);

    $formData = app(LoanRequestService::class)->getFormData($member->fresh(['memberApplicationProfile']));

    expect($formData['applicant']['civil_status'])->toBe('Widowed');
});

test('getFormData does not overwrite an applicant civil status the member already saved on the draft', function (): void {
    $member = createApplicantSyncTestMember('004401');

    app(LoanRequestService::class)->saveDraft($member, [
        'applicant' => [
            'first_name' => 'Cecilia',
            'last_name' => 'De Gracia',
            'civil_status' => 'Single',
        ],
    ]);

    $member->memberApplicationProfile()->update(['civil_status' => 'Widowed']);

    $formData = app(LoanRequestService::class)->getFormData($member->fresh(['memberApplicationProfile']));

    expect($formData['applicant']['civil_status'])->toBe('Single');
});
