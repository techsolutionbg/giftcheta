<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Admin;

use Giftcheta\Http\Request;
use Giftcheta\Http\Response;
use Giftcheta\Modules\Banners\BannerImageStorage;
use Giftcheta\Modules\Banners\BannerRepository;
use InvalidArgumentException;
use PDO;
use Throwable;

final class BannersApi
{
    private BannerRepository $banners;
    private BannerImageStorage $images;

    public function __construct(private readonly PDO $db, private readonly AuthApi $auth, string $projectRoot)
    { $this->banners = new BannerRepository($db); $this->images = new BannerImageStorage($projectRoot); }

    public function index(Request $request): Response
    {
        if ($error = $this->auth->guard($request, 'banners.read', false)) return $error;
        return Response::json(['data' => $this->banners->all()]);
    }

    public function create(Request $request): Response
    {
        if ($error = $this->auth->guard($request, 'banners.write', true)) return $error;
        $fields = $this->fields($request->form);
        if ($fields instanceof Response) return $fields;
        try { $image = $this->images->store($request->files['image'] ?? null); }
        catch (InvalidArgumentException $error) { return $this->error('validation_error', $error->getMessage(), 422); }
        catch (Throwable) { return $this->error('upload_error', 'Изображението не можа да бъде запазено.', 500); }
        try {
            $query = $this->db->prepare('INSERT INTO banners (title,description,button_label,button_url,image_path,image_alt,sort_order,is_active) VALUES (?,?,?,?,?,?,?,?)');
            $query->execute([$fields[0], $fields[1], $fields[2], $fields[3], $image, $fields[4], $fields[5], $fields[6]]);
            return Response::json(['data' => ['id' => (int)$this->db->lastInsertId()]], 201);
        } catch (Throwable) { $this->images->remove($image); return $this->error('save_error', 'Банерът не можа да бъде създаден.', 500); }
    }

    public function update(Request $request, array $params): Response
    {
        if ($error = $this->auth->guard($request, 'banners.write', true)) return $error;
        $id = (int)($params['id'] ?? 0); $current = $this->banners->find($id);
        if (!$current) return $this->error('not_found', 'Банерът не е намерен.', 404);
        $fields = $this->fields($request->form);
        if ($fields instanceof Response) return $fields;
        $newImage = null;
        if (isset($request->files['image']) && ($request->files['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try { $newImage = $this->images->store($request->files['image']); }
            catch (InvalidArgumentException $error) { return $this->error('validation_error', $error->getMessage(), 422); }
            catch (Throwable) { return $this->error('upload_error', 'Изображението не можа да бъде запазено.', 500); }
        }
        try {
            $query = $this->db->prepare('UPDATE banners SET title=?,description=?,button_label=?,button_url=?,image_path=?,image_alt=?,sort_order=?,is_active=? WHERE id=?');
            $query->execute([$fields[0], $fields[1], $fields[2], $fields[3], $newImage ?? $current['image_path'], $fields[4], $fields[5], $fields[6], $id]);
            if ($newImage !== null) $this->images->remove($current['image_path']);
            return Response::json(['data' => ['updated' => true]]);
        } catch (Throwable) { if ($newImage !== null) $this->images->remove($newImage); return $this->error('save_error', 'Банерът не можа да бъде обновен.', 500); }
    }

    public function delete(Request $request, array $params): Response
    {
        if ($error = $this->auth->guard($request, 'banners.write', true)) return $error;
        $banner = $this->banners->find((int)($params['id'] ?? 0));
        if (!$banner) return $this->error('not_found', 'Банерът не е намерен.', 404);
        $this->db->prepare('DELETE FROM banners WHERE id=?')->execute([(int)$banner['id']]);
        $this->images->remove($banner['image_path']);
        return Response::json(['data' => ['deleted' => true]]);
    }

    public function reorder(Request $request): Response
    {
        if ($error = $this->auth->guard($request, 'banners.write', true)) return $error;
        $rawIds = $request->json()['ids'] ?? null;
        if (!is_array($rawIds) || count($rawIds) > 500) return $this->error('validation_error', 'Невалиден ред на банерите.', 422);
        $ids = array_map('intval', $rawIds);
        if (in_array(0, $ids, true) || count(array_unique($ids)) !== count($ids)) return $this->error('validation_error', 'Невалиден ред на банерите.', 422);
        $existing = array_map('intval', $this->db->query('SELECT id FROM banners')->fetchAll(PDO::FETCH_COLUMN));
        $expected = $ids; sort($expected); sort($existing);
        if ($expected !== $existing) return $this->error('validation_error', 'Списъкът с банери е променен. Заредете страницата отново.', 409);
        $this->db->beginTransaction();
        try { $query = $this->db->prepare('UPDATE banners SET sort_order=? WHERE id=?'); foreach (array_values($ids) as $position => $id) { if ((int)$id < 1) throw new InvalidArgumentException(); $query->execute([$position, (int)$id]); } $this->db->commit(); }
        catch (Throwable) { if ($this->db->inTransaction()) $this->db->rollBack(); return $this->error('save_error', 'Подредбата не можа да бъде запазена.', 500); }
        return Response::json(['data' => ['updated' => count($ids)]]);
    }

    private function fields(array $input): array|Response
    {
        $title = trim((string)($input['title'] ?? '')); $description = trim((string)($input['description'] ?? ''));
        $label = trim((string)($input['button_label'] ?? '')); $url = trim((string)($input['button_url'] ?? ''));
        $alt = trim((string)($input['image_alt'] ?? '')); $order = filter_var($input['sort_order'] ?? 0, FILTER_VALIDATE_INT);
        $validUrl = $url !== '' && strlen($url) <= 2048 && !preg_match('/[\x00-\x20]/', $url) && !str_contains($url, '\\')
            && ((str_starts_with($url, '/') && !str_starts_with($url, '//')) || (str_starts_with($url, 'https://') && filter_var($url, FILTER_VALIDATE_URL) !== false));
        $active = filter_var($input['is_active'] ?? '1', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($title === '' || mb_strlen($title) > 180 || $description === '' || mb_strlen($description) > 500 || $label === '' || mb_strlen($label) > 80 || !$validUrl || mb_strlen($alt) > 255 || $order === false || $order < 0 || $order > 1000000 || $active === null) {
            return $this->error('validation_error', 'Попълнете заглавие, текст на бутона и валиден вътрешен или HTTPS адрес. Проверете ограниченията за дължина и подредба.', 422);
        }
        return [$title, $description, $label, $url, $alt === '' ? null : $alt, $order, $active ? 1 : 0];
    }

    private function error(string $code, string $message, int $status): Response { return Response::json(['error' => ['code' => $code, 'message' => $message]], $status); }
}
