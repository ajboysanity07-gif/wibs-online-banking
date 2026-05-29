<?php

use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\LoanRequestPerson;
use App\Models\LoanRequestSignatureLink;
use App\Models\MemberApplicationProfile;
use App\Models\UserProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    if (! Schema::hasTable('wmaster')) {
        Schema::create('wmaster', function (Blueprint $table) {
            $table->string('acctno')->primary();
            $table->string('lname')->nullable();
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();
            $table->string('bname')->nullable();
            $table->date('birthday')->nullable();
            $table->string('birthplace')->nullable();
            $table->string('address')->nullable();
            $table->string('address2')->nullable();
            $table->string('address3')->nullable();
            $table->string('address4')->nullable();
            $table->string('civilstat')->nullable();
            $table->string('occupation')->nullable();
            $table->string('spouse')->nullable();
            $table->string('restype')->nullable();
            $table->string('dependent')->nullable();
        });
    }

    if (! Schema::hasTable('wlntype')) {
        Schema::create('wlntype', function (Blueprint $table) {
            $table->string('typecode')->primary();
            $table->string('lntype');
        });
    }

    Cache::forget('loan_requests.loan_types');
    Cache::forget('loan_requests.loan_type_labels');
});

function signatureLinkSampleSignatureDataUrl(string $variant = 'one'): string
{
    $base64 = match ($variant) {
        'two' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAAA1BMVEUAAACnej3aAAAAAXRSTlMAQObYZgAAAApJREFUCNdjYAAAAAIAAeIhvDMAAAAASUVORK5CYII=',
        default => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=',
    };

    return 'data:image/png;base64,'.$base64;
}

function createApprovedMemberForSignatureLinkTests(string $acctno): User
{
    DB::table('wlntype')->updateOrInsert(
        ['typecode' => 'LN-SIG'],
        ['lntype' => 'Personal'],
    );

    $user = User::factory()->create([
        'acctno' => $acctno,
    ]);

    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wmaster')->updateOrInsert([
        'acctno' => $user->acctno,
    ], [
        'bname' => 'Member, Loan',
        'fname' => 'Loan',
        'lname' => 'Member',
        'birthday' => '1990-04-10',
        'address' => 'Loan Street',
        'civilstat' => 'Single',
        'occupation' => 'Analyst',
    ]);

    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validLoanRequestPayload(array $overrides = []): array
{
    $payload = [
        'typecode' => 'LN-SIG',
        'requested_amount' => 15000,
        'requested_term' => 12,
        'loan_purpose' => 'Medical expenses',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        'applicant_signature_data' => signatureLinkSampleSignatureDataUrl('one'),
        'applicant' => [
            'first_name' => 'Loan',
            'last_name' => 'Member',
            'middle_name' => 'Q',
            'nickname' => 'LM',
            'birthdate' => '1990-04-10',
            'birthplace_city' => 'Manila',
            'birthplace_province' => 'Metro Manila',
            'address1' => 'Loan Street',
            'address2' => 'Manila',
            'address3' => 'Metro Manila',
            'length_of_stay' => '5 years',
            'housing_status' => 'OWNED',
            'cell_no' => '09123456789',
            'civil_status' => 'Single',
            'educational_attainment' => 'College',
            'number_of_children' => 0,
            'spouse_name' => null,
            'spouse_age' => null,
            'spouse_cell_no' => null,
            'employment_type' => 'Private',
            'employer_business_name' => 'Loan Company',
            'employer_business_address1' => 'Loan City Center',
            'employer_business_address2' => 'Manila',
            'employer_business_address3' => 'Metro Manila',
            'telephone_no' => '021234567',
            'current_position' => 'Analyst',
            'nature_of_business' => 'Finance',
            'years_in_work_business' => '3 years',
            'gross_monthly_income' => 25000,
            'payday' => '15th & 30th',
        ],
        'co_maker_1' => [
            'first_name' => 'Co',
            'last_name' => 'Maker',
            'middle_name' => 'One',
            'nickname' => null,
            'birthdate' => '1989-03-12',
            'birthplace_city' => 'Cebu',
            'birthplace_province' => 'Cebu',
            'address1' => 'Co Maker Street',
            'address2' => 'Cebu City',
            'address3' => 'Cebu',
            'length_of_stay' => '4 years',
            'housing_status' => 'RENT',
            'cell_no' => '09998887777',
            'civil_status' => 'Married',
            'educational_attainment' => 'College',
            'employment_type' => 'Government',
            'employer_business_name' => 'Co Maker Office',
            'employer_business_address1' => 'Co Maker Plaza',
            'employer_business_address2' => 'Cebu City',
            'employer_business_address3' => 'Cebu',
            'telephone_no' => '021234567',
            'current_position' => 'Clerk',
            'nature_of_business' => 'Government',
            'years_in_work_business' => '6 years',
            'gross_monthly_income' => 18000,
            'payday' => '30th',
        ],
        'co_maker_2' => [
            'first_name' => 'Second',
            'last_name' => 'Maker',
            'middle_name' => 'Two',
            'nickname' => null,
            'birthdate' => '1987-02-12',
            'birthplace_city' => 'Davao',
            'birthplace_province' => 'Davao del Sur',
            'address1' => 'Second Street',
            'address2' => 'Davao City',
            'address3' => 'Davao del Sur',
            'length_of_stay' => '2 years',
            'housing_status' => 'OWNED',
            'cell_no' => '09111112222',
            'civil_status' => 'Single',
            'educational_attainment' => 'High School',
            'employment_type' => 'Self Employed',
            'employer_business_name' => 'Second Store',
            'employer_business_address1' => 'Davao Store',
            'employer_business_address2' => 'Davao City',
            'employer_business_address3' => 'Davao del Sur',
            'telephone_no' => '021234567',
            'current_position' => 'Owner',
            'nature_of_business' => 'Retail',
            'years_in_work_business' => '8 years',
            'gross_monthly_income' => 22000,
            'payday' => '15th',
        ],
    ];

    return array_replace_recursive($payload, $overrides);
}

