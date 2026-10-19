<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Storefront;

use Giftcheta\Core\Config;
use Giftcheta\Http\Request;
use Giftcheta\Http\Response;
use Giftcheta\Modules\Cart\CartRepository;
use Giftcheta\Modules\Cart\CartService;
use Giftcheta\Modules\Cart\CartFileStorage;
use Giftcheta\Modules\Cart\CartSessionManager;
use Giftcheta\Modules\Commerce\ProductPricingService;
use PDO;

final class StorefrontController
{
    private CatalogRepository $catalog;
    private StorefrontView $view;
    private CartService $cart;
    private CartSessionManager $cartSessions;
    private string $baseUrl;

    public function __construct(private readonly PDO $db,private readonly string $projectRoot,Config $config)
    { $this->catalog=new CatalogRepository($db);$this->view=new StorefrontView($projectRoot.'/app/Modules/Storefront/Views');$this->baseUrl=rtrim((string)$config->get('app.url',''),'/');$repo=new CartRepository($db);$files=new CartFileStorage($db,$projectRoot);$this->cart=new CartService($db,$repo,$files);$this->cartSessions=new CartSessionManager(); }

    public function home(Request $request): Response
    { $categories=$this->catalog->homeCategories();$products=$this->catalog->latestProducts();$banners=$this->catalog->homeBanners();return $this->html('home',['title'=>'Подаръци за всеки повод','description'=>'Разгледайте идеи за подаръци за рожден ден, имен ден и специални поводи.','canonical'=>$this->absolute('/'),'categories'=>$categories,'products'=>$products,'banners'=>$banners,'schema'=>$this->webSiteSchema()]); }

    public function products(Request $request): Response
    { $products=$this->catalog->latestProducts(100);return $this->html('products',['title'=>'Всички продукти','description'=>'Разгледайте всички подаръци в каталога на Giftcheta.','canonical'=>$this->absolute('/products'),'items'=>$products,'total'=>count($products),'schema'=>null]); }

    public function category(Request $request,array $params): Response
    {
        $category=$this->catalog->category((string)$params['slug']);if(!$category)return $this->notFound();$filters=$this->filters($request);$result=$this->catalog->categoryProducts((int)$category['id'],$filters);$categories=$this->catalog->categories();$children=array_values(array_filter($categories,static fn(array $c):bool=>(int)$c['parent_id']===(int)$category['id']));$breadcrumbs=$this->breadcrumbs($category,$categories);$canonical=$this->absolute('/category/'.$category['slug'].($filters['page']>1?'?page='.$filters['page']:''));
        $description=(string)($category['seo_description']?:$this->plainDescription($category['description']));$schema=$this->breadcrumbSchema($breadcrumbs,$canonical);
        return $this->html('category',['title'=>$category['seo_title']?:$category['name'],'description'=>$description,'canonical'=>$canonical,'category'=>$category,'breadcrumbs'=>$breadcrumbs,'children'=>$children,'items'=>$result['items'],'total'=>$result['total'],'filters'=>$filters,'pages'=>max(1,(int)ceil($result['total']/$filters['per_page'])),'schema'=>$schema,'noindex'=>$this->hasFilters($request)]);
    }

