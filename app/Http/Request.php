<?php
declare(strict_types=1);
namespace Giftcheta\Http;
final class Request
{
    public function __construct(public readonly string $method, public readonly string $path, public readonly array $query = [], public readonly array $headers = [], public readonly string $body = '', public readonly array $files = []) {}
    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), $path, $_GET, function_exists('getallheaders') ? getallheaders() : [], file_get_contents('php://input') ?: '', $_FILES);
    }
    public function json(): array
    {
        $data = json_decode($this->body, true);
        return is_array($data) ? $data : [];
    }
}
