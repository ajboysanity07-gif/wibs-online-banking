<?php

use App\Models\AppUser;
use App\Models\MemberApplicationProfile;
use App\Models\MemberPaymentAccount;
use App\Models\Role;
use App\Models\UserProfile;
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
});

function createSavedPaymentAccountTestMember(string $acctno): AppUser
{
    $member = AppUser::factory()->create([
        'acctno' => $acctno,
        'email_verified_at' => now(),
    ]);

    $member->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $member->user_id,
        'release_method' => 'Cash',
    ]);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $acctno],
        ['fname' => 'Payment', 'lname' => 'Member', 'birthday' => '1990-01-01', 'address' => 'Bank St'],
    );

    return $member->fresh(['roles.permissions', 'userProfile', 'memberApplicationProfile']);
}

test('the owning member can list, create, update, and delete their saved payment accounts', function (): void {
    $member = createSavedPaymentAccountTestMember('005500');

    $this->actingAs($member)
        ->getJson('/client/saved-payment-accounts')
        ->assertOk()
        ->assertJsonPath('data', []);

    $created = $this->actingAs($member)
        ->postJson('/client/saved-payment-accounts', [
            'label' => 'My BDO account',
            'bank_name' => 'BDO',
            'account_name' => 'Payment Member',
            'account_number' => '1234567890',
            'account_type' => 'Savings',
        ])
        ->assertCreated()
        ->json('data');

    $this->actingAs($member)
        ->getJson('/client/saved-payment-accounts')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($member)
        ->patchJson("/client/saved-payment-accounts/{$created['id']}", [
            'label' => 'Updated label',
            'bank_name' => 'BDO',
            'account_number' => '1234567890',
        ])
        ->assertOk()
        ->assertJsonPath('data.label', 'Updated label');

    $this->actingAs($member)
        ->deleteJson("/client/saved-payment-accounts/{$created['id']}")
        ->assertNoContent();

    expect(MemberPaymentAccount::query()->whereKey($created['id'])->exists())->toBeFalse();
});

test('a member cannot load, update, or delete another members saved payment account', function (): void {
    $owner = createSavedPaymentAccountTestMember('005501');
    $other = createSavedPaymentAccountTestMember('005502');

    $saved = MemberPaymentAccount::factory()->forProfile($owner->memberApplicationProfile)->create();

    $this->actingAs($other)
        ->patchJson("/client/saved-payment-accounts/{$saved->id}", [
            'bank_name' => 'Hacked Bank',
            'account_number' => '0000000000',
        ])
        ->assertNotFound();

    $this->actingAs($other)
        ->deleteJson("/client/saved-payment-accounts/{$saved->id}")
        ->assertNoContent();

    expect(MemberPaymentAccount::query()->whereKey($saved->id)->exists())->toBeTrue();
});

test('creating a saved payment account requires a bank name and account number', function (): void {
    $member = createSavedPaymentAccountTestMember('005503');

    $this->actingAs($member)
        ->postJson('/client/saved-payment-accounts', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['bank_name', 'account_number']);
});

test('saved payment account responses report whether the label was member-typed and expose the atm number separately from the account number', function (): void {
    $member = createSavedPaymentAccountTestMember('005504');

    $withCustomLabel = $this->actingAs($member)
        ->postJson('/client/saved-payment-accounts', [
            'label' => 'My BDO account',
            'bank_name' => 'BDO',
            'account_number' => '1234567890',
            'atm_number' => '9876543210',
        ])
        ->assertCreated()
        ->json('data');

    expect($withCustomLabel['has_custom_label'])->toBeTrue();
    expect($withCustomLabel['label'])->toBe('My BDO account');
    expect($withCustomLabel['account_number'])->toBe('1234567890');
    expect($withCustomLabel['atm_number'])->toBe('9876543210');

    $withoutCustomLabel = $this->actingAs($member)
        ->postJson('/client/saved-payment-accounts', [
            'bank_name' => 'Land Bank',
            'account_number' => '1111222233',
            'atm_number' => '4444555566',
        ])
        ->assertCreated()
        ->json('data');

    expect($withoutCustomLabel['has_custom_label'])->toBeFalse();
    expect($withoutCustomLabel['label'])->toBe('Land Bank ••2233');

    $this->actingAs($member)
        ->getJson('/client/saved-payment-accounts')
        ->assertOk()
        ->assertJsonPath('data.0.has_custom_label', false)
        ->assertJsonPath('data.1.has_custom_label', true);

    $updated = $this->actingAs($member)
        ->patchJson("/client/saved-payment-accounts/{$withoutCustomLabel['id']}", [
            'label' => 'Now labeled',
            'bank_name' => 'Land Bank',
            'account_number' => '1111222233',
        ])
        ->assertOk()
        ->json('data');

    expect($updated['has_custom_label'])->toBeTrue();
    expect($updated['label'])->toBe('Now labeled');
});
