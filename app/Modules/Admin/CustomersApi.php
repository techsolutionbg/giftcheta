<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Admin;

use Giftcheta\Http\Request;
use Giftcheta\Http\Response;
use PDO;
use PDOException;
use Throwable;

/** Admin-only customer and address management. Password hashes are never selected or accepted here. */
final class CustomersApi
{
    public function __construct(private readonly PDO $db, private readonly AuthApi $auth) {}

    public function index(Request $request): Response
    {
        if ($error = $this->auth->guard($request, 'customers.view', false)) return $error;
        $page = max(1, (int) ($request->query['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($request->query['per_page'] ?? 20)));
        $trash = ($request->query['trash'] ?? '') === 'only';
        $where = [$trash ? 'c.deleted_at IS NOT NULL' : 'c.deleted_at IS NULL'];
        $args = [];
        $search = trim((string) ($request->query['search'] ?? ''));
        if ($search !== '') {
            $where[] = "(CONCAT_WS(' ',c.first_name,c.last_name) LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
            $like = '%' . $search . '%';
            array_push($args, $like, $like, $like);
        }
        if (in_array($request->query['status'] ?? '', ['active','inactive','blocked'], true)) {
            $where[] = 'c.status = ?';
            $args[] = $request->query['status'];
        }
        $sorts = ['name' => 'c.last_name', 'email' => 'c.email', 'phone' => 'c.phone', 'status' => 'c.status', 'created_at' => 'c.created_at'];
        $sort = $sorts[(string) ($request->query['sort'] ?? 'created_at')] ?? 'c.created_at';
        $direction = strtolower((string) ($request->query['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $clause = ' WHERE ' . implode(' AND ', $where);
        $count = $this->db->prepare('SELECT COUNT(*) FROM customers c' . $clause);
        $count->execute($args);
        $total = (int) $count->fetchColumn();
        $offset = ($page - 1) * $perPage;
        $sql = 'SELECT c.id,c.first_name,c.last_name,c.email,c.phone,c.status,c.email_verified_at,c.created_at,c.updated_at,c.deleted_at,'
            . '(SELECT COUNT(*) FROM customer_addresses ca WHERE ca.customer_id=c.id) AS address_count '
            . 'FROM customers c' . $clause . " ORDER BY {$sort} {$direction},c.id DESC LIMIT {$perPage} OFFSET {$offset}";
        $rows = $this->db->prepare($sql);
        $rows->execute($args);
        return Response::json(['data' => $rows->fetchAll(PDO::FETCH_ASSOC), 'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => (int) ceil($total / $perPage)]]);
    }

    public function show(Request $request, array $params): Response
    {
        if ($error = $this->auth->guard($request, 'customers.view', false)) return $error;
        $customer = $this->findCustomer((int) ($params['id'] ?? 0));
        if (!$customer) return $this->notFound();
        $addresses = $this->db->prepare('SELECT id,customer_id,recipient_name,phone,country_code,city,postal_code,address_line1,address_line2,is_default,created_at,updated_at FROM customer_addresses WHERE customer_id=? ORDER BY is_default DESC,id');
        $addresses->execute([$customer['id']]);
        $customer['addresses'] = $addresses->fetchAll(PDO::FETCH_ASSOC);
        return Response::json(['data' => $customer]);
    }

    public function create(Request $request): Response
    {
        if ($error = $this->auth->guard($request, 'customers.create', true)) return $error;
        $data = $request->json();
        $valid = $this->validateCustomer($data, true);
        if ($valid instanceof Response) return $valid;
        [$first, $last, $email, $phone, $status, $notes] = $valid;
        if ($status === 'blocked' && ($error = $this->auth->guard($request, 'customers.manage_status', true))) return $error;
        $insert = $this->db->prepare('INSERT INTO customers (first_name,last_name,email,phone,status,admin_notes) VALUES (?,?,?,?,?,?)');
        try { $insert->execute([$first, $last, $email, $phone, $status, $notes]); }
        catch (PDOException $exception) {
            if ($exception->getCode() === '23000' && $email !== null) return $this->error('registered_email_in_use', 'Имейлът вече се използва от регистриран клиент.', 409);
            throw $exception;
        }
        return Response::json(['data' => ['id' => (int) $this->db->lastInsertId()]], 201);
    }

    public function update(Request $request, array $params): Response
    {
        if ($error = $this->auth->guard($request, 'customers.update', true)) return $error;
        $id = (int) ($params['id'] ?? 0);
        $old = $this->findCustomer($id);
        if (!$old) return $this->notFound();
        $data = $request->json();
        if (array_key_exists('status', $data) && $data['status'] !== $old['status']) {
            if ($error = $this->auth->guard($request, 'customers.manage_status', true)) return $error;
        }
        $valid = $this->validateCustomer($data, false, $old);
        if ($valid instanceof Response) return $valid;
        [$first, $last, $email, $phone, $status, $notes] = $valid;
        $update = $this->db->prepare('UPDATE customers SET first_name=?,last_name=?,email=?,phone=?,status=?,admin_notes=? WHERE id=? AND deleted_at IS NULL');
        try { $update->execute([$first, $last, $email, $phone, $status, $notes, $id]); }
        catch (PDOException $exception) {
            if ($exception->getCode() === '23000' && $email !== null) return $this->error('registered_email_in_use', 'Имейлът вече се използва от регистриран клиент.', 409);
            throw $exception;
        }
        return Response::json(['data' => ['updated' => true]]);
    }

    public function trash(Request $request, array $params): Response
    {
        if ($error = $this->auth->guard($request, 'customers.delete', true)) return $error;
        $id = (int) ($params['id'] ?? 0);
        if (!$this->findCustomer($id)) return $this->notFound();
        $delete = $this->db->prepare('UPDATE customers SET deleted_at=CURRENT_TIMESTAMP WHERE id=? AND deleted_at IS NULL');
        $delete->execute([$id]);
        if (!$delete->rowCount()) return $this->error('customer_already_trashed', 'Клиентът вече е в кошчето.', 409);
        return Response::json(['data' => ['trashed' => true]]);
    }

    public function restore(Request $request, array $params): Response
    {
        if ($error = $this->auth->guard($request, 'customers.restore', true)) return $error;
        $id = (int) ($params['id'] ?? 0);
        $update = $this->db->prepare('UPDATE customers SET deleted_at=NULL WHERE id=? AND deleted_at IS NOT NULL');
        $update->execute([$id]);
        if (!$update->rowCount()) return $this->error('customer_not_trashed', 'Клиентът не е в кошчето или не съществува.', 404);
        return Response::json(['data' => ['restored' => true]]);
    }

    public function permanentlyDelete(Request $request, array $params): Response
    {
        if ($error = $this->auth->guard($request, 'customers.delete', true)) return $error;
        $id = (int) ($params['id'] ?? 0);
        $customer = $this->findCustomer($id, true);
        if (!$customer) return $this->notFound();
        if ($customer['deleted_at'] === null) return $this->error('customer_not_trashed', 'Първо преместете клиента в кошчето.', 409);
        try {
            $this->db->beginTransaction();
            $this->db->prepare('DELETE FROM customer_addresses WHERE customer_id=?')->execute([$id]);
            $this->db->prepare('DELETE FROM customers WHERE id=? AND deleted_at IS NOT NULL')->execute([$id]);
            $this->db->commit();
        } catch (PDOException $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if ($exception->getCode() === '23000') return $this->error('customer_has_related_records', 'Клиентът има свързани записи и не може да бъде изтрит завинаги.', 409);
            throw $exception;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
        return Response::json(['data' => ['deleted' => true]]);
    }

    public function bulk(Request $request): Response
    {
        if ($error = $this->auth->guard($request, null, true)) return $error;
        $data = $request->json();
        $action = (string) ($data['action'] ?? '');
        $permission = match ($action) {
            'activate','deactivate','block' => 'customers.manage_status',
            'restore' => 'customers.restore',
            'trash','permanent' => 'customers.delete',
            default => null,
        };
        if ($permission === null) return $this->error('validation_error', 'Неподдържано масово действие.', 422);
        if ($error = $this->auth->guard($request, $permission, false)) return $error;
        $ids = is_array($data['ids'] ?? null)
            ? array_values(array_unique(array_filter(array_map(static fn (mixed $id): int => is_scalar($id) && ctype_digit((string) $id) ? (int) $id : 0, $data['ids']), static fn (int $id): bool => $id > 0)))
            : [];
        if (!$ids || count($ids) > 100) return $this->error('validation_error', 'Изберете от 1 до 100 клиента.', 422);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $affected = 0;
        $failedIds = [];
        if (in_array($action, ['activate','deactivate','block'], true)) {
            $status = ['activate' => 'active', 'deactivate' => 'inactive', 'block' => 'blocked'][$action];
            $statement = $this->db->prepare("UPDATE customers SET status=? WHERE id IN ({$placeholders}) AND deleted_at IS NULL");
            $statement->execute([$status, ...$ids]);
            $affected = $statement->rowCount();
        } elseif ($action === 'trash') {
            $statement = $this->db->prepare("UPDATE customers SET deleted_at=CURRENT_TIMESTAMP WHERE id IN ({$placeholders}) AND deleted_at IS NULL");
            $statement->execute($ids);
            $affected = $statement->rowCount();
        } elseif ($action === 'restore') {
            $statement = $this->db->prepare("UPDATE customers SET deleted_at=NULL WHERE id IN ({$placeholders}) AND deleted_at IS NOT NULL");
            $statement->execute($ids);
            $affected = $statement->rowCount();
        } elseif ($action === 'permanent') {
            foreach ($ids as $id) {
                try {
                    $statement = $this->db->prepare('DELETE FROM customers WHERE id=? AND deleted_at IS NOT NULL');
                    $statement->execute([$id]);
                    $affected += $statement->rowCount();
                } catch (PDOException $exception) {
                    if ($exception->getCode() !== '23000') throw $exception;
                    $failedIds[] = $id;
                }
            }
        }
        return Response::json(['data' => ['affected' => $affected, 'failed_ids' => $failedIds]]);
    }

    public function addresses(Request $request, array $params): Response
    {
        if ($error = $this->auth->guard($request, 'customers.view', false)) return $error;
        $id = (int) ($params['id'] ?? 0);
        if (!$this->findCustomer($id)) return $this->notFound();
        $query = $this->db->prepare('SELECT id,customer_id,recipient_name,phone,country_code,city,postal_code,address_line1,address_line2,is_default,created_at,updated_at FROM customer_addresses WHERE customer_id=? ORDER BY is_default DESC,id');
        $query->execute([$id]);
        return Response::json(['data' => $query->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function createAddress(Request $request, array $params): Response
    {
        if ($error = $this->auth->guard($request, 'customers.update', true)) return $error;
        $customerId = (int) ($params['id'] ?? 0);
        if (!$this->findCustomer($customerId)) return $this->notFound();
        $valid = $this->validateAddress($request->json());
        if ($valid instanceof Response) return $valid;
        $data = $request->json();
        try {
            $this->db->beginTransaction();
            $this->lockCustomer($customerId);
            $count = $this->addressCount($customerId);
            $isDefault = $this->boolean($data['is_default'] ?? false) || $count === 0;
            if ($isDefault) $this->db->prepare('UPDATE customer_addresses SET is_default=0 WHERE customer_id=?')->execute([$customerId]);
            $insert = $this->db->prepare('INSERT INTO customer_addresses (customer_id,recipient_name,phone,country_code,city,postal_code,address_line1,address_line2,is_default) VALUES (?,?,?,?,?,?,?,?,?)');
            $insert->execute([$customerId, ...$valid, $isDefault ? 1 : 0]);
            $id = (int) $this->db->lastInsertId();
            $this->db->commit();
            return Response::json(['data' => ['id' => $id]], 201);
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public function updateAddress(Request $request, array $params): Response
    {
        if ($error = $this->auth->guard($request, 'customers.update', true)) return $error;
        $customerId = (int) ($params['id'] ?? 0);
        $addressId = (int) ($params['address_id'] ?? 0);
        if (!$this->findCustomer($customerId)) return $this->notFound();
        $old = $this->findAddress($customerId, $addressId);
        if (!$old) return $this->notFound();
        $data = $request->json();
        $valid = $this->validateAddress($data, $old);
        if ($valid instanceof Response) return $valid;
        $isDefault = array_key_exists('is_default', $data) ? $this->boolean($data['is_default']) : (bool) $old['is_default'];
        try {
            $this->db->beginTransaction();
            $this->lockCustomer($customerId);
            if ($isDefault) $this->db->prepare('UPDATE customer_addresses SET is_default=0 WHERE customer_id=? AND id<>?')->execute([$customerId, $addressId]);
            $update = $this->db->prepare('UPDATE customer_addresses SET recipient_name=?,phone=?,country_code=?,city=?,postal_code=?,address_line1=?,address_line2=?,is_default=? WHERE id=? AND customer_id=?');
            $update->execute([...$valid, $isDefault ? 1 : 0, $addressId, $customerId]);
            $this->db->commit();
            return Response::json(['data' => ['updated' => true]]);
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public function deleteAddress(Request $request, array $params): Response
    {
        if ($error = $this->auth->guard($request, 'customers.update', true)) return $error;
        $customerId = (int) ($params['id'] ?? 0);
        $addressId = (int) ($params['address_id'] ?? 0);
        if (!$this->findCustomer($customerId)) return $this->notFound();
        $address = $this->findAddress($customerId, $addressId);
        if (!$address) return $this->notFound();
        try {
            $this->db->beginTransaction();
            $this->lockCustomer($customerId);
            $this->db->prepare('DELETE FROM customer_addresses WHERE id=? AND customer_id=?')->execute([$addressId, $customerId]);
            if ((bool) $address['is_default']) {
                $next = $this->db->prepare('SELECT id FROM customer_addresses WHERE customer_id=? ORDER BY id LIMIT 1');
                $next->execute([$customerId]);
                $nextId = $next->fetchColumn();
                if ($nextId !== false) $this->db->prepare('UPDATE customer_addresses SET is_default=1 WHERE id=? AND customer_id=?')->execute([(int) $nextId, $customerId]);
            }
            $this->db->commit();
            return Response::json(['data' => ['deleted' => true]]);
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    private function validateCustomer(array $data, bool $creating, ?array $old = null): array|Response
    {
        foreach (['first_name','last_name','email','phone','status','admin_notes'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null && !is_scalar($data[$field])) return $this->error('validation_error', 'Невалиден формат на клиентските данни.', 422);
        }
        $first = trim((string) ($data['first_name'] ?? ($old['first_name'] ?? '')));
        $last = trim((string) ($data['last_name'] ?? ($old['last_name'] ?? '')));
        if ($first === '' || mb_strlen($first) > 100 || $last === '' || mb_strlen($last) > 100) return $this->error('validation_error', 'Името и фамилията са задължителни (до 100 знака).', 422);
        $emailValue = array_key_exists('email', $data) ? $data['email'] : ($old['email'] ?? null);
        $email = $emailValue === null ? null : strtolower(trim((string) $emailValue));
        if ($email === '') $email = null;
        if ($email !== null && (mb_strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL))) return $this->error('validation_error', 'Въведете валиден имейл адрес до 254 знака.', 422);
        $phoneValue = array_key_exists('phone', $data) ? $data['phone'] : ($old['phone'] ?? null);
        $phone = $this->normalizePhone($phoneValue);
        if ($phone instanceof Response) return $phone;
        $status = (string) ($data['status'] ?? ($old['status'] ?? 'active'));
        if (!in_array($status, ['active','inactive','blocked'], true)) return $this->error('validation_error', 'Невалиден статус на клиента.', 422);
        $notesValue = array_key_exists('admin_notes', $data) ? $data['admin_notes'] : ($old['admin_notes'] ?? null);
        $notes = $notesValue === null ? null : trim((string) $notesValue);
        if ($notes === '') $notes = null;
        if ($notes !== null && mb_strlen($notes) > 10000) return $this->error('validation_error', 'Административната бележка може да бъде до 10 000 знака.', 422);
        return [$first, $last, $email, $phone, $status, $notes];
    }

    private function validateAddress(array $data, ?array $old = null): array|Response
    {
        foreach (['recipient_name','phone','country_code','city','postal_code','address_line1','address_line2','is_default'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null && !is_scalar($data[$field])) return $this->error('validation_error', 'Невалиден формат на адресните данни.', 422);
        }
        $value = static fn (string $key, mixed $default = null): mixed => array_key_exists($key, $data) ? $data[$key] : ($old[$key] ?? $default);
        $recipient = trim((string) $value('recipient_name', ''));
        $country = strtoupper(trim((string) $value('country_code', 'BG')));
        $city = trim((string) $value('city', ''));
        $postal = $value('postal_code');
        $line1 = trim((string) $value('address_line1', ''));
        $line2 = $value('address_line2');
        $phone = $this->normalizePhone($value('phone'));
        if ($phone instanceof Response) return $phone;
        $postal = $postal === null ? null : trim((string) $postal);
        $line2 = $line2 === null ? null : trim((string) $line2);
        if ($recipient === '' || mb_strlen($recipient) > 160 || $city === '' || mb_strlen($city) > 100 || !preg_match('/^[A-Z]{2}$/', $country) || $line1 === '' || mb_strlen($line1) > 200 || ($postal !== null && mb_strlen($postal) > 20) || ($line2 !== null && mb_strlen($line2) > 200)) {
            return $this->error('validation_error', 'Проверете получателя, държавата, града и адреса. Някои стойности са празни или прекалено дълги.', 422);
        }
        return [$recipient, $phone, $country, $city, $postal === '' ? null : $postal, $line1, $line2 === '' ? null : $line2];
    }

    private function normalizePhone(mixed $value): string|null|Response
    {
        if ($value !== null && !is_scalar($value)) return $this->error('validation_error', 'Невалиден формат на телефона.', 422);
        if ($value === null || trim((string) $value) === '') return null;
        $raw = trim((string) $value);
        $phone = preg_replace('/[^0-9+]/u', '', $raw);
        if (!is_string($phone) || preg_match('/(?!^)\+/', $phone) || !preg_match('/^\+?[0-9]{5,20}$/', $phone)) return $this->error('validation_error', 'Телефонът трябва да съдържа от 5 до 20 цифри и може да започва с +.', 422);
        return $phone;
    }

    private function boolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function findCustomer(int $id, bool $includeDeleted = false): ?array
    {
        $sql = 'SELECT id,first_name,last_name,email,phone,status,email_verified_at,(password_hash IS NOT NULL) AS has_account,admin_notes,created_at,updated_at,deleted_at FROM customers WHERE id=?' . ($includeDeleted ? '' : ' AND deleted_at IS NULL');
        $query = $this->db->prepare($sql);
        $query->execute([$id]);
        $customer = $query->fetch(PDO::FETCH_ASSOC);
        return $customer ?: null;
    }

    private function findAddress(int $customerId, int $addressId): ?array
    {
        $query = $this->db->prepare('SELECT id,customer_id,recipient_name,phone,country_code,city,postal_code,address_line1,address_line2,is_default FROM customer_addresses WHERE id=? AND customer_id=?');
        $query->execute([$addressId, $customerId]);
        $address = $query->fetch(PDO::FETCH_ASSOC);
        return $address ?: null;
    }

    private function addressCount(int $customerId): int
    {
        $query = $this->db->prepare('SELECT COUNT(*) FROM customer_addresses WHERE customer_id=?');
        $query->execute([$customerId]);
        return (int) $query->fetchColumn();
    }

    private function lockCustomer(int $customerId): void
    {
        $this->db->prepare('SELECT id FROM customers WHERE id=? AND deleted_at IS NULL FOR UPDATE')->execute([$customerId]);
    }

    private function notFound(): Response { return $this->error('not_found', 'Клиентът не е намерен.', 404); }
    private function error(string $code, string $message, int $status): Response { return Response::json(['error' => ['code' => $code, 'message' => $message]], $status); }
}
