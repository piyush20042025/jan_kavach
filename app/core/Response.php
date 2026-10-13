<?php
namespace JanKavach\Core;

/**
 * Response helpers: JSON, redirects, downloads, and the security headers
 * that are set on every response.
 */
class Response
{
    public static function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 1; mode=block');
        header('Permissions-Policy: geolocation=(self), camera=(self), microphone=()');
        // Assets are bundled locally, so the policy can stay tight.
        header("Content-Security-Policy: default-src 'self'; "
             . "script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
             . "style-src 'self' 'unsafe-inline'; "
             . "img-src 'self' data: blob:; "
             . "font-src 'self' data:; "
             . "connect-src 'self'; "
             . "frame-ancestors 'self'; "
             . "base-uri 'self'; "
             . "form-action 'self'");
        header_remove('X-Powered-By');
    }

    public static function json($data, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success($data = null, string $message = 'OK', int $status = 200): void
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    public static function error(string $message = 'Request failed', $errors = null, int $status = 400): void
    {
        self::json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
    }

    public static function redirect(string $url, int $status = 302): void
    {
        if (!headers_sent()) {
            header('Location: ' . $url, true, $status);
        }
        exit;
    }

    /** Redirect to a route path inside the app. */
    public static function to(string $path, int $status = 302): void
    {
        self::redirect(url($path), $status);
    }

    public static function back(string $fallback = '/'): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        // Only follow a referer that points back at this host.
        if ($referer) {
            $host = parse_url($referer, PHP_URL_HOST);
            if ($host === null || $host === ($_SERVER['HTTP_HOST'] ?? '')) {
                self::redirect($referer);
            }
        }
        self::to($fallback);
    }

    /** Stream a file from disk as a download, never revealing its path. */
    public static function download(string $path, ?string $filename = null, ?string $mime = null): void
    {
        if (!is_file($path) || !is_readable($path)) {
            http_response_code(404);
            echo 'File not found.';
            exit;
        }
        $filename = $filename ?: basename($path);
        $mime     = $mime ?: (mime_content_type($path) ?: 'application/octet-stream');

        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
            header('Content-Length: ' . filesize($path));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            header('X-Content-Type-Options: nosniff');
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        readfile($path);
        exit;
    }

    /** Serve a file inline (document preview) with a restrictive CSP. */
    public static function inline(string $path, ?string $filename = null, ?string $mime = null): void
    {
        if (!is_file($path) || !is_readable($path)) {
            http_response_code(404);
            echo 'File not found.';
            exit;
        }
        $filename = $filename ?: basename($path);
        $mime     = $mime ?: (mime_content_type($path) ?: 'application/octet-stream');

        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Content-Disposition: inline; filename="' . addslashes($filename) . '"');
            header('Content-Length: ' . filesize($path));
            header('X-Content-Type-Options: nosniff');
            // A stored file must never be able to run script in our origin.
            header("Content-Security-Policy: default-src 'none'; img-src 'self'; object-src 'self'; plugin-types application/pdf");
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        readfile($path);
        exit;
    }

    /** Send string content as a download (CSV / generated files). */
    public static function attachment(string $content, string $filename, string $mime = 'text/csv'): void
    {
        if (!headers_sent()) {
            header('Content-Type: ' . $mime . '; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
            header('Content-Length: ' . strlen($content));
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        echo $content;
        exit;
    }

    public static function abort(int $status, string $message = ''): void
    {
        http_response_code($status);
        $messages = [
            400 => 'Bad Request',
            401 => 'Unauthorised',
            403 => 'Access Denied',
            404 => 'Page Not Found',
            405 => 'Method Not Allowed',
            419 => 'Session Expired',
            422 => 'Validation Failed',
            429 => 'Too Many Requests',
            500 => 'Server Error',
        ];
        $title = $messages[$status] ?? 'Error';

        $view = VIEW_PATH . '/errors/' . $status . '.php';
        if (!is_file($view)) {
            $view = VIEW_PATH . '/errors/generic.php';
        }
        if (is_file($view)) {
            $data = ['status' => $status, 'title' => $title, 'message' => $message];
            extract($data, EXTR_SKIP);
            require $view;
        } else {
            echo "<h1>{$status} - {$title}</h1><p>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        exit;
    }
}
