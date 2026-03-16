<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    if (! Schema::hasTable('wsavled')) {
        Schema::create('wsavled', function (Blueprint $table) {
            $table->string('acctno');
            $table->string('svnumber');
            $table->string('svtype')->nullable();
            $table->dateTime('date_in')->nullable();
            $table->decimal('deposit', 12, 2)->default(0);
            $table->decimal('withdrawal', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
        });
    }
});

test('admin can view member loans page', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000701',
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.members.loans', $member->user_id));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/member-loans')
            ->has('member')
            ->has('summary')
            ->has('loans')
            ->where('member.user_id', $member->user_id));
});

test('admin can view member savings page', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000702',
    ]);

    DB::table('wsavled')->insert([
        'acctno' => $member->acctno,
        'svnumber' => 'SV-701',
        'svtype' => 'Regular',
        'date_in' => Carbon::parse('2024-02-12 08:00:00')->toDateTimeString(),
        'deposit' => 250,
        'withdrawal' => 0,
        'balance' => 750,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.members.savings', $member->user_id));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/member-savings')
            ->has('member')
            ->has('summary')
            ->has('savings')
            ->has('savings.items', 1)
            ->where('member.user_id', $member->user_id)
            ->where('savings.items.0.svnumber', 'SV-701')
            ->where('savings.items.0.date_in', '2024-02-12 08:00:00'));
});

test('non-admin users cannot access member account pages', function () {
    $user = User::factory()->create();
    $member = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.members.loans', $member->user_id))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.members.savings', $member->user_id))
        ->assertForbidden();
});
