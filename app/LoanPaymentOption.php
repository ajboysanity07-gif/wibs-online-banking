<?php

namespace App;

enum LoanPaymentOption: string
{
    case SalaryDeduction = 'Salary Deduction';
    case AtmDeduction = 'ATM Deduction';
    case BankTransfer = 'Bank Transfer';
    case Check = 'Check';
    case Cash = 'Cash';
}
