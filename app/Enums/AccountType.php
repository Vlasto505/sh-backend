<?php

namespace App\Enums;

enum AccountType: string
{
    case Student    = 'student';
    case Company    = 'company';
    case Mentor     = 'mentor';
    case Editor     = 'editor';
    case Admin      = 'admin';
    case SuperAdmin = 'super_admin';

    public function defaultRole(): string
    {
        return match($this) {
            self::Student    => 'student',
            self::Company    => 'company_contact',
            self::Mentor     => 'mentor',
            self::Editor     => 'editor',
            self::Admin      => 'admin',
            self::SuperAdmin => 'super_admin',
        };
    }
}
