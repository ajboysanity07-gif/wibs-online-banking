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

test('member verification accepts a full middle name when the first letter matches', function () {
    $member = Wmaster::query()->create([
        'acctno' => '000322',
        'lname' => 'Paray',
        'fname' => 'Ludelio',
        'mname' => 'Santos',
        'bname' => 'PARAY, LUDELIO, S.',
        'phone' => null,
    ]);

    $response = $this->post(route('register.verify'), [
        'accntno' => $member->acctno,
        'last_name' => 'Paray',
        'first_name' => 'Ludelio',
        'middle_initial' => 'Samuel',
    ]);

    $response->assertRedirect(route('register.create'));
    $response->assertSessionHas('member_verification.middle_initial', 'S');
});

test('member verification rejects a middle name when the first letter does not match', function () {
    Wmaster::query()->create([
        'acctno' => '000323',
        'lname' => 'Paray',
        'fname' => 'Ludelio',
        'mname' => 'Santos',
        'bname' => 'PARAY, LUDELIO, S.',
        'phone' => null,
    ]);

    $response = $this->post(route('register.verify'), [
        'accntno' => '000323',
        'last_name' => 'Paray',
        'first_name' => 'Ludelio',
        'middle_initial' => 'Maria',
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

test('member verification falls back to bname when structured names are missing', function () {
    $member = Wmaster::query()->create([
        'acctno' => '000888',
        'lname' => '',
        'fname' => '',
        'mname' => null,
        'bname' => 'PARAY, LUDELIO S.',
        'phone' => null,
    ]);

    $response = $this->post(route('register.verify'), [
        'accntno' => $member->acctno,
        'last_name' => 'Paray',
        'first_name' => 'Ludelio',
        'middle_initial' => 'S',
    ]);

    $response->assertRedirect(route('register.create'));
    $response->assertSessionHas('member_verification.first_name', 'LUDELIO');
    $response->assertSessionHas('member_verification.last_name', 'PARAY');
});

test('member verification does not fall back to bname when structured names exist', function () {
    Wmaster::query()->create([
        'acctno' => '000777',
        'lname' => 'Paray',
        'fname' => 'Luis',
        'mname' => 'Santos',
        'bname' => 'PARAY, LUDELIO S.',
        'phone' => null,
    ]);

    $response = $this->post(route('register.verify'), [
        'accntno' => '000777',
        'last_name' => 'Paray',
        'first_name' => 'Ludelio',
        'middle_initial' => 'S',
    ]);

    $response->assertSessionHasErrors('verification');
});

test('member verification returns a generic error on mismatch', function () {
    Wmaster::query()->create([
        'acctno' => '000555',
        'lname' => 'Paray',
        'fname' => 'Ludelio',
        'mname' => 'Santos',
        'bname' => 'PARAY, LUDELIO, S.',
        'phone' => null,
    ]);

    $response = $this->post(route('register.verify'), [
        'accntno' => '000555',
        'last_name' => 'PARAY',
        'first_name' => 'NOTMATCH',
        'middle_initial' => 'S',
    ]);

    $response->assertSessionHasErrors('verification');
});
