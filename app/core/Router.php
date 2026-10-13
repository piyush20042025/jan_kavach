<?php
namespace JanKavach\Core;

/**
 * Regex router with named parameters and per-route middleware.
 *
 *   $router->get('/schemes/{id}', [SchemeController::class, 'show'])
 *          ->middleware('auth', 'permission:scheme.view');
 */
class Router
{
    private array $routes = [];
    private array $groupStack = [];
    private ?array $lastRoute = null;

    // ------------------------------------------------------------------
    // Registration
    // ------------------------------------------------------------------

    public function get(string $uri, $action): self
    {
        return $this->addRoute(['GET'], $uri, $action);
    }

    public function post(string $uri, $action): self
    {
        return $this->addRoute(['POST'], $uri, $action);
    }

    public function put(string $uri, $action): self
    {
        return $this->addRoute(['PUT'], $uri, $action);
    }

    public function patch(string $uri, $action): self
    {
        return $this->addRoute(['PATCH'], $uri, $action);
    }

    public function delete(string $uri, $action): self
    {
        return $this->addRoute(['DELETE'], $uri, $action);
    }

    public function any(string $uri, $action): self
    {
        return $this->addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $uri, $action);
    }

    public function match(array $methods, string $uri, $action): self
    {
        return $this->addRoute(array_map('strtoupper', $methods), $uri, $action);
    }

    /**
     * Group routes under a shared prefix / middleware set.
     *   $router->group(['prefix' => 'admin', 'middleware' => ['auth']], function ($r) { ... });
     */
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    /** Attach middleware to the most recently registered route. */
    public function middleware(string ...$middleware): self
    {
        if ($this->lastRoute !== null) {
            $index = $this->lastRoute['index'];
            $this->routes[$index]['middleware'] = array_merge(
                $this->routes[$index]['middleware'],
                $middleware
            );
        }
        return $this;
    }

    /** Give the route a name so url_for() can build links to it. */
    public function name(string $name): self
    {
        if ($this->lastRoute !== null) {
            $this->routes[$this->lastRoute['index']]['name'] = $name;
        }
        return $this;
    }

    private function addRoute(array $methods, string $uri, $action): self
    {
        $prefix = '';
        $middleware = [];
        foreach ($this->groupStack as $group) {
            if (!empty($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            if (!empty($group['middleware'])) {
                $middleware = array_merge($middleware, (array)$group['middleware']);
            }
        }

        $uri  = $prefix . '/' . trim($uri, '/');
        $uri  = '/' . trim($uri, '/');
        $uri  = $uri === '/' ? '/' : rtrim($uri, '/');

        $this->routes[] = [
            'methods'    => $methods,
            'uri'        => $uri,
            'pattern'    => $this->compile($uri),
            'action'     => $action,
            'middleware' => $middleware,
            'name'       => null,
        ];

        $this->lastRoute = ['index' => count($this->routes) - 1];
        return $this;
    }

    /**
     * Turn /schemes/{id}/documents/{docId?} into a regex with named groups.
     *
     * The URI is walked segment by segment: literal segments are quoted,
     * parameter segments become named groups. Building it this way (rather
     * than patching a regex afterwards) keeps the separating slashes exact.
     *
     *   {id}     - one required path segment
     *   {id?}    - optional trailing segment
     *   {path*}  - greedy, matches the remainder including slashes
     */
    private function compile(string $uri): string
    {
        if ($uri === '/') {
            return '#^/$#';
        }

        $pattern = '';
        foreach (explode('/', trim($uri, '/')) as $segment) {
            if (preg_match('#^\{([a-zA-Z_][a-zA-Z0-9_]*)(\?)?(\*)?\}$#', $segment, $m)) {
                $name   = $m[1];
                $body   = !empty($m[3]) ? '.+' : '[^/]+';
                $pattern .= !empty($m[2])
                    ? '(?:/(?P<' . $name . '>' . $body . '))?'
                    : '/(?P<' . $name . '>' . $body . ')';
            } else {
                $pattern .= '/' . preg_quote($segment, '#');
            }
        }

        return '#^' . ($pattern === '' ? '/' : $pattern) . '$#';
    }

    // ------------------------------------------------------------------
    // Dispatch
    // ------------------------------------------------------------------

    public function dispatch(Request $request)
    {
        $path   = $request->path();
        $method = $request->method();
        $methodMismatch = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }
            if (!in_array($method, $route['methods'], true)) {
                $methodMismatch = true;
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_int($key)) {
                    $params[$key] = $value;
                }
            }
            $request->setRouteParams($params);

            return $this->runRoute($route, $request, $params);
        }

        if ($methodMismatch) {
            Response::abort(405, 'The ' . $method . ' method is not allowed for this URL.');
        }
        Response::abort(404, 'The page you requested could not be found.');
    }

    private function runRoute(array $route, Request $request, array $params)
    {
        // Middleware runs outside-in; any of them may halt the request.
        foreach ($route['middleware'] as $definition) {
            $result = Middleware::run($definition, $request);
            if ($result === false) {
                return null;
            }
        }

        $action = $route['action'];

        if (is_callable($action) && !is_array($action)) {
            return $action($request, ...array_values($params));
        }

        [$class, $method] = is_array($action) ? $action : explode('@', $action);

        if (!class_exists($class)) {
            throw new \RuntimeException("Controller {$class} not found.");
        }
        $controller = new $class($request);
        if (!method_exists($controller, $method)) {
            throw new \RuntimeException("Method {$method} not found on {$class}.");
        }

        return $controller->{$method}(...array_values($params));
    }

    public function routes(): array
    {
        return $this->routes;
    }

    /** Look up a named route's URI, substituting parameters. */
    public function urlFor(string $name, array $params = []): ?string
    {
        foreach ($this->routes as $route) {
            if ($route['name'] !== $name) {
                continue;
            }
            $uri = $route['uri'];
            foreach ($params as $key => $value) {
                $uri = str_replace(['{' . $key . '}', '{' . $key . '?}'], (string)$value, $uri);
            }
            // Drop any optional parameters that were not supplied.
            $uri = preg_replace('#/\{[a-zA-Z_][a-zA-Z0-9_]*\?\}#', '', $uri);
            return $uri;
        }
        return null;
    }
}
