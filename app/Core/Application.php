<?php
declare(strict_types=1);
namespace Giftcheta\Core;
use Giftcheta\Http\Request;
use Giftcheta\Http\Response;
use Throwable;
final class Application
{
    public function __construct(private readonly Router $router, private readonly Config $config, private readonly Logger $logger, private readonly array $middleware = []) {}
    public function handle(Request $request): Response
    {
        $terminal = fn (Request $req): Response => $this->router->dispatch($req, $this->config);
        $next = array_reduce(array_reverse($this->middleware), fn (callable $next, callable $middleware): callable => fn (Request $req): Response => $middleware($req, $next), $terminal);
        try { return $next($request); }
        catch (Throwable $error) {
            $this->logger->log('error', 'Unhandled application exception', ['type' => $error::class]);
            return Response::json(['error' => ['code' => 'internal_error', 'message' => 'An unexpected error occurred.']], 500);
        }
    }
    public function run(Request $request): void { $this->handle($request)->send(); }
}
