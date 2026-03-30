<?php

use App\Models\Wmaster;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (! Schema::hasTable('wmaster')) {
        Schema::create('wmaster', function (Blueprint $table) {
            $table->string('acctno')->primary();
            $table->string('lname');
            $table->string('fname');
            $table->string('mname')->nullable();
            $table->string('bname')->nullable();
            $table->string('birthplace')->nullable();
            $table->string('address')->nullable();
            $table->string('address2')->nullable();
            $table->string('address3')->nullable();
            $table->string('address4')->nullable();
            $table->string('phone')->nullable();
        });
    }
});

test('member verification stores session when details match structured fields with middle initial', function () {
    $member = Wmaster::query()->create([
        'acctno' => '000321',
        'lname' => 'Paray',
        'fname' => 'Ludelio',
        'mname' => 'Santos',
        'bname' => 'PARAY, OTHER',
        'birthplace' => 'Cebu City',
        'address2' => '123 Main Street',
        'address3' => 'Cebu City',
        'address4' => 'Cebu',
        'phone' => null,
    ]);

    $response = $this->post(route('register.verify'), [
        'accntno' => $member->acctno,
        'last_name' => 'Paray',
        'first_name' => 'Ludelio',
        'middle_initial' => 'S',
    ]);

    $response->assertRedirect(route('register.create'));
    $response->assertSessionHas('member_verification');
    $response->assertSessionHas('member_verification.first_name', 'Ludelio');
    $response->assertSessionHas('member_verification.last_name', 'Paray');
    $response->assertSessionHas('member_verification.middle_initial', 'S');
    $response->assertSessionHas('member_verification.middle_name', 'Santos');
    $response->assertSessionHas('member_verification.birthplace', 'Cebu City');
    $response->assertSessionHas('member_verification.address2', '123 Main Street');
    $response->assertSessionHas('member_verification.address3', 'Cebu City');
    $response->assertSessionHas('member_verification.address4', 'Cebu');
});

test('member verification accepts case variations for structured names', function (
    string $lastName,
    string $firstName,
    string $acctno,
) {
    $member = Wmaster::query()->create([
        'acctno' => $acctno,
        'lname' => 'ARANAS',
        'fname' => 'JOEL',
        'mname' => 'LALA',
        'bname' => null,
        'phone' => null,
    ]);

    $response = $this->post(route('register.verify'), [
        'accntno' => $member->acctno,
        'last_name' => $lastName,
        'first_name' => $firstName,
        'middle_initial' => 'L',
    ]);

    $response->assertRedirect(route('register.create'));
})->with([
    'uppercase' => ['ARANAS', 'JOEL', '000901'],
    'lowercase' => ['Aranas', 'Joel', '000902'],
    'mixed case' => ['AraNas', 'JoEl', '000903'],
]);

test('member verification accepts middle name variations for structured mname', function (
    string $middleInput,
    string $acctno,
) {
    $member = Wmaster::query()->create([
        'acctno' => $acctno,
        'lname' => 'Aranas',
        'fname' => 'Joel',
        'mname' => 'LALA',
        'bname' => null,
        'phone' => null,
    ]);

    $response = $this->post(route('register.verify'), [
        'accntno' => $member->acctno,
        'last_name' => 'Aranas',
        'first_name' => 'Joel',
        'middle_initial' => $middleInput,
    ]);

    $response->assertRedirect(route('register.create'));
    $response->assertSessionHas('member_verification.middle_initial', 'L');
})->with([
    'initial' => ['L', '000904'],
    'initial with period' => ['L.', '000905'],
    'full middle name' => ['LALA', '000906'],
    'mixed case' => ['LaLa', '000907'],
    'title case' => ['Lala', '000908'],
]);

test('member verification rejects middle input when the first letter does not match', function () {
    Wmaster::query()->create([
        'acctno' => '000909',
        'lname' => 'Aranas',
        'fname' => 'Joel',
        'mname' => 'LALA',
        'bname' => null,
        'phone' => null,
    ]);

    $response = $this->post(route('register.verify'), [
        'accntno' => '000909',
        'last_name' => 'Aranas',
        'first_name' => 'Joel',
        'middle_initial' => 'Maria',
    ]);

    $response->assertSessionHasErrors('verification');
});

test('member verification rejects when last name differs after normalization', function () {
    Wmaster::query()->create([
        'acctno' => '000910',
        'lname' => 'ARANAS',
        'fname' => 'JOEL',
        'mname' => 'LALA',
        'bname' => null,
        'phone' => null,
    ]);

    $response = $this->post(route('register.verify'), [
        'accntno' => '000910',
        'last_name' => 'ARANASX',
        'first_name' => 'JOEL',
        'middle_initial' => 'L',
    ]);

    $response->assertSessionHasErrors('verification');
});

