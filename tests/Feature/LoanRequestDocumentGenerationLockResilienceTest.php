<?php

use App\LoanRequestDocumentKey;
use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\Role;
use App\Services\LoanRequests\LoanRequestDocumentWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    // The `database` cache store is what actually persists lock rows to the
    // `cache_locks` table this test manipulates directly; the test suite's
    // default `array` store (phpunit.xml) does not.
    config(['cache.default' => 'database']);

    Role::ensureWorkflowDefaults();
});

function makeLockResilienceLoanRequest(): LoanRequest
{
    $processor = AppUser::factory()->create([
        'email_verified_at' => now(),
    ]);
    Role::attachNamedRole($processor, Role::LOAN_PROCESSOR);
    $processor = $processor->fresh(['roles.permissions', 'staffAccessControl']);

    $member = AppUser::factory()->create([
        'acctno' => '900050',
        'email_verified_at' => now(),
    ]);
    Role::attachNamedRole($member, Role::MEMBER);

    return LoanRequest::factory()
        ->forUser($member)
        ->create([
            'status' => LoanRequestStatus::UnderReview,
            'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
            'submitted_at' => now(),
            'assigned_officer_id' => $processor->user_id,
        ]);
}

/**
 * Only `withDocumentGenerationLock()` throws a `ValidationException` keyed
 * under `documents` (plural). Business-rule failures inside document
 * generation itself use different keys/messages, so this key is a reliable
 * signal that the lock — not document rendering — was the source.
 */
function threwDocumentGenerationLockException(callable $callback): bool
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return array_key_exists('documents', $exception->errors());
    } catch (\Throwable) {
        // Not the lock exception; irrelevant to what this test verifies.
    }

    return false;
}

test('an orphaned expired lock row is stolen and the acquisition self-heals', function (): void {
    $loanRequest = makeLockResilienceLoanRequest();
    // Cache::lock() writes cache_locks rows under cache.prefix + key, not the
    // bare key — without this, direct row manipulation silently targets a
    // different row than the one the lock actually reads/writes.
    $lockKey = config('cache.prefix').sprintf('loan-workflow:documents:%d', $loanRequest->id);

    DB::table('cache_locks')->insert([
        'key' => $lockKey,
        'owner' => 'stale-owner-from-a-crashed-process',
        'expiration' => now()->subMinute()->timestamp,
    ]);

    $service = app(LoanRequestDocumentWorkflowService::class);
    $processor = $loanRequest->assignedOfficer;

    $threwLockException = threwDocumentGenerationLockException(
        fn () => $service->generateDocument($loanRequest, LoanRequestDocumentKey::ApplicationForm, $processor),
    );

    expect($threwLockException)->toBeFalse();

    // A normal completion (whether the callback itself succeeded or threw an
    // ordinary exception) releases the lock via Lock::get()'s own `finally`,
    // so no row should remain under the stale owner.
    $lockRow = DB::table('cache_locks')->where('key', $lockKey)->first();
    expect($lockRow?->owner ?? null)->not->toBe('stale-owner-from-a-crashed-process');
});

test('a still-valid lock row under a different owner blocks acquisition', function (): void {
    $loanRequest = makeLockResilienceLoanRequest();
    $lockKey = config('cache.prefix').sprintf('loan-workflow:documents:%d', $loanRequest->id);

    DB::table('cache_locks')->insert([
        'key' => $lockKey,
        'owner' => 'another-request-actively-running',
        'expiration' => now()->addMinute()->timestamp,
    ]);

    $service = app(LoanRequestDocumentWorkflowService::class);
    $processor = $loanRequest->assignedOfficer;

    $threwLockException = threwDocumentGenerationLockException(
        fn () => $service->generateDocument($loanRequest, LoanRequestDocumentKey::ApplicationForm, $processor),
    );

    expect($threwLockException)->toBeTrue();

    $lockRow = DB::table('cache_locks')->where('key', $lockKey)->first();
    expect($lockRow)->not->toBeNull();
    expect($lockRow->owner)->toBe('another-request-actively-running');
});