function blankLoanRequestPersonPayload(bool $includeApplicantFields = false): array
{
    $section = $includeApplicantFields ? 'applicant' : 'co_maker_1';

    return array_fill_keys(
        array_keys(validLoanRequestPayload()[$section]),
        '',
    );
}

function extractTokenFromUrl(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH);

    if (! is_string($path)) {
        throw new RuntimeException('Unable to parse signing link URL.');
    }

    $token = basename($path);

    if ($token === '') {
        throw new RuntimeException('Signing link token is missing.');
    }

    return $token;
}

/**
 * @return array{loanRequest: LoanRequest, person: LoanRequestPerson, token: string}
 */
function createPublicSignatureLinkFixture(
    string $token,
    array $linkOverrides = [],
): array {
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::PendingCoMakerSignatures,
        'submitted_at' => null,
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'first_name' => 'Borrower',
            'middle_name' => null,
            'last_name' => 'Member',
        ]);

    $person = LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::CoMakerOne)
        ->create([
            'first_name' => 'Co',
            'middle_name' => null,
            'last_name' => 'Maker',
        ]);

    LoanRequestSignatureLink::factory()
        ->forPerson($person)
        ->state(array_merge([
            'role' => LoanRequestPersonRole::CoMakerOne,
            'token_hash' => hash('sha256', $token),
        ], $linkOverrides))
        ->create();

    return [
        'loanRequest' => $loanRequest,
        'person' => $person,
        'token' => $token,
    ];
}

test('generated co-maker signing tokens are hashed before storage', function () {
    $user = createApprovedMemberForSignatureLinkTests('000801');

    $response = $this
        ->actingAs($user)
        ->postJson(
            route('client.loan-requests.signature-links.store', [
                'role' => LoanRequestPersonRole::CoMakerOne->value,
            ]),
            validLoanRequestPayload(),
        );

    $response->assertOk();

    $linkUrl = $response->json('data.signingLink.signing_url');
    $token = extractTokenFromUrl((string) $linkUrl);
    $link = LoanRequestSignatureLink::query()->sole();

    expect($link->token_hash)->toBe(hash('sha256', $token));
    expect($link->token_hash)->not->toBe($token);
});

