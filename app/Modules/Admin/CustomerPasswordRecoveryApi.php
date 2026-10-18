<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Admin;

use Giftcheta\Core\Config;
use Giftcheta\Core\Logger;
use Giftcheta\Core\SmtpMailer;
use Giftcheta\Http\Request;
use Giftcheta\Http\Response;
use PDO;
use Throwable;

final class CustomerPasswordRecoveryApi
{
    public function __construct(private readonly PDO $db, private readonly AuthApi $auth, private readonly Config $config, private readonly Logger $logger) {}

    public function sendResetLink(Request $request, array $params): Response
    {
        if ($error = $this->auth->guard($request, 'customers.reset_password', true)) return $error;
        $customerId = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($customerId === false) return $this->error('not_found', 'Клиентът не е намерен.', 404);

        $this->db->beginTransaction();
        try {
            $query = $this->db->prepare("SELECT id,first_name,last_name,email FROM customers WHERE id=? AND password_hash IS NOT NULL AND status='active' AND deleted_at IS NULL LIMIT 1 FOR UPDATE");
            $query->execute([$customerId]);
            $customer = $query->fetch(PDO::FETCH_ASSOC);
            if (!$customer || !is_string($customer['email']) || !filter_var($customer['email'], FILTER_VALIDATE_EMAIL)) {
                $this->db->rollBack();
                return $this->error('customer_account_unavailable', 'Клиентът няма активен акаунт с валиден имейл адрес.', 422);
            }
            $rate = $this->db->prepare('SELECT COUNT(*) FROM customer_password_resets WHERE customer_id=? AND created_at >= UTC_TIMESTAMP() - INTERVAL 15 MINUTE');
            $rate->execute([$customerId]);
            if ((int) $rate->fetchColumn() >= 3) {
                $this->db->rollBack();
                return $this->error('reset_rate_limited', 'За този клиент вече са изпратени няколко линка. Опитайте отново след 15 минути.', 429);
            }
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $this->db->prepare('UPDATE customer_password_resets SET used_at=UTC_TIMESTAMP() WHERE customer_id=? AND used_at IS NULL')->execute([$customerId]);
            $insert = $this->db->prepare('INSERT INTO customer_password_resets (customer_id,email,token_hash,expires_at) VALUES (?,?,?,UTC_TIMESTAMP() + INTERVAL 60 MINUTE)');
            $insert->execute([$customerId, $customer['email'], $tokenHash]);
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }

        try {
            $link = $this->resetUrl($token);
            $name = trim((string) $customer['first_name'] . ' ' . (string) $customer['last_name']);
            $body = "Здравейте, {$name},\n\nПолучихме заявка от администрацията на Giftcheta за възстановяване на паролата към клиентския Ви акаунт.\n\nЗа да зададете нова парола, отворете този еднократен линк в рамките на 60 минути:\n{$link}\n\nАко не очаквате това съобщение, не отваряйте линка и се свържете с магазина. Паролата Ви няма да бъде променена, докато не зададете нова чрез линка.\n";
            (new SmtpMailer($this->config))->send((string) $customer['email'], 'Възстановяване на паролата за Giftcheta', $body);
        } catch (Throwable $error) {
            $this->db->prepare('UPDATE customer_password_resets SET used_at=UTC_TIMESTAMP() WHERE token_hash=? AND used_at IS NULL')->execute([$tokenHash]);
            $this->logger->log('error', 'Customer password reset email could not be sent', ['customer_id' => (int) $customerId, 'exception' => $error::class]);
            return $this->error('mail_delivery_failed', 'Имейлът не можа да бъде изпратен. Проверете настройките за SMTP и опитайте отново.', 503);
        }

        $this->logger->log('info', 'Customer password reset email sent', ['customer_id' => (int) $customerId]);
        return Response::json(['data' => ['sent' => true]]);
    }

    public function reset(Request $request): Response
    {
        $input = $request->json();
        $token = is_string($input['token'] ?? null) ? $input['token'] : '';
        $password = is_string($input['password'] ?? null) ? $input['password'] : '';
        $confirmation = is_string($input['password_confirmation'] ?? null) ? $input['password_confirmation'] : '';
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) return $this->invalidToken();
        if (mb_strlen($password, 'UTF-8') < 12 || mb_strlen($password, 'UTF-8') > 72 || strlen($password) > 72) return $this->error('password_policy', 'Паролата трябва да е между 12 и 72 знака.', 422);
        if (!hash_equals($password, $confirmation)) return $this->error('password_mismatch', 'Двете въведени пароли не съвпадат.', 422);

        $tokenHash = hash('sha256', $token);
        $this->db->beginTransaction();
        try {
            $query = $this->db->prepare("SELECT r.id AS reset_id,r.customer_id,r.email,c.email AS current_email FROM customer_password_resets r JOIN customers c ON c.id=r.customer_id WHERE r.token_hash=? AND r.used_at IS NULL AND r.expires_at > UTC_TIMESTAMP() AND c.password_hash IS NOT NULL AND c.status='active' AND c.deleted_at IS NULL FOR UPDATE");
            $query->execute([$tokenHash]);
            $reset = $query->fetch(PDO::FETCH_ASSOC);
            if (!$reset || !hash_equals(strtolower((string) $reset['email']), strtolower((string) $reset['current_email']))) {
                $this->db->rollBack();
                return $this->invalidToken();
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $update = $this->db->prepare('UPDATE customers SET password_hash=?,email_verified_at=COALESCE(email_verified_at,UTC_TIMESTAMP()) WHERE id=?');
            $update->execute([$passwordHash, $reset['customer_id']]);
            $this->db->prepare('UPDATE customer_password_resets SET used_at=UTC_TIMESTAMP() WHERE customer_id=? AND used_at IS NULL')->execute([$reset['customer_id']]);
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }

        try {
            (new SmtpMailer($this->config))->send((string) $reset['current_email'], 'Паролата за Giftcheta е променена', "Паролата за клиентския Ви акаунт в Giftcheta беше променена.\n\nАко не сте направили тази промяна, свържете се незабавно с магазина.\n");
        } catch (Throwable $error) {
            $this->logger->log('error', 'Customer password reset notification could not be sent', ['customer_id' => (int) $reset['customer_id'], 'exception' => $error::class]);
        }

        $this->logger->log('info', 'Customer password was reset', ['customer_id' => (int) $reset['customer_id']]);
        return Response::json(['data' => ['reset' => true]]);
    }

    private function resetUrl(string $token): string
    {
        $base = rtrim((string) $this->config->get('app.url', ''), '/');
        $parts = parse_url($base);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new \RuntimeException('APP_URL must be a trusted absolute HTTP(S) URL.');
        }
        if ($this->config->get('app.env') === 'production' && ($parts['scheme'] ?? '') !== 'https') throw new \RuntimeException('Password reset links must use HTTPS in production.');
        return $base . '/account/password-reset?token=' . rawurlencode($token);
    }

    private function invalidToken(): Response { return $this->error('invalid_or_expired_token', 'Линкът е невалиден или е изтекъл. Поискайте нов линк от магазина.', 422); }
    private function error(string $code, string $message, int $status): Response { return Response::json(['error' => ['code' => $code, 'message' => $message]], $status); }
}
