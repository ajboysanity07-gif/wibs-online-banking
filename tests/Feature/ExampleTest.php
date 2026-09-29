<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

test('renders the welcome page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('name', 'Member Portal - MRDINC')
            ->has('canRegister')
            ->has('loanTypes'));
});

test('welcome page lists the real loan types', function () {
    if (! Schema::hasTable('wlntype')) {
        Schema::create('wlntype', function (Blueprint $table): void {
            $table->string('typecode')->primary();
            $table->string('lntype');
        });
    }

    DB::table('wlntype')->updateOrInsert(['typecode' => 'LN-005'], ['lntype' => 'Personal']);
    Cache::forget('loan_requests.loan_types');

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('loanTypes', fn ($types) => collect($types)->contains(
                fn ($type) => $type['typecode'] === 'LN-005' && $type['label'] === 'Personal',
            )));
});