test('owners can generate active co-maker signing links', function () {
    $user = createApprovedMemberForSignatureLinkTests('000801A');

    $response = $this
        ->actingAs($user)
        ->postJson(
            route('client.loan-requests.signature-links.store', [
                'role' => LoanRequestPersonRole::CoMakerOne->value,
            ]),
            validLoanRequestPayload(),
        );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.loanRequest.status',
            LoanRequestStatus::PendingCoMakerSignatures->value,
        )
        ->assertJsonPath('data.coMakerOneSignature.state', 'link_active')
        ->assertJsonPath(
            'data.signingLink.status',
            'link_active',
        )
        ->assertJsonPath(
            'data.signingLink.role',
            LoanRequestPersonRole::CoMakerOne->value,
        )
        ->assertJsonPath(
            'data.role',
            LoanRequestPersonRole::CoMakerOne->value,
        )
        ->assertJsonPath('data.status', 'link_active')
        ->assertJsonPath(
            'data.signing_url',
            $response->json('data.signingLink.signing_url'),
        )
        ->assertJsonPath(
            'data.expires_at',
            $response->json('data.signingLink.expires_at'),
        )
        ->assertJsonPath(
            'data.loan_request_person_id',
            $response->json('data.signingLink.loan_request_person_id'),
        );

    $loanRequest = LoanRequest::query()->sole();
    $link = LoanRequestSignatureLink::query()->sole();
    $signingUrl = (string) $response->json('data.signingLink.signing_url');

    expect($loanRequest->status)->toBe(
        LoanRequestStatus::PendingCoMakerSignatures,
    );
    expect($signingUrl)->toContain('/loan-requests/sign/co-maker/');
    expect($link->loan_request_id)->toBe($loanRequest->id);
    expect($response->json('data.signingLink.loan_request_person_id'))
        ->toBe($link->loan_request_person_id);
    expect($link->signed_at)->toBeNull();
    expect($link->revoked_at)->toBeNull();
    expect($link->expires_at)->not->toBeNull();
});

test('co-maker signing link generation accepts blank non-target sections and returns the top-level signing payload', function () {
    $user = createApprovedMemberForSignatureLinkTests('000801AA');
    $payload = validLoanRequestPayload();
    unset($payload['undertaking_accepted']);
    $payload['applicant'] = blankLoanRequestPersonPayload(true);
    $payload['co_maker_2'] = blankLoanRequestPersonPayload();

    $response = $this
        ->actingAs($user)
        ->postJson(
            route('client.loan-requests.signature-links.store', [
                'role' => LoanRequestPersonRole::CoMakerOne->value,
            ]),
            $payload,
        );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.role',
            LoanRequestPersonRole::CoMakerOne->value,
        )
        ->assertJsonPath('data.status', 'link_active')
        ->assertJsonPath(
            'data.signingLink.signing_url',
            $response->json('data.signing_url'),
        )
        ->assertJsonPath(
            'data.signingLink.loan_request_person_id',
            $response->json('data.loan_request_person_id'),
        )
        ->assertJsonPath('data.coMakerOneSignature.state', 'link_active');

    $token = extractTokenFromUrl(
        (string) $response->json('data.signingLink.signing_url'),
    );

    $this->get(route('loan-requests.sign.co-maker.show', $token))
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/loan-request-co-maker-signature')
            ->where('status', 'ready')
            ->where('signing.borrower_name', 'Loan Member'));
});

test('regenerating a co-maker signing link revokes the previous active unsigned link', function () {
    $user = createApprovedMemberForSignatureLinkTests('000801AB');
    $payload = validLoanRequestPayload();

    $firstResponse = $this
        ->actingAs($user)
        ->postJson(
            route('client.loan-requests.signature-links.store', [
                'role' => LoanRequestPersonRole::CoMakerOne->value,
            ]),
            $payload,
        );

    $firstResponse->assertOk();
    $firstLink = LoanRequestSignatureLink::query()->sole();

    $secondResponse = $this
        ->actingAs($user)
        ->postJson(
            route('client.loan-requests.signature-links.store', [
                'role' => LoanRequestPersonRole::CoMakerOne->value,
            ]),
            $payload,
        );

    $secondResponse->assertOk();

    $links = LoanRequestSignatureLink::query()->orderBy('id')->get();

    expect($links)->toHaveCount(2);
    expect($links[0]->id)->toBe($firstLink->id);
    expect($links[0]->revoked_at)->not->toBeNull();
    expect($links[0]->signed_at)->toBeNull();
    expect($links[1]->revoked_at)->toBeNull();
    expect($links[1]->signed_at)->toBeNull();
    expect($secondResponse->json('data.signingLink.signing_url'))
        ->not->toBe($firstResponse->json('data.signingLink.signing_url'));
});

