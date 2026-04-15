<?php

use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\OrganizationSetting;
use App\Models\UserProfile;
use App\Notifications\AdminAccessAuditNotification;
use App\Notifications\AdminAccessChangedNotification;
use App\Notifications\LoanRequestDecisionNotification;
use App\Notifications\LoanRequestSubmittedNotification;
use App\Notifications\MemberStatusAuditNotification;
use App\Notifications\MemberStatusChangedNotification;
use App\Notifications\OrganizationSettingsUpdatedNotification;
use App\Services\LoanRequests\LoanRequestService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    if (! Schema::hasTable('wmaster')) {
        Schema::create('wmaster', function (Blueprint $table) {
            $table->string('acctno')->primary();
            $table->string('lname')->nullable();
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();
            $table->string('bname')->nullable();
            $table->string('email_address')->nullable();
            $table->date('datemem')->nullable();
        });
    }
});

test('approved loan request sends a database notification', function () {
    $admin = createAdminUser();
    $member = User::factory()->create([
        'phoneno' => null,
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);

    $payload = [
        'approved_amount' => 15000,
        'approved_term' => 12,
        'decision_notes' => 'Approved for release.',
    ];

    $response = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/approve", $payload);

    $response->assertOk();

    $notification = latestNotificationFor($member, LoanRequestDecisionNotification::class);

    expect($notification)->not->toBeNull();

    $data = $notification->data;

    expect($data['type'])->toBe('loan_request_decision');
    expect($data['loan_request_id'])->toBe($loanRequest->id);
    expect($data['reference'])->toBe($loanRequest->reference);
    expect($data['status'])->toBe(LoanRequestStatus::Approved->value);
    expect($data['title'])->toBe('Loan request approved');
    expect($data['message'])->toBe(
        sprintf('Your loan request %s was approved.', $loanRequest->reference),
    );
    expect($data['entity_type'])->toBe('loan_request');
    expect($data['entity_id'])->toBe($loanRequest->id);
    expect($data['member_id'])->toBe($member->user_id);
    expect($data['actor_id'])->toBe($admin->user_id);
    expect($data['actor_role'])->toBe('admin');
    expect($data['decision_notes'])->toBe('Approved for release.');
    expect($data['reviewed_at'])->not->toBeNull();
});

test('declined loan request sends a database notification', function () {
    $admin = createAdminUser();
    $member = User::factory()->create([
        'phoneno' => null,
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);

    $payload = [
        'decision_notes' => 'Declined due to incomplete documents.',
    ];

    $response = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/decline", $payload);

    $response->assertOk();

    $notification = latestNotificationFor($member, LoanRequestDecisionNotification::class);

    expect($notification)->not->toBeNull();

    $data = $notification->data;

    expect($data['type'])->toBe('loan_request_decision');
    expect($data['loan_request_id'])->toBe($loanRequest->id);
    expect($data['reference'])->toBe($loanRequest->reference);
    expect($data['status'])->toBe(LoanRequestStatus::Declined->value);
    expect($data['title'])->toBe('Loan request declined');
    expect($data['message'])->toBe(
        sprintf('Your loan request %s was declined.', $loanRequest->reference),
    );
    expect($data['entity_type'])->toBe('loan_request');
    expect($data['entity_id'])->toBe($loanRequest->id);
    expect($data['member_id'])->toBe($member->user_id);
    expect($data['actor_id'])->toBe($admin->user_id);
    expect($data['actor_role'])->toBe('admin');
    expect($data['decision_notes'])->toBe(
        'Declined due to incomplete documents.',
    );
    expect($data['reviewed_at'])->not->toBeNull();
});

test('loan request submission notifies admins and superadmins', function () {
    $admin = createAdminUser();
    $superadmin = createAdminUser(superadmin: true);
    $member = createRegisteredMember('000712', 'Loan', 'Member');

    $service = app(LoanRequestService::class);
    $loanRequest = $service->submit($member, loanRequestPayload());

    $adminNotification = latestNotificationFor($admin, LoanRequestSubmittedNotification::class);
    $superadminNotification = latestNotificationFor(
        $superadmin,
        LoanRequestSubmittedNotification::class,
    );

    expect($adminNotification)->not->toBeNull();
    expect($superadminNotification)->not->toBeNull();

    $data = $adminNotification->data;

    expect($data['type'])->toBe('loan_request_submitted');
    expect($data['loan_request_id'])->toBe($loanRequest->id);
    expect($data['reference'])->toBe($loanRequest->reference);
    expect($data['status'])->toBe(LoanRequestStatus::UnderReview->value);
    expect($data['entity_type'])->toBe('loan_request');
    expect($data['entity_id'])->toBe($loanRequest->id);
    expect($data['member_id'])->toBe($member->user_id);
    expect($data['member_name'])->toBe($member->name);
    expect($data['member_acctno'])->toBe($member->acctno);
    expect($data['actor_id'])->toBe($member->user_id);
    expect($data['actor_role'])->toBe('member');
    expect($data['loan_type_code'])->toBe('LN-100');
    expect($data['loan_type_label'])->toBe('LN-100');
    expect($data['requested_amount'])->toBe('25000.00');
    expect($data['requested_term'])->toBe(12);
    expect($data['submitted_at'])->not->toBeNull();
});

test('admin-only users can access and use notifications', function () {
    $adminOnly = createAdminUser(acctno: null);
    $member = createRegisteredMember('000820', 'Inbox', 'Member');
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);

    $adminOnly->notify(new LoanRequestSubmittedNotification($loanRequest));

    $this->actingAs($adminOnly)
        ->getJson('/spa/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.unreadCount', 1);

    $indexResponse = $this->actingAs($adminOnly)->getJson('/spa/notifications');

    $indexResponse
        ->assertOk()
        ->assertJsonPath('data.items.0.data.type', 'loan_request_submitted');

    $notificationId = $adminOnly->unreadNotifications()->first()?->id;

    expect($notificationId)->not->toBeNull();

    $this->actingAs($adminOnly)
        ->patchJson("/spa/notifications/{$notificationId}/read")
        ->assertOk()
        ->assertJsonPath('data.unreadCount', 0);

    $adminOnly->notify(new LoanRequestSubmittedNotification($loanRequest));

    $this->actingAs($adminOnly)
        ->patchJson('/spa/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('data.unreadCount', 0);
});

test('superadmins can access and use notifications', function () {
    $superadmin = createAdminUser(superadmin: true, acctno: null);
    $member = createRegisteredMember('000821', 'Super', 'Viewer');
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);

    $superadmin->notify(new LoanRequestSubmittedNotification($loanRequest));

    $this->actingAs($superadmin)
        ->getJson('/spa/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.unreadCount', 1);

    $notificationId = $superadmin->unreadNotifications()->first()?->id;

    expect($notificationId)->not->toBeNull();

    $this->actingAs($superadmin)
        ->patchJson("/spa/notifications/{$notificationId}/read")
        ->assertOk()
        ->assertJsonPath('data.unreadCount', 0);
});

test('member suspend and reactivate send notifications to member and superadmins', function () {
    $admin = createAdminUser();
    $superadmin = createAdminUser(superadmin: true);
    $member = createRegisteredMember('000830', 'Status', 'Member');

    $suspendResponse = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/members/{$member->user_id}/suspend");

    $suspendResponse->assertOk();

    $memberNotification = notificationWithStatusFor(
        $member,
        MemberStatusChangedNotification::class,
        'suspended',
    );
    $auditNotification = notificationWithStatusFor(
        $superadmin,
        MemberStatusAuditNotification::class,
        'suspended',
    );

    expect($memberNotification)->not->toBeNull();
    expect($auditNotification)->not->toBeNull();
    expect($memberNotification->data['status'])->toBe('suspended');
    expect($auditNotification->data['status'])->toBe('suspended');
    expect($auditNotification->data['actor_id'])->toBe($admin->user_id);

    $reactivateResponse = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/members/{$member->user_id}/reactivate");

    $reactivateResponse->assertOk();

    $memberNotification = notificationWithStatusFor(
        $member,
        MemberStatusChangedNotification::class,
        'active',
    );
    $auditNotification = notificationWithStatusFor(
        $superadmin,
        MemberStatusAuditNotification::class,
        'active',
    );

    expect($memberNotification)->not->toBeNull();
    expect($auditNotification)->not->toBeNull();
    expect($memberNotification->data['status'])->toBe('active');
    expect($auditNotification->data['status'])->toBe('active');
});

test('admin access grant and revoke send notifications to affected user and superadmins', function () {
    $actor = createAdminUser(superadmin: true);
    $observer = createAdminUser(superadmin: true);
    $member = createRegisteredMember('001001', 'Grace', 'Member');

    $grantResponse = $this
        ->actingAs($actor)
        ->patchJson("/spa/admin/members/{$member->user_id}/grant-admin");

    $grantResponse->assertOk();

    $memberNotification = notificationWithStatusFor(
        $member,
        AdminAccessChangedNotification::class,
        'granted',
    );
    $actorAudit = notificationWithStatusFor(
        $actor,
        AdminAccessAuditNotification::class,
        'granted',
    );
    $observerAudit = notificationWithStatusFor(
        $observer,
        AdminAccessAuditNotification::class,
        'granted',
    );

    expect($memberNotification)->not->toBeNull();
    expect($actorAudit)->not->toBeNull();
    expect($observerAudit)->not->toBeNull();
    expect($memberNotification->data['status'])->toBe('granted');
    expect($actorAudit->data['status'])->toBe('granted');
    expect($observerAudit->data['status'])->toBe('granted');

    $revokeResponse = $this
        ->actingAs($actor)
        ->patchJson("/spa/admin/members/{$member->user_id}/revoke-admin");

    $revokeResponse->assertOk();

    $memberNotification = notificationWithStatusFor(
        $member,
        AdminAccessChangedNotification::class,
        'revoked',
    );
    $actorAudit = notificationWithStatusFor(
        $actor,
        AdminAccessAuditNotification::class,
        'revoked',
    );
    $observerAudit = notificationWithStatusFor(
        $observer,
        AdminAccessAuditNotification::class,
        'revoked',
    );

    expect($memberNotification)->not->toBeNull();
    expect($actorAudit)->not->toBeNull();
    expect($observerAudit)->not->toBeNull();
    expect($memberNotification->data['status'])->toBe('revoked');
    expect($actorAudit->data['status'])->toBe('revoked');
    expect($observerAudit->data['status'])->toBe('revoked');
});

test('organization settings updates notify superadmins', function () {
    $actor = createAdminUser(superadmin: true);
    $observer = createAdminUser(superadmin: true);

    OrganizationSetting::factory()->create([
        'company_name' => 'Old Cooperative',
        'portal_label' => 'Portal',
    ]);

    $response = $this->actingAs($actor)->patch(
        route('admin.settings.organization.update'),
        [
            'company_name' => 'Acme Cooperative',
            'portal_label' => 'Members Hub',
            'support_email' => 'support@acme.test',
        ],
    );

    $response->assertRedirect(route('admin.settings.organization'));

    $actorNotification = latestNotificationFor(
        $actor,
        OrganizationSettingsUpdatedNotification::class,
    );
    $observerNotification = latestNotificationFor(
        $observer,
        OrganizationSettingsUpdatedNotification::class,
    );

    expect($actorNotification)->not->toBeNull();
    expect($observerNotification)->not->toBeNull();
    expect($actorNotification->data['status'])->toBe('updated');
    expect($actorNotification->data['reference'])->toBe('Acme Cooperative');
    expect($actorNotification->data['actor_id'])->toBe($actor->user_id);
    expect($actorNotification->data['actor_role'])->toBe('superadmin');
    expect($actorNotification->data['changed_fields'])->toContain(
        'company_name',
        'portal_label',
        'support_email',
    );
});

test('unread count endpoint returns the correct value', function () {
    $user = User::factory()->create();

    $approvedLoan = LoanRequest::factory()->forUser($user)->create([
        'status' => LoanRequestStatus::Approved,
        'reviewed_at' => now(),
    ]);
    $declinedLoan = LoanRequest::factory()->forUser($user)->create([
        'status' => LoanRequestStatus::Declined,
        'reviewed_at' => now(),
    ]);

    $user->notify(new LoanRequestDecisionNotification($approvedLoan));
    $user->notify(new LoanRequestDecisionNotification($declinedLoan));

    $user->unreadNotifications()->first()?->markAsRead();

    $response = $this
        ->actingAs($user)
        ->getJson('/spa/notifications/unread-count');

    $response
        ->assertOk()
        ->assertJsonPath('data.unreadCount', 1);
});

test('user cannot mark another user notification as read', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $loanRequest = LoanRequest::factory()->forUser($owner)->create([
        'status' => LoanRequestStatus::Approved,
        'reviewed_at' => now(),
    ]);

    $owner->notify(new LoanRequestDecisionNotification($loanRequest));

    $notificationId = $owner->unreadNotifications()->first()?->id;

    expect($notificationId)->not->toBeNull();

    $response = $this
        ->actingAs($otherUser)
        ->patchJson("/spa/notifications/{$notificationId}/read");

    $response->assertForbidden();
});

