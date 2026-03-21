<?php

namespace App;

enum LoanRequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
}
