<?php

use App\Models\AdminProfile;
use App\Models\AppUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (! Schema::hasTable('wmaster')) {
        Schema::create('wmaster', function (Blueprint $table) {
            $table->string('acctno')->primary();
            $table->string('lname')->nullable();
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();
            $table->string('bname')->nullable();
        });
    }
});

test('resolvedDisplayName prefers adminProfile fullname over a linked wmaster record', function () {
    $user = AppUser::factory()->create(['acctno' => '900101']);

    AdminProfile::factory()->create([
        'user_id' => $user->user_id,
        'fullname' => 'Admin Profile Name',
    ]);

    DB::table('wmaster')->insert([
        'acctno' => '900101',
        'fname' => 'Wmaster',
        'mname' => 'Should',
        'lname' => 'BeIgnored',
    ]);

    expect($user->fresh()->resolvedDisplayName())->toBe('Admin Profile Name');
});

test('resolvedDisplayName falls back to the linked wmaster record when there is no AdminProfile', function () {
    $user = AppUser::factory()->create(['acctno' => '900102']);

    DB::table('wmaster')->insert([
        'acctno' => '900102',
        'fname' => 'Emma',
        'mname' => 'Alcantara',
        'lname' => 'Requilme',
    ]);

    expect($user->fresh()->resolvedDisplayName())->toBe('Emma A. Requilme');
});

test('resolvedDisplayName falls back to username when there is no AdminProfile and no acctno', function () {
    $user = AppUser::factory()->create([
        'acctno' => null,
        'username' => 'plainmember123',
    ]);

    expect($user->fresh()->resolvedDisplayName())->toBe('plainmember123');
});

test('resolvedDisplayName falls back to username when acctno has no matching wmaster record', function () {
    $user = AppUser::factory()->create([
        'acctno' => '900103',
        'username' => 'unlinkedstaff456',
    ]);

    expect($user->fresh()->resolvedDisplayName())->toBe('unlinkedstaff456');
});
