<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Commerce;

use PDO;

final class ProductPricingService
{
    public static function unitPrice(array $product,?array $variant): ?int
    {
        $base=self::moneyCents($product['sale_price']!==null?$product['sale_price']:$product['price']);if($base===null)return null;
        if($variant===null)return $base;if($variant['price']!==null)return self::moneyCents($variant['price']);$adjustment=self::moneyCents($variant['price_adjustment']??'0',true);return $adjustment===null?null:$base+$adjustment;
    }

    public static function moneyCents(mixed $value,bool $signed=false): ?int
    { if(!is_string($value)&&!is_int($value))return null;$value=trim((string)$value);$pattern=$signed?'/^-?(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/D':'/^(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/D';if(!preg_match($pattern,$value))return null;$negative=str_starts_with($value,'-');if($negative)$value=substr($value,1);[$whole,$fraction]=array_pad(explode('.',$value,2),2,'');$cents=((int)$whole*100)+(int)str_pad($fraction,2,'0');return $negative?-$cents:$cents; }

    public static function formatCents(int $value): string
    { return ($value<0?'-':'').intdiv(abs($value),100).'.'.str_pad((string)(abs($value)%100),2,'0',STR_PAD_LEFT); }

    public static function available(array $record): bool
    { return (bool)($record['is_available']??false)&&(($record['inventory_mode']??'boolean')!=='tracked'||(int)($record['stock_quantity']??0)>0); }

    public static function availableQuantity(array $record): ?int
    { return ($record['inventory_mode']??'boolean')==='tracked'?max(0,(int)($record['stock_quantity']??0)):null; }
}
