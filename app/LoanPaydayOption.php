<?php

namespace App;

enum LoanPaydayOption: string
{
    case Weekly = 'Weekly';
    case Fifteenth = '15th';
    case Thirtieth = '30th';
    case FifteenthAndThirtieth = '15th & 30th';
    case BiWeekly = 'Bi-Weekly';
    case Monthly = 'Monthly';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
