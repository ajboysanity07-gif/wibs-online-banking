<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyMemberRequest;
use App\Models\Wmaster;
use App\Support\MemberVerificationMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MemberVerificationController extends Controller
{
    private const VERIFICATION_SESSION_KEY = 'member_verification';

    public function store(
        VerifyMemberRequest $request,
        MemberVerificationMatcher $matcher,
    ): JsonResponse {
        $validated = $request->validated();
        $accountNumber = $matcher->normalizeAccountNumber($validated['accntno']);

        $member = Wmaster::find($accountNumber);

        if ($member === null) {
            $this->logVerificationFailure(
                $accountNumber,
                $matcher->normalizeInputValues(
                    $validated['last_name'] ?? null,
                    $validated['first_name'] ?? null,
                    $validated['middle_initial'] ?? null,
                ),
                null,
                'member_not_found',
            );

            $this->verificationFailed();
        }

        $comparison = $matcher->compare(
            $member,
            $validated['last_name'] ?? null,
            $validated['first_name'] ?? null,
            $validated['middle_initial'] ?? null,
        );

        if (! $comparison['matches']) {
            $this->logVerificationFailure(
                $accountNumber,
                $comparison['input'],
                $comparison['member'],
                $comparison['failure'] ?? 'unknown',
            );

            $this->verificationFailed();
        }

        $request->session()->put(
            self::VERIFICATION_SESSION_KEY,
            $this->buildVerificationSession($member, $matcher),
        );

        return response()->json([
            'ok' => true,
            'redirect_to' => route('register.create', absolute: false),
        ]);
    }

    private function verificationFailed(): void
    {
        throw ValidationException::withMessages([
            'verification' => "Details don't match our records.",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildVerificationSession(
        Wmaster $member,
        MemberVerificationMatcher $matcher,
    ): array {
        $firstName = $this->normalizeOptionalString($member->fname);
        $lastName = $this->normalizeOptionalString($member->lname);
        $middleName = $this->normalizeOptionalString($member->mname);
        $middleInitial = $matcher->normalizedMiddleInitial($member->mname);

        return [
            'acctno' => (string) $member->acctno,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'middle_initial' => $middleInitial !== '' ? $middleInitial : null,
            'middle_name' => $middleName,
            'birthplace' => $this->normalizeOptionalString($member->birthplace),
            'address2' => $this->normalizeOptionalString($member->address2),
            'address3' => $this->normalizeOptionalString($member->address3),
            'address4' => $this->normalizeOptionalString($member->address4),
            'verified_at' => now()->getTimestamp(),
        ];
    }

    private function normalizeOptionalString(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * @param  array{last: string, first: string, middle: string}  $normalizedInput
     * @param  array{last: string, first: string, middle: string, middle_initial: string}|null  $normalizedMember
     */
    private function logVerificationFailure(
        string $accountNumber,
        array $normalizedInput,
        ?array $normalizedMember,
        string $failure,
    ): void {
        Log::warning('Member verification failed.', [
            'flow' => 'spa',
            'acctno' => $accountNumber,
            'member_found' => $normalizedMember !== null,
            'failure' => $failure,
            'normalized_input' => $normalizedInput,
            'normalized_member' => $normalizedMember,
        ]);
    }
}
