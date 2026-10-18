<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use Giftcheta\Core\Config;
use Giftcheta\Core\Database;
use Giftcheta\Core\MigrationRunner;
Config::loadEnvironment(dirname(__DIR__) . '/.env');
$config = Config::fromDirectory(dirname(__DIR__) . '/config');
$applied = (new MigrationRunner(Database::connect($config), dirname(__DIR__) . '/database/migrations'))->migrate();
echo $applied === [] ? "No pending migrations.\n" : 'Applied: ' . implode(', ', $applied) . "\n";