test('non owners cannot generate signing links for another members loan requests', function () {
    $owner = createApprovedMemberForSignatureLinkTests('000801B');
    $otherMember = createApprovedMemberForSignatureLinkTests('000801C');

    $this->actingAs($owner)->postJson(
        route('client.loan-requests.signature-links.store', [
            'role' => LoanRequestPersonRole::CoMakerOne->value,
        ]),
        validLoanRequestPayload(),
    )->assertOk();

    $loanRequest = LoanRequest::query()->sole();

    $this->actingAs($otherMember)
        ->postJson(
            sprintf(
                '/spa/admin/requests/%d/co-makers/%s/signature-link',
                $loanRequest->id,
                LoanRequestPersonRole::CoMakerOne->value,
            ),
        )
        ->assertForbidden();

    expect(LoanRequestSignatureLink::query()->count())->toBe(1);
});

test('expired co-maker signing links cannot be opened or submitted', function () {
    $fixture = createPublicSignatureLinkFixture('expired-token', [
        'expires_at' => now()->subMinute(),
    ]);

    $this->get(
        route('loan-requests.sign.co-maker.show', $fixture['token']),
    )->assertInertia(fn (Assert $page) => $page
        ->component('public/loan-request-co-maker-signature')
        ->where('status', 'expired'));

    $this->from(route('loan-requests.sign.co-maker.show', $fixture['token']))
        ->post(route('loan-requests.sign.co-maker.store', $fixture['token']), [
            'consent' => true,
            'signature_data' => signatureLinkSampleSignatureDataUrl('one'),
        ])
        ->assertRedirect(route('loan-requests.sign.co-maker.show', $fixture['token']))
        ->assertSessionHasErrors('link');
});

test('public co-maker signing page only exposes the required review details', function () {
    $fixture = createPublicSignatureLinkFixture('review-details-token');
    $link = LoanRequestSignatureLink::query()->sole();

    $this->get(
        route('loan-requests.sign.co-maker.show', $fixture['token']),
    )->assertInertia(fn (Assert $page) => $page
        ->component('public/loan-request-co-maker-signature')
        ->where('status', 'ready')
        ->where('signing.borrower_name', 'Borrower Member')
        ->where('signing.co_maker_name', 'Co Maker')
        ->where('signing.role_label', 'Co-maker 1')
        ->where('signing.expires_at', $link->expires_at?->toDateTimeString())
        ->has('signing.loan_type')
        ->has('signing.requested_amount')
        ->has('signing.requested_term')
        ->has('signing.contact_number')
        ->has('signing.address')
        ->has('signing.employment_type')
        ->has('signing.employer_business_name')
        ->has('signing.employer_business_address')
        ->has('signing.current_position')
        ->has('signing.nature_of_business')
        ->missing('signing.borrower_address')
        ->missing('signing.borrower_contact_number')
        ->missing('signing.account_balances')
        ->missing('signing.savings_data')
        ->missing('signing.private_documents')
        ->missing('signing.admin_notes')
        ->missing('signing.signature_path')
        ->missing('signing.token_hash'));
});

test('revoked co-maker signing links cannot be used', function () {
    $fixture = createPublicSignatureLinkFixture('revoked-token', [
        'revoked_at' => now(),
    ]);

    $this->get(
        route('loan-requests.sign.co-maker.show', $fixture['token']),
    )->assertInertia(fn (Assert $page) => $page
        ->component('public/loan-request-co-maker-signature')
        ->where('status', 'revoked'));

    $this->from(route('loan-requests.sign.co-maker.show', $fixture['token']))
        ->post(route('loan-requests.sign.co-maker.store', $fixture['token']), [
            'consent' => true,
            'signature_data' => signatureLinkSampleSignatureDataUrl('one'),
        ])
        ->assertRedirect(route('loan-requests.sign.co-maker.show', $fixture['token']))
        ->assertSessionHasErrors('link');
});

