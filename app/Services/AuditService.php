<?php

namespace App\Services;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public static function log(string $action, ?Model $subject = null, array $metadata = []): void
    {
        AuditEvent::create([
            'actor_id'     => Auth::id(),
            'action'       => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id'   => $subject?->getKey(),
            'metadata'     => empty($metadata) ? null : $metadata,
            'ip_address'   => Request::ip(),
        ]);
    }
}