test('member verification rejects when first name differs after normalization', function () {
    Wmaster::query()->create([
        'acctno' => '000911',
        'lname' => 'ARANAS',
        'fname' => 'JOEL',
        'mname' => 'LALA',
        'bname' => null,
        'phone' => null,
    ]);

    $response = $this->post(route('register.verify'), [
        'accntno' => '000911',
        'last_name' => 'ARANAS',
        'first_name' => 'JOELX',
        'middle_initial' => 'L',
    ]);

    $response->assertSessionHasErrors('verification');
});

test('member verification allows a missing middle initial when record has none', function () {
    $member = Wmaster::query()->create([
        'acctno' => '000654',
        'lname' => 'Paray',
        'fname' => 'Ludelio',
        'mname' => null,
        'bname' => 'PARAY, LUDELIO',
        'phone' => null,
    ]);

    $response = $this->post(route('register.verify'), [
        'accntno' => $member->acctno,
        'last_name' => 'PARAY',
        'first_name' => 'LUDELIO',
    ]);

    $response->assertRedirect(route('register.create'));
});

test('member verification rejects middle input when record has no middle name', function () {
    Wmaster::query()->create([
        'acctno' => '000655',
        'lname' => 'Paray',
        'fname' => 'Ludelio',
        'mname' => null,
        'bname' => 'PARAY, LUDELIO',
        'phone' => null,
    ]);

    $response = $this->post(route('register.verify'), [
        'accntno' => '000655',
        'last_name' => 'PARAY',
        'first_name' => 'LUDELIO',
        'middle_initial' => 'S',
    ]);

    $response->assertSessionHasErrors('verification');
});

test('member verification ignores middle initial when blank', function () {
    $member = Wmaster::query()->create([
        'acctno' => '000999',
        'lname' => 'Paray',
        'fname' => 'Ludelio',
        'mname' => 'Santos',
        'bname' => 'PARAY, LUDELIO, S.',
        'phone' => null,
    ]);

    $response = $this->post(route('register.verify'), [
        'accntno' => $member->acctno,
        'last_name' => 'PARAY',
        'first_name' => 'LUDELIO',
    ]);

    $response->assertRedirect(route('register.create'));
});

test('member verification rejects when structured names are missing even if bname exists', function () {
    Wmaster::query()->create([
        'acctno' => '000915',
        'lname' => '',
        'fname' => '',
        'mname' => null,
        'bname' => 'ARANAS, JOEL L.',
        'phone' => null,
    ]);

    $response = $this->post(route('register.verify'), [
        'accntno' => '000915',
        'last_name' => 'Aranas',
        'first_name' => 'Joel',
        'middle_initial' => 'L',
    ]);

    $response->assertSessionHasErrors('verification');
});

test('member verification returns a generic error on mismatch', function () {
    Wmaster::query()->create([
        'acctno' => '000912',
        'lname' => 'Paray',
        'fname' => 'Ludelio',
        'mname' => 'Santos',
        'bname' => null,
        'phone' => null,
    ]);

    $response = $this->post(route('register.verify'), [
        'accntno' => '000912',
        'last_name' => 'PARAY',
        'first_name' => 'NOTMATCH',
        'middle_initial' => 'S',
    ]);

    $response->assertSessionHasErrors('verification');
});

test('spa member verification matches structured names for production case', function () {
    $member = Wmaster::query()->create([
        'acctno' => '000041',
        'lname' => 'ARANAS',
        'fname' => 'JOEL',
        'mname' => 'LALA',
        'bname' => null,
        'phone' => null,
    ]);

    $response = $this->postJson('/spa/member/verify', [
        'accntno' => $member->acctno,
        'last_name' => 'Aranas',
        'first_name' => 'Joel',
        'middle_initial' => 'Lala',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('ok', true);
    $response->assertSessionHas('member_verification.middle_initial', 'L');
});

test('spa member verification rejects middle mismatches', function () {
    Wmaster::query()->create([
        'acctno' => '000914',
        'lname' => 'ARANAS',
        'fname' => 'JOEL',
        'mname' => 'LALA',
        'bname' => null,
        'phone' => null,
    ]);

    $response = $this->postJson('/spa/member/verify', [
        'accntno' => '000914',
        'last_name' => 'Aranas',
        'first_name' => 'Joel',
        'middle_initial' => 'Maria',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonPath('errors.verification.0', "Details don't match our records.");
});
