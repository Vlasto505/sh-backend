<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('organizations.view_all')
            || $user->hasAnyRole(['admin', 'super_admin']);
    }

    public function view(?User $user, Organization $organization): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('organizations.create');
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->hasPermissionTo('organizations.edit_own')
            && $organization->users()->where('users.id', $user->id)->exists();
    }
}
