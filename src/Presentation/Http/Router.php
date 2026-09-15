<?php

declare(strict_types=1);

namespace Aster\Presentation\Http;

use Aster\Domain\Exception\HttpException;

/**
 * Route table and dispatcher.
 *
 * Routes are declared as literal paths with optional {placeholders}. Each is
 * compiled to a regex once, at registration. Placeholders match a single
 * path segment and never a slash, so /articles/{slug} cannot swallow
 * /articles/a/b and reach an unintended handler.
 *
 * Middleware is a simple onion: the outermost wraps the innermost, and any
 * layer may short-circuit by returning a Response instead of calling next.
 */
final class Router
{
    /**
     * @var list<array{
     *   method: string,
     *   regex: string,
     *   params: list<string>,
     *   handler: callable|array,
     *   middleware: list<string>,
     *   name: ?string
     * }>
     */
    private array $routes = [];

    /** @var array<string, string> name => path template, for url generation */
    private array $namedRoutes = [];

    /** @var list<string> middleware applied to every route in the current group */
    private array $groupMiddleware = [];

    private string $groupPrefix = '';

    /** @var array<string, callable(Request, callable): Response> */
    private array $middlewareFactories = [];

    public function get(string $path, callable|array $handler, array $middleware = [], ?string $name = null): self
    {
        return $this->map('GET', $path, $handler, $middleware, $name);
    }

    public function post(string $path, callable|array $handler, array $middleware = [], ?string $name = null): self
    {
        return $this->map('POST', $path, $handler, $middleware, $name);
    }

    public function put(string $path, callable|array $handler, array $middleware = [], ?string $name = null): self
    {
        return $this->map('PUT', $path, $handler, $middleware, $name);
    }

    public function patch(string $path, callable|array $handler, array $middleware = [], ?string $name = null): self
    {
        return $this->map('PATCH', $path, $handler, $middleware, $name);
    }

    public function delete(string $path, callable|array $handler, array $middleware = [], ?string $name = null): self
    {
        return $this->map('DELETE', $path, $handler, $middleware, $name);
    }

    /**
     * Register a group of routes sharing a prefix and middleware stack.
     *
     * @param callable(self): void $routes
     */
    public function group(string $prefix, array $middleware, callable $routes): self
    {
        $previousPrefix     = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix     = $previousPrefix . '/' . trim($prefix, '/');
        $this->groupMiddleware = [...$previousMiddleware, ...$middleware];

        $routes($this);

        $this->groupPrefix     = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;

        return $this;
    }

    public function map(
        string $method,
        string $path,
        callable|array $handler,
        array $middleware = [],
        ?string $name = null,
    ): self {
        $full = '/' . trim($this->groupPrefix . '/' . trim($path, '/'), '/');

        [$regex, $params] = self::compile($full);

        $this->routes[] = [
            'method'     => strtoupper($method),
            'regex'      => $regex,
            'params'     => $params,
            'handler'    => $handler,
            'middleware' => [...$this->groupMiddleware, ...$middleware],
            'name'       => $name,
        ];

        if ($name !== null) {
            $this->namedRoutes[$name] = $full;
        }

        return $this;
    }

    /**
     * Turn "/articles/{slug}" into a regex plus the ordered parameter names.
     *
     * @return array{string, list<string>}
     */
    private static function compile(string $path): array
    {
        $params = [];

        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/',
            static function (array $m) use (&$params): string {
                $params[] = $m[1];

                // A custom pattern may be supplied as {id:\d+}; otherwise
                // match anything except a slash, keeping routes segment-safe.
                return '(' . ($m[2] ?? '[^/]+') . ')';
            },
            $path,
        ) ?? $path;

        return ['#^' . $regex . '$#u', $params];
    }

    /** Register a middleware factory under the name used in route definitions. */
    public function registerMiddleware(string $name, callable $factory): self
    {
        $this->middlewareFactories[$name] = $factory;

        return $this;
    }

    /**
     * Match and run the request.
     *
     * @throws HttpException 404 when nothing matches, 405 when the path
     *                       exists under a different verb.
     */
    public function dispatch(Request $request): Response
    {
        $pathMatchedOtherMethod = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path, $matches) !== 1) {
                continue;
            }

            if ($route['method'] !== $request->method) {
                // HEAD is served by the GET handler; the body is discarded by
                // the client, and PHP will not send one for a HEAD request.
                if (!($route['method'] === 'GET' && $request->method === 'HEAD')) {
                    $pathMatchedOtherMethod = true;
                    continue;
                }
            }

            array_shift($matches);

            $attributes = [];
            foreach ($route['params'] as $index => $param) {
                $attributes[$param] = $matches[$index] ?? null;
            }

            return $this->runPipeline(
                $request->withAttributes($attributes),
                $route['middleware'],
                $route['handler'],
            );
        }

        if ($pathMatchedOtherMethod) {
            throw HttpException::methodNotAllowed();
        }

        throw HttpException::notFound();
    }

    /**
     * Build and execute the middleware onion around the controller.
     *
     * @param list<string> $middleware
     */
    private function runPipeline(Request $request, array $middleware, callable|array $handler): Response
    {
        $core = function (Request $req) use ($handler): Response {
            if (is_array($handler)) {
                [$object, $method] = $handler;

                return $object->{$method}($req);
            }

            return $handler($req);
        };

        // Fold right so the first-listed middleware ends up outermost.
        foreach (array_reverse($middleware) as $name) {
            if (!isset($this->middlewareFactories[$name])) {
                throw new \LogicException("Middleware '{$name}' is not registered.");
            }

            $layer = $this->middlewareFactories[$name];
            $next  = $core;

            $core = static fn (Request $req): Response => $layer($req, $next);
        }

        return $core($request);
    }

    /**
     * Build a URL from a route name, substituting {placeholders}.
     *
     * @param array<string, string|int> $params
     */
    public function route(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \LogicException("Route '{$name}' is not defined.");
        }

        $path = $this->namedRoutes[$name];

        foreach ($params as $key => $value) {
            $path = preg_replace(
                '/\{' . preg_quote((string) $key, '/') . '(?::[^}]+)?\}/',
                rawurlencode((string) $value),
                $path,
            ) ?? $path;
        }

        return $path;
    }

    /** @return list<array{method:string, path:string, name:?string}> */
    public function routeList(): array
    {
        return array_map(
            static fn (array $r): array => [
                'method' => $r['method'],
                'path'   => $r['name'] !== null ? $r['name'] : $r['regex'],
                'name'   => $r['name'],
            ],
            $this->routes,
        );
    }
}
