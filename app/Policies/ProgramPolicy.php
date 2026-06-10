<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;

class ProgramPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Program $program): bool
    {
        return $program->is_active || ($user && $user->hasAnyRole(['admin', 'super_admin', 'editor']));
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin']);
    }

    public function update(User $user, Program $program): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin']);
    }

    public function delete(User $user, Program $program): bool
    {
        return $user->hasRole('super_admin');
    }
}
