<?php

namespace App\Support;

class PersonName
{
    public static function format(?string $firstName, ?string $middleName, ?string $lastName): string
    {
        $first = DisplayText::normalize(trim((string) $firstName)) ?? '';
        $middle = DisplayText::normalize(trim((string) $middleName)) ?? '';
        $last = DisplayText::normalize(trim((string) $lastName)) ?? '';

        $parts = array_filter([
            $first,
            $middle !== '' ? mb_substr($middle, 0, 1).'.' : '',
            $last,
        ], static fn (string $value): bool => $value !== '');

        return implode(' ', $parts);
    }
}
