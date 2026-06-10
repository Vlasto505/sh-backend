<?php

namespace App\Policies;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['applications.view_own', 'applications.view_all']);
    }

    public function view(User $user, Application $application): bool
    {
        if ($user->hasPermissionTo('applications.view_all')) {
            return true;
        }

        return $application->user_id === $user->id
            || ($application->team && $application->team->members()->where('users.id', $user->id)->exists());
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['student', 'team_leader', 'company_contact', 'company_product_owner']);
    }

    public function update(User $user, Application $application): bool
    {
        if ($application->status !== ApplicationStatus::Draft
            && $application->status !== ApplicationStatus::SupplementRequested) {
            return false;
        }

        return $application->user_id === $user->id
            || ($application->team && $application->team->leader_id === $user->id);
    }

    public function submit(User $user, Application $application): bool
    {
        return $this->update($user, $application)
            && $application->status === ApplicationStatus::Draft;
    }

    public function evaluate(User $user, Application $application): bool
    {
        return $user->hasPermissionTo('applications.evaluate');
    }

    public function decide(User $user, Application $application): bool
    {
        return $user->hasPermissionTo('applications.decide');
    }

    public function delete(User $user, Application $application): bool
    {
        return $application->status === ApplicationStatus::Draft
            && $application->user_id === $user->id;
    }
}
