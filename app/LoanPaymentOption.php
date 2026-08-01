<?php

namespace App;

enum LoanPaymentOption: string
{
    case SalaryDeduction = 'Salary Deduction';
    case AtmDeduction = 'ATM Deduction';
    case Check = 'Check';
    case Cash = 'Cash';
}
