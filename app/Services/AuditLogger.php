<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogger
{
    public function record(
        ?User $actor,
        ?int $branchId,
        string $event,
        string $subjectType,
        ?int $subjectId,
        string $summary,
    ): AuditLog {
        return AuditLog::create([
            'actor_user_id' => $actor?->id,
            'branch_id' => $branchId,
            'event' => $event,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'summary' => $summary,
            'created_at' => now(),
        ]);
    }
}
