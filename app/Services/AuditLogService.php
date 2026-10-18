<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    public function record(Model|string $entity, int|string|null $entityId, string $action, array $old = [], array $new = []): AuditLog
    {
        $entityType = is_string($entity) ? $entity : $entity::class;
        $id = $entityId ?? (is_string($entity) ? null : $entity->getKey());

        return AuditLog::create([
            'entity_type' => $entityType,
            'entity_id' => $id,
            'action' => $action,
            'old_values' => $this->sanitize($old),
            'new_values' => $this->sanitize($new),
            'changed_by' => Auth::id(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'changed_at' => now(),
        ]);
    }

    private function sanitize(array $values): array
    {
        $sensitive = ['account_number', 'account_number_encrypted', 'password', 'token', 'secret'];

        foreach ($values as $key => $value) {
            if (in_array($key, $sensitive, true)) {
                $values[$key] = $this->mask((string) $value);
            } elseif (is_array($value)) {
                $values[$key] = $this->sanitize($value);
            }
        }

        return $values;
    }

    private function mask(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value);

        return 'XXXX'.substr($digits, -4);
    }
}
