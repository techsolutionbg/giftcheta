<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Commerce;

use PDO;

final class ProductCustomizationValidator
{
    /** Returns normalized fields or a user-safe error; imageAllowed validates ownership and existence. */
    public static function validate(PDO $db,int $productId,array $input,callable $imageAllowed,?array $fields=null): array
    {
        if($fields===null){$q=$db->prepare('SELECT id,name,field_type,is_required,validation_rules,options,additional_price FROM product_customization_fields WHERE product_id=? AND is_active=1 ORDER BY position,id');$q->execute([$productId]);$fields=$q->fetchAll(PDO::FETCH_ASSOC);}
        $byId=[];foreach($fields as $field)$byId[(int)$field['id']]=$field;$values=[];
        foreach($input as $row){if(!is_array($row))return self::invalid('Невалидна персонализация.');$id=filter_var($row['field_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($id===false||!isset($byId[(int)$id])||isset($values[(int)$id]))return self::invalid('Персонализацията не съответства на продукта.');$values[(int)$id]=$row;}
        $result=[];
        foreach($fields as $field){$id=(int)$field['id'];$row=$values[$id]??null;$type=(string)$field['field_type'];$value=null;$file=null;$price=$field['additional_price']??'0';
            if($type==='checkbox'){$value=filter_var($row['value']??false,FILTER_VALIDATE_BOOLEAN)?'1':'';}
            elseif($type==='image'){$file=is_string($row['file_reference']??null)?trim($row['file_reference']):null;if($file!==null&&($file===''||!preg_match('/^[a-f0-9]{64}\.(?:jpg|png|webp)$/D',$file)||!$imageAllowed($file,$field)))return self::invalid('Невалидно изображение за персонализацията „'.$field['name'].'“.');}
            else {$value=is_string($row['value']??null)?trim($row['value']):'';$rules=json_decode((string)($field['validation_rules']??''),true)?:[];$max=(int)($rules['max_length']??$rules['maxlength']??$rules['max']??10000);$min=(int)($rules['min_length']??$rules['minlength']??0);if(mb_strlen($value)>max(1,min(10000,$max)))return self::invalid('Текстът за „'.$field['name'].'“ е прекалено дълъг.');if($value!==''&&mb_strlen($value)<$min)return self::invalid('Текстът за „'.$field['name'].'“ е прекалено кратък.');
                if(in_array($type,['select','radio'],true)&&$value!==''){$option=self::findOption(json_decode((string)$field['options'],true)?:[],$value);if($option===null)return self::invalid('Изберете валидна стойност за „'.$field['name'].'“.');if(isset($option['additional_price']))$price=$option['additional_price'];}
            }
            $present=$type==='image'?$file!==null&&$file!=='':($type==='checkbox'?$value==='1':$value!=='');if((bool)$field['is_required']&&!$present)return self::invalid('Попълнете задължителното поле „'.$field['name'].'“.');if(!$present)continue;
            $priceCents=ProductPricingService::moneyCents($price);if($priceCents===null)return ['ok'=>false,'message'=>'Цената за персонализацията не е валидна.','code'=>'invalid_price','status'=>409];
            $result[]=['field_id'=>$id,'identifier'=>(string)$id,'label'=>(string)$field['name'],'type'=>$type,'value'=>$value,'file_reference'=>$file,'price_cents'=>$priceCents];
        }
        return ['ok'=>true,'customizations'=>$result];
    }

    public static function optionValue(mixed $option): string
    { return is_array($option)?(string)($option['value']??$option['label']??''):(string)$option; }
    public static function optionLabel(mixed $option): string
    { return is_array($option)?(string)($option['label']??$option['value']??''):(string)$option; }
    private static function findOption(array $options,string $value): ?array
    { foreach($options as $option)if(self::optionValue($option)===$value)return is_array($option)?$option:['value'=>(string)$option];return null; }
    private static function invalid(string $message): array { return ['ok'=>false,'message'=>$message,'code'=>'validation_error','status'=>422]; }
}
