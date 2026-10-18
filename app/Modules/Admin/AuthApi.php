<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Admin;

use Giftcheta\Core\Config;
use Giftcheta\Http\Request;
use Giftcheta\Http\Response;
use PDO;

final class AuthApi
{
    public function __construct(private readonly PDO $db, private readonly Config $config) {}

    public function csrf(Request $request): Response
    {
        $this->startSession();
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
        return Response::json(['data' => ['csrfToken' => $_SESSION['csrf']]]);
    }

    public function login(Request $request): Response
    {
        $this->startSession();
        if (!$this->validCsrf($request)) return $this->error('csrf_invalid', 'The security token is invalid.', 419);
        $input = $request->json();
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '' || strlen($password) > 4096) return $this->error('validation_error', 'Enter a valid email and password.', 422);

        $key = hash('sha256', strtolower((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')) . '|' . $email);
        $attempt = $this->db->prepare('SELECT attempts, locked_until FROM admin_login_attempts WHERE attempt_key = ?');
        $attempt->execute([$key]);
        $row = $attempt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['locked_until'] && strtotime($row['locked_until']) > time()) return $this->error('login_rate_limited', 'Too many attempts. Try again later.', 429);

        $query = $this->db->prepare('SELECT a.id, a.name, a.email, a.password_hash, a.role_id, r.name AS role_name FROM admins a JOIN roles r ON r.id = a.role_id WHERE a.email = ? AND a.active = 1 LIMIT 1');
        $query->execute([$email]);
        $admin = $query->fetch(PDO::FETCH_ASSOC);
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            $this->db->prepare('INSERT INTO admin_login_attempts (attempt_key, attempts, window_started_at, locked_until) VALUES (?, 1, NOW(), NULL) ON DUPLICATE KEY UPDATE attempts = IF(window_started_at < NOW() - INTERVAL 15 MINUTE, 1, attempts + 1), window_started_at = IF(window_started_at < NOW() - INTERVAL 15 MINUTE, NOW(), window_started_at), locked_until = IF(attempts >= 4, DATE_ADD(NOW(), INTERVAL 15 MINUTE), locked_until)')->execute([$key]);
            return $this->error('invalid_credentials', 'Email or password is incorrect.', 401);
        }

        $this->db->prepare('DELETE FROM admin_login_attempts WHERE attempt_key = ?')->execute([$key]);
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $this->db->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?')->execute([$admin['id']]);
        return Response::json(['data' => ['admin' => ['id' => (int) $admin['id'], 'name' => $admin['name'], 'email' => $admin['email'], 'role' => $admin['role_name']], 'csrfToken' => $_SESSION['csrf']]]);
    }

    public function logout(Request $request): Response
    {
        $guard = $this->guard($request, null, true);
        if ($guard) return $guard;
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $params['path'], 'domain' => $params['domain'], 'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Strict']);
        }
        session_destroy();
        return Response::json(['data' => ['loggedOut' => true]]);
    }

    public function me(Request $request): Response
    {
        $guard = $this->guard($request, null, false);
        if ($guard) return $guard;
        $stmt = $this->db->prepare('SELECT a.id, a.name, a.email, r.name AS role FROM admins a JOIN roles r ON r.id = a.role_id WHERE a.id = ? AND a.active = 1');
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$admin) return $this->error('unauthenticated', 'Please sign in.', 401);
        return Response::json(['data' => ['admin' => $admin, 'csrfToken' => $_SESSION['csrf'] ?? '']]);
    }

    public function guard(Request $request, ?string $permission, bool $csrf): ?Response
    {
        $this->startSession();
        if (!isset($_SESSION['admin_id'])) return $this->error('unauthenticated', 'Please sign in.', 401);
        if ($csrf && !$this->validCsrf($request)) return $this->error('csrf_invalid', 'The security token is invalid.', 419);
        if ($permission !== null) {
            $stmt = $this->db->prepare('SELECT p.name FROM admins a JOIN role_permissions rp ON rp.role_id = a.role_id JOIN permissions p ON p.id = rp.permission_id WHERE a.id = ? AND a.active = 1');
            $stmt->execute([$_SESSION['admin_id']]);
            $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('*', $permissions, true) && !in_array($permission, $permissions, true)) return $this->error('forbidden', 'You do not have permission to perform this action.', 403);
        }
        return null;
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        session_name('giftcheta_admin');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), 'httponly' => true, 'samesite' => 'Strict']);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();
    }

    private function validCsrf(Request $request): bool
    {
        $provided = '';
        foreach ($request->headers as $key => $value) if (strtolower((string) $key) === 'x-csrf-token') $provided = (string) $value;
        return isset($_SESSION['csrf']) && $provided !== '' && hash_equals($_SESSION['csrf'], $provided);
    }

    private function error(string $code, string $message, int $status): Response { return Response::json(['error' => ['code' => $code, 'message' => $message]], $status); }
}
