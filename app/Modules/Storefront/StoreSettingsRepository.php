<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Storefront;

use PDO;

final class StoreSettingsRepository
{
    public function __construct(private readonly PDO $db) {}

    public function shipping(): array
    {
        $row=$this->db->query('SELECT free_shipping_enabled,free_shipping_threshold FROM store_settings WHERE setting_id=1')->fetch();
        $threshold=(string)($row['free_shipping_threshold']??'40.90');
        return ['freeShippingEnabled'=>(bool)($row['free_shipping_enabled']??true),'freeShippingThreshold'=>$threshold,'freeShippingThresholdCents'=>(int)round((float)$threshold*100)];
    }
}
