<?php

namespace App\Support;

use App\Services\Locations\PsgcService;

class LocationComposer
{
    public static function compose(
        ?string $address1,
        ?string $address2,
        ?string $address3,
        ?string $barangay = null,
    ): string {
        $parts = [
            $address1,
            $barangay,
            $address2,
            $address3,
        ];

        $parts = array_map(
            static fn (?string $value): string => trim((string) $value),
            $parts,
        );
        $parts = array_values(array_filter($parts, static fn (string $value): bool => $value !== ''));

        return implode(', ', $parts);
    }

    /**
     * Joins address parts while dropping any part already present in the
     * preceding parts. Handles the common Philippine data-entry pattern where
     * the street line already contains the full address (e.g. address1 =
     * "Purok 1 poblacion Lianga Surigao del sur" while address2/address3
     * repeat "Lianga, Surigao del Sur").
     *
     * A part is considered redundant when:
     *  - it is a case-insensitive substring of the joined-so-far text, or
     *  - all of its significant words (length >= 3) align against the trailing
     *    significant words of the joined text with at most a small typo
     *    (Levenshtein distance <= 2) per word. This catches near-duplicate
     *    provinces ("Surigao del sue" vs already-present "Surigao del sur")
     *    without collapsing legitimate distinct tokens ("123 Loan Street,
     *    Loan City, Loan Province").
     */
    public static function composeUnique(
        ?string $address1,
        ?string $address2,
        ?string $address3,
        ?string $barangay = null,
    ): string {
        $parts = [
            trim((string) $address1),
            trim((string) $barangay),
            trim((string) $address2),
            trim((string) $address3),
        ];
        $parts = array_values(array_filter(
            $parts,
            static fn (string $value): bool => $value !== '',
        ));

        $kept = [];
        $joined = '';

        foreach ($parts as $part) {
            $normalized = self::normalizeForMatch($part);

            if ($normalized !== '' && self::isRedundantPart($normalized, $joined)) {
                continue;
            }

            $kept[] = $part;
            $joined = $joined !== '' ? "{$joined} {$normalized}," : "{$normalized},";
        }

        return implode(', ', $kept);
    }

    public static function composeBirthplace(?string $city, ?string $province): string
    {
        return self::compose($city, $province, null);
    }

    private static function normalizeForMatch(string $value): string
    {
        $lower = mb_strtolower(trim($value));

        return preg_replace('/\s+/', ' ', $lower) ?? $lower;
    }

    private static function isRedundantPart(string $part, string $joined): bool
    {
        if ($joined === '' || $part === '') {
            return false;
        }

        if (mb_strpos($joined, $part) !== false) {
            return true;
        }

        $partWords = self::significantWords($part);

        if (count($partWords) < 2) {
            return false;
        }

        $joinedWords = self::significantWords($joined);
        $tail = array_slice($joinedWords, -count($partWords));

        if (count($tail) !== count($partWords)) {
            return false;
        }

        foreach ($partWords as $index => $word) {
            if (self::levenshteinDistance($word, $tail[$index]) > 2) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function significantWords(string $value): array
    {
        $words = preg_split('/[^a-z0-9]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $words,
            static fn (string $word): bool => mb_strlen($word) >= 3,
        ));
    }

    private static function levenshteinDistance(string $left, string $right): int
    {
        return levenshtein($left, $right);
    }

    /**
     * @return array{address1: string|null, address2: string|null, address3: string|null}
     */
    public static function parseLegacyAddress(?string $value): array
    {
        $parts = self::splitParts($value);
        $count = count($parts);

        if ($count === 0) {
            return [
                'address1' => null,
                'address2' => null,
                'address3' => null,
            ];
        }

        if ($count === 1) {
            return [
                'address1' => $parts[0],
                'address2' => null,
                'address3' => null,
            ];
        }

        if ($count === 2) {
            return [
                'address1' => $parts[0],
                'address2' => $parts[1],
                'address3' => null,
            ];
        }

        $city = $parts[$count - 2];
        $province = $parts[$count - 1];
        $street = implode(', ', array_slice($parts, 0, $count - 2));

        return [
            'address1' => $street !== '' ? $street : null,
            'address2' => $city,
            'address3' => $province,
        ];
    }

    /**
     * @return array{city: string|null, province: string|null}
     */
    public static function parseLegacyBirthplace(?string $value): array
    {
        $parts = self::splitParts($value);
        $count = count($parts);

        if ($count === 0) {
            return [
                'city' => null,
                'province' => null,
            ];
        }

        if ($count === 1) {
            return [
                'city' => $parts[0],
                'province' => null,
            ];
        }

        $province = $parts[$count - 1];
        $city = implode(', ', array_slice($parts, 0, $count - 1));

        return [
            'city' => $city !== '' ? $city : null,
            'province' => $province,
        ];
    }

    /**
     * Re-joins a legacy free-text address with consistent ", " separators.
     * When the text has no commas to parse at all, falls back to matching
     * known PSGC province/city names against the trailing words so a comma
     * can still be inserted (e.g. "Purok 4 Tagbina Surigao Del Sur" ->
     * "Purok 4, Tagbina, Surigao Del Sur"). Returns the trimmed original text
     * unchanged when neither approach recognizes anything to split on.
     */
    public static function recomposeLegacyAddress(?string $value): string
    {
        $legacy = trim((string) $value);

        if ($legacy === '') {
            return '';
        }

        $parsed = self::parseLegacyAddress($legacy);
        $recomposed = self::compose($parsed['address1'], $parsed['address2'], $parsed['address3']);

        if ($recomposed !== '' && $recomposed !== $legacy) {
            return $recomposed;
        }

        if (! str_contains($legacy, ',')) {
            $split = app(PsgcService::class)->splitFreeTextAddress($legacy);

            if ($split['locality'] !== null || $split['province'] !== null) {
                $guessed = self::compose($split['street'], $split['locality'], $split['province']);

                if ($guessed !== '') {
                    return $guessed;
                }
            }
        }

        return $recomposed !== '' ? $recomposed : $legacy;
    }

    /**
     * Re-joins a legacy free-text birthplace with consistent ", " separators.
     * When the text has no commas to parse at all, falls back to matching
     * known PSGC province/city names against the trailing words so a comma
     * can still be inserted (e.g. "Cebu City Cebu" -> "Cebu City, Cebu").
     * Returns the trimmed original text unchanged when neither approach
     * recognizes anything to split on.
     */
    public static function recomposeLegacyBirthplace(?string $value): string
    {
        $legacy = trim((string) $value);

        if ($legacy === '') {
            return '';
        }

        $parsed = self::parseLegacyBirthplace($legacy);
        $recomposed = self::compose($parsed['city'], $parsed['province'], null);

        if ($recomposed !== '' && $recomposed !== $legacy) {
            return $recomposed;
        }

        if (! str_contains($legacy, ',')) {
            $split = app(PsgcService::class)->splitFreeTextAddress($legacy);

            if ($split['locality'] !== null || $split['province'] !== null) {
                $city = trim(implode(' ', array_filter([$split['street'], $split['locality']])));
                $guessed = self::compose($city !== '' ? $city : null, $split['province'], null);

                if ($guessed !== '') {
                    return $guessed;
                }
            }
        }

        return $recomposed !== '' ? $recomposed : $legacy;
    }

    /**
     * @return list<string>
     */
    private static function splitParts(?string $value): array
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return [];
        }

        $parts = array_map(
            static fn (string $part): string => trim($part),
            explode(',', $trimmed),
        );

        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

        return $parts;
    }
}
