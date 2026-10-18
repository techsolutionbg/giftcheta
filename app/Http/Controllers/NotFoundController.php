<?php
declare(strict_types=1);
namespace Giftcheta\Http\Controllers;
use Giftcheta\Http\Response;
final class NotFoundController { public function show(): Response { return Response::json(['error' => ['code' => 'not_found', 'message' => 'The requested resource was not found.']], 404); } }
