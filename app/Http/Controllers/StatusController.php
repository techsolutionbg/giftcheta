<?php
declare(strict_types=1);
namespace Giftcheta\Http\Controllers;
use Giftcheta\Core\Config;
use Giftcheta\Http\Response;
final class StatusController
{
    public function show(mixed $request, Config $config): Response
    {
        if ($config->get('app.env') !== 'development') return Response::json(['error' => ['code' => 'not_found', 'message' => 'Not found.']], 404);
        return Response::json(['data' => ['status' => 'ok', 'environment' => 'development']]);
    }
}
