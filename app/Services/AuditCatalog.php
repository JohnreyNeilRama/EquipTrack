<?php

namespace App\Services;

/**
 * Describes every kind of activity recorded in `audit_trail` and turns a raw
 * row (`action` + `affected_entity`) into the labels the Audit Trail page shows
 * and filters by.
 *
 * Rows are matched on the START of the `action` text, so rows written before the
 * Audit Trail page existed (for example "Approved borrow request #12 for ...")
 * and rows written by AuditLogger are classified the same way. Each prefix maps
 * to the noun that begins the readable description.
 */
class AuditCatalog
{
    private const RULES = [
        // Authentication
        ['label' => 'Login', 'module' => 'Authentication', 'prefixes' => ['Login' => '']],
        ['label' => 'Logout', 'module' => 'Authentication', 'prefixes' => ['Logout' => '']],
        ['label' => 'Requested Password Reset', 'module' => 'Authentication', 'prefixes' => ['Requested password reset' => 'Password reset']],
        ['label' => 'Reset Password', 'module' => 'Authentication', 'prefixes' => ['Reset password via email link' => 'Password']],

        // Equipment management
        ['label' => 'Added Equipment', 'module' => 'Equipment Management', 'prefixes' => ['Registered equipment' => 'Equipment']],
        ['label' => 'Updated Equipment', 'module' => 'Equipment Management', 'prefixes' => ['Updated equipment' => 'Equipment']],
        ['label' => 'Deleted Equipment', 'module' => 'Equipment Management', 'prefixes' => ['Deleted equipment' => 'Equipment']],
        ['label' => 'Added Category', 'module' => 'Equipment Management', 'prefixes' => ['Added category' => 'Category']],

        // Borrow requests
        ['label' => 'Submitted Request', 'module' => 'Requests', 'prefixes' => ['Submitted borrow request' => 'Borrow request']],
        ['label' => 'Approved Request', 'module' => 'Requests', 'prefixes' => [
            'Approved borrow request' => 'Borrow request',
            'Department approved borrow request' => 'Borrow request (department)',
        ]],
        ['label' => 'Rejected Request', 'module' => 'Requests', 'prefixes' => [
            'Rejected borrow request' => 'Borrow request',
            'Department rejected borrow request' => 'Borrow request (department)',
        ]],

        // Returns
        ['label' => 'Returned Equipment', 'module' => 'Returns', 'prefixes' => [
            // Written before Return Confirmation, when a return settled the loan at once.
            'Returned borrow request' => 'Borrow request',
            'Submitted return for borrow request' => 'Return of borrow request',
        ]],
        ['label' => 'Confirmed Return', 'module' => 'Returns', 'prefixes' => [
            'Department confirmed return of borrow request' => 'Return of borrow request (department)',
        ]],
        ['label' => 'Rejected Return', 'module' => 'Returns', 'prefixes' => [
            'Department rejected return of borrow request' => 'Return of borrow request (department)',
        ]],

        // Damage & penalty recording
        ['label' => 'Recorded Penalty', 'module' => 'Penalties', 'prefixes' => ['Recorded penalty' => 'Penalty']],
        ['label' => 'Updated Penalty', 'module' => 'Penalties', 'prefixes' => ['Updated penalty' => 'Penalty']],
        ['label' => 'Settled Penalty', 'module' => 'Penalties', 'prefixes' => ['Settled penalty' => 'Penalty']],
        ['label' => 'Waived Penalty', 'module' => 'Penalties', 'prefixes' => ['Waived penalty' => 'Penalty']],
        ['label' => 'Submitted Proof', 'module' => 'Penalties', 'prefixes' => ['Submitted proof for penalty' => 'Proof for penalty']],
        ['label' => 'Approved Proof', 'module' => 'Penalties', 'prefixes' => ['Approved proof for penalty' => 'Proof for penalty']],
        ['label' => 'Rejected Proof', 'module' => 'Penalties', 'prefixes' => ['Rejected proof for penalty' => 'Proof for penalty']],

        // User management
        ['label' => 'Registered Account', 'module' => 'Users', 'prefixes' => ['Registered account' => 'Account']],
        ['label' => 'Added User', 'module' => 'Users', 'prefixes' => [
            'Added student account' => 'Student account',
            'Added department account' => 'Department account',
        ]],
        ['label' => 'Updated User', 'module' => 'Users', 'prefixes' => ['Updated user' => 'User']],
        ['label' => 'Activated User', 'module' => 'Users', 'prefixes' => ['Activated user account' => 'Account']],
        ['label' => 'Deactivated User', 'module' => 'Users', 'prefixes' => ['Deactivated user account' => 'Account']],
        ['label' => 'Deleted User', 'module' => 'Users', 'prefixes' => [
            'Deleted user account' => 'Account',
            'Deleted department account' => 'Department account',
        ]],

        // Own account (admin, department and user profile pages)
        ['label' => 'Updated Profile', 'module' => 'Profile', 'prefixes' => [
            // Longer prefix first, so a picture change keeps its own description.
            'Updated profile picture' => 'Profile picture',
            'Updated profile' => 'Profile',
        ]],
        ['label' => 'Changed Password', 'module' => 'Profile', 'prefixes' => ['Changed password' => 'Password']],

        // Reports
        ['label' => 'Generated Report', 'module' => 'Reports', 'prefixes' => ['Generated report' => 'Report']],
        ['label' => 'Exported Report', 'module' => 'Reports', 'prefixes' => ['Exported report' => 'Report']],

        // Audit trail
        ['label' => 'Exported Audit Log', 'module' => 'Audit Trail', 'prefixes' => ['Exported audit log' => 'Audit log']],
    ];

