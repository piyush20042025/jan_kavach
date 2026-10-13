<?php
namespace JanKavach\Core;

/**
 * Plain-PHP view renderer with layout support and section capture.
 *
 * Views receive their data as extracted local variables.  Escaping is the
 * view's job via e() / the helpers in app/helpers - nothing is auto-escaped,
 * so anywhere raw HTML is intended it has to say so explicitly.
 */
class View
{
    private static array $shared = [];
    private static array $sections = [];
    private static array $sectionStack = [];

    /** Data made available to every view (menu counts, user, etc.). */
    public static function share(string $key, $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function shared(): array
    {
        return self::$shared;
    }

    /**
     * Render a view inside a layout.
     *   View::render('schemes/index', ['schemes' => $rows], 'layouts/app')
     */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = self::capture($view, $data);

        if ($layout === null) {
            return $content;
        }

        $layoutData = array_merge($data, ['content' => $content]);
        return self::capture($layout, $layoutData);
    }

    /** Render and send to the browser. */
    public static function display(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        echo self::render($view, $data, $layout);
    }

    /** Render a view fragment with no layout (AJAX partials). */
    public static function partial(string $view, array $data = []): string
    {
        return self::capture($view, $data);
    }

    private static function capture(string $view, array $data): string
    {
        $path = self::path($view);
        if (!is_file($path)) {
            throw new \RuntimeException("View [{$view}] not found at {$path}");
        }

        $variables = array_merge(self::$shared, $data);
        extract($variables, EXTR_SKIP);

        ob_start();
        try {
            require $path;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string)ob_get_clean();
    }

    private static function path(string $view): string
    {
        $view = str_replace(['..', "\0"], '', $view);
        $view = ltrim(str_replace('.', '/', $view), '/');
        // Allow both 'schemes/index' and 'schemes.index'.
        return VIEW_PATH . '/' . $view . '.php';
    }

    public static function exists(string $view): bool
    {
        return is_file(self::path($view));
    }

    // ------------------------------------------------------------------
    // Sections - let a view push extra CSS/JS up into the layout <head>
    // ------------------------------------------------------------------

    public static function startSection(string $name): void
    {
        self::$sectionStack[] = $name;
        ob_start();
    }

    public static function endSection(): void
    {
        $name = array_pop(self::$sectionStack);
        if ($name === null) {
            throw new \RuntimeException('endSection() called without a matching startSection().');
        }
        self::$sections[$name] = (self::$sections[$name] ?? '') . ob_get_clean();
    }

    public static function section(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    public static function hasSection(string $name): bool
    {
        return isset(self::$sections[$name]) && trim(self::$sections[$name]) !== '';
    }

    public static function resetSections(): void
    {
        self::$sections = [];
        self::$sectionStack = [];
    }
}
