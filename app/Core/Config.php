<?php
declare(strict_types=1);
namespace Giftcheta\Core;
final class Config
{
    public function __construct(private readonly array $values) {}
    public static function fromDirectory(string $directory): self
    {
        $values = [];
        foreach (glob(rtrim($directory, DIRECTORY_SEPARATOR) . '/*.php') ?: [] as $file) $values[basename($file, '.php')] = require $file;
        return new self($values);
    }
    public static function loadEnvironment(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) return;
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === '' || getenv($key) !== false) continue;
            if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) $value = substr($value, 1, -1);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->values;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) return $default;
            $value = $value[$segment];
        }
        return $value;
    }
    public static function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null) return $default;
        if (is_string($value)) return match (strtolower($value)) {'true' => true, 'false' => false, 'null' => null, default => $value};
        return $value;
    }
}
