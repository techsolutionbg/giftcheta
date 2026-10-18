<?php
declare(strict_types=1);
namespace Giftcheta\Http\Controllers;
use Giftcheta\Http\Response;
final class HealthController { public function show(): Response { return Response::json(['data' => ['status' => 'ok']]); } }