test('signed co-maker signing links cannot be reused', function () {
    $fixture = createPublicSignatureLinkFixture('signed-token', [
        'signed_at' => now(),
    ]);

    $this->get(
        route('loan-requests.sign.co-maker.show', $fixture['token']),
    )->assertInertia(fn (Assert $page) => $page
        ->component('public/loan-request-co-maker-signature')
        ->where('status', 'signed'));

    $this->from(route('loan-requests.sign.co-maker.show', $fixture['token']))
        ->post(route('loan-requests.sign.co-maker.store', $fixture['token']), [
            'consent' => true,
            'signature_data' => signatureLinkSampleSignatureDataUrl('two'),
        ])
        ->assertRedirect(route('loan-requests.sign.co-maker.show', $fixture['token']))
        ->assertSessionHasErrors('link');
});

test('loan request people expose public signature urls for stored images', function () {
    $person = LoanRequestPerson::factory()->create([
        'signature_path' => 'storage/app/public/loan-requests/signatures/sample.jpg',
    ]);

    expect($person->signature_url)
        ->toBe(url('/storage/loan-requests/signatures/sample.jpg'));
});

test('valid co-maker signatures store the signature path and signed at timestamp', function () {
    Storage::fake('public');

    $fixture = createPublicSignatureLinkFixture('valid-token');

    $this->from(route('loan-requests.sign.co-maker.show', $fixture['token']))
        ->post(route('loan-requests.sign.co-maker.store', $fixture['token']), [
            'consent' => true,
            'signature_data' => signatureLinkSampleSignatureDataUrl('one'),
        ])
        ->assertRedirect(route('loan-requests.sign.co-maker.show', [
            'token' => $fixture['token'],
            'signed' => 1,
        ]));

    $person = $fixture['person']->fresh();
    $link = LoanRequestSignatureLink::query()->sole();

    expect($person?->signature_path)->not->toBeNull();
    expect($link->signed_at)->not->toBeNull();
    expect($link->ip_address)->not->toBeNull();
    expect($link->user_agent)->not->toBeNull();

    Storage::disk('public')->assertExists((string) $person?->signature_path);
});

test('stored co-maker signature png is cleaned for transparent document overlays', function () {
    Storage::fake('public');

    $fixture = createPublicSignatureLinkFixture('transparent-token');

    $this->from(route('loan-requests.sign.co-maker.show', $fixture['token']))
        ->post(route('loan-requests.sign.co-maker.store', $fixture['token']), [
            'consent' => true,
            'signature_data' => testOpaqueWhiteSignatureDataUrl(),
        ])
        ->assertRedirect(route('loan-requests.sign.co-maker.show', [
            'token' => $fixture['token'],
            'signed' => 1,
        ]));

    $person = $fixture['person']->fresh();

    expect($person?->signature_path)->not->toBeNull();

    $storedBinary = Storage::disk('public')->get((string) $person?->signature_path);
    $storedDimensions = pngDimensions($storedBinary);

    expect(pngHasTransparency($storedBinary))->toBeTrue();
    expect($storedDimensions['width'])->toBeLessThan(160);
    expect($storedDimensions['height'])->toBeLessThan(60);
});

test('successful co-maker signing shows the public confirmation state', function () {
    Storage::fake('public');

    $fixture = createPublicSignatureLinkFixture('confirmation-token');

    $this->from(route('loan-requests.sign.co-maker.show', $fixture['token']))
        ->post(route('loan-requests.sign.co-maker.store', $fixture['token']), [
            'consent' => true,
            'signature_data' => signatureLinkSampleSignatureDataUrl('one'),
        ])
        ->assertRedirect(route('loan-requests.sign.co-maker.show', [
            'token' => $fixture['token'],
            'signed' => 1,
        ]));

    $this->get(route('loan-requests.sign.co-maker.show', [
        'token' => $fixture['token'],
        'signed' => 1,
    ]))->assertInertia(fn (Assert $page) => $page
        ->component('public/loan-request-co-maker-signature')
        ->where('status', 'signed')
        ->where('recentlySigned', true)
        ->where('signing.co_maker_name', 'Co Maker')
        ->where('signing.borrower_name', 'Borrower Member')
        ->where('signing.role_label', 'Co-maker 1'));
});

