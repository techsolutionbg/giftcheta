<?php
declare(strict_types=1);
use Giftcheta\Core\Config;
return [
    'mailer' => Config::env('MAIL_MAILER', 'smtp'),
    'host' => Config::env('MAIL_HOST', '127.0.0.1'),
    'port' => (int) Config::env('MAIL_PORT', 1025),
    'username' => Config::env('MAIL_USERNAME'),
    'password' => Config::env('MAIL_PASSWORD'),
    'encryption' => Config::env('MAIL_ENCRYPTION'),
    'from_address' => Config::env('MAIL_FROM_ADDRESS', 'no-reply@giftcheta.local'),
    'from_name' => Config::env('MAIL_FROM_NAME', 'Giftcheta'),
];
