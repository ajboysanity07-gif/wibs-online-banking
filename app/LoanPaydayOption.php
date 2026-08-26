<?php

namespace App;

enum LoanPaydayOption: string
{
    case Daily = 'Daily';
    case DueDate = 'Due date';
    case Monthly = 'Monthly';
    case Quincenal = 'Quincenal';
    case SemiAnnual = 'Semi-annual';
    case Weekly = 'Weekly';
    case Yearly = 'Yearly';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
