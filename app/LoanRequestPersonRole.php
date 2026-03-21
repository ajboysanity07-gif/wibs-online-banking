<?php

namespace App;

enum LoanRequestPersonRole: string
{
    case Applicant = 'applicant';
    case CoMakerOne = 'co_maker_1';
    case CoMakerTwo = 'co_maker_2';
}
