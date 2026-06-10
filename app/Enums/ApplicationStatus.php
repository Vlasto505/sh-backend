<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Draft               = 'draft';
    case Submitted           = 'submitted';
    case UnderReview         = 'under_review';
    case SupplementRequested = 'supplement_requested';
    case Approved            = 'approved';
    case Rejected            = 'rejected';
    case Withdrawn           = 'withdrawn';
}
