<?php

namespace App\Enums;

enum CallStatus: string
{
    case Draft    = 'draft';
    case Open     = 'open';
    case Closed   = 'closed';
    case Archived = 'archived';
}
