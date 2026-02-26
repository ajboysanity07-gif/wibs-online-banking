<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AppUser;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UsernameSuggestionController extends Controller
{
    private const VERIFICATION_SESSION_KEY = 'member_verification';

    private const VERIFICATION_TTL_MINUTES = 15;

    private const SUGGESTION_LIMIT = 6;

    public function __invoke(Request $request): JsonResponse
    {
        $verification = $this->verifiedSession($request);

        if ($verification === null) {
            return response()->json(['message' => 'Verification required.'], 403);
        }

        $memberName = $this->memberNameFromSession($verification);

        if ($memberName === null) {
            return response()->json(['message' => 'Verification required.'], 403);
        }

        $current = $request->query('current');
        $currentValue = is_string($current) ? trim($current) : '';
        $currentNormalized = $currentValue !== '' ? Str::lower($currentValue) : '';

        $candidates = $this->candidateUsernames($memberName);

        if ($currentNormalized !== '') {
            $candidates[] = $currentNormalized;
        }

        $existing = $this->existingUsernames($candidates);
        $suggestions = $this->availableSuggestions($memberName, $existing);

        return response()->json([
            'current' => $currentValue !== ''
                ? [
                    'value' => $currentValue,
                    'available' => ! in_array($currentNormalized, $existing, true),
                ]
                : null,
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * @return array{first_name: string, last_name: string, middle_initial: string}
     */
    private function memberNameFromSession(array $verification): ?array
    {
        $firstName = $verification['first_name'] ?? null;
        $lastName = $verification['last_name'] ?? null;
        $middleInitial = $verification['middle_initial'] ?? '';

        if (! is_string($firstName) || trim($firstName) === '') {
            return null;
        }

        if (! is_string($lastName) || trim($lastName) === '') {
            return null;
        }

        $middleInitial = is_string($middleInitial) ? $middleInitial : '';

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'middle_initial' => $middleInitial,
        ];
    }

    private function verifiedSession(Request $request): ?array
    {
        $verification = $request->session()->get(self::VERIFICATION_SESSION_KEY);

        if (! is_array($verification)) {
            return null;
        }

        $verifiedAt = $verification['verified_at'] ?? null;

        if (! is_numeric($verifiedAt)) {
            return null;
        }

        $expiresAt = CarbonImmutable::createFromTimestamp((int) $verifiedAt)
            ->addMinutes(self::VERIFICATION_TTL_MINUTES);

        if (now()->greaterThan($expiresAt)) {
            return null;
        }

        return $verification;
    }

    /**
     * @param  array{first_name: string, last_name: string, middle_initial: string}  $memberName
     * @return list<string>
     */
    private function candidateUsernames(array $memberName): array
    {
        $first = $this->normalizeNamePart($memberName['first_name']);
        $last = $this->normalizeNamePart($memberName['last_name']);
        $middleInitial = $this->normalizeNamePart($memberName['middle_initial']);
        $middleInitial = $middleInitial !== '' ? substr($middleInitial, 0, 1) : '';

        $firstCompact = str_replace(' ', '', $first);
        $lastCompact = str_replace(' ', '', $last);

        if ($firstCompact === '' || $lastCompact === '') {
            return [];
        }

        $candidates = [
            sprintf('%s.%s', $firstCompact, $lastCompact),
            sprintf('%s.%s', $lastCompact, $firstCompact),
            sprintf('%s%s', $firstCompact, $lastCompact),
            sprintf('%s_%s', $firstCompact, $lastCompact),
            sprintf('%s%s', substr($firstCompact, 0, 1), $lastCompact),
            sprintf('%s%s', $lastCompact, substr($firstCompact, 0, 1)),
        ];

        if ($middleInitial !== '') {
            $candidates[] = sprintf('%s.%s.%s', $firstCompact, $middleInitial, $lastCompact);
            $candidates[] = sprintf('%s%s%s', $firstCompact, $lastCompact, $middleInitial);
        }

        $candidates = array_values(array_unique(array_filter($candidates)));

        return array_merge($candidates, $this->suffixCandidates($candidates[0] ?? ''));
    }

    /**
     * @return list<string>
     */
    private function suffixCandidates(string $base): array
    {
        if ($base === '') {
            return [];
        }

        $suffixes = [];

        for ($i = 2; $i <= 99; $i++) {
            $suffixes[] = $base.$i;
        }

        return $suffixes;
    }

    /**
     * @param  list<string>  $candidates
     * @return list<string>
     */
    private function existingUsernames(array $candidates): array
    {
        $candidates = array_values(array_unique(array_filter($candidates)));

        if ($candidates === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($candidates), '?'));

        return AppUser::query()
            ->whereRaw('lower(username) in ('.$placeholders.')', $candidates)
            ->pluck('username')
            ->map(fn (string $username) => Str::lower($username))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array{first_name: string, last_name: string, middle_initial: string}  $memberName
     * @param  list<string>  $existing
     * @return list<string>
     */
    private function availableSuggestions(array $memberName, array $existing): array
    {
        $existing = array_values(array_unique($existing));
        $suggestions = [];

        foreach ($this->candidateUsernames($memberName) as $candidate) {
            if (in_array($candidate, $existing, true)) {
                continue;
            }

            if (! in_array($candidate, $suggestions, true)) {
                $suggestions[] = $candidate;
            }

            if (count($suggestions) >= self::SUGGESTION_LIMIT) {
                break;
            }
        }

        return $suggestions;
    }

    private function normalizeNamePart(string $value): string
    {
        $normalized = Str::lower(Str::ascii($value));
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }
}
