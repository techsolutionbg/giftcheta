<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Cart;

use Giftcheta\Http\Request;

final class CartSessionManager
{
    public function start(): array
    {
        if(session_status()!==PHP_SESSION_ACTIVE){session_name('giftcheta_cart');ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');ini_set('session.cookie_httponly','1');$secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off');session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);session_start();}
        if(empty($_SESSION['cart_session_initialized'])){session_regenerate_id(true);$_SESSION['cart_session_initialized']=true;}
        $_SESSION['cart_key']??=bin2hex(random_bytes(32));$_SESSION['cart_csrf']??=bin2hex(random_bytes(32));return ['key'=>hash('sha256',(string)$_SESSION['cart_key']),'csrf'=>(string)$_SESSION['cart_csrf']];
    }
    public function validCsrf(Request $request): bool
    { $provided=(string)($request->form['_csrf']??'');foreach($request->headers as $key=>$value)if(strtolower((string)$key)==='x-csrf-token')$provided=(string)$value;return isset($_SESSION['cart_csrf'])&&$provided!==''&&hash_equals((string)$_SESSION['cart_csrf'],$provided); }
    public function setFlash(string $message,string $kind='success'): void { $_SESSION['cart_flash']=['message'=>$message,'kind'=>$kind]; }
    public function consumeFlash(): ?array { $flash=$_SESSION['cart_flash']??null;unset($_SESSION['cart_flash']);return is_array($flash)?$flash:null; }
}
