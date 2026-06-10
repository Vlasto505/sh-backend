<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\Attachment;
use App\Models\User;

class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        $attachable = $attachment->attachable;

        if ($attachable instanceof Application) {
            return app(ApplicationPolicy::class)->view($user, $attachable);
        }

        return $user->hasAnyRole(['admin', 'super_admin']);
    }

    public function create(User $user, Application $application): bool
    {
        return app(ApplicationPolicy::class)->update($user, $application);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        $attachable = $attachment->attachable;

        if ($attachable instanceof Application) {
            return app(ApplicationPolicy::class)->update($user, $attachable);
        }

        return $user->hasAnyRole(['admin', 'super_admin']);
    }
}