    /** Action labels offered by the Action filter. */
    public static function actions(): array
    {
        return array_column(self::RULES, 'label');
    }

    /** Module names offered by the Module filter (in display order). */
    public static function modules(): array
    {
        return array_values(array_unique(array_column(self::RULES, 'module')));
    }

    /** `action` prefixes that make up one action label (used to filter in SQL). */
    public static function prefixesForAction(string $label): array
    {
        $prefixes = [];

        foreach (self::RULES as $rule) {
            if ($rule['label'] === $label) {
                array_push($prefixes, ...array_keys($rule['prefixes']));
            }
        }

        return $prefixes;
    }

    /** `action` prefixes that make up one module (used to filter in SQL). */
    public static function prefixesForModule(string $module): array
    {
        $prefixes = [];

        foreach (self::RULES as $rule) {
            if ($rule['module'] === $module) {
                array_push($prefixes, ...array_keys($rule['prefixes']));
            }
        }

        return $prefixes;
    }

    /**
     * Classifies a stored action text.
     *
     * @return array{label: string, module: string, noun: string, rest: string}
     */
    public static function classify(string $action): array
    {
        $lower = mb_strtolower($action);

        foreach (self::RULES as $rule) {
            foreach ($rule['prefixes'] as $prefix => $noun) {
                if (str_starts_with($lower, mb_strtolower((string) $prefix))) {
                    return [
                        'label' => $rule['label'],
                        'module' => $rule['module'],
                        'noun' => $noun,
                        'rest' => trim(mb_substr($action, mb_strlen((string) $prefix))),
                    ];
                }
            }
        }

        return ['label' => 'Other Activity', 'module' => 'Other', 'noun' => '', 'rest' => trim($action)];
    }

    /** "Borrow request" + "#12 for Dell" -> "Borrow request #12 for Dell". */
    public static function description(string $noun, string $rest): string
    {
        if ($rest === '') {
            return $noun;
        }

        return $noun === '' || str_starts_with($rest, ':')
            ? trim($noun . $rest)
            : $noun . ' ' . $rest;
    }

    /** Readable name of the record an audit row refers to (from `affected_entity`). */
    public static function entityLabel(string $entity): string
    {
        $parts = explode(':', $entity, 2);
        $kind = $parts[0];
        $id = $parts[1] ?? '';

        return match ($kind) {
            'borrow_request' => 'Request #' . $id,
            'equipment' => 'Equipment #' . $id,
            'category' => 'Category #' . $id,
            'user_account' => 'User account #' . $id,
            'admin' => 'Admin account #' . $id,
            'penalty' => 'Penalty #' . $id,
            'department_account' => 'Department account #' . $id,
            'report' => 'Report: ' . ucfirst($id),
            'audit' => 'Audit trail',
            'auth' => match ($id) {
                'admin' => 'Administrator account',
                'dept' => 'Department account',
                'user' => 'User account',
                default => 'Account',
            },
            default => $entity !== '' ? $entity : '—',
        };
    }
}