    public function product(Request $request,array $params): Response
    {
        $product=$this->catalog->product((string)$params['slug']);if(!$product)return $this->notFound();$product['images']=$this->catalog->productImages((int)$product['id']);$product['variants']=$this->catalog->variants((int)$product['id']);$product['fields']=$this->catalog->customizationFields((int)$product['id']);$product['categories']=$this->catalog->productCategories((int)$product['id']);
        $requestedVariantId=filter_var($request->query['variant']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:null;$selectedId=$requestedVariantId;$selected=null;$product['base_price_cents']=ProductPricingService::moneyCents((string)($product['sale_price']??$product['price']))??0;
        foreach($product['variants'] as &$variant){$variant['option_values']=json_decode((string)$variant['option_values'],true)?:[];$variant['effective_price']=$variant['price']!==null?(string)$variant['price']:(string)((float)($product['sale_price']??$product['price'])+(float)$variant['price_adjustment']);$variant['effective_price_cents']=ProductPricingService::moneyCents($variant['effective_price'])??0;$variant['available']=ProductPricingService::available($variant);}unset($variant);
        foreach($product['fields'] as &$field){$field['additional_price_cents']=ProductPricingService::moneyCents((string)$field['additional_price'])??0;$field['options_list']=json_decode((string)$field['options'],true)?:[];}unset($field);
        $defaultVariantId=filter_var($product['default_variant_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:null;$variantForSelection=static function(?int $id)use($product):?array{if(!$id)return null;foreach($product['variants'] as $variant)if((int)$variant['id']===$id)return $variant;return null;};
        if($selectedId!==null)$selected=$variantForSelection($selectedId);if(!$selected){$selectedId=$defaultVariantId;$selected=$variantForSelection($selectedId);}if(!$selected)$selectedId=null;
        $base=(string)($product['sale_price']??$product['price']);$effectivePrice=$selected['effective_price']??$base;$baseAvailable=(bool)$product['is_available']&&($product['inventory_mode']!=='tracked'||(int)$product['stock_quantity']>0);$available=$selected?$selected['available']:($product['variants']?(bool)array_filter($product['variants'],static fn(array $v):bool=>$v['available']):$baseAvailable);$breadcrumbs=$this->productBreadcrumbs($product['categories']);$canonical=$this->absolute('/product/'.$product['slug']);$desc=(string)($product['seo_description']?:$product['short_description']?:$this->plainDescription($product['description']));$schema=$this->productSchema($product,$canonical,$breadcrumbs);
        return $this->html('product',['title'=>$product['seo_title']?:$product['name'],'description'=>$desc,'canonical'=>$canonical,'product'=>$product,'selected'=>$selected,'selectedId'=>$selectedId,'effectivePrice'=>$effectivePrice,'available'=>$available,'breadcrumbs'=>$breadcrumbs,'schema'=>$schema]);
    }

    public function search(Request $request): Response
    {
        $term=trim((string)($request->query['q']??''));$term=mb_substr($term,0,100);$filters=$this->filters($request);$result=$term===''?['items'=>[],'total'=>0]:$this->catalog->search($term,$filters);$canonical=$this->absolute('/search');
        return $this->html('search',['title'=>'Търсене','description'=>'Търсене на подаръци в магазина.','canonical'=>$canonical,'term'=>$term,'items'=>$result['items'],'total'=>$result['total'],'filters'=>$filters,'pages'=>max(1,(int)ceil($result['total']/$filters['per_page'])),'schema'=>null,'noindex'=>true]);
    }

    public function sitemap(Request $request): Response
    { $rows=$this->catalog->sitemapRows();$xml='<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>'.$this->xml($this->absolute('/')).'</loc></url>';foreach($rows as $row)$xml.='<url><loc>'.$this->xml($this->absolute($row['path'])).'</loc><lastmod>'.gmdate('Y-m-d',strtotime((string)$row['updated_at'])).'</lastmod></url>';$xml.='</urlset>';return new Response($xml,200,['Content-Type'=>'application/xml; charset=utf-8','X-Content-Type-Options'=>'nosniff']); }

    public function robots(Request $request): Response
    { $body="User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /api/\nDisallow: /account/\nSitemap: ".$this->absolute('/sitemap.xml')."\n";return new Response($body,200,['Content-Type'=>'text/plain; charset=utf-8']); }

    public function redirect(Request $request): ?Response
    { $path=$request->path;if(!str_starts_with($path,'/category/')&&!str_starts_with($path,'/product/'))return null;$target=$this->catalog->redirect($path);if(!$target)return null;if(!preg_match('#^/(category|product)/[a-zA-Z0-9_-]+$#',$target))return null;return new Response('',301,['Location'=>$target,'Cache-Control'=>'public, max-age=3600']); }

    private function html(string $name,array $data,int $status=200): Response
    { $context=$this->cartSessions->start();$cartSummary=$this->cart->summary($context['key']);$data['baseUrl']=$this->baseUrl;$data['view']=$this->view;$data['cartCount']=$cartSummary['count'];$data['cartSubtotalCents']=$cartSummary['subtotal_cents'];$data['headerCategories']=$this->catalog->categories();$data+= (new StoreSettingsRepository($this->db))->shipping();$data['csrfToken']=$context['csrf'];$data['cartFlash']=$this->cartSessions->consumeFlash();$body=$this->view->render($name,$data);return new Response($body,$status,['Content-Type'=>'text/html; charset=utf-8','X-Content-Type-Options'=>'nosniff','Referrer-Policy'=>'strict-origin-when-cross-origin','Cache-Control'=>'private, no-store, max-age=0']); }
    private function notFound(): Response { return $this->html('404',['title'=>'Страницата не е намерена','description'=>'Търсената страница не съществува.','canonical'=>null,'schema'=>null,'noindex'=>true],404); }
    private function absolute(string $path): string { return $this->baseUrl.$path; }
    private function xml(string $value): string { return htmlspecialchars($value,ENT_XML1|ENT_QUOTES,'UTF-8'); }
    private function plainDescription(?string $text): string { $plain=trim(preg_replace('/\s+/u',' ',strip_tags((string)$text))??'');return mb_substr($plain,0,320); }
    private function filters(Request $request): array
    { $query=$request->query;$min=$this->validPrice($query['min_price']??null);$max=$this->validPrice($query['max_price']??null);if($min!==null&&$max!==null&&$min>$max)[$min,$max]=[$max,$min];$sort=(string)($query['sort']??'latest');if(!in_array($sort,['latest','price_asc','price_desc','name'],true))$sort='latest';return ['min_price'=>$min,'max_price'=>$max,'in_stock'=>in_array((string)($query['in_stock']??''),['1','true','on'],true),'sort'=>$sort,'page'=>max(1,min(10000,(int)($query['page']??1))),'per_page'=>24]; }
    private function validPrice(mixed $raw): ?string { if(!is_string($raw)&&!is_numeric($raw))return null;$s=trim((string)$raw);if(!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D',$s))return null;return number_format((float)$s,2,'.',''); }
    private function hasFilters(Request $request): bool { foreach(['min_price','max_price','in_stock','sort','page'] as $key)if(isset($request->query[$key])&&$request->query[$key]!==''&& !($key==='page'&&(int)$request->query[$key]===1))return true;return false; }
    private function breadcrumbs(array $category,array $categories): array { $map=[];foreach($categories as $item)$map[(int)$item['id']]=$item;$trail=[];$current=$category;$seen=[];while($current){$id=(int)$current['id'];if(isset($seen[$id]))break;$seen[$id]=true;array_unshift($trail,$current);$current=isset($current['parent_id'])?$map[(int)$current['parent_id']]??null:null;}return $trail; }
    private function productBreadcrumbs(array $categories): array { return count($categories)?[$categories[0]]:[]; }
    private function breadcrumbSchema(array $crumbs,string $canonical): array { $items=[['@type'=>'ListItem','position'=>1,'name'=>'Начало','item'=>$this->absolute('/')]];foreach($crumbs as $i=>$crumb)$items[]=['@type'=>'ListItem','position'=>$i+2,'name'=>$crumb['name'],'item'=>$this->absolute('/category/'.$crumb['slug'])];return ['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>$items]; }
    private function productSchema(array $product,string $canonical,array $breadcrumbs): array
    { $variants=$product['variants'];$available=array_values(array_filter($variants,static fn(array $v):bool=>$v['available']));$base=(string)($product['sale_price']??$product['price']);$schema=['@context'=>'https://schema.org','@type'=>'Product','name'=>$product['name'],'description'=>$product['seo_description']?:$product['short_description']?:$this->plainDescription($product['description']),'url'=>$canonical];if($product['sku'])$schema['sku']=$product['sku'];$images=[];foreach($product['images'] as $image){$path=$this->view->image($image['file_path']);if($path!==null)$images[]=$this->absolute($path);}if($images)$schema['image']=$images;if(count($available)>1){$prices=array_map(static fn(array $v):float=>(float)$v['effective_price'],$available);$schema['offers']=['@type'=>'AggregateOffer','priceCurrency'=>'EUR','lowPrice'=>number_format(min($prices),2,'.',''),'highPrice'=>number_format(max($prices),2,'.',''),'offerCount'=>count($available),'availability'=>'https://schema.org/InStock','url'=>$canonical];}else{$variant=$available[0]??null;$price=$variant['effective_price']??$base;$inStock=$variants?(bool)$available:(bool)$product['is_available']&&($product['inventory_mode']!=='tracked'||(int)$product['stock_quantity']>0);$schema['offers']=['@type'=>'Offer','priceCurrency'=>'EUR','price'=>number_format((float)$price,2,'.',''),'availability'=>'https://schema.org/'.($inStock?'InStock':'OutOfStock'),'url'=>$canonical];}return [$schema,$this->breadcrumbSchema($breadcrumbs,$canonical)]; }
    private function webSiteSchema(): array { return ['@context'=>'https://schema.org','@type'=>'WebSite','name'=>'Giftcheta','url'=>$this->absolute('/'),'potentialAction'=>['@type'=>'SearchAction','target'=>$this->absolute('/search?q={search_term_string}'),'query-input'=>'required name=search_term_string']]; }
}
