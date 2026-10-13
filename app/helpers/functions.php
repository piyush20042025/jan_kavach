<?php
/**
 * Global helper functions.
 *
 * These are intentionally short names because views use them constantly.
 * Anything that emits to HTML escapes by default; the only raw output is
 * through the explicitly-named raw helpers.
 */

use JanKavach\Core\Auth;
use JanKavach\Core\Config;
use JanKavach\Core\Csrf;
use JanKavach\Core\Request;

// ---------------------------------------------------------------------
// Escaping / output
// ---------------------------------------------------------------------

if (!function_exists('e')) {
    /** HTML-escape. Used on every piece of dynamic text in the views. */
    function e($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_array($value)) {
            $value = implode(', ', $value);
        }
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('eattr')) {
    /** Escape for an HTML attribute (same as e(), named for intent). */
    function eattr($value): string
    {
        return e($value);
    }
}

if (!function_exists('ejs')) {
    /** Escape a PHP value for embedding in inline JavaScript. */
    function ejs($value): string
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('nl2br_e')) {
    /** Escape, then turn newlines into <br> - for stored multi-line text. */
    function nl2br_e($value): string
    {
        return nl2br(e($value));
    }
}

if (!function_exists('safe_html')) {
    /**
     * Allow a very small tag whitelist through for rich-text fields
     * (benefit descriptions etc.) while stripping attributes that could
     * carry script.
     */
    function safe_html(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }
        $allowed = '<p><br><b><strong><i><em><u><ul><ol><li><h4><h5><h6><table><thead><tbody><tr><th><td><span>';
        $clean = strip_tags($html, $allowed);
        // Remove every on* handler and javascript: URL that survived.
        $clean = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean);
        $clean = preg_replace('/javascript\s*:/i', '', $clean);
        $clean = preg_replace('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $clean);
        return $clean;
    }
}

// ---------------------------------------------------------------------
// URLs
// ---------------------------------------------------------------------

if (!function_exists('base_url')) {
    /**
     * Auto-detect the application base URL so the app runs unchanged from
     * a sub-folder (http://host/FXB/jan-kavach/public) or a vhost root.
     */
    function base_url(): string
    {
        static $base = null;
        if ($base !== null) {
            return $base;
        }

        $configured = Config::get('url.base');
        if ($configured) {
            return $base = rtrim($configured, '/');
        }

        $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || ($_SERVER['SERVER_PORT'] ?? null) == 443
               || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $scheme = $https ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

        return $base = $scheme . '://' . $host . $dir;
    }
}

if (!function_exists('url')) {
    /** Absolute URL for an application path. */
    function url(string $path = '/'): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return base_url() . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /** URL for a file under public/assets, cache-busted by mtime. */
    function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = PUBLIC_PATH . '/' . Config::get('url.assets', 'assets') . '/' . $path;
        $url  = base_url() . '/' . Config::get('url.assets', 'assets') . '/' . $path;

        if (is_file($file)) {
            return $url . '?v=' . filemtime($file);
        }
        return $url;
    }
}

if (!function_exists('current_url')) {
    function current_url(): string
    {
        return url((new Request())->path());
    }
}

if (!function_exists('query_url')) {
    /**
     * Current path with the query string merged - used by sortable table
     * headers and filter chips so existing filters are preserved.
     */
    function query_url(array $params, array $remove = []): string
    {
        $query = $_GET ?? [];
        foreach ($remove as $key) {
            unset($query[$key]);
        }
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                unset($query[$key]);
            } else {
                $query[$key] = $value;
            }
        }
        $path = (new Request())->path();
        return url($path) . ($query ? '?' . http_build_query($query) : '');
    }
}

if (!function_exists('is_active_path')) {
    /** True when the current URL is under the given path - for nav highlighting. */
    function is_active_path(string $path, bool $exact = false): bool
    {
        $current = (new Request())->path();
        $path    = '/' . trim($path, '/');
        return $exact ? $current === $path : ($current === $path || str_starts_with($current, $path . '/'));
    }
}

if (!function_exists('nav_active')) {
    function nav_active(string $path, bool $exact = false): string
    {
        return is_active_path($path, $exact) ? 'active' : '';
    }
}

// ---------------------------------------------------------------------
// Auth shortcuts for views
// ---------------------------------------------------------------------

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('can')) {
    function can(string $permission): bool
    {
        return Auth::can($permission);
    }
}

