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

        return Inertia::render('auth/register', [
            'memberName' => [
                'first_name' => $verification['first_name'] ?? null,
                'last_name' => $verification['last_name'] ?? null,
                'middle_initial' => $verification['middle_initial'] ?? null,
            ],
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

        $baseTokens = $this->tokensFromNormalizedName(
            trim(sprintf('%s %s', $normalizedLast, $normalizedFirst))
        );
        $bnameTokens = $this->tokensFromNormalizedName($member->normalizedBname());

        if (count($baseTokens) === 0 || count($bnameTokens) < count($baseTokens)) {
            return $this->verificationFailed($request);
        }

        foreach ($baseTokens as $index => $token) {
            if (($bnameTokens[$index] ?? null) !== $token) {
                return $this->verificationFailed($request);
            }
        }

        if ($normalizedMiddle !== '') {
            $middleInitial = substr($normalizedMiddle, 0, 1);
            $middleIndex = count($baseTokens);

            if (($bnameTokens[$middleIndex] ?? null) !== $middleInitial) {
                return $this->verificationFailed($request);
            }
        }

        $request->session()->put(self::VERIFICATION_SESSION_KEY, [
            'acctno' => (string) $member->acctno,
            'first_name' => $normalizedFirst,
            'last_name' => $normalizedLast,
            'middle_initial' => $normalizedMiddle !== '' ? substr($normalizedMiddle, 0, 1) : null,
            'verified_at' => now()->getTimestamp(),
        ]);

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

    /**
     * @return list<string>
     */
    private function tokensFromNormalizedName(string $value): array
    {
        $tokens = preg_split('/\s+/', trim($value)) ?: [];

        return array_values(array_filter($tokens, static fn ($token) => $token !== ''));
    }
}
