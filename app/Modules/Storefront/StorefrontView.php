<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Storefront;

final class StorefrontView
{
    public function __construct(private readonly string $viewsPath) {}
    public function e(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
    public function money(mixed $amount): string { return number_format((float)$amount,2,',',' ').' €'; }
    public function url(string $path): string { return '/'.ltrim($path,'/'); }
    public function image(?string $path): ?string
    { if(!$path)return null;$path=str_replace('\\','/',$path);if(str_starts_with($path,'/'))return $path;if(str_starts_with($path,'uploads/'))return '/'.$path;return null; }
    public function render(string $name,array $data): string
    { extract($data,EXTR_SKIP);$view=$this;$viewFile=$this->viewsPath.'/'.$name.'.php';ob_start();require $viewFile;return (string)ob_get_clean(); }
}
