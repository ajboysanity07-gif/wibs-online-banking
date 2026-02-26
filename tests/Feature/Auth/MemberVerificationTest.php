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
            $table->string('phone')->nullable();
        });
    }
});

test('member verification stores session when details match', function () {
    $member = Wmaster::query()->create([
        'acctno' => '000321',
        'lname' => '',
        'fname' => '',
        'mname' => '',
        'bname' => 'PARAY, LUDELIO, S.',
        'phone' => null,
    ]);

    $response = $this->post(route('register.verify'), [
        'accntno' => $member->acctno,
        'last_name' => 'Paray',
        'first_name' => 'Ludelio',
        'middle_initial' => 'S.',
    ]);

    $response->assertRedirect(route('register.create'));
    $response->assertSessionHas('member_verification');
    $response->assertSessionHas('member_verification.first_name', 'LUDELIO');
    $response->assertSessionHas('member_verification.last_name', 'PARAY');
    $response->assertSessionHas('member_verification.middle_initial', 'S');
});

test('member verification allows a missing middle initial when record has none', function () {
    $member = Wmaster::query()->create([
        'acctno' => '000654',
        'lname' => '',
        'fname' => '',
        'mname' => '',
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

test('member verification ignores middle initial when blank', function () {
    $member = Wmaster::query()->create([
        'acctno' => '000999',
        'lname' => '',
        'fname' => '',
        'mname' => '',
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

test('member verification returns a generic error on mismatch', function () {
    Wmaster::query()->create([
        'acctno' => '000777',
        'lname' => '',
        'fname' => '',
        'mname' => '',
        'bname' => 'PARAY, LUDELIO, S.',
        'phone' => null,
    ]);

    $response = $this->post(route('register.verify'), [
        'accntno' => '000777',
        'last_name' => 'PARAY',
        'first_name' => 'NOTMATCH',
        'middle_initial' => 'S',
    ]);

    $response->assertSessionHasErrors('verification');
});
