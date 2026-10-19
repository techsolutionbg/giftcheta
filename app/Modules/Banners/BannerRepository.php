<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Banners;

use PDO;

final class BannerRepository
{
    public function __construct(private readonly PDO $db) {}

    public function all(): array
    {
        return $this->db->query('SELECT * FROM banners ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function active(): array
    {
        return $this->db->query('SELECT title, description, button_label, button_url, image_path, image_alt FROM banners WHERE is_active=1 ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $query = $this->db->prepare('SELECT * FROM banners WHERE id=?');
        $query->execute([$id]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
