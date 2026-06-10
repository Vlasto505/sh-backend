<?php

namespace App\Policies;

use App\Models\Call;
use App\Models\User;

class CallPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Call $call): bool
    {
        return $call->isOpen() || ($user && $user->hasAnyRole(['admin', 'super_admin', 'editor']));
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('calls.create');
    }

    public function update(User $user, Call $call): bool
    {
        return $user->hasPermissionTo('calls.edit');
    }

    public function close(User $user, Call $call): bool
    {
        return $user->hasPermissionTo('calls.close');
    }

    public function delete(User $user, Call $call): bool
    {
        return $user->hasRole('super_admin');
    }
}