if (!function_exists('can_any')) {
    function can_any(array $permissions): bool
    {
        return Auth::canAny($permissions);
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('method_field')) {
    /** Spoof PUT/PATCH/DELETE from an HTML form. */
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('old')) {
    /**
     * Repopulate a form field after a failed validation round-trip.
     * $old is shared into every view by the base controller.
     */
    function old(string $key, $default = '', array $oldInput = [])
    {
        if (array_key_exists($key, $oldInput)) {
            return $oldInput[$key];
        }
        return $default;
    }
}

// ---------------------------------------------------------------------
// Formatting
// ---------------------------------------------------------------------

if (!function_exists('fmt_date')) {
    function fmt_date(?string $date, string $empty = '-'): string
    {
        if (empty($date) || $date === '0000-00-00' || str_starts_with((string)$date, '0000')) {
            return $empty;
        }
        $time = strtotime($date);
        return $time === false ? $empty : date(Config::get('app.date_format', 'd-m-Y'), $time);
    }
}

if (!function_exists('fmt_datetime')) {
    function fmt_datetime(?string $datetime, string $empty = '-'): string
    {
        if (empty($datetime) || str_starts_with((string)$datetime, '0000')) {
            return $empty;
        }
        $time = strtotime($datetime);
        return $time === false ? $empty : date(Config::get('app.datetime_format', 'd-m-Y h:i A'), $time);
    }
}

if (!function_exists('time_ago')) {
    function time_ago(?string $datetime): string
    {
        if (empty($datetime)) {
            return '-';
        }
        $time = strtotime($datetime);
        if ($time === false) {
            return '-';
        }
        $diff = time() - $time;

        if ($diff < 0)      return fmt_datetime($datetime);
        if ($diff < 60)     return 'just now';
        if ($diff < 3600)   return floor($diff / 60) . ' min ago';
        if ($diff < 86400)  return floor($diff / 3600) . ' hr ago';
        if ($diff < 604800) return floor($diff / 86400) . ' day' . (floor($diff / 86400) > 1 ? 's' : '') . ' ago';

        return fmt_date($datetime);
    }
}

if (!function_exists('money')) {
    /** Indian-format currency with the rupee sign. */
    function money($amount, bool $symbol = true, string $empty = '-'): string
    {
        if ($amount === null || $amount === '') {
            return $empty;
        }
        $formatted = indian_number((float)$amount);
        return ($symbol ? Config::get('app.currency_symbol', "\u{20B9}") . ' ' : '') . $formatted;
    }
}

if (!function_exists('indian_number')) {
    /**
     * 1234567.5 -> "12,34,567.50" (lakh/crore grouping).
     */
    function indian_number(float $number, int $decimals = 2): string
    {
        $negative = $number < 0;
        $number   = abs($number);

        $parts    = explode('.', number_format($number, $decimals, '.', ''));
        $integer  = $parts[0];
        $fraction = $parts[1] ?? '';

        if (strlen($integer) > 3) {
            $last3 = substr($integer, -3);
            $rest  = substr($integer, 0, -3);
            $rest  = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $integer = $rest . ',' . $last3;
        }

        $out = $integer . ($decimals > 0 && $fraction !== '' ? '.' . $fraction : '');
        return ($negative ? '-' : '') . $out;
    }
}

if (!function_exists('short_money')) {
    /** Compact currency for KPI tiles: 1.2 Cr, 4.5 L, 12.3 K. */
    function short_money($amount, string $empty = '-'): string
    {
        if ($amount === null || $amount === '') {
            return $empty;
        }
        $amount = (float)$amount;
        $sign   = $amount < 0 ? '-' : '';
        $abs    = abs($amount);
        $symbol = Config::get('app.currency_symbol', "\u{20B9}");

        if ($abs >= 10000000)  return $sign . $symbol . ' ' . round($abs / 10000000, 2) . ' Cr';
        if ($abs >= 100000)    return $sign . $symbol . ' ' . round($abs / 100000, 2) . ' L';
        if ($abs >= 1000)      return $sign . $symbol . ' ' . round($abs / 1000, 1) . ' K';
        return $sign . $symbol . ' ' . indian_number($abs, 0);
    }
}

if (!function_exists('num')) {
    function num($value, int $decimals = 0): string
    {
        return number_format((float)($value ?? 0), $decimals);
    }
}

if (!function_exists('pct')) {
    function pct($value, int $decimals = 1): string
    {
        return number_format((float)($value ?? 0), $decimals) . '%';
    }
}

if (!function_exists('percent_of')) {
    function percent_of($part, $whole, int $decimals = 1): float
    {
        $whole = (float)$whole;
        if ($whole == 0.0) {
            return 0.0;
        }
        return round(((float)$part / $whole) * 100, $decimals);
    }
}

if (!function_exists('str_limit')) {
    function str_limit(?string $value, int $limit = 100, string $end = '...'): string
    {
        $value = (string)$value;
        if (mb_strlen($value) <= $limit) {
            return $value;
        }
        return rtrim(mb_substr($value, 0, $limit)) . $end;
    }
}

if (!function_exists('initials')) {
    function initials(?string $name, int $count = 2): string
    {
        $words = preg_split('/\s+/', trim((string)$name)) ?: [];
        $out = '';
        foreach ($words as $word) {
            if ($word === '') continue;
            $out .= mb_strtoupper(mb_substr($word, 0, 1));
            if (mb_strlen($out) >= $count) break;
        }
        return $out ?: '?';
    }
}

if (!function_exists('slugify')) {
    function slugify(string $text, int $maxLength = 300): string
    {
        $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text);
        $text = trim((string)$text, '-');
        $text = mb_strtolower($text);
        return mb_substr($text, 0, $maxLength) ?: 'item';
    }
}

