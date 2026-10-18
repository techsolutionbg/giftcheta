<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use Giftcheta\Core\Config;
use Giftcheta\Core\Database;
Config::loadEnvironment(dirname(__DIR__) . '/.env');
$config = Config::fromDirectory(dirname(__DIR__) . '/config');
$db = Database::connect($config);
$email = strtolower(trim((string) readline('Administrator email: ')));
$name = trim((string) readline('Administrator name: '));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') { fwrite(STDERR, "Valid email and name are required.\n"); exit(1); }
$password = '';
if (PHP_OS_FAMILY === 'Windows') {
    fwrite(STDOUT, 'Password (at least 12 characters): ');
    $password = (string) stream_get_contents(STDIN, -1, 0);
    $password = rtrim($password, "\r\n");
} else {
    fwrite(STDOUT, 'Password (at least 12 characters): ');
    shell_exec('stty -echo');
    try { $password = (string) fgets(STDIN); } finally { shell_exec('stty echo'); fwrite(STDOUT, "\n"); }
    $password = rtrim($password, "\r\n");
}
if (strlen($password) < 12) { fwrite(STDERR, "Password must be at least 12 characters.\n"); exit(1); }
$role = $db->query("SELECT id FROM roles WHERE slug='administrator'")->fetchColumn();
if (!$role) { fwrite(STDERR, "Administrator role is missing; run migrations first.\n"); exit(1); }
$q = $db->prepare('INSERT INTO admins (role_id,name,email,password_hash) VALUES (?,?,?,?)');
try { $q->execute([(int) $role,$name,$email,password_hash($password,PASSWORD_DEFAULT)]); }
catch (PDOException $error) { fwrite(STDERR, $error->getCode()==='23000' ? "An administrator with that email already exists.\n" : "Administrator could not be created.\n"); exit(1); }
fwrite(STDOUT, "Administrator created.\n");
