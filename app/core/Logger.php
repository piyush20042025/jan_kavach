<?php
namespace JanKavach\Core;

/**
 * Minimal file logger. Writes to storage/logs/app-YYYY-MM-DD.log.
 * Used for application faults; business events go to `audit_logs` instead.
 */
class Logger
{
    private const LEVELS = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];

    public static function debug(string $message, array $context = []): void
    {
        self::write('debug', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    private static function write(string $level, string $message, array $context = []): void
    {
        if (!Config::get('log.enabled', true)) {
            return;
        }
        $minimum = self::LEVELS[Config::get('log.level', 'debug')] ?? 0;
        if ((self::LEVELS[$level] ?? 0) < $minimum) {
            return;
        }

        $dir = defined('LOG_PATH') ? LOG_PATH : (dirname(__DIR__, 2) . '/storage/logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $line = sprintf(
            "[%s] %-7s %s%s%s",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
            PHP_EOL
        );

        @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }
}
