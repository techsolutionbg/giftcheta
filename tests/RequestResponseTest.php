<?php
declare(strict_types=1);
namespace Giftcheta\Tests;
use Giftcheta\Http\Request;
use Giftcheta\Http\Response;
use PHPUnit\Framework\TestCase;
final class RequestResponseTest extends TestCase
{
    public function testResponseIsJson(): void
    {
        $response = Response::json(['data' => ['ok' => true]]);
        self::assertSame('application/json; charset=utf-8', $response->headers['Content-Type']);
        self::assertSame(['data' => ['ok' => true]], json_decode($response->body, true));
    }
    public function testRequestHoldsPathAndMethod(): void
    {
        $request = new Request('GET', '/api/v1/health');
        self::assertSame('GET', $request->method);
        self::assertSame('/api/v1/health', $request->path);
    }
}