test('in-person co-maker signatures on the member device are saved and allow submission', function () {
    Storage::fake('public');

    $user = createApprovedMemberForSignatureLinkTests('000804A');
    $payload = validLoanRequestPayload([
        'co_maker_1_signature_data' => signatureLinkSampleSignatureDataUrl('one'),
        'co_maker_2_signature_data' => signatureLinkSampleSignatureDataUrl('two'),
    ]);

    $this->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload)
        ->assertRedirect();

    $loanRequest = LoanRequest::query()->sole();
    $coMakerOne = LoanRequestPerson::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('role', LoanRequestPersonRole::CoMakerOne->value)
        ->sole();
    $coMakerTwo = LoanRequestPerson::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('role', LoanRequestPersonRole::CoMakerTwo->value)
        ->sole();

    expect($loanRequest->status)->toBe(LoanRequestStatus::UnderReview);
    expect($coMakerOne->signature_path)->not->toBeNull();
    expect($coMakerTwo->signature_path)->not->toBeNull();
    expect($loanRequest->signatureLinks()->count())->toBe(0);

    Storage::disk('public')->assertExists((string) $coMakerOne->signature_path);
    Storage::disk('public')->assertExists((string) $coMakerTwo->signature_path);
});

test('invalid base64 co-maker signatures are rejected', function () {
    $fixture = createPublicSignatureLinkFixture('invalid-png-token');

    $this->from(route('loan-requests.sign.co-maker.show', $fixture['token']))
        ->post(route('loan-requests.sign.co-maker.store', $fixture['token']), [
            'consent' => true,
            'signature_data' => 'data:image/png;base64,not-a-valid-png',
        ])
        ->assertRedirect(route('loan-requests.sign.co-maker.show', $fixture['token']))
        ->assertSessionHasErrors('signature_data');
});

test('loan requests cannot move to under review while required co-maker signatures are missing', function () {
    Storage::fake('public');

    $user = createApprovedMemberForSignatureLinkTests('000802');
    $payload = validLoanRequestPayload();

    $generateResponse = $this
        ->actingAs($user)
        ->postJson(
            route('client.loan-requests.signature-links.store', [
                'role' => LoanRequestPersonRole::CoMakerOne->value,
            ]),
            $payload,
        );

    $generateResponse->assertOk();

    $token = extractTokenFromUrl(
        (string) $generateResponse->json('data.signingLink.signing_url'),
    );

    $this->from(route('loan-requests.sign.co-maker.show', $token))
        ->post(route('loan-requests.sign.co-maker.store', $token), [
            'consent' => true,
            'signature_data' => signatureLinkSampleSignatureDataUrl('one'),
        ])
        ->assertRedirect(route('loan-requests.sign.co-maker.show', [
            'token' => $token,
            'signed' => 1,
        ]));

    $loanRequest = LoanRequest::query()->sole();

    $this->actingAs($user)
        ->from(route('client.loan-requests.create'))
        ->post(route('client.loan-requests.store'), $payload)
        ->assertRedirect(route('client.loan-requests.create'))
        ->assertSessionHasErrors('co_maker_2.signature');

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(
        LoanRequestStatus::PendingCoMakerSignatures,
    );
    expect($loanRequest->submitted_at)->toBeNull();
});

test('loan requests can move to pending co-maker signatures while waiting for external signing', function () {
    $user = createApprovedMemberForSignatureLinkTests('000803');

    $response = $this
        ->actingAs($user)
        ->postJson(
            route('client.loan-requests.signature-links.store', [
                'role' => LoanRequestPersonRole::CoMakerOne->value,
            ]),
            validLoanRequestPayload(),
        );

    $response->assertOk();

    $loanRequest = LoanRequest::query()->sole();

    expect($loanRequest->status)->toBe(
        LoanRequestStatus::PendingCoMakerSignatures,
    );
    expect($loanRequest->submitted_at)->toBeNull();
    expect($loanRequest->signatureLinks()->count())->toBe(1);
    expect($response->json('data.loanRequest.status'))->toBe(
        LoanRequestStatus::PendingCoMakerSignatures->value,
    );
});

