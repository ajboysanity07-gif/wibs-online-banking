<?php

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequestDataEntry;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;
use App\Services\LoanRequests\ApprovedLoanDocumentService;
use App\Services\LoanRequests\LoanRequestService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Role::ensureWorkflowDefaults();

    if (! Schema::hasTable('wmaster')) {
        Schema::create('wmaster', function (Blueprint $table) {
            $table->string('acctno')->primary();
            $table->string('lname')->nullable();
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();
            $table->string('bname')->nullable();
            $table->date('birthday')->nullable();
            $table->string('address')->nullable();
            $table->string('zone_number')->nullable();
            $table->string('civilstat')->nullable();
            $table->string('occupation')->nullable();
        });
    }

    if (! Schema::hasTable('wlntype')) {
        Schema::create('wlntype', function (Blueprint $table) {
            $table->string('typecode')->primary();
            $table->string('lntype');
        });
    }

    DB::table('wlntype')->updateOrInsert(
        ['typecode' => 'PL'],
        ['lntype' => 'Personal'],
    );

    if (! Schema::hasTable('wlnmaster')) {
        Schema::create('wlnmaster', function (Blueprint $table) {
            $table->increments('id');
            $table->string('acctno');
            $table->string('lnnumber');
            $table->string('lntype')->nullable();
            $table->string('lnstatus')->nullable();
            $table->decimal('principal', 15, 2)->nullable();
            $table->decimal('balance', 15, 2)->nullable();
            $table->date('date_rel')->nullable();
            $table->date('date_mat')->nullable();
            $table->date('lastmove')->nullable();
        });
    }
});

function existingLoanPersistenceMember(string $acctno): AppUser
{
    $member = AppUser::factory()->create(['acctno' => $acctno]);
    $member->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );
    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);
    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create([
        'user_id' => $member->user_id,
    ]);
    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $acctno],
        [
            'fname' => 'Test',
            'lname' => 'Member',
            'bname' => 'Test Member',
            'birthday' => '1990-04-10',
            'address' => 'Persistence Street',
            'zone_number' => '8307',
            'civilstat' => 'Single',
            'occupation' => 'Analyst',
        ],
    );

    return $member->fresh(['roles.permissions', 'userProfile', 'memberApplicationProfile']);
}

