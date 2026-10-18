<?php
declare(strict_types=1);
namespace Giftcheta\Core;
use Giftcheta\Http\Controllers\NotFoundController;
use Giftcheta\Http\Request;
use Giftcheta\Http\Response;
final class Router
{
    private array $routes = [];
    public function get(string $path, callable|array $handler): void { $this->add('GET', $path, $handler); }
    public function post(string $path, callable|array $handler): void { $this->add('POST', $path, $handler); }
    public function put(string $path, callable|array $handler): void { $this->add('PUT', $path, $handler); }
    public function patch(string $path, callable|array $handler): void { $this->add('PATCH', $path, $handler); }
    public function delete(string $path, callable|array $handler): void { $this->add('DELETE', $path, $handler); }
    public function add(string $method, string $path, callable|array $handler): void { $this->routes[strtoupper($method)][$path] = $handler; }
    public function dispatch(Request $request, Config $config): Response
    {
        foreach ($this->routes[$request->method] ?? [] as $pattern => $handler) {
            $regex = preg_replace_callback('/\\{([A-Za-z_][A-Za-z0-9_]*)\\}/', static fn ($m) => '(?P<' . $m[1] . '>[^/]+)', $pattern);
            if (!preg_match('#^' . $regex . '$#', $request->path, $matches)) continue;
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            if (is_array($handler)) { [$class, $method] = $handler; return (new $class())->$method($request, $config, $params); }
            return $handler($request, $config, $params);
        }
        return (new NotFoundController())->show();
    }
}
