<?php
namespace JanKavach\Core;

/**
 * Configuration registry with dot-notation access, plus a lazy read-through
 * cache over the `system_settings` table so administrators can change
 * behaviour without touching files.
 */
class Config
{
    private static array $items = [];
    private static ?array $dbSettings = null;

    public static function load(array $config): void
    {
        self::$items = $config;
    }

    /** Config::get('app.name') */
    public static function get(string $key, $default = null)
    {
        $segments = explode('.', $key);
        $value    = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public static function set(string $key, $value): void
    {
        $segments = explode('.', $key);
        $ref = &self::$items;
        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }

    public static function all(): array
    {
        return self::$items;
    }

    // ------------------------------------------------------------------
    // Database-backed settings
    // ------------------------------------------------------------------

    /** Read a value from system_settings, cast to its declared type. */
    public static function setting(string $key, $default = null)
    {
        if (self::$dbSettings === null) {
            self::loadDbSettings();
        }
        if (!array_key_exists($key, self::$dbSettings)) {
            return $default;
        }
        return self::$dbSettings[$key];
    }

    public static function settingsAll(): array
    {
        if (self::$dbSettings === null) {
            self::loadDbSettings();
        }
        return self::$dbSettings;
    }

    public static function flushSettings(): void
    {
        self::$dbSettings = null;
    }

    private static function loadDbSettings(): void
    {
        self::$dbSettings = [];
        try {
            $rows = Database::getInstance()
                ->select('SELECT setting_key, setting_value, value_type FROM system_settings');
            foreach ($rows as $row) {
                self::$dbSettings[$row['setting_key']] = self::cast($row['setting_value'], $row['value_type']);
            }
        } catch (\Throwable $e) {
            // Settings table may not exist yet during first-time install.
            Logger::warning('system_settings unavailable: ' . $e->getMessage());
        }
    }

    private static function cast(?string $value, string $type)
    {
        if ($value === null) {
            return null;
        }
        return match ($type) {
            'int'  => (int)$value,
            'bool' => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true),
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
