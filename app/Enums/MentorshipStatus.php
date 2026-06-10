<?php

namespace App\Enums;

enum MentorshipStatus: string
{
    case Active    = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
