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
        $store = $this->db->query('SELECT free_shipping_enabled, free_shipping_threshold FROM store_settings WHERE setting_id = 1')->fetch(PDO::FETCH_ASSOC);
        $issuer=$this->db->query('SELECT legal_name,company_id,vat_number,address,phone,email FROM invoice_issuer_settings WHERE setting_id=1')->fetch(PDO::FETCH_ASSOC)?:[];
        return Response::json(['data' => ['sidebarWidth' => $this->clampWidth($width), 'sidebarOpen' => $open, 'freeShippingEnabled' => (bool) $store['free_shipping_enabled'], 'freeShippingThreshold' => (string) $store['free_shipping_threshold'], 'invoiceIssuer'=>$issuer]]);
    }

    public function update(Request $request): Response
    {
        if ($error = $this->auth->guard($request, null, true)) return $error;
        $input = $request->json();
        $width = array_key_exists('sidebarWidth', $input) ? filter_var($input['sidebarWidth'], FILTER_VALIDATE_INT) : null;
        $open = array_key_exists('sidebarOpen', $input) ? filter_var($input['sidebarOpen'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
        $shippingEnabled = array_key_exists('freeShippingEnabled', $input) ? filter_var($input['freeShippingEnabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
        $threshold = null;
        $issuerFields=['legal_name'=>'legalName','company_id'=>'companyId','vat_number'=>'vatNumber','address'=>'address','phone'=>'phone','email'=>'email'];$issuer=[];
        foreach($issuerFields as $column=>$key)if(array_key_exists($key,$input)){if(!is_scalar($input[$key])||mb_strlen(trim((string)$input[$key]))>($column==='address'?500:254))return Response::json(['error'=>['code'=>'validation_error','message'=>'Проверете данните на издателя.']],422);$value=trim((string)$input[$key]);if($column==='email'&&$value!==''&&!filter_var($value,FILTER_VALIDATE_EMAIL))return Response::json(['error'=>['code'=>'validation_error','message'=>'Имейлът на издателя е невалиден.']],422);$issuer[$column]=$value;}
        if (array_key_exists('freeShippingThreshold', $input)) {
            $rawThreshold = is_scalar($input['freeShippingThreshold']) ? trim((string) $input['freeShippingThreshold']) : '';
            if (preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D', $rawThreshold) !== 1 || (float) $rawThreshold < 0.01 || (float) $rawThreshold > 1000000) {
                return Response::json(['error' => ['code' => 'validation_error', 'message' => 'Сумата за безплатна доставка трябва да бъде между 0,01 и 1 000 000 евро.']], 422);
            }
            $threshold = number_format((float) $rawThreshold, 2, '.', '');
        }
        if ($width === false || ($width !== null && ($width < self::MIN_SIDEBAR_WIDTH || $width > self::MAX_SIDEBAR_WIDTH)) || (array_key_exists('sidebarOpen', $input) && $open === null) || (array_key_exists('freeShippingEnabled', $input) && $shippingEnabled === null) || ($width === null && $open === null && $shippingEnabled === null && $threshold === null && !$issuer)) {
            return Response::json(['error' => ['code' => 'validation_error', 'message' => 'Проверете стойностите на настройките.']], 422);
        }
        $statement = $this->db->prepare('INSERT INTO admin_preferences (admin_id, preference_key, preference_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE preference_value = VALUES(preference_value)');
        if ($width !== null) $statement->execute([$_SESSION['admin_id'], 'sidebar_width', (string) $width]);
        if ($open !== null) $statement->execute([$_SESSION['admin_id'], 'sidebar_open', $open ? '1' : '0']);
        if ($shippingEnabled !== null || $threshold !== null) {
            $update = $this->db->prepare('UPDATE store_settings SET free_shipping_enabled = COALESCE(?, free_shipping_enabled), free_shipping_threshold = COALESCE(?, free_shipping_threshold) WHERE setting_id = 1');
            $update->execute([$shippingEnabled === null ? null : ($shippingEnabled ? 1 : 0), $threshold]);
        }
        if($issuer){$sql='UPDATE invoice_issuer_settings SET '.implode(',',array_map(static fn($column)=>$column.'=?',array_keys($issuer))).' WHERE setting_id=1';$this->db->prepare($sql)->execute(array_values($issuer));}
        return Response::json(['data' => array_filter(['sidebarWidth' => $width, 'sidebarOpen' => $open, 'freeShippingEnabled' => $shippingEnabled, 'freeShippingThreshold' => $threshold], static fn ($value) => $value !== null)]);
    }

    private function clampWidth(int $width): int
    {
        return max(self::MIN_SIDEBAR_WIDTH, min(self::MAX_SIDEBAR_WIDTH, $width));
    }
}
