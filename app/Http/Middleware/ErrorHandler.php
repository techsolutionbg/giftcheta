<?php
declare(strict_types=1);
namespace Giftcheta\Http\Middleware;
use Giftcheta\Core\Config;
use Giftcheta\Core\Logger;
use Giftcheta\Http\Request;
use Giftcheta\Http\Response;
use Throwable;
final class ErrorHandler
{
    public function __construct(private readonly Logger $logger, private readonly Config $config) {}
    public function __invoke(Request $request, callable $next): Response
    {
        try { return $next($request); }
        catch (Throwable $error) {
            $this->logger->log('error', 'Request failed', ['type' => $error::class, 'path' => $request->path]);
            $message = $this->config->get('app.debug', false) ? $error->getMessage() : 'An unexpected error occurred.';
            return Response::json(['error' => ['code' => 'internal_error', 'message' => $message]], 500);
        }
    }
}
