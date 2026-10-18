<?php
declare(strict_types=1);
namespace Giftcheta\Core;
use PDO;
final class Database
{
    public static function connect(Config $config): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config->get('database.host'), $config->get('database.port'), $config->get('database.name'), $config->get('database.charset', 'utf8mb4'));
        return new PDO($dsn, $config->get('database.username'), $config->get('database.password'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    }
}
