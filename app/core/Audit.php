<?php
namespace JanKavach\Core;

/**
 * Audit trail (specification section 29).
 *
 * Records who did what, when, from where, on which record - and for
 * important records the before/after values, so a change can be explained
 * months later.
 */
class Audit
{
    /** Fields that must never be written into an audit snapshot. */
    private const REDACT = [
        'password', 'password_hash', 'password_confirmation', 'new_password',
        'current_password', 'remember_token', 'aadhaar_hash', 'aadhaar_number',
        '_token', 'token_hash', 'csrf',
    ];

    /**
     * Audit::log('Scheme', 'Modified', [
     *     'entity_type' => 'Scheme', 'entity_id' => 12,
     *     'entity_label' => 'PMMVY',
     *     'old_values' => $before, 'new_values' => $after,
     *     'description' => 'Updated eligibility rules',
     * ]);
     */
    public static function log(string $module, string $action, array $options = []): void
    {
        try {
            $request = new Request();
            $user    = Auth::user();

            $old = $options['old_values'] ?? null;
            $new = $options['new_values'] ?? null;

            // Only store what actually changed, so the log stays readable.
            if (is_array($old) && is_array($new)) {
                [$old, $new] = self::diff($old, $new);
                if ($old === [] && $new === [] && $action === 'Modified') {
                    return;   // nothing changed; do not create noise
                }
            }

            Database::getInstance()->insert('audit_logs', [
                'user_id'      => $user['id'] ?? null,
                'user_name'    => $user['full_name'] ?? ($options['user_name'] ?? 'System'),
                'role_code'    => $user['role_code'] ?? null,
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'module'       => substr($module, 0, 60),
                'action'       => $action,
                'entity_type'  => isset($options['entity_type']) ? substr((string)$options['entity_type'], 0, 60) : null,
                'entity_id'    => isset($options['entity_id']) ? (int)$options['entity_id'] : null,
                'entity_label' => isset($options['entity_label']) ? substr((string)$options['entity_label'], 0, 255) : null,
                'description'  => isset($options['description']) ? substr((string)$options['description'], 0, 500) : null,
                'old_values'   => self::encode($old),
                'new_values'   => self::encode($new),
            ]);
        } catch (\Throwable $e) {
            // Auditing must never break the operation it is recording.
            Logger::error('Audit write failed: ' . $e->getMessage(), [
                'module' => $module, 'action' => $action,
            ]);
        }
    }

    public static function created(string $module, string $entityType, int $id, ?string $label, array $values = [], string $description = ''): void
    {
        self::log($module, 'Created', [
            'entity_type'  => $entityType,
            'entity_id'    => $id,
            'entity_label' => $label,
            'new_values'   => $values,
            'description'  => $description ?: "Created {$entityType}" . ($label ? ": {$label}" : ''),
        ]);
    }

    public static function modified(string $module, string $entityType, int $id, ?string $label, array $before, array $after, string $description = ''): void
    {
        self::log($module, 'Modified', [
            'entity_type'  => $entityType,
            'entity_id'    => $id,
            'entity_label' => $label,
            'old_values'   => $before,
            'new_values'   => $after,
            'description'  => $description ?: "Updated {$entityType}" . ($label ? ": {$label}" : ''),
        ]);
    }

    public static function deleted(string $module, string $entityType, int $id, ?string $label, array $values = [], string $description = ''): void
    {
        self::log($module, 'Deleted', [
            'entity_type'  => $entityType,
            'entity_id'    => $id,
            'entity_label' => $label,
            'old_values'   => $values,
            'description'  => $description ?: "Deleted {$entityType}" . ($label ? ": {$label}" : ''),
        ]);
    }

    public static function viewed(string $module, string $entityType, int $id, ?string $label = null): void
    {
        self::log($module, 'Viewed', [
            'entity_type'  => $entityType,
            'entity_id'    => $id,
            'entity_label' => $label,
            'description'  => "Viewed {$entityType}" . ($label ? ": {$label}" : ''),
        ]);
    }

    public static function exported(string $module, string $what, int $rowCount, array $filters = []): void
    {
        self::log($module, 'Exported', [
            'entity_type' => $what,
            'description' => "Exported {$rowCount} row(s) of {$what}",
            'new_values'  => ['filters' => $filters],
        ]);
    }

    /**
     * Keep only the keys whose value actually changed, and redact secrets.
     */
    private static function diff(array $before, array $after): array
    {
        $oldOut = [];
        $newOut = [];

        foreach ($after as $key => $newValue) {
            if (self::isRedacted($key)) {
                continue;
            }
            $oldValue = $before[$key] ?? null;

            // Loose comparison on scalars: "1" from a form equals 1 from the DB.
            $same = is_scalar($oldValue) && is_scalar($newValue)
                ? ((string)$oldValue === (string)$newValue)
                : ($oldValue == $newValue);

            if (!$same) {
                $oldOut[$key] = $oldValue;
                $newOut[$key] = $newValue;
            }
        }
        return [$oldOut, $newOut];
    }

    private static function isRedacted(string $key): bool
    {
        $lower = strtolower($key);
        foreach (self::REDACT as $needle) {
            if ($lower === $needle || str_contains($lower, 'password')) {
                return true;
            }
        }
        return false;
    }

    private static function encode($values): ?string
    {
        if ($values === null || $values === [] ) {
            return null;
        }
        if (is_array($values)) {
            foreach (array_keys($values) as $key) {
                if (self::isRedacted((string)$key)) {
                    $values[$key] = '[redacted]';
                }
            }
        }
        $json = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        // Keep very large payloads from bloating the table.
        return $json === false ? null : substr($json, 0, 60000);
    }
}
