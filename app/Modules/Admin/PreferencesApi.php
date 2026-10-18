<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Admin;

use Giftcheta\Http\Request;
use Giftcheta\Http\Response;
use PDO;

final class PreferencesApi
{
    private const DEFAULT_SIDEBAR_WIDTH = 252;
    private const MIN_SIDEBAR_WIDTH = 220;
    private const MAX_SIDEBAR_WIDTH = 360;

    public function __construct(private readonly PDO $db, private readonly AuthApi $auth) {}

    public function show(Request $request): Response
    {
        if ($error = $this->auth->guard($request, null, false)) return $error;
        $query = $this->db->prepare('SELECT preference_key, preference_value FROM admin_preferences WHERE admin_id = ? AND preference_key IN (?, ?)');
        $query->execute([$_SESSION['admin_id'], 'sidebar_width', 'sidebar_open']);
        $preferences = $query->fetchAll(PDO::FETCH_KEY_PAIR);
        $width = isset($preferences['sidebar_width']) ? (int) $preferences['sidebar_width'] : self::DEFAULT_SIDEBAR_WIDTH;
        $open = !array_key_exists('sidebar_open', $preferences) || $preferences['sidebar_open'] === '1';
        return Response::json(['data' => ['sidebarWidth' => $this->clampWidth($width), 'sidebarOpen' => $open]]);
    }

    public function update(Request $request): Response
    {
        if ($error = $this->auth->guard($request, null, true)) return $error;
        $input = $request->json();
        $width = array_key_exists('sidebarWidth', $input) ? filter_var($input['sidebarWidth'], FILTER_VALIDATE_INT) : null;
        $open = array_key_exists('sidebarOpen', $input) ? filter_var($input['sidebarOpen'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
        if ($width === false || ($width !== null && ($width < self::MIN_SIDEBAR_WIDTH || $width > self::MAX_SIDEBAR_WIDTH)) || (array_key_exists('sidebarOpen', $input) && $open === null) || ($width === null && $open === null)) {
            return Response::json(['error' => ['code' => 'validation_error', 'message' => 'Ширината трябва да бъде между 220 и 360 пиксела.']], 422);
        }
        $statement = $this->db->prepare('INSERT INTO admin_preferences (admin_id, preference_key, preference_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE preference_value = VALUES(preference_value)');
        if ($width !== null) $statement->execute([$_SESSION['admin_id'], 'sidebar_width', (string) $width]);
        if ($open !== null) $statement->execute([$_SESSION['admin_id'], 'sidebar_open', $open ? '1' : '0']);
        return Response::json(['data' => array_filter(['sidebarWidth' => $width, 'sidebarOpen' => $open], static fn ($value) => $value !== null)]);
    }

    private function clampWidth(int $width): int
    {
        return max(self::MIN_SIDEBAR_WIDTH, min(self::MAX_SIDEBAR_WIDTH, $width));
    }
}
