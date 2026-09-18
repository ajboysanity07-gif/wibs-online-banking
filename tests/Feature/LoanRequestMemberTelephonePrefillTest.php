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
            $table->date('birthday')->nullable();
            $table->string('address')->nullable();
            $table->string('civilstat')->nullable();
            $table->string('occupation')->nullable();
            $table->string('telephone')->nullable();
        });
    }
});

test('loan request wizard form data exposes the member telephone from wmaster', function (): void {
    $member = AppUser::factory()->create([
        'acctno' => '970101',
        'email_verified_at' => now(),
    ]);

    $member->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);
    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create([
        'user_id' => $member->user_id,
    ]);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => '970101'],
        [
            'fname' => 'Telephone',
            'lname' => 'Tester',
            'birthday' => '1990-01-01',
            'telephone' => '09170000002',
        ],
    );

    $member = $member->fresh(['roles.permissions', 'userProfile', 'memberApplicationProfile']);

    $formData = app(LoanRequestService::class)->getFormData($member);

    expect($formData['member']['telephone'])->toBe('09170000002');
});
