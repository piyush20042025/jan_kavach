<?php
namespace JanKavach\Core;

/**
 * Immutable-ish wrapper over the PHP superglobals.
 *
 * Controllers never touch $_GET / $_POST directly; they go through here so
 * every value arrives trimmed and every retrieval site is greppable.
 */
class Request
{
    private array $query;
    private array $post;
    private array $files;
    private array $server;
    private array $cookies;
    private ?array $json = null;
    private array $routeParams = [];

    public function __construct()
    {
        $this->query   = $_GET    ?? [];
        $this->post    = $_POST   ?? [];
        $this->files   = $_FILES  ?? [];
        $this->server  = $_SERVER ?? [];
        $this->cookies = $_COOKIE ?? [];
    }

    // ------------------------------------------------------------------
    // Request metadata
    // ------------------------------------------------------------------

    public function method(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        // Allow forms to spoof PUT/PATCH/DELETE via _method.
        if ($method === 'POST' && !empty($this->post['_method'])) {
            $spoofed = strtoupper((string)$this->post['_method']);
            if (in_array($spoofed, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $spoofed;
            }
        }
        return $method;
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }

    /** True for XHR / fetch calls so controllers can answer with JSON. */
    public function isAjax(): bool
    {
        if (strtolower($this->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
            return true;
        }
        $accept = $this->server['HTTP_ACCEPT'] ?? '';
        return str_contains($accept, 'application/json');
    }

    /** Path portion of the URI, relative to the front controller. */
    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Strip the directory the front controller lives in, so the app
        // works from a sub-folder without route changes.
        $base = rtrim(str_replace('\\', '/', dirname($this->server['SCRIPT_NAME'] ?? '')), '/');
        if ($base !== '' && $base !== '/' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        $uri = '/' . trim($uri, '/');
        return $uri === '/' ? '/' : rtrim($uri, '/');
    }

    public function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($this->server[$key])) {
                $ip = trim(explode(',', (string)$this->server[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr((string)($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function referer(): ?string
    {
        return $this->server['HTTP_REFERER'] ?? null;
    }

    public function isSecure(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || ($this->server['SERVER_PORT'] ?? null) == 443
            || strtolower($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    // ------------------------------------------------------------------
    // Input accessors - all values are trimmed
    // ------------------------------------------------------------------

    /** Any input source: route params, then POST, then query, then JSON. */
    public function input(string $key, $default = null)
    {
        if (array_key_exists($key, $this->routeParams)) {
            return $this->routeParams[$key];
        }
        if (array_key_exists($key, $this->post)) {
            return $this->clean($this->post[$key]);
        }
        if (array_key_exists($key, $this->query)) {
            return $this->clean($this->query[$key]);
        }
        $json = $this->json();
        if (is_array($json) && array_key_exists($key, $json)) {
            return $this->clean($json[$key]);
        }
        return $default;
    }

    public function post(string $key, $default = null)
    {
        return array_key_exists($key, $this->post) ? $this->clean($this->post[$key]) : $default;
    }

    public function query(string $key, $default = null)
    {
        return array_key_exists($key, $this->query) ? $this->clean($this->query[$key]) : $default;
    }

    public function cookie(string $key, $default = null)
    {
        return $this->cookies[$key] ?? $default;
    }

    /** Integer cast, with null for blank so "no filter" is distinguishable. */
    public function int(string $key, ?int $default = null): ?int
    {
        $value = $this->input($key);
        if ($value === null || $value === '') {
            return $default;
        }
        return (int)$value;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->input($key);
        if ($value === null) {
            return $default;
        }
        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
    }

    /** Array input (checkbox groups, multi-selects). */
    public function array(string $key): array
    {
        $value = $this->post[$key] ?? $this->query[$key] ?? [];
        if (!is_array($value)) {
            $value = $value === '' ? [] : [$value];
        }
        return array_values(array_map(fn($v) => is_array($v) ? $v : $this->clean($v), $value));
    }

    public function all(): array
    {
        return array_map([$this, 'clean'], array_merge($this->query, $this->post, $this->json() ?? []));
    }

    /** Only the listed keys, missing keys omitted entirely. */
    public function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $value = $this->input($key);
            if ($value !== null) {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->post)
            || array_key_exists($key, $this->query)
            || array_key_exists($key, $this->routeParams);
    }

    /** Non-empty check, useful for filters. */
    public function filled(string $key): bool
    {
        $value = $this->input($key);
        return $value !== null && $value !== '' && $value !== [];
    }

    public function file(string $key): ?array
    {
        if (!isset($this->files[$key])) {
            return null;
        }
        $file = $this->files[$key];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $file;
    }

    public function files(): array
    {
        return $this->files;
    }

    public function json(): ?array
    {
        if ($this->json !== null) {
            return $this->json;
        }
        $type = $this->server['CONTENT_TYPE'] ?? '';
        if (!str_contains($type, 'application/json')) {
            return $this->json = [];
        }
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw ?: '[]', true);
        return $this->json = (is_array($decoded) ? $decoded : []);
    }

    // ------------------------------------------------------------------
    // Route parameters (populated by the router)
    // ------------------------------------------------------------------

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function routeParam(string $key, $default = null)
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function routeParams(): array
    {
        return $this->routeParams;
    }

    /** CSRF token from the form field or the X-CSRF-TOKEN header. */
    public function csrfToken(): ?string
    {
        $name = Config::get('security.csrf_token_name', '_token');
        $token = $this->post[$name] ?? null;
        if ($token === null) {
            $json = $this->json();
            $token = $json[$name] ?? null;
        }
        if ($token === null) {
            $token = $this->server['HTTP_X_CSRF_TOKEN'] ?? null;
        }
        return is_string($token) ? $token : null;
    }

    /** Current URL including query string; used for "return to" redirects. */
    public function fullUrl(): string
    {
        return ($this->server['REQUEST_URI'] ?? '/');
    }

    /** Trim strings, recurse into arrays, leave everything else alone. */
    private function clean($value)
    {
        if (is_array($value)) {
            return array_map([$this, 'clean'], $value);
        }
        if (is_string($value)) {
            // Strip null bytes - they break C-level string handling downstream.
            return trim(str_replace("\0", '', $value));
        }
        return $value;
    }
}
