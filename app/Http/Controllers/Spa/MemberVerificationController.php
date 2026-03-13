<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyMemberRequest;
use App\Models\Wmaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MemberVerificationController extends Controller
{
    private const VERIFICATION_SESSION_KEY = 'member_verification';

    public function store(VerifyMemberRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $accountNumber = trim($validated['accntno']);

        $member = Wmaster::find($accountNumber);

        if ($member === null) {
            $this->verificationFailed();
        }

        $normalizedLast = $this->normalizeValue($validated['last_name']);
        $normalizedFirst = $this->normalizeValue($validated['first_name']);
        $normalizedMiddle = $this->normalizeValue($validated['middle_initial'] ?? null);

        $baseTokens = $this->tokensFromNormalizedName(
            trim(sprintf('%s %s', $normalizedLast, $normalizedFirst))
        );
        $bnameTokens = $this->tokensFromNormalizedName($member->normalizedBname());

        if (count($baseTokens) === 0 || count($bnameTokens) < count($baseTokens)) {
            $this->verificationFailed();
        }

        foreach ($baseTokens as $index => $token) {
            if (($bnameTokens[$index] ?? null) !== $token) {
                $this->verificationFailed();
            }
        }

        if ($normalizedMiddle !== '') {
            $middleInitial = substr($normalizedMiddle, 0, 1);
            $middleIndex = count($baseTokens);

            if (($bnameTokens[$middleIndex] ?? null) !== $middleInitial) {
                $this->verificationFailed();
            }
        }

        $request->session()->put(self::VERIFICATION_SESSION_KEY, [
            'acctno' => (string) $member->acctno,
            'first_name' => $normalizedFirst,
            'last_name' => $normalizedLast,
            'middle_initial' => $normalizedMiddle !== '' ? substr($normalizedMiddle, 0, 1) : null,
            'verified_at' => now()->getTimestamp(),
        ]);

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

    private function normalizeValue(?string $value): string
    {
        $normalized = Str::upper(trim((string) $value));
        $normalized = preg_replace('/[.,\\-]/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * @return list<string>
     */
    private function tokensFromNormalizedName(string $value): array
    {
        $tokens = preg_split('/\\s+/', trim($value)) ?: [];

        return array_values(array_filter($tokens, static fn ($token) => $token !== ''));
    }
}
