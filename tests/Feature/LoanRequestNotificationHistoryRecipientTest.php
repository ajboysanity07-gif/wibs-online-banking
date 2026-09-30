<?php

use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestNotificationEvent;
use App\Services\LoanRequests\LoanRequestPayloadSerializer;

test('notification history shows the member name with a masked contact', function () {
    $loanRequest = LoanRequest::factory()->create();
    $member = AppUser::factory()->create(['username' => 'jose.olivar']);

    LoanRequestNotificationEvent::factory()->create([
        'loan_request_id' => $loanRequest->id,
        'recipient_user_id' => $member->user_id,
        'channel' => 'sms',
        'recipient' => '09513038139',
    ]);
    LoanRequestNotificationEvent::factory()->create([
        'loan_request_id' => $loanRequest->id,
        'recipient_user_id' => $member->user_id,
        'channel' => 'email',
        'recipient' => 'jose@example.com',
        'event_type' => 'loan_approved',
    ]);

    $history = collect(
        app(LoanRequestPayloadSerializer::class)->serializeNotificationHistory($loanRequest),
    )->keyBy('channel');

    expect($history['sms']['recipient'])->toBe('jose.olivar · ***8139')
        ->and($history['email']['recipient'])->toBe('jose.olivar · j***@example.com');
});

test('notification history falls back to the masked contact when the recipient user is gone', function () {
    $loanRequest = LoanRequest::factory()->create();

    LoanRequestNotificationEvent::factory()->create([
        'loan_request_id' => $loanRequest->id,
        'recipient_user_id' => null,
        'recipient' => '09513038139',
    ]);

    $history = app(LoanRequestPayloadSerializer::class)->serializeNotificationHistory($loanRequest);

    expect($history[0]['recipient'])->toBe('***8139');
});