test('mark all notifications as read updates unread count', function () {
    $user = User::factory()->create();

    $loanRequest = LoanRequest::factory()->forUser($user)->create([
        'status' => LoanRequestStatus::Approved,
        'reviewed_at' => now(),
    ]);

    $user->notify(new LoanRequestDecisionNotification($loanRequest));
    $user->notify(new LoanRequestDecisionNotification($loanRequest));

    $response = $this
        ->actingAs($user)
        ->patchJson('/spa/notifications/read-all');

    $response
        ->assertOk()
        ->assertJsonPath('data.unreadCount', 0);

    expect($user->unreadNotifications()->count())->toBe(0);
    expect(
        $user->notifications()->whereNull('read_at')->count(),
    )->toBe(0);
});

test('admin-only loan request owners are not notified', function () {
    $admin = createAdminUser(acctno: '000500');
    $adminOnly = createAdminUser(acctno: null, name: 'Admin Only');

    $loanRequest = LoanRequest::factory()->forUser($adminOnly)->create([
        'acctno' => '000501',
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);

    $payload = [
        'approved_amount' => 12000,
        'approved_term' => 12,
        'decision_notes' => 'Approved for release.',
    ];

    $response = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/approve", $payload);

    $response->assertOk();

    $notificationCount = DatabaseNotification::query()
        ->where('notifiable_type', User::class)
        ->where('notifiable_id', $adminOnly->user_id)
        ->count();

    expect($notificationCount)->toBe(0);
});

test('malformed legacy notification payload does not crash notification listing', function () {
    $user = User::factory()->create();

    insertRawNotification($user, '{invalid-json');

    $response = $this
        ->actingAs($user)
        ->getJson('/spa/notifications');

    $response->assertOk();
    expect($response->json('data.items'))->toHaveCount(0);
});

test('unexpected notification data types are handled safely', function () {
    $user = User::factory()->create();

    insertRawNotification($user, json_encode('legacy-string-payload'));

    $response = $this
        ->actingAs($user)
        ->getJson('/spa/notifications');

    $response->assertOk();
    expect($response->json('data.items'))->toHaveCount(0);
});

test('invalid utf-8 in notification payload does not crash notification listing', function () {
    $user = User::factory()->create();
    $invalidUtf8 = hex2bin('C328') ?: '';

    expect($invalidUtf8)->not->toBe('');

    insertRawNotification(
        $user,
        sprintf(
            '{"type":"loan_request_decision","title":"Loan %stitle","message":"Message %s","meta":{"nested":"Value %s","flags":[1,"%s",null]}}',
            $invalidUtf8,
            $invalidUtf8,
            $invalidUtf8,
            $invalidUtf8,
        ),
    );

    $response = $this
        ->actingAs($user)
        ->getJson('/spa/notifications');

    $response->assertOk();
    expect($response->json('data.items'))->toHaveCount(1);
    expect($response->json('data.items.0.data.type'))->toBe('loan_request_decision');
    expect($response->json('data.items.0.data.title'))->toBeString()->not->toBe('');
    expect($response->json('data.items.0.data.message'))->toBeString()->not->toBe('');
    expect($response->json('data.items.0.data.meta.nested'))->toBeString()->not->toBe('');
    expect($response->json('data.items.0.data.meta.flags.0'))->toBe(1);
    expect($response->json('data.items.0.data.meta.flags.1'))->toBeString();
    expect($response->json('data.items.0.data.meta.flags.2'))->toBeNull();
});

test('one bad row plus one good row still returns 200 and includes the good notification', function () {
    $user = User::factory()->create();

    $loanRequest = LoanRequest::factory()->forUser($user)->create([
        'status' => LoanRequestStatus::Approved,
        'reviewed_at' => now(),
    ]);

    $user->notify(new LoanRequestDecisionNotification($loanRequest));

    $validNotification = latestNotificationFor(
        $user,
        LoanRequestDecisionNotification::class,
    );

    expect($validNotification)->not->toBeNull();

    insertRawNotification($user, '{invalid-json');

    $response = $this
        ->actingAs($user)
        ->getJson('/spa/notifications');

    $response->assertOk();
    expect($response->json('data.items'))->toHaveCount(1);
    expect($response->json('data.items.0.id'))->toBe((string) $validNotification->id);
    expect($response->json('data.items.0.data.type'))->toBe('loan_request_decision');
    expect($response->json('data.items.0.data.title'))->toBe('Loan request approved');
    expect($response->json('data.items.0.data.message'))->toBe(
        sprintf('Your loan request %s was approved.', $loanRequest->reference),
    );
});

test('valid notifications still serialize correctly in notification listing', function () {
    $user = User::factory()->create();

    $loanRequest = LoanRequest::factory()->forUser($user)->create([
        'status' => LoanRequestStatus::Approved,
        'reviewed_at' => now(),
    ]);

    $user->notify(new LoanRequestDecisionNotification($loanRequest));

    $notification = latestNotificationFor(
        $user,
        LoanRequestDecisionNotification::class,
    );

    expect($notification)->not->toBeNull();

    $response = $this
        ->actingAs($user)
        ->getJson('/spa/notifications');

    $response->assertOk();
    expect($response->json('data.items'))->toHaveCount(1);
    expect($response->json('data.items.0'))->toMatchArray([
        'id' => (string) $notification->id,
        'data' => $notification->data,
        'read_at' => null,
        'created_at' => $notification->created_at?->toDateTimeString(),
    ]);
    expect($response->json('data.items.0.data'))->toEqual($notification->data);
});

test('unread count succeeds while notification listing skips malformed rows and returns valid items', function () {
    $user = User::factory()->create();

    $loanRequest = LoanRequest::factory()->forUser($user)->create([
        'status' => LoanRequestStatus::Approved,
        'reviewed_at' => now(),
    ]);

    $user->notify(new LoanRequestDecisionNotification($loanRequest));

    $validNotification = latestNotificationFor(
        $user,
        LoanRequestDecisionNotification::class,
    );

    expect($validNotification)->not->toBeNull();

    insertRawNotification($user, '{"title":');

    $countResponse = $this
        ->actingAs($user)
        ->getJson('/spa/notifications/unread-count');

    $countResponse
        ->assertOk()
        ->assertJsonPath('data.unreadCount', 2);

    $listResponse = $this
        ->actingAs($user)
        ->getJson('/spa/notifications');

    $listResponse->assertOk();
    expect($listResponse->json('data.items'))->toHaveCount(1);
    expect($listResponse->json('data.items.0.id'))->toBe((string) $validNotification->id);
    expect($listResponse->json('data.items.0.data.type'))->toBe('loan_request_decision');
});

function insertRawNotification(
    User $user,
    string $data,
    ?string $type = null,
): string {
    $notificationId = (string) Str::uuid();

    DB::table('notifications')->insert([
        'id' => $notificationId,
        'type' => $type ?? LoanRequestDecisionNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $user->user_id,
        'data' => $data,
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $notificationId;
}

function createAdminUser(
    bool $superadmin = false,
    ?string $acctno = null,
    ?string $name = null,
): User {
    $user = User::factory()->create([
        'acctno' => $acctno,
        'username' => $name ?? fake()->userName(),
    ]);

    AdminProfile::factory()
        ->state([
            'user_id' => $user->user_id,
            'access_level' => $superadmin
                ? AdminProfile::ACCESS_LEVEL_SUPERADMIN
                : AdminProfile::ACCESS_LEVEL_ADMIN,
        ])
        ->create();

    if ($acctno !== null) {
        seedWmaster($user, 'Admin', 'User');
    }

    return $user;
}

function createRegisteredMember(
    string $acctno,
    string $firstName = 'Member',
    string $lastName = 'User',
): User {
    $user = User::factory()->create([
        'acctno' => $acctno,
        'username' => strtolower($firstName.$lastName),
    ]);

    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    seedWmaster($user, $firstName, $lastName);

    return $user;
}

function seedWmaster(User $user, string $firstName, string $lastName): void
{
    if ($user->acctno === null) {
        return;
    }

    DB::table('wmaster')->updateOrInsert([
        'acctno' => $user->acctno,
    ], [
        'fname' => $firstName,
        'lname' => $lastName,
        'bname' => sprintf('%s, %s', $lastName, $firstName),
        'email_address' => $user->email,
    ]);
}

function latestNotificationFor(
    User $user,
    ?string $notificationClass = null,
): ?DatabaseNotification {
    $query = DatabaseNotification::query()
        ->where('notifiable_type', User::class)
        ->where('notifiable_id', $user->user_id);

    if ($notificationClass !== null) {
        $query->where('type', $notificationClass);
    }

    return $query->latest()->first();
}

function notificationWithStatusFor(
    User $user,
    string $notificationClass,
    string $status,
): ?DatabaseNotification {
    return DatabaseNotification::query()
        ->where('notifiable_type', User::class)
        ->where('notifiable_id', $user->user_id)
        ->where('type', $notificationClass)
        ->get()
        ->first(function (DatabaseNotification $notification) use ($status): bool {
            return ($notification->data['status'] ?? null) === $status;
        });
}

function loanRequestPayload(): array
{
    return [
        'typecode' => 'LN-100',
        'requested_amount' => 25000,
        'requested_term' => 12,
        'loan_purpose' => 'Home improvement',
        'availment_status' => 'New',
        'applicant' => [
            'first_name' => 'Loan',
            'last_name' => 'Member',
            'middle_name' => 'Q',
            'nickname' => null,
            'birthdate' => '1990-04-10',
            'birthplace_city' => 'Manila',
            'birthplace_province' => 'Metro Manila',
            'address1' => 'Loan Street',
            'address2' => 'Manila',
            'address3' => 'Metro Manila',
            'length_of_stay' => '5 years',
            'housing_status' => 'OWNED',
            'cell_no' => '09170000001',
            'civil_status' => 'Single',
            'educational_attainment' => 'College',
            'number_of_children' => 0,
            'spouse_name' => null,
            'spouse_age' => null,
            'spouse_cell_no' => null,
            'employment_type' => 'Private',
            'employer_business_name' => 'Acme Corp',
            'employer_business_address1' => 'Acme Street',
            'employer_business_address2' => 'Manila',
            'employer_business_address3' => 'Metro Manila',
            'telephone_no' => '021234567',
            'current_position' => 'Analyst',
            'nature_of_business' => 'Services',
            'years_in_work_business' => '4 years',
            'gross_monthly_income' => 20000,
            'payday' => '15th',
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
}
