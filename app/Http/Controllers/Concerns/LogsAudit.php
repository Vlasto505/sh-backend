<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AuditEvent;
use Illuminate\Http\Request;

trait LogsAudit
{
    /**
     * Write an entry to the audit trail (section 13 of the NTI spec).
     */
    protected function audit(Request $request, string $action, string $subjectType, int $subjectId, array $metadata = []): void
    {
        AuditEvent::create([
            'actor_id'     => $request->user()?->id,
            'action'       => $action,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'metadata'     => $metadata,
            'ip_address'   => $request->ip(),
        ]);
    }
}
