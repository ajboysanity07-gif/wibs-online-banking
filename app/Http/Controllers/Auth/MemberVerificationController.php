<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyMemberRequest;
use App\Models\Wmaster;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

    public function store(VerifyMemberRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $accountNumber = trim($validated['accntno']);

        $member = Wmaster::find($accountNumber);

        if ($member === null) {
            return $this->verificationFailed($request);
        }

        $normalizedLast = $this->normalizeValue($validated['last_name']);
        $normalizedFirst = $this->normalizeValue($validated['first_name']);
        $normalizedMiddle = $this->normalizeValue($validated['middle_initial'] ?? null);

        if ($member->hasStructuredName()) {
            if (! $this->matchesStructuredName($member, $normalizedLast, $normalizedFirst, $normalizedMiddle)) {
                return $this->verificationFailed($request);
            }
        } elseif (! $this->matchesLegacyName($member, $normalizedLast, $normalizedFirst, $normalizedMiddle)) {
            return $this->verificationFailed($request);
        }

        $request->session()->put(
            self::VERIFICATION_SESSION_KEY,
            $this->buildVerificationSession($member),
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

    private function normalizeValue(?string $value): string
    {
        $normalized = Str::upper(trim((string) $value));
        $normalized = preg_replace('/[.,\-]/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function matchesStructuredName(
        Wmaster $member,
        string $normalizedLast,
        string $normalizedFirst,
        string $normalizedMiddle,
    ): bool {
        if (! $member->hasStructuredName()) {
            return false;
        }

        if ($member->normalizedLastName() !== $normalizedLast) {
            return false;
        }

        if ($member->normalizedFirstName() !== $normalizedFirst) {
            return false;
        }

        if ($normalizedMiddle === '') {
            return true;
        }

        $memberMiddleInitial = $member->normalizedMiddleInitial();

        if ($memberMiddleInitial === '') {
            return false;
        }

        return substr($normalizedMiddle, 0, 1) === $memberMiddleInitial;
    }

    private function matchesLegacyName(
        Wmaster $member,
        string $normalizedLast,
        string $normalizedFirst,
        string $normalizedMiddle,
    ): bool {
        $baseTokens = $this->tokensFromNormalizedName(
            trim(sprintf('%s %s', $normalizedLast, $normalizedFirst)),
        );
        $bnameTokens = $this->tokensFromNormalizedName($member->normalizedBname());

        if (count($baseTokens) === 0 || count($bnameTokens) < count($baseTokens)) {
            return false;
        }

        foreach ($baseTokens as $index => $token) {
            if (($bnameTokens[$index] ?? null) !== $token) {
                return false;
            }
        }

        if ($normalizedMiddle === '') {
            return true;
        }

        $middleInitial = substr($normalizedMiddle, 0, 1);
        $middleIndex = count($baseTokens);

        return ($bnameTokens[$middleIndex] ?? null) === $middleInitial;
    }

    /**
     * @return list<string>
     */
    private function tokensFromNormalizedName(string $value): array
    {
        $tokens = preg_split('/\s+/', trim($value)) ?: [];

        return array_values(array_filter($tokens, static fn ($token) => $token !== ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildVerificationSession(Wmaster $member): array
    {
        $resolvedNames = $member->resolvedNameParts();
        $firstName = trim($resolvedNames['first_name']);
        $lastName = trim($resolvedNames['last_name']);
        $middleName = $this->normalizeOptionalString($resolvedNames['middle_name']);

        return [
            'acctno' => (string) $member->acctno,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'middle_initial' => $middleName !== null ? substr($middleName, 0, 1) : null,
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
}
