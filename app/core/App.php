<?php
namespace JanKavach\Core;

/**
 * Application bootstrapper.
 *
 * Wires up the autoloader, configuration, error handling, session, security
 * headers and the router, then dispatches the current request.
 */
class App
{
    private Router $router;
    private Request $request;

    public function __construct()
    {
        // Order matters: the config file defines the BASE_PATH/APP_PATH
        // constants that the autoloader and everything after it rely on,
        // so it is read with a literal path before anything else runs.
        $this->loadConfiguration();
        $this->registerAutoloader();
        $this->configureErrorHandling();
        $this->loadHelpers();
        $this->ensureStorageDirectories();

        Session::start();
        Response::sendSecurityHeaders();

        $this->request = new Request();
        $this->router  = new Router();
        $this->loadRoutes();
    }

    /** PSR-4 style autoloading for the JanKavach namespace. */
    private function registerAutoloader(): void
    {
        spl_autoload_register(function (string $class): void {
            $prefix = 'JanKavach\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $segments = explode('\\', $relative);

            // JanKavach\Core\Auth            -> app/core/Auth.php
            // JanKavach\Models\Scheme        -> app/models/Scheme.php
            // JanKavach\Controllers\Foo      -> app/controllers/Foo.php
            // JanKavach\Libraries\XlsxReader -> app/libraries/XlsxReader.php
            $className = array_pop($segments);
            $dir = strtolower(implode('/', $segments));
            $path = APP_PATH . '/' . ($dir !== '' ? $dir . '/' : '') . $className . '.php';

            if (is_file($path)) {
                require_once $path;
            }
        });
    }

    private function loadConfiguration(): void
    {
        // Literal path: CONFIG_PATH is one of the constants this file defines.
        $settings = require dirname(__DIR__, 2) . '/config/config.php';

        require_once __DIR__ . '/Config.php';
        Config::load($settings);

        date_default_timezone_set(Config::get('app.timezone', 'Asia/Kolkata'));
        setlocale(LC_ALL, 'en_IN.UTF-8', 'en_IN', 'English_India', 'en_US.UTF-8');
        mb_internal_encoding('UTF-8');
    }

    /**
     * Turn every PHP notice into an exception during development so bugs
     * surface early, and hide details from users in production.
     */
    private function configureErrorHandling(): void
    {
        $debug = (bool)Config::get('app.debug', false);

        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', LOG_PATH . '/php-error.log');

        set_error_handler(function (int $severity, string $message, string $file = '', int $line = 0) {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (\Throwable $e) {
            $this->handleException($e);
        });

        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                Logger::error('Fatal: ' . $error['message'], [
                    'file' => $error['file'], 'line' => $error['line'],
                ]);
                if (!Config::get('app.debug')) {
                    while (ob_get_level() > 0) { ob_end_clean(); }
                    Response::abort(500, 'A fatal error occurred. The administrator has been notified.');
                }
            }
        });
    }

    private function handleException(\Throwable $e): void
    {
        Logger::error(get_class($e) . ': ' . $e->getMessage(), [
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'path'  => $this->request ?? null ? $this->request->path() : null,
            'user'  => Auth::check() ? Auth::id() : null,
            'trace' => Config::get('app.debug') ? $e->getTraceAsString() : null,
        ]);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $isAjax = isset($this->request) && $this->request->isAjax();

        if (Config::get('app.debug')) {
            if ($isAjax) {
                Response::json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile() . ':' . $e->getLine(),
                    'trace'   => explode("\n", $e->getTraceAsString()),
                ], 500);
            }
            http_response_code(500);
            $this->renderDebugPage($e);
            exit;
        }

        if ($isAjax) {
            Response::json(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
        Response::abort(500, 'An unexpected error occurred. Please try again or contact the administrator.');
    }

    private function renderDebugPage(\Throwable $e): void
    {
        $title = get_class($e);
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Jan Kavach - Error</title>';
        echo '<style>body{font:14px/1.6 system-ui,Segoe UI,sans-serif;margin:0;background:#f6f8fb;color:#1f2937}';
        echo '.wrap{max-width:1000px;margin:40px auto;padding:0 20px}';
        echo '.card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06)}';
        echo '.hd{background:#b91c1c;color:#fff;padding:16px 20px}.hd h1{margin:0;font-size:16px}';
        echo '.bd{padding:20px}pre{background:#0f172a;color:#e2e8f0;padding:16px;border-radius:8px;overflow:auto;font-size:12px}';
        echo 'code{background:#eef2f7;padding:2px 6px;border-radius:4px}</style></head><body><div class="wrap"><div class="card">';
        echo '<div class="hd"><h1>' . e($title) . '</h1></div><div class="bd">';
        echo '<p><strong>' . e($e->getMessage()) . '</strong></p>';
        echo '<p>in <code>' . e($e->getFile()) . '</code> on line <code>' . (int)$e->getLine() . '</code></p>';
        echo '<pre>' . e($e->getTraceAsString()) . '</pre>';
        echo '<p style="color:#6b7280">Set <code>app.debug = false</code> in config/config.php for production.</p>';
        echo '</div></div></div></body></html>';
    }

    private function loadHelpers(): void
    {
        foreach (glob(APP_PATH . '/helpers/*.php') ?: [] as $helper) {
            require_once $helper;
        }
    }

    /** Create the writable directories on first run. */
    private function ensureStorageDirectories(): void
    {
        foreach ([STORAGE_PATH, DOCUMENT_PATH, LOG_PATH, IMPORT_PATH, EXPORT_PATH, STORAGE_PATH . '/temp'] as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            // Belt-and-braces: even if storage/ ends up inside the docroot,
            // this stops Apache serving anything out of it.
            $htaccess = $dir . '/.htaccess';
            if (is_dir($dir) && !is_file($htaccess)) {
                @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
            }
        }
    }

    private function loadRoutes(): void
    {
        $router = $this->router;
        require ROUTES_PATH . '/web.php';
        require ROUTES_PATH . '/api.php';
        require ROUTES_PATH . '/mobile_api.php';
    }

    public function run(): void
    {
        $this->router->dispatch($this->request);
    }

    public function router(): Router
    {
        return $this->router;
    }
}
