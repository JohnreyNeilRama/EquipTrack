<?php

namespace App\Services;

use App\Models\AuditTrail;
use Illuminate\Http\Request;

/**
 * Single write path for new audit_trail rows.
 *
 * - Works out who is acting (admin, department or user) unless the caller
 *   passes the actor explicitly (needed for login, logout and registration,
 *   where the guard state differs from the person being recorded).
 * - Trims every value to its column size instead of letting the insert fail.
 * - Never lets a logging problem break the action being logged.
 * - Ignores an identical entry written twice within one request.
 * - Leaves `created_at` to the database default so new rows use the same clock
 *   as the existing ones.
 *
 * The `action` text must start with one of the prefixes listed in
 * AuditCatalog so the Audit Trail page can classify and filter it.
 */
class AuditLogger
{
    /**
     * @param  array{user_id?: int|null, admin_id?: int|null, dept_acc_id?: int|null}|null  $actor
     */
    public static function log(
        string $action,
        string $entity = '',
        ?string $details = null,
        ?array $actor = null,
        ?Request $request = null,
    ): ?AuditTrail {
        try {
            $request ??= request();
            $actor ??= self::currentActor();

            $row = [
                'user_id' => $actor['user_id'] ?? null,
                'admin_id' => $actor['admin_id'] ?? null,
                'dept_acc_id' => $actor['dept_acc_id'] ?? null,
                'action' => mb_substr($action, 0, 100),
                'ip_address' => mb_substr((string) ($request->ip() ?: '127.0.0.1'), 0, 45),
                'affected_entity' => mb_substr($entity, 0, 100),
                'details' => ($details !== null && trim($details) !== '') ? mb_substr(trim($details), 0, 255) : null,
            ];

            // The same entry twice in one request is a bug, not two events.
            $key = md5(json_encode($row));
            $seen = $request->attributes->get('audit.logged', []);
            if (isset($seen[$key])) {
                return null;
            }
            $seen[$key] = true;
            $request->attributes->set('audit.logged', $seen);

            return AuditTrail::create($row);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Labels of the fields whose value differs between $before and $after,
     * in the order of $labels. Values are compared as trimmed text, so null
     * and '' count as the same (no change).
     *
     * @param  array<string, string>  $labels  field => label
     * @return array<int, string>
     */
    public static function changedFields(array $before, array $after, array $labels): array
    {
        $changed = [];

        foreach ($labels as $field => $label) {
            if (trim((string) ($before[$field] ?? '')) !== trim((string) ($after[$field] ?? ''))) {
                $changed[] = $label;
            }
        }

        return $changed;
    }

    /** The audit_trail actor column for an authenticated account of the given guard. */
    public static function actorFor(string $guard, $account): array
    {
        return match ($guard) {
            'admin' => ['admin_id' => $account->admin_id],
            'dept' => ['dept_acc_id' => $account->dept_acc_id],
            default => ['user_id' => $account->user_id],
        };
    }

    /** Whoever is signed in on this request (one identity per session). */
    private static function currentActor(): array
    {
        foreach (['admin', 'dept', 'user'] as $guard) {
            if ($account = auth($guard)->user()) {
                return self::actorFor($guard, $account);
            }
        }

        return [];
    }
}
