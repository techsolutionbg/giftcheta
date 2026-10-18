<?php
declare(strict_types=1);
use Giftcheta\Core\Config;
return ['host' => Config::env('DB_HOST', '127.0.0.1'), 'port' => Config::env('DB_PORT', '3306'), 'name' => Config::env('DB_DATABASE', 'giftcheta'), 'username' => Config::env('DB_USERNAME', 'giftcheta'), 'password' => Config::env('DB_PASSWORD', ''), 'charset' => Config::env('DB_CHARSET', 'utf8mb4')];
