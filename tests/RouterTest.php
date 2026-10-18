<?php
declare(strict_types=1);
namespace Giftcheta\Tests;
use Giftcheta\Core\Config;
use Giftcheta\Core\Router;
use Giftcheta\Http\Request;
use Giftcheta\Http\Response;
use PHPUnit\Framework\TestCase;
final class RouterTest extends TestCase
{
    public function testUnknownRouteReturnsJson404(): void
    {
        $response = (new Router())->dispatch(new Request('GET', '/missing'), new Config([]));
        self::assertSame(404, $response->status);
        self::assertStringContainsString('not_found', $response->body);
    }
    public function testRegisteredHandlerRuns(): void
    {
        $router = new Router();
        $router->get('/ok', fn () => Response::json(['data' => 'ok']));
        self::assertSame(200, $router->dispatch(new Request('GET', '/ok'), new Config([]))->status);
    }
}
