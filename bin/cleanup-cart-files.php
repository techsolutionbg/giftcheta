<?php
declare(strict_types=1);
use Giftcheta\Core\Config;
use Giftcheta\Core\Database;
require dirname(__DIR__).'/vendor/autoload.php';
Config::loadEnvironment(dirname(__DIR__).'/.env');$config=Config::fromDirectory(dirname(__DIR__).'/config');$db=Database::connect($config);$directory=dirname(__DIR__).'/storage/private/cart-customizations';$references=[];
$expired=$db->query("SELECT u.file_reference FROM cart_uploads u JOIN carts c ON c.id=u.cart_id WHERE c.status IN ('active','abandoned') AND c.expires_at<NOW()")->fetchAll(PDO::FETCH_COLUMN);$references=[...$references,...$expired];$staged=$db->query("SELECT file_reference FROM cart_uploads WHERE cart_item_id IS NULL AND created_at<DATE_SUB(NOW(),INTERVAL 2 DAY)")->fetchAll(PDO::FETCH_COLUMN);$references=[...$references,...$staged];
$db->exec("DELETE FROM carts WHERE status IN ('active','abandoned') AND expires_at<NOW()");$db->exec("DELETE FROM cart_uploads WHERE cart_item_id IS NULL AND created_at<DATE_SUB(NOW(),INTERVAL 2 DAY)");
foreach(array_unique($references) as $reference)if(is_string($reference)&&preg_match('/^[a-f0-9]{64}\.(?:jpg|png|webp)$/D',$reference)){ $path=$directory.DIRECTORY_SEPARATOR.$reference;if(is_file($path))@unlink($path); }
$swept=0;if(is_dir($directory))foreach(glob($directory.DIRECTORY_SEPARATOR.'*')?:[] as $path){if(!is_file($path)||!preg_match('/^[a-f0-9]{64}\.(?:jpg|png|webp)$/D',basename($path))||filemtime($path)>time()-172800)continue;$q=$db->prepare('SELECT 1 FROM cart_uploads WHERE file_reference=? LIMIT 1');$q->execute([basename($path)]);if(!$q->fetchColumn()&&@unlink($path))$swept++;}
fwrite(STDOUT,'Removed '.count(array_unique($references)).' expired cart upload records and '.$swept." orphan files.\n");
