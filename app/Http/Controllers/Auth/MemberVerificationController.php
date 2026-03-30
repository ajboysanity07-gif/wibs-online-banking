<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyMemberRequest;
use App\Models\Wmaster;
use App\Support\MemberVerificationMatcher;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class MemberVerificationController extends Controller
{
    private const VERIFICATION_SESSION_KEY = 'member_verification';

    private const VERIFICATION_TTL_MINUTES = 15;

    public function create(Request $request): Response|RedirectResponse
    {
        if (! $this->hasValidVerification($request)) {
            $request->session()->forget(self::VERIFICATION_SESSION_KEY);

            return to_route('register');
        }

        $verification = $request->session()->get(self::VERIFICATION_SESSION_KEY, []);
        $memberRecord = [
            'birthplace' => $verification['birthplace'] ?? null,
            'address2' => $verification['address2'] ?? null,
            'address3' => $verification['address3'] ?? null,
            'address4' => $verification['address4'] ?? null,
        ];

        return Inertia::render('auth/register', [
            'memberName' => [
                'first_name' => $verification['first_name'] ?? null,
                'last_name' => $verification['last_name'] ?? null,
                'middle_initial' => $verification['middle_initial'] ?? null,
                'middle_name' => $verification['middle_name'] ?? null,
            ],
            'verifiedMember' => $memberRecord,
        ]);
    }

    public function store(
        VerifyMemberRequest $request,
        MemberVerificationMatcher $matcher,
    ): RedirectResponse {
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

            return $this->verificationFailed($request);
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

            return $this->verificationFailed($request);
        }

        $request->session()->put(
            self::VERIFICATION_SESSION_KEY,
            $this->buildVerificationSession($member, $matcher),
        );

        return to_route('register.create');
    }

    private function hasValidVerification(Request $request): bool
    {
        $verification = $request->session()->get(self::VERIFICATION_SESSION_KEY);

        if (! is_array($verification)) {
            return false;
        }

        $verifiedAt = $verification['verified_at'] ?? null;
        $firstName = $verification['first_name'] ?? null;
        $lastName = $verification['last_name'] ?? null;

        if (
            ! is_numeric($verifiedAt)
            || ! is_string($firstName)
            || trim($firstName) === ''
            || ! is_string($lastName)
            || trim($lastName) === ''
        ) {
            return false;
        }

        $expiresAt = CarbonImmutable::createFromTimestamp((int) $verifiedAt)
            ->addMinutes(self::VERIFICATION_TTL_MINUTES);

        return now()->lessThanOrEqualTo($expiresAt);
    }

    private function verificationFailed(Request $request): RedirectResponse
    {
        return back()
            ->withErrors([
                'verification' => "Details don't match our records.",
            ])
            ->withInput($request->only([
                'accntno',
                'last_name',
                'first_name',
                'middle_initial',
            ]));
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
            'flow' => 'web',
            'acctno' => $accountNumber,
            'member_found' => $normalizedMember !== null,
            'failure' => $failure,
            'normalized_input' => $normalizedInput,
            'normalized_member' => $normalizedMember,
        ]);
    }
}
