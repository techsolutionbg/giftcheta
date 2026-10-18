<?php
declare(strict_types=1);
namespace Giftcheta\Core;
final class Logger
{
    private const LEVELS = ['debug' => 10, 'info' => 20, 'warning' => 30, 'error' => 40];
    public function __construct(private readonly string $path, private readonly string $minimum = 'error') {}
    public function log(string $level, string $message, array $context = []): void
    {
        if ((self::LEVELS[$level] ?? 100) < (self::LEVELS[$this->minimum] ?? 40)) return;
        $directory = dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) return;
        $line = json_encode(['time' => gmdate(DATE_ATOM), 'level' => $level, 'message' => $message, 'context' => $context], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($line !== false) file_put_contents($this->path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