// ---------------------------------------------------------------------
// Privacy helpers (specification section 30)
// ---------------------------------------------------------------------

if (!function_exists('mask_aadhaar')) {
    /** Show only the last four digits: "XXXX XXXX 1234". */
    function mask_aadhaar(?string $last4): string
    {
        if (empty($last4)) {
            return 'Not provided';
        }
        return 'XXXX XXXX ' . e($last4);
    }
}

if (!function_exists('mask_mobile')) {
    /** 9876543210 -> "98XXXXXX10" for listing pages. */
    function mask_mobile(?string $mobile): string
    {
        if (empty($mobile)) {
            return '-';
        }
        $mobile = preg_replace('/\D/', '', $mobile);
        if (strlen($mobile) < 6) {
            return str_repeat('X', strlen($mobile));
        }
        return substr($mobile, 0, 2) . str_repeat('X', strlen($mobile) - 4) . substr($mobile, -2);
    }
}

if (!function_exists('mask_document_number')) {
    function mask_document_number(?string $number, bool $sensitive = true): string
    {
        if (empty($number)) {
            return '-';
        }
        if (!$sensitive) {
            return e($number);
        }
        $length = strlen($number);
        if ($length <= 4) {
            return str_repeat('X', $length);
        }
        return str_repeat('X', $length - 4) . substr($number, -4);
    }
}

if (!function_exists('hash_aadhaar')) {
    /** Peppered hash used only for duplicate detection. */
    function hash_aadhaar(string $aadhaar): string
    {
        $digits = preg_replace('/\D/', '', $aadhaar);
        return hash('sha256', $digits . '|' . Config::get('security.aadhaar_pepper'));
    }
}

// ---------------------------------------------------------------------
// UI helpers
// ---------------------------------------------------------------------

if (!function_exists('badge')) {
    function badge(?string $text, string $variant = 'secondary', string $extraClass = ''): string
    {
        if ($text === null || $text === '') {
            return '<span class="text-muted">-</span>';
        }
        return sprintf('<span class="badge bg-%s %s">%s</span>', e($variant), e($extraClass), e($text));
    }
}

if (!function_exists('badge_color')) {
    function badge_color(?string $text, string $hexColor, string $extraClass = ''): string
    {
        if ($text === null || $text === '') {
            return '<span class="text-muted">-</span>';
        }
        return sprintf('<span class="badge %s" style="background-color:%s">%s</span>',
            e($extraClass), e($hexColor ?: '#6c757d'), e($text));
    }
}

