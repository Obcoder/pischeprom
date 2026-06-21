<?php

namespace App\Services\CommercialOffers;

use App\Models\MailingAuditLog;
use Illuminate\Http\Request;

class MailingAuditLogger
{
    public function log(string $action, string $entityType, int|string|null $entityId = null, ?array $before = null, ?array $after = null, ?Request $request = null, ?int $userId = null): MailingAuditLog
    {
        return MailingAuditLog::query()->create([
            'user_id' => $userId ?? $request?->user()?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => is_numeric($entityId) ? (int) $entityId : null,
            'before_json' => $before,
            'after_json' => $after,
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
