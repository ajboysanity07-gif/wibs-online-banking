<?php

namespace App;

enum LoanSex: string
{
    case Male = 'Male';
    case Female = 'Female';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Normalizes a raw sex string (e.g. legacy core-banking "M"/"F" or mixed
     * casing) to one of this enum's canonical labels, or null if it doesn't
     * resolve to a recognized value.
     */
    public static function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        return match (strtoupper($trimmed)) {
            'MALE', 'M' => self::Male->value,
            'FEMALE', 'F' => self::Female->value,
            default => null,
        };
    }
}
