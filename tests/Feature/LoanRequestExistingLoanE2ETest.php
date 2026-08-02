<?php

use App\Models\AppUser;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;
use App\Services\LoanRequests\ApprovedLoanDocumentService;
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
        });
    }

    if (! Schema::hasTable('wlntype')) {
        Schema::create('wlntype', function (Blueprint $table): void {
            $table->string('typecode')->primary();
            $table->string('lntype');
        });
    }

    if (! Schema::hasTable('wlnmaster')) {
        Schema::create('wlnmaster', function (Blueprint $table): void {
            $table->string('acctno');
            $table->string('lnnumber');
            $table->string('lntype')->nullable();
            $table->string('lnstatus')->nullable();
            $table->decimal('principal', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->date('date_rel')->nullable();
            $table->date('date_mat')->nullable();
            $table->dateTime('lastmove')->nullable();
        });
    }
});

function existingLoanE2EMember(string $acctno): AppUser
{
    $member = AppUser::factory()->create([
        'acctno' => $acctno,
        'email_verified_at' => now(),
    ]);

    $member->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);
    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create(['user_id' => $member->user_id]);

    return $member->fresh(['roles.permissions', 'userProfile']);
}

test('member submit persists existing loan slot 1 and document data includes it', function (): void {
    $acctno = '000001';
    $member = existingLoanE2EMember($acctno);

    DB::table('wlnmaster')->insert([
        'acctno' => $acctno,
        'lnnumber' => 'LN-001',
        'lntype' => 'Salary loan',
        'lnstatus' => 'ACT',
        'principal' => 15000.50,
        'balance' => 8000,
        'date_rel' => '2024-01-15',
        'date_mat' => '2025-01-15',
    ]);

    $service = app(LoanRequestService::class);
    $formData = $service->getFormData($member);

    expect($formData['autoFilledDeclarations']['declaration_existing_loans'])->toBeTrue();
    expect($formData['autoFilledDeclarations']['existing_loan_1_date'])->toBe('2024-01-15');

    $payload = [
        'typecode' => 'PL',
        'requested_amount' => 10000,
        'requested_term' => 12,
        'loan_purpose' => 'E2E existing loan',
        'availment_status' => 'new',
        'applicant' => [
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'middle_name' => 'P',
            'civil_status' => 'single', 'sex' => 'male',
        ],
        'declarations' => array_merge(
            $formData['dataSections']['declarations'],
            $formData['autoFilledDeclarations'],
            [
                'declaration_truth_confirmation' => true,
                'declaration_data_privacy_consent' => true,
            ],
        ),
        'insurance' => array_merge($formData['dataSections']['insurance'], [
            'beneficiary_primary_name' => 'Beneficiary One',
            'beneficiary_primary_relationship' => 'Spouse',
            'beneficiary_primary_birthdate' => '1990-01-01',
        ]),
        'health' => array_merge($formData['dataSections']['health'], [
            'health_smoking_status' => 'none',
            'health_hypertension' => false,
        ]),
        'health_glapi' => array_merge($formData['dataSections']['health_glapi'], [
            'health_recent_hospitalization' => false,
        ]),
        'banking' => array_merge($formData['dataSections']['banking'], [
            'payout_account_type' => 'Savings',
            'release_method' => 'Bank transfer',
            'payment_option' => 'Salary Deduction',
        ]),
    ];

    try {
        $loanRequest = $service->submit($member, $payload);
    } catch (Illuminate\Validation\ValidationException $e) {
        dump($e->errors());
        throw $e;
    }

    $loanRequest->loadMissing('dataEntries');

    $dateEntry = $loanRequest->dataEntries->firstWhere('field_key', 'existing_loan_1_date');
    expect($dateEntry)->not->toBeNull('existing_loan_1_date persisted');
    expect($dateEntry->value_json['value'])->toBe('2024-01-15');

    $typeEntry = $loanRequest->dataEntries->firstWhere('field_key', 'existing_loan_1_type');
    expect($typeEntry)->not->toBeNull('existing_loan_1_type persisted');
    expect($typeEntry->value_json['value'])->toBe('Salary loan');

    $amountEntry = $loanRequest->dataEntries->firstWhere('field_key', 'existing_loan_1_amount');
    expect($amountEntry)->not->toBeNull('existing_loan_1_amount persisted');
    expect((float) $amountEntry->value_json['value'])->toBe(15000.5);

    // Now approve and build document data
    $loanRequest->update([
        'status' => App\LoanRequestStatus::Approved,
        'approved_amount' => 10000,
        'approved_term' => 12,
        'approved_interest_rate' => 0.36,
        'recommended_payment_frequency' => '15th & 30th',
    ]);

    $docService = app(ApprovedLoanDocumentService::class);
    $documentData = $docService->buildDocumentData($loanRequest);

    $existingLoans = data_get($documentData, 'existing_loans');

    expect($existingLoans)->toHaveCount(1);
    expect($existingLoans[0]['date'])->toBe('01/15/2024');
    expect($existingLoans[0]['type'])->toBe('Salary loan');
    expect($existingLoans[0]['amount'])->toBe('15000.5');
});
