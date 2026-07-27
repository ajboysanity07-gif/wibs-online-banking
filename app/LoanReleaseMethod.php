<?php

namespace App;

enum LoanReleaseMethod: string
{
    case Atm = 'ATM';
    case BankTransfer = 'Bank Transfer';
    case Check = 'Check';
    case Cash = 'Cash';
}