test('member submit preserves existing loan auto-fill through to document data', function () {
    $acctno = 'EXSTLOAN01';
    $member = existingLoanPersistenceMember($acctno);

    // Seed wlnmaster
    LoanRequestDataEntry::query()->where('loan_request_id', '<', 0)->delete(); // no-op
    DB::table('wlnmaster')->insert([
        'acctno' => $acctno,
        'lnnumber' => 'LN-2024-001',
        'lntype' => 'Salary loan',
        'lnstatus' => 'ACT',
        'principal' => 15000.50,
        'balance' => 8000,
        'date_rel' => '2024-01-15',
        'date_mat' => '2025-01-15',
    ]);

    // Step 1: Get form data (triggers auto-fill)
    $service = app(LoanRequestService::class);
    $formData = $service->getFormData($member);

    expect($formData['autoFilledDeclarations']['declaration_existing_loans'])->toBeTrue();
    expect($formData['autoFilledDeclarations']['existing_loan_1_date'])->toBe('2024-01-15');
    expect($formData['autoFilledDeclarations']['existing_loan_1_type'])->toBe('Salary loan');
    expect($formData['autoFilledDeclarations']['existing_loan_1_amount'])->toBe(15000.50);

    // Step 2: Submit with the auto-filled values merged into declarations
    $payload = [
        'typecode' => 'PL',
        'requested_amount' => 10000,
        'requested_term' => 12,
        'loan_purpose' => 'Test existing loan persistence',
        'availment_status' => 'new',
        'applicant' => [
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'middle_name' => 'P',
            'civil_status' => 'single', 'sex' => 'male',
        ],
        'declarations' => array_merge(
            $formData['dataSections']['declarations'] ?? [],
            $formData['autoFilledDeclarations'],
            [
                'declaration_truth_confirmation' => true,
                'declaration_data_privacy_consent' => true,
            ],
        ),
        'banking' => [/* already in form data */],
    ];
    // The getFormData returns dataSections and autoFilledDeclarations separately
    // But in the frontend, they're merged. Let's use the same merge.
    $declarations = array_merge(
        $formData['dataSections']['declarations'] ?? [],
        $formData['autoFilledDeclarations'],
        ['declaration_truth_confirmation' => true, 'declaration_data_privacy_consent' => true],
    );
    $payload['declarations'] = $declarations;

    // Add banking fields required by submit validation
    $payload['banking'] = array_merge(
        $formData['dataSections']['banking'],
        [
            'payout_account_type' => 'Savings',
            'release_method' => 'Bank transfer',
            'payment_option' => 'Salary Deduction',
        ],
    );

    $payload['insurance'] = [
        'beneficiary_primary_name' => 'Beneficiary One',
        'beneficiary_primary_relationship' => 'Sibling',
        'beneficiary_primary_birthdate' => '1992-03-21',
        'beneficiary_secondary_name' => null,
        'beneficiary_secondary_relationship' => null,
        'beneficiary_secondary_birthdate' => null,
    ];
    $payload['health'] = [
        'health_smoking_status' => 'none',
        'health_hypertension' => false,
    ];
    $payload['health_glapi'] = [
        'health_recent_hospitalization' => false,
    ];
    $payload['dependents'] = array_merge(
        $formData['dataSections']['dependents'] ?? [],
        ['applicant_cycle_status' => 'New'],
    );

    try {
        $loanRequest = $service->submit($member, $payload);
    } catch (\Illuminate\Validation\ValidationException $e) {
        // Dump errors for debugging
        dump($e->errors());
        throw $e;
    }

    $loanRequest->loadMissing('dataEntries');

    // Step 3: Verify the entries were persisted
    $entry = $loanRequest->dataEntries->firstWhere('field_key', 'declaration_existing_loans');
    expect($entry)->not->toBeNull();
    expect($entry->value_json['value'])->toBe(true);

    $dateEntry = $loanRequest->dataEntries->firstWhere('field_key', 'existing_loan_1_date');
    expect($dateEntry)->not->toBeNull('existing_loan_1_date not persisted');
    expect($dateEntry->value_json['value'])->toBe('2024-01-15');

    $typeEntry = $loanRequest->dataEntries->firstWhere('field_key', 'existing_loan_1_type');
    expect($typeEntry)->not->toBeNull('existing_loan_1_type not persisted');
    expect($typeEntry->value_json['value'])->toBe('Salary loan');

    $amountEntry = $loanRequest->dataEntries->firstWhere('field_key', 'existing_loan_1_amount');
    expect($amountEntry)->not->toBeNull('existing_loan_1_amount not persisted');
    expect((float) $amountEntry->value_json['value'])->toBe(15000.5);

    // Step 4: Check document data
    $docService = app(ApprovedLoanDocumentService::class);
    $documentData = $docService->buildDocumentData($loanRequest);

    expect(data_get($documentData, 'declarations.declaration_existing_loans'))->toBeTrue();

    $existingLoans = data_get($documentData, 'existing_loans');
    expect($existingLoans)->toHaveCount(1, 'existing_loans should have 1 row');
    expect($existingLoans[0]['date'])->toBe('01/15/2024');
    expect($existingLoans[0]['type'])->toBe('Salary loan');
    expect($existingLoans[0]['amount'])->toBe('15000.5');
});

