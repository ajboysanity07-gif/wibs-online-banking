<?php

namespace App;

enum LoanCivilStatus: string
{
    case Single = 'Single';
    case Married = 'Married';
    case Separated = 'Separated';
    case Widowed = 'Widowed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Civil statuses with no active spouse -- spouse fields are hidden and
     * optional for these, same as Single.
     *
     * @return list<string>
     */
    public static function spouseNotApplicableValues(): array
    {
        return [self::Single->value, self::Widowed->value, self::Separated->value];
    }

    /**
     * Normalizes a raw civil-status string (e.g. legacy core-banking values
     * with inconsistent casing/whitespace) to one of this enum's canonical
     * labels, or null if it doesn't resolve to a recognized status.
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

        $resolved = match (strtoupper($trimmed)) {
            'SINGLE' => self::Single->value,
            'MARRIED' => self::Married->value,
            'SEPARATED' => self::Separated->value,
            'WIDOWED' => self::Widowed->value,
            'ANNULLED' => null,
            default => $trimmed,
        };

        if ($resolved === null) {
            return null;
        }

        return in_array($resolved, self::values(), true) ? $resolved : null;
    }
}
