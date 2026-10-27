<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PlatformAuditLog;

/**
 * Jejak audit tindakan operator. Selalu ditulis ke platform_audit_logs; bila menyangkut sebuah workspace,
 * dicatat juga di audit log workspace (tampil sebagai "Notifora Support", tanpa identitas operator).
 */
class AdminAudit
{
    public static function record(string $action, ?string $targetType = null, ?string $targetId = null, ?string $workspaceId = null, array $meta = []): void
    {
        $adminId = session('impersonator_id') ?? auth()->id();

        PlatformAuditLog::create([
            'admin_id' => $adminId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'workspace_id' => $workspaceId,
            'meta' => $meta ?: null,
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);

        if ($workspaceId) {
            AuditLog::create([
                'workspace_id' => $workspaceId,
                'user_id' => null,
                'action' => 'admin.'.$action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'meta' => $meta ?: null,
                'created_at' => now(),
            ]);
        }
    }
}
