<?php

namespace App\Enums;

enum EvaluationStatus: string
{
    case Pending    = 'pending';
    case InProgress = 'in_progress';
    case Completed  = 'completed';
}
