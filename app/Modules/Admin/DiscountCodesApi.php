<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Admin;

use Giftcheta\Http\Request;
use Giftcheta\Http\Response;
use PDO;
use Throwable;

final class DiscountCodesApi
{
    public function __construct(private readonly PDO $db, private readonly AuthApi $auth) {}

    public function index(Request $request): Response
    {
        if ($error = $this->auth->guard($request, 'discount_codes.read', false)) return $error;
        $rows = $this->db->query('SELECT id,code,description,discount_type,discount_value,minimum_subtotal,maximum_uses,uses_count,starts_at,ends_at,is_active,created_at,updated_at FROM discount_codes ORDER BY created_at DESC,id DESC')->fetchAll(PDO::FETCH_ASSOC);
        return Response::json(['data' => $rows]);
    }

    public function create(Request $request): Response
    {
        if ($error = $this->auth->guard($request, 'discount_codes.write', true)) return $error;
        $fields = $this->fields($request->json());
        if ($fields instanceof Response) return $fields;
        try {
            $query = $this->db->prepare('INSERT INTO discount_codes (code,description,discount_type,discount_value,minimum_subtotal,maximum_uses,starts_at,ends_at,is_active) VALUES (?,?,?,?,?,?,?,?,?)');
            $query->execute($fields);
            return Response::json(['data' => ['id' => (int)$this->db->lastInsertId()]], 201);
        } catch (Throwable) { return $this->error('save_error', 'Кодът не можа да бъде създаден. Проверете дали вече не съществува.', 409); }
    }

    public function update(Request $request, array $params): Response
    {
        if ($error = $this->auth->guard($request, 'discount_codes.write', true)) return $error;
        $id = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$id) return $this->error('not_found', 'Кодът не е намерен.', 404);
        $fields = $this->fields($request->json());
        if ($fields instanceof Response) return $fields;
        try {
            $query = $this->db->prepare('UPDATE discount_codes SET code=?,description=?,discount_type=?,discount_value=?,minimum_subtotal=?,maximum_uses=?,starts_at=?,ends_at=?,is_active=? WHERE id=?');
            $query->execute([...$fields, $id]);
            if (!$query->rowCount() && !$this->exists((int)$id)) return $this->error('not_found', 'Кодът не е намерен.', 404);
            return Response::json(['data' => ['updated' => true]]);
        } catch (Throwable) { return $this->error('save_error', 'Кодът не можа да бъде запазен. Възможно е този код вече да съществува.', 409); }
    }

    public function delete(Request $request, array $params): Response
    {
        if ($error = $this->auth->guard($request, 'discount_codes.write', true)) return $error;
        $id = (int)($params['id'] ?? 0);
        $query = $this->db->prepare('UPDATE discount_codes SET is_active=0 WHERE id=?');
        $query->execute([$id]);
        if (!$query->rowCount() && !$this->exists($id)) return $this->error('not_found', 'Кодът не е намерен.', 404);
        return Response::json(['data' => ['deactivated' => true]]);
    }

    private function fields(array $input): array|Response
    {
        $code = strtoupper(trim((string)($input['code'] ?? '')));
        $description = trim((string)($input['description'] ?? ''));
        $type = (string)($input['discount_type'] ?? '');
        $value = filter_var($input['discount_value'] ?? null, FILTER_VALIDATE_FLOAT);
        $minimum = filter_var($input['minimum_subtotal'] ?? 0, FILTER_VALIDATE_FLOAT);
        $maximum = ($input['maximum_uses'] ?? '') === '' || ($input['maximum_uses'] ?? null) === null ? null : filter_var($input['maximum_uses'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $starts = $this->date($input['starts_at'] ?? null); $ends = $this->date($input['ends_at'] ?? null);
        if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{2,63}$/D', $code) || mb_strlen($description) > 255 || !in_array($type, ['percentage','fixed'], true) || $value === false || $value <= 0 || $minimum === false || $minimum < 0 || ($maximum !== null && $maximum === false) || ($starts === false) || ($ends === false) || ($starts && $ends && $starts >= $ends) || ($type === 'percentage' && $value > 100)) return $this->error('validation_error', 'Проверете полетата на кода за отстъпка.', 422);
        return [$code, $description, $type, number_format((float)$value, 2, '.', ''), number_format((float)$minimum, 2, '.', ''), $maximum, $starts, $ends, !empty($input['is_active']) ? 1 : 0];
    }
    private function date(mixed $value): string|null|false { $value = trim((string)$value); if ($value === '') return null; $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value); $errors = \DateTimeImmutable::getLastErrors(); return $date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) ? $date->format('Y-m-d H:i:00') : false; }
    private function exists(int $id): bool { if ($id < 1) return false; $query=$this->db->prepare('SELECT 1 FROM discount_codes WHERE id=?');$query->execute([$id]);return (bool)$query->fetchColumn(); }
    private function error(string $code, string $message, int $status): Response { return Response::json(['error'=>['code'=>$code,'message'=>$message]],$status); }
}
