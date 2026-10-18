<?php
declare(strict_types=1);
use Giftcheta\Core\Config;
return ['env' => Config::env('APP_ENV', 'production'), 'debug' => Config::env('APP_DEBUG', false), 'log_level' => Config::env('LOG_LEVEL', 'error')];
