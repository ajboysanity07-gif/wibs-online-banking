<?php

use App\Models\AppUser;

test('username suggestions require verification', function () {
    $response = $this->get(route('register.username-suggestions'));

    $response->assertForbidden();
});

test('username suggestions return availability and available suggestions', function () {
    AppUser::factory()->create([
        'username' => 'ludelio.paray',
    ]);

    $response = $this->withSession([
        'member_verification' => [
            'acctno' => '000123',
            'first_name' => 'LUDELIO',
            'last_name' => 'PARAY',
            'middle_initial' => 'S',
            'verified_at' => now()->getTimestamp(),
        ],
    ])->get(route('register.username-suggestions', [
        'current' => 'ludelio.paray',
    ]));

    $response->assertOk();

    expect($response->json('current.available'))->toBeFalse();
    expect($response->json('suggestions'))
        ->toBeArray()
        ->not->toContain('ludelio.paray');
});