if (!function_exists('status_variant')) {
    /** Map a domain status to a Bootstrap colour. */
    function status_variant(?string $status): string
    {
        return match ((string)$status) {
            'Active', 'Verified', 'Approved', 'Eligible', 'Completed', 'Resolved', 'Benefit Received', 'Yes'
                => 'success',
            'Dormant', 'Pending', 'Potentially Eligible', 'In Progress', 'Under Verification', 'Documents Pending'
                => 'warning',
            'Discontinued', 'Rejected', 'Not Eligible', 'Overdue', 'No'
                => 'danger',
            'Needs Verification', 'Identified', 'Eligibility Checked'
                => 'info',
            'Closed', 'Skipped'
                => 'secondary',
            default => 'primary',
        };
    }
}

if (!function_exists('eligibility_variant')) {
    function eligibility_variant(?string $status): string
    {
        return match ((string)$status) {
            'Eligible'             => 'success',
            'Potentially Eligible' => 'warning',
            'Needs Verification'   => 'info',
            'Not Eligible'         => 'danger',
            default                => 'secondary',
        };
    }
}

if (!function_exists('yes_no')) {
    function yes_no($value, string $empty = '-'): string
    {
        if ($value === null || $value === '') {
            return $empty;
        }
        return (int)$value === 1 || $value === 'Yes' || $value === true ? 'Yes' : 'No';
    }
}

if (!function_exists('yes_no_badge')) {
    function yes_no_badge($value): string
    {
        if ($value === null || $value === '') {
            return '<span class="text-muted">-</span>';
        }
        $isYes = (int)$value === 1 || $value === 'Yes' || $value === true;
        return badge($isYes ? 'Yes' : 'No', $isYes ? 'success' : 'secondary');
    }
}

if (!function_exists('sort_link')) {
    /**
     * Sortable column header that toggles direction and keeps filters.
     */
    function sort_link(string $label, string $key, string $currentSort, string $currentDir): string
    {
        $newDir = ($currentSort === $key && strtoupper($currentDir) === 'ASC') ? 'desc' : 'asc';
        $icon   = 'bi-arrow-down-up text-muted';
        if ($currentSort === $key) {
            $icon = strtoupper($currentDir) === 'ASC' ? 'bi-sort-up' : 'bi-sort-down';
        }
        return sprintf('<a href="%s" class="table-sort-link">%s <i class="bi %s small"></i></a>',
            e(query_url(['sort' => $key, 'dir' => $newDir, 'page' => null])),
            e($label), e($icon));
    }
}

if (!function_exists('link_external')) {
    /** Outbound link that cannot manipulate our window. */
    function link_external(?string $url, ?string $label = null, string $class = ''): string
    {
        if (empty($url)) {
            return '<span class="text-muted">-</span>';
        }
        // Only http/https may be rendered as a clickable link.
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array(strtolower((string)$scheme), ['http', 'https'], true)) {
            return e($url);
        }
        $label = $label ?: parse_url($url, PHP_URL_HOST) ?: $url;
        return sprintf('<a href="%s" target="_blank" rel="noopener noreferrer nofollow" class="%s">%s <i class="bi bi-box-arrow-up-right small"></i></a>',
            e($url), e($class), e($label));
    }
}

if (!function_exists('config')) {
    function config(string $key, $default = null)
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('setting')) {
    function setting(string $key, $default = null)
    {
        return Config::setting($key, $default);
    }
}

if (!function_exists('array_get')) {
    function array_get(?array $array, string $key, $default = null)
    {
        if ($array === null) {
            return $default;
        }
        return $array[$key] ?? $default;
    }
}

if (!function_exists('json_list')) {
    /** Decode a JSON column into an array, tolerating nulls and bad data. */
    function json_list($json): array
    {
        if (is_array($json)) {
            return $json;
        }
        if (empty($json)) {
            return [];
        }
        $decoded = json_decode((string)$json, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('age_from_dob')) {
    function age_from_dob(?string $dob): ?int
    {
        if (empty($dob)) {
            return null;
        }
        try {
            $birth = new DateTime($dob);
            $now   = new DateTime('today');
            if ($birth > $now) {
                return null;
            }
            return (int)$birth->diff($now)->y;
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('age_group')) {
    /** Bucket used by the dashboard age-group chart. */
    function age_group(?int $age): string
    {
        if ($age === null)  return 'Unknown';
        if ($age <= 5)      return '0-5';
        if ($age <= 14)     return '6-14';
        if ($age <= 17)     return '15-17';
        if ($age <= 35)     return '18-35';
        if ($age <= 59)     return '36-59';
        return '60+';
    }
}
