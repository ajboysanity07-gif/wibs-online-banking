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
}