test('loan requests can move to under review after all required co-maker signatures exist', function () {
    Storage::fake('public');

    $user = createApprovedMemberForSignatureLinkTests('000804');
    $payload = validLoanRequestPayload();

    $coMakerOneResponse = $this
        ->actingAs($user)
        ->postJson(
            route('client.loan-requests.signature-links.store', [
                'role' => LoanRequestPersonRole::CoMakerOne->value,
            ]),
            $payload,
        );
    $coMakerOneResponse->assertOk();

    $coMakerOneToken = extractTokenFromUrl(
        (string) $coMakerOneResponse->json('data.signingLink.signing_url'),
    );

    $this->from(route('loan-requests.sign.co-maker.show', $coMakerOneToken))
        ->post(route('loan-requests.sign.co-maker.store', $coMakerOneToken), [
            'consent' => true,
            'signature_data' => signatureLinkSampleSignatureDataUrl('one'),
        ])
        ->assertRedirect(route('loan-requests.sign.co-maker.show', [
            'token' => $coMakerOneToken,
            'signed' => 1,
        ]));

    $coMakerTwoResponse = $this
        ->actingAs($user)
        ->postJson(
            route('client.loan-requests.signature-links.store', [
                'role' => LoanRequestPersonRole::CoMakerTwo->value,
            ]),
            $payload,
        );

    $coMakerTwoResponse->assertOk();

    $coMakerTwoToken = extractTokenFromUrl(
        (string) $coMakerTwoResponse->json('data.signingLink.signing_url'),
    );

    $this->from(route('loan-requests.sign.co-maker.show', $coMakerTwoToken))
        ->post(route('loan-requests.sign.co-maker.store', $coMakerTwoToken), [
            'consent' => true,
            'signature_data' => signatureLinkSampleSignatureDataUrl('two'),
        ])
        ->assertRedirect(route('loan-requests.sign.co-maker.show', [
            'token' => $coMakerTwoToken,
            'signed' => 1,
        ]));

    $loanRequest = LoanRequest::query()->sole();

    $this->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload)
        ->assertRedirect(route('client.loan-requests.show', $loanRequest));

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::UnderReview);
    expect($loanRequest->submitted_at)->not->toBeNull();
});

test('signed co-maker details cannot be changed without invalidating the signature', function () {
    Storage::fake('public');

    $user = createApprovedMemberForSignatureLinkTests('000805');
    $payload = validLoanRequestPayload();

    $generateResponse = $this
        ->actingAs($user)
        ->postJson(
            route('client.loan-requests.signature-links.store', [
                'role' => LoanRequestPersonRole::CoMakerOne->value,
            ]),
            $payload,
        );

    $generateResponse->assertOk();

    $token = extractTokenFromUrl(
        (string) $generateResponse->json('data.signingLink.signing_url'),
    );

    $this->from(route('loan-requests.sign.co-maker.show', $token))
        ->post(route('loan-requests.sign.co-maker.store', $token), [
            'consent' => true,
            'signature_data' => signatureLinkSampleSignatureDataUrl('one'),
        ])
        ->assertRedirect(route('loan-requests.sign.co-maker.show', [
            'token' => $token,
            'signed' => 1,
        ]));

    $loanRequest = LoanRequest::query()->sole();
    $person = LoanRequestPerson::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('role', LoanRequestPersonRole::CoMakerOne->value)
        ->sole();
    $originalSignaturePath = $person->signature_path;

    $updatedPayload = validLoanRequestPayload([
        'co_maker_1' => [
            'address1' => 'Updated Co Maker Street',
        ],
    ]);

    $this->actingAs($user)
        ->patch(route('client.loan-requests.draft'), $updatedPayload)
        ->assertRedirect(route('client.loan-requests.create'));

    $loanRequest->refresh();
    $person->refresh();

    expect($loanRequest->status)->toBe(
        LoanRequestStatus::PendingCoMakerSignatures,
    );
    expect($person->address1)->toBe('Updated Co Maker Street');
    expect($person->signature_path)->toBeNull();

    Storage::disk('public')->assertMissing((string) $originalSignaturePath);

    $this->actingAs($user)
        ->from(route('client.loan-requests.create'))
        ->post(route('client.loan-requests.store'), $updatedPayload)
        ->assertRedirect(route('client.loan-requests.create'))
        ->assertSessionHasErrors('co_maker_1.signature');
});
