<?php

use App\Models\AppUser;
use App\Models\LoanRequest;

test('loan request owner repair command detects mismatches without updating', function () {
    $currentOwner = AppUser::factory()->create([
        'acctno' => '009001',
    ]);
    $expectedOwner = AppUser::factory()->create([
        'acctno' => '009002',
    ]);

    $loanRequest = LoanRequest::factory()->create([
        'user_id' => $currentOwner->user_id,
        'acctno' => $expectedOwner->acctno,
    ]);

    $this->artisan('loan-requests:repair-owners')
        ->assertExitCode(0);

    $loanRequest->refresh();

    expect($loanRequest->user_id)->toBe($currentOwner->user_id);
});

test('loan request owner repair command updates mismatches when fix is enabled', function () {
    $currentOwner = AppUser::factory()->create([
        'acctno' => '009011',
    ]);
    $expectedOwner = AppUser::factory()->create([
        'acctno' => '009012',
    ]);

    $loanRequest = LoanRequest::factory()->create([
        'user_id' => $currentOwner->user_id,
        'acctno' => $expectedOwner->acctno,
    ]);

    $this->artisan('loan-requests:repair-owners --fix')
        ->assertExitCode(0);

    $loanRequest->refresh();

    expect($loanRequest->user_id)->toBe($expectedOwner->user_id);
});
