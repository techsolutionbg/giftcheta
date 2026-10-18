<?php
declare(strict_types=1);
namespace Giftcheta\Http;
final class Response
{
    public function __construct(public readonly string $body, public readonly int $status = 200, public readonly array $headers = ['Content-Type' => 'text/plain; charset=utf-8']) {}
    public static function json(array $data, int $status = 200): self
    {
        return new self(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $status, ['Content-Type' => 'application/json; charset=utf-8', 'X-Content-Type-Options' => 'nosniff']);
    }
    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) header($name . ': ' . $value);
        echo $this->body;
    }
}
