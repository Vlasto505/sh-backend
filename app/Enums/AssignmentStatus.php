<?php

namespace App\Enums;

/**
 * Program B company assignment lifecycle (spec 8.2).
 */
enum AssignmentStatus: string
{
    case Draft      = 'draft';        // rozpracované firmou
    case Backlog    = 'backlog';      // publikované do backlogu
    case Matching   = 'matching';     // v párovaní
    case Assigned   = 'assigned';     // pridelené tímu
    case InProgress = 'in_progress';  // v realizácii
    case Closed     = 'closed';       // uzavreté

    public function label(): string
    {
        return match ($this) {
            self::Draft      => 'Koncept',
            self::Backlog    => 'V backlogu',
            self::Matching   => 'V párovaní',
            self::Assigned   => 'Pridelené',
            self::InProgress => 'V realizácii',
            self::Closed     => 'Uzavreté',
        };
    }

    /** Statuses a company may set itself. */
    public static function companySettable(): array
    {
        return [self::Draft->value, self::Backlog->value];
    }

    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