test('existing loan data persists when member manually adds slot 1 in wizard', function () {
    $member = existingLoanPersistenceMember('MANUAL-EX01');

    $service = app(LoanRequestService::class);

    $loanRequest = $service->submit($member, [
        'typecode' => 'PL',
        'requested_amount' => 10000,
        'requested_term' => 12,
        'loan_purpose' => 'Manual existing loan test',
        'availment_status' => 'new',
        'applicant' => [
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'middle_name' => 'P',
            'civil_status' => 'single', 'sex' => 'male',
        ],
        'declarations' => [
            'declaration_existing_loans' => true,
            'declaration_pending_cases' => false,
            'declaration_truth_confirmation' => true,
            'declaration_data_privacy_consent' => true,
            'existing_loan_1_date' => '2024-06-15',
            'existing_loan_1_type' => 'Emergency loan',
            'existing_loan_1_amount' => 25000,
        ],
        'banking' => [
            'payout_bank_name' => 'WIBS Cooperative Bank',
            'payout_account_name' => 'Loan Member',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'release_method' => 'Bank transfer',
            'payment_option' => 'Salary Deduction',
        ],
        'insurance' => [
            'beneficiary_primary_name' => 'Beneficiary One',
            'beneficiary_primary_relationship' => 'Sibling',
            'beneficiary_primary_birthdate' => '1992-03-21',
            'beneficiary_secondary_name' => null,
            'beneficiary_secondary_relationship' => null,
            'beneficiary_secondary_birthdate' => null,
        ],
        'health' => [
            'health_smoking_status' => 'none',
            'health_hypertension' => false,
        ],
        'health_glapi' => [
            'health_recent_hospitalization' => false,
        ],
        'dependents' => [
            'applicant_cycle_status' => 'New',
        ],
    ]);

    $loanRequest->loadMissing('dataEntries');

    $loanRequest->update(['status' => LoanRequestStatus::Approved]);
    $loanRequest->update(['approved_amount' => 25000, 'approved_term' => 12, 'approved_interest_rate' => 0.36, 'recommended_payment_frequency' => '15th & 30th']);

    $docService = app(ApprovedLoanDocumentService::class);
    $documentData = $docService->buildDocumentData($loanRequest);

    $existingLoans = data_get($documentData, 'existing_loans');
    expect($existingLoans)->toHaveCount(1);
    expect($existingLoans[0]['date'])->toBe('06/15/2024');
    expect($existingLoans[0]['type'])->toBe('Emergency loan');
    expect($existingLoans[0]['amount'])->toBe('25000');
});

test('document data falls back to most recent wlnmaster loan when request has no stored existing loans', function () {
    $acctno = 'OLDREC-01';
    $member = AppUser::factory()->create(['acctno' => $acctno]);
    UserProfile::factory()->create(['user_id' => $member->user_id]);

    // Old request submitted before the auto-fill feature existed: no
    // existing_loan_* entries stored, but the member has loans in wlnmaster.
    DB::table('wlnmaster')->insert([
        'acctno' => $acctno,
        'lnnumber' => 'LN-2021-001',
        'lntype' => 'Salary loan',
        'lnstatus' => 'ACT',
        'principal' => 20000.00,
        'balance' => 5000,
        'date_rel' => '2021-05-10',
        'date_mat' => '2022-05-10',
    ]);
    DB::table('wlnmaster')->insert([
        'acctno' => $acctno,
        'lnnumber' => 'LN-2024-002',
        'lntype' => 'Micro business loan',
        'lnstatus' => 'ACT',
        'principal' => 50000.00,
        'balance' => 10000,
        'date_rel' => '2024-03-20',
        'date_mat' => '2025-03-20',
    ]);

    $loanRequest = App\Models\LoanRequest::factory()->create([
        'user_id' => $member->user_id,
        'acctno' => $acctno,
        'status' => LoanRequestStatus::Approved,
        'approved_amount' => 15000,
        'approved_term' => 12,
        'approved_interest_rate' => 0.36,
        'recommended_payment_frequency' => '15th & 30th',
    ]);

    $docService = app(ApprovedLoanDocumentService::class);
    $documentData = $docService->buildDocumentData($loanRequest);

    $existingLoans = data_get($documentData, 'existing_loans');
    expect($existingLoans)->toHaveCount(1, 'fallback should surface the most recent wlnmaster loan');
    expect($existingLoans[0]['date'])->toBe('03/20/2024');
    expect($existingLoans[0]['type'])->toBe('Micro business loan');
    expect($existingLoans[0]['amount'])->toBe('50000.00');

    expect(data_get($documentData, 'declarations.declaration_existing_loans'))->toBeTrue();
});
