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
}
