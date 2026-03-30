<?php

namespace App\Support;

use App\Models\Wmaster;
use Illuminate\Support\Str;

class MemberVerificationMatcher
{
    /**
     * @return array{matches: bool, failure: ?string, input: array{last: string, first: string, middle: string}, member: array{last: string, first: string, middle: string, middle_initial: string}}
     */
    public function compare(
        Wmaster $member,
        ?string $lastName,
        ?string $firstName,
        ?string $middleInput,
    ): array {
        $normalizedInput = $this->normalizeInputValues($lastName, $firstName, $middleInput);
        $normalizedMember = [
            'last' => $this->normalizeMemberValue($member->lname),
            'first' => $this->normalizeMemberValue($member->fname),
            'middle' => $this->normalizeMemberValue($member->mname),
            'middle_initial' => $this->normalizedMiddleInitial($member->mname),
        ];

        if ($normalizedMember['last'] === '' || $normalizedMember['first'] === '') {
            return [
                'matches' => false,
                'failure' => 'member_missing_name',
                'input' => $normalizedInput,
                'member' => $normalizedMember,
            ];
        }

        if ($normalizedInput['last'] === '' || $normalizedInput['first'] === '') {
            return [
                'matches' => false,
                'failure' => 'input_missing_name',
                'input' => $normalizedInput,
                'member' => $normalizedMember,
            ];
        }

        if ($normalizedMember['last'] !== $normalizedInput['last']) {
            return [
                'matches' => false,
                'failure' => 'last_name_mismatch',
                'input' => $normalizedInput,
                'member' => $normalizedMember,
            ];
        }

        if ($normalizedMember['first'] !== $normalizedInput['first']) {
            return [
                'matches' => false,
                'failure' => 'first_name_mismatch',
                'input' => $normalizedInput,
                'member' => $normalizedMember,
            ];
        }

        if ($normalizedInput['middle'] === '') {
            return [
                'matches' => true,
                'failure' => null,
                'input' => $normalizedInput,
                'member' => $normalizedMember,
            ];
        }

        if ($normalizedMember['middle_initial'] === '') {
            return [
                'matches' => false,
                'failure' => 'member_missing_middle_name',
                'input' => $normalizedInput,
                'member' => $normalizedMember,
            ];
        }

        if (Str::substr($normalizedInput['middle'], 0, 1) !== $normalizedMember['middle_initial']) {
            return [
                'matches' => false,
                'failure' => 'middle_initial_mismatch',
                'input' => $normalizedInput,
                'member' => $normalizedMember,
            ];
        }

        return [
            'matches' => true,
            'failure' => null,
            'input' => $normalizedInput,
            'member' => $normalizedMember,
        ];
    }

    /**
     * @return array{last: string, first: string, middle: string}
     */
    public function normalizeInputValues(
        ?string $lastName,
        ?string $firstName,
        ?string $middleInput,
    ): array {
        return [
            'last' => $this->normalizeMemberValue($lastName),
            'first' => $this->normalizeMemberValue($firstName),
            'middle' => $this->normalizeMemberValue($middleInput),
        ];
    }

    public function normalizeMemberValue(?string $value): string
    {
        $normalized = Str::upper((string) $value);
        $normalized = preg_replace('/[.,-]/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/[\p{Z}\s\p{Cf}]+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    public function normalizedMiddleInitial(?string $value): string
    {
        $normalized = $this->normalizeMemberValue($value);

        return $normalized === '' ? '' : Str::substr($normalized, 0, 1);
    }

    public function normalizeAccountNumber(?string $value): string
    {
        $normalized = (string) $value;
        $normalized = preg_replace('/[\p{Z}\s\p{Cf}]+/u', '', $normalized) ?? $normalized;

        return trim($normalized);
    }
}
