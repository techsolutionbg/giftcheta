<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Cart;

use PDO;

final class CartFileStorage
{
    private const MAX_BYTES=10_485_760;
    private const MIME_EXTENSIONS=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    public function __construct(private readonly PDO $db,private readonly string $projectRoot) {}

    public function stage(int $cartId,int $productId,int $fieldId,array $file): array
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name']))return ['ok'=>false,'message'=>'Изберете изображение за персонализацията.','status'=>422];
        $path=(string)$file['tmp_name'];$size=filesize($path);if(!is_int($size)||$size<1||$size>self::MAX_BYTES)return ['ok'=>false,'message'=>'Изображението трябва да е до 10 MB.','status'=>422];
        $finfo=new \finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($path);$dimensions=@getimagesize($path);if(!isset(self::MIME_EXTENSIONS[$mime])||!$dimensions||($dimensions['mime']??null)!==$mime||$dimensions[0]<1||$dimensions[1]<1||$dimensions[0]>6000||$dimensions[1]>6000)return ['ok'=>false,'message'=>'Поддържат се валидни JPEG, PNG или WebP изображения до 6000×6000 пиксела.','status'=>422];
        $rate=$this->db->prepare('SELECT COUNT(*) FROM cart_uploads WHERE cart_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)');$rate->execute([$cartId]);if((int)$rate->fetchColumn()>=12)return ['ok'=>false,'message'=>'Достигнат е временният лимит за качване. Опитайте отново след няколко минути.','status'=>429];
        $directory=$this->directory();if(!is_dir($directory)&&!mkdir($directory,0750,true)&&!is_dir($directory))return ['ok'=>false,'message'=>'Частното хранилище за изображения не е достъпно.','status'=>503];
        $reference=bin2hex(random_bytes(32)).'.'.self::MIME_EXTENSIONS[$mime];$target=$directory.DIRECTORY_SEPARATOR.$reference;if(!move_uploaded_file($path,$target))return ['ok'=>false,'message'=>'Изображението не можа да бъде запазено.','status'=>500];chmod($target,0640);$hash=hash_file('sha256',$target);
        try{$q=$this->db->prepare('INSERT INTO cart_uploads (cart_id,product_id,customization_field_id,file_reference,content_hash,mime_type,file_size) VALUES (?,?,?,?,?,?,?)');$q->execute([$cartId,$productId,$fieldId,$reference,$hash,$mime,$size]);}
        catch(\Throwable $error){@unlink($target);throw $error;}
        return ['ok'=>true,'file_reference'=>$reference,'content_hash'=>$hash,'file_size'=>$size,'mime_type'=>$mime];
    }

    public function path(string $reference): ?string
    { if(!preg_match('/^[a-f0-9]{64}\.(?:jpg|png|webp)$/D',$reference))return null;$path=$this->directory().DIRECTORY_SEPARATOR.$reference;return is_file($path)?$path:null; }
    public function delete(array $references): void { foreach($references as $reference)if(is_string($reference)&&preg_match('/^[a-f0-9]{64}\.(?:jpg|png|webp)$/D',$reference)){$path=$this->directory().DIRECTORY_SEPARATOR.$reference;if(is_file($path))@unlink($path);} }
    public function directory(): string { return rtrim($this->projectRoot,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'cart-customizations'; }
}
