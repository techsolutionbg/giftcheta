<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Banners;

use InvalidArgumentException;
use RuntimeException;

final class BannerImageStorage
{
    private const MAX_BYTES = 10 * 1024 * 1024;
    private const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(private readonly string $projectRoot) {}

    public function store(?array $file): string
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !isset($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) {
            throw new InvalidArgumentException('Изберете валидно изображение.');
        }
        $size = @filesize((string)$file['tmp_name']);
        if (!is_int($size) || $size < 1 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('Изображението трябва да бъде до 10 MB.');
        }
        $temporary = (string)$file['tmp_name'];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporary);
        $extension = self::EXTENSIONS[$mime] ?? null;
        $imageInfo = @getimagesize($temporary);
        if ($extension === null || !is_array($imageInfo) || $imageInfo[0] < 1 || $imageInfo[1] < 1 || $imageInfo[0] > 12000 || $imageInfo[1] > 8000) {
            throw new InvalidArgumentException('Допускат се валидни JPEG, PNG и WebP изображения.');
        }
        $directory = $this->projectRoot . '/public/uploads/banners';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Папката за изображения не може да бъде създадена.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file($temporary, $directory . '/' . $name)) {
            throw new RuntimeException('Изображението не може да бъде запазено.');
        }
        return 'uploads/banners/' . $name;
    }

    public function remove(?string $relativePath): void
    {
        if (!is_string($relativePath) || !preg_match('~^uploads/banners/[a-f0-9]{32}\.(?:jpg|png|webp)$~', $relativePath)) return;
        $directory = realpath($this->projectRoot . '/public/uploads/banners');
        $path = realpath($this->projectRoot . '/public/' . $relativePath);
        if ($directory !== false && $path !== false && str_starts_with($path, $directory . DIRECTORY_SEPARATOR) && is_file($path)) @unlink($path);
    }
}
