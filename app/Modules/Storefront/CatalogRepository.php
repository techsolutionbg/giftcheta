<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Storefront;

use PDO;

final class CatalogRepository
{
    public function __construct(private readonly PDO $db) {}

    public function homeCategories(): array
    {
        return $this->db->query("SELECT c.id,c.name,c.slug,c.image_path,COUNT(DISTINCT p.id) product_count FROM categories c LEFT JOIN product_categories pc ON pc.category_id=c.id LEFT JOIN products p ON p.id=pc.product_id AND p.status='active' AND p.deleted_at IS NULL WHERE c.is_active=1 AND c.deleted_at IS NULL GROUP BY c.id ORDER BY c.sort_order,c.name LIMIT 12")->fetchAll();
    }

    public function categories(): array
    {
        return $this->db->query('SELECT id,parent_id,name,slug,description,image_path,sort_order,seo_title,seo_description FROM categories WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_order,name,id')->fetchAll();
    }

    public function category(string $slug): ?array
    {
        $q=$this->db->prepare('SELECT * FROM categories WHERE slug=? AND is_active=1 AND deleted_at IS NULL LIMIT 1');$q->execute([$slug]);$r=$q->fetch();return $r?:null;
    }

    public function categoryProducts(int $categoryId,array $filters): array
    {
        return $this->listProducts($filters,$categoryId);
    }

    public function products(array $filters): array
    {
        return $this->listProducts($filters,null);
    }

    public function favoriteProducts(array $ids): array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn(int $id):bool=>$id>0)));
        if(!$ids)return [];
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $price=$this->publicPriceExpression();$regularPrice=$this->regularPublicPriceExpression();$cardFlags=$this->productCardFlags();
        $sql="SELECT p.id,p.sku,p.name,p.slug,p.short_description,p.price,p.sale_price,p.is_available,p.inventory_mode,p.stock_quantity,p.default_variant_id,p.created_at,{$price} public_price,{$regularPrice} regular_public_price,(SELECT i.file_path FROM product_images i WHERE i.product_id=p.id ORDER BY i.is_primary DESC,i.position,i.id LIMIT 1) image_path,(SELECT i.alt_text FROM product_images i WHERE i.product_id=p.id ORDER BY i.is_primary DESC,i.position,i.id LIMIT 1) image_alt{$cardFlags} FROM products p WHERE p.status='active' AND p.deleted_at IS NULL AND p.id IN ({$marks}) ORDER BY FIELD(p.id,{$marks})";
        $query=$this->db->prepare($sql);$query->execute([...$ids,...$ids]);
        return $this->attachVariantOptions($query->fetchAll());
    }

    public function filterFacets(?int $categoryId=null,array $categoryIds=[]): array
    {
        $scope="p.status='active' AND p.deleted_at IS NULL";$args=[];
        if($categoryIds){$marks=implode(',',array_fill(0,count($categoryIds),'?'));$scope.=" AND EXISTS(SELECT 1 FROM product_categories fpc JOIN categories fc ON fc.id=fpc.category_id WHERE fpc.product_id=p.id AND fc.is_active=1 AND fc.deleted_at IS NULL AND fpc.category_id IN ({$marks}))";array_push($args,...$categoryIds);}elseif($categoryId!==null){$scope.=' AND EXISTS(SELECT 1 FROM product_categories fpc WHERE fpc.product_id=p.id AND fpc.category_id=?)';$args[]=$categoryId;}
        $query=$this->db->prepare("SELECT v.option_values,v.color_hex FROM product_variants v JOIN products p ON p.id=v.product_id WHERE v.is_active=1 AND v.is_available=1 AND (v.inventory_mode='boolean' OR v.stock_quantity>0) AND {$scope}");$query->execute($args);
        $colors=[];$sizes=[];
        foreach($query->fetchAll() as $row){$values=json_decode((string)$row['option_values'],true);if(!is_array($values))continue;foreach($values as $key=>$value){if(!is_scalar($value)||trim((string)$value)==='')continue;$normalized=function_exists('mb_strtolower')?mb_strtolower((string)$key,'UTF-8'):strtolower((string)$key);$label=trim((string)$value);$identity=function_exists('mb_strtolower')?mb_strtolower($label,'UTF-8'):strtolower($label);if(str_contains($normalized,'цвят')||str_contains($normalized,'color'))$colors[$identity]=['label'=>$label,'hex'=>preg_match('/^#[0-9A-F]{6}$/i',(string)$row['color_hex'])?(string)$row['color_hex']:null];elseif(str_contains($normalized,'размер')||str_contains($normalized,'size'))$sizes[$identity]=$label;}}
        $price=$this->publicPriceExpression();$range=$this->db->prepare("SELECT MIN({$price}) min_price,MAX({$price}) max_price FROM products p WHERE {$scope}");$range->execute($args);$prices=$range->fetch()?:[];
        return ['colors'=>array_values($colors),'sizes'=>array_values($sizes),'min_price'=>(float)($prices['min_price']??0),'max_price'=>(float)($prices['max_price']??0)];
    }

    private function listProducts(array $filters,?int $categoryId): array
    {
        $where=["p.status='active'",'p.deleted_at IS NULL'];$args=[];
        $categoryIds=$filters['categories']??[];
        if($categoryId!==null&&!$categoryIds){$where[]='c.is_active=1';$where[]='c.deleted_at IS NULL';$where[]='c.id=?';$args[]=$categoryId;}
        $price=$this->publicPriceExpression();$regularPrice=$this->regularPublicPriceExpression();
        $cardFlags=$this->productCardFlags();
        if($filters['min_price']!==null){$where[]="{$price}>=?";$args[]=$filters['min_price'];}
        if($filters['max_price']!==null){$where[]="{$price}<=?";$args[]=$filters['max_price'];}
        if($filters['on_sale']??false)$where[]="{$price}<{$regularPrice}";
        if($filters['in_stock'])$where[]="((EXISTS(SELECT 1 FROM product_variants va WHERE va.product_id=p.id AND va.is_active=1) AND EXISTS(SELECT 1 FROM product_variants vb WHERE vb.product_id=p.id AND vb.is_active=1 AND vb.is_available=1 AND (vb.inventory_mode='boolean' OR vb.stock_quantity>0))) OR (NOT EXISTS(SELECT 1 FROM product_variants vc WHERE vc.product_id=p.id AND vc.is_active=1) AND p.is_available=1 AND (p.inventory_mode='boolean' OR p.stock_quantity>0)))";
        $variantConditions=[];
        foreach(['colors'=>['цвят','color'],'sizes'=>['размер','size']] as $filterKey=>$keyHints){$selected=$filters[$filterKey]??[];if(!$selected)continue;$parts=[];foreach($selected as $value){$path="JSON_SEARCH(vf.option_values,'one',?)";$parts[]="({$path} IS NOT NULL AND (LOWER({$path}) LIKE ? OR LOWER({$path}) LIKE ?))";$searchValue=strtr((string)$value,['\\'=>'\\\\','%'=>'\\%','_'=>'\\_']);$args[]=$searchValue;$args[]=$searchValue;$args[]='%'.$keyHints[0].'%';$args[]=$searchValue;$args[]='%'.$keyHints[1].'%';}$variantConditions[]='('.implode(' OR ',$parts).')';}
        if($variantConditions)$where[]="EXISTS(SELECT 1 FROM product_variants vf WHERE vf.product_id=p.id AND vf.is_active=1 AND vf.is_available=1 AND (vf.inventory_mode='boolean' OR vf.stock_quantity>0) AND ".implode(' AND ',$variantConditions).')';
        if($categoryIds){$marks=implode(',',array_fill(0,count($categoryIds),'?'));$where[]="EXISTS(SELECT 1 FROM product_categories fc JOIN categories fcat ON fcat.id=fc.category_id WHERE fc.product_id=p.id AND fcat.is_active=1 AND fcat.deleted_at IS NULL AND fc.category_id IN ({$marks}))";array_push($args,...$categoryIds);}
        $sort=match($filters['sort']){'price_asc'=>"public_price ASC,p.id DESC",'price_desc'=>"public_price DESC,p.id DESC",'name'=>"p.name ASC,p.id DESC",default=>'p.created_at DESC,p.id DESC'};
        $clause=' WHERE '.implode(' AND ',$where);
        $base=' FROM products p'.($categoryId!==null&&!$categoryIds?' JOIN product_categories pc ON pc.product_id=p.id JOIN categories c ON c.id=pc.category_id':'').$clause;
        $count=$this->db->prepare('SELECT COUNT(DISTINCT p.id)'.$base);$count->execute($args);$total=(int)$count->fetchColumn();
        $sql="SELECT DISTINCT p.id,p.sku,p.name,p.slug,p.short_description,p.price,p.sale_price,p.is_available,p.inventory_mode,p.stock_quantity,p.default_variant_id,p.created_at,{$price} public_price,{$regularPrice} regular_public_price,(SELECT i.file_path FROM product_images i WHERE i.product_id=p.id ORDER BY i.is_primary DESC,i.position,i.id LIMIT 1) image_path,(SELECT i.alt_text FROM product_images i WHERE i.product_id=p.id ORDER BY i.is_primary DESC,i.position,i.id LIMIT 1) image_alt{$cardFlags}{$base} ORDER BY {$sort} LIMIT {$filters['per_page']} OFFSET ".(($filters['page']-1)*$filters['per_page']);
        $q=$this->db->prepare($sql);$q->execute($args);return ['items'=>$this->attachVariantOptions($q->fetchAll()),'total'=>$total];
    }

    public function search(string $term,array $filters): array
    {
        $where=["p.status='active'",'p.deleted_at IS NULL'];$args=[];$price=$this->publicPriceExpression();$regularPrice=$this->regularPublicPriceExpression();$cardFlags=$this->productCardFlags();
        $like='%'.strtr($term,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$where[]="(p.name LIKE ? ESCAPE '!' OR p.sku LIKE ? ESCAPE '!' OR p.short_description LIKE ? ESCAPE '!')";array_push($args,$like,$like,$like);
        if($filters['min_price']!==null){$where[]="{$price}>=?";$args[]=$filters['min_price'];}if($filters['max_price']!==null){$where[]="{$price}<=?";$args[]=$filters['max_price'];}
        if($filters['in_stock'])$where[]="((EXISTS(SELECT 1 FROM product_variants va WHERE va.product_id=p.id AND va.is_active=1) AND EXISTS(SELECT 1 FROM product_variants vb WHERE vb.product_id=p.id AND vb.is_active=1 AND vb.is_available=1 AND (vb.inventory_mode='boolean' OR vb.stock_quantity>0)) ) OR (NOT EXISTS(SELECT 1 FROM product_variants vc WHERE vc.product_id=p.id AND vc.is_active=1) AND p.is_available=1 AND (p.inventory_mode='boolean' OR p.stock_quantity>0)))";
        $sort=match($filters['sort']){'price_asc'=>'public_price ASC,p.id DESC','price_desc'=>'public_price DESC,p.id DESC','name'=>'p.name ASC,p.id DESC',default=>'p.created_at DESC,p.id DESC'};$clause=' WHERE '.implode(' AND ',$where);
        $count=$this->db->prepare('SELECT COUNT(*) FROM products p'.$clause);$count->execute($args);$total=(int)$count->fetchColumn();
        $sql="SELECT p.id,p.name,p.slug,p.short_description,p.price,p.sale_price,p.is_available,p.inventory_mode,p.stock_quantity,p.default_variant_id,p.created_at,{$price} public_price,{$regularPrice} regular_public_price,(SELECT i.file_path FROM product_images i WHERE i.product_id=p.id ORDER BY i.is_primary DESC,i.position,i.id LIMIT 1) image_path,(SELECT i.alt_text FROM product_images i WHERE i.product_id=p.id ORDER BY i.is_primary DESC,i.position,i.id LIMIT 1) image_alt{$cardFlags} FROM products p{$clause} ORDER BY {$sort} LIMIT {$filters['per_page']} OFFSET ".(($filters['page']-1)*$filters['per_page']);$q=$this->db->prepare($sql);$q->execute($args);return ['items'=>$this->attachVariantOptions($q->fetchAll()),'total'=>$total];
    }

    public function latestProducts(int $limit=8): array
    { $limit=max(1,min(100,$limit));$price=$this->publicPriceExpression();$regularPrice=$this->regularPublicPriceExpression();$cardFlags=$this->productCardFlags();$items=$this->db->query("SELECT p.id,p.name,p.slug,p.short_description,p.price,p.sale_price,p.is_available,p.inventory_mode,p.stock_quantity,p.default_variant_id,{$price} public_price,{$regularPrice} regular_public_price,(SELECT i.file_path FROM product_images i WHERE i.product_id=p.id ORDER BY i.is_primary DESC,i.position,i.id LIMIT 1) image_path,(SELECT i.alt_text FROM product_images i WHERE i.product_id=p.id ORDER BY i.is_primary DESC,i.position,i.id LIMIT 1) image_alt{$cardFlags} FROM products p WHERE p.status='active' AND p.deleted_at IS NULL ORDER BY p.created_at DESC,p.id DESC LIMIT {$limit}")->fetchAll();return $this->attachVariantOptions($items); }

    public function homeBanners(): array
    { return $this->db->query('SELECT title,description,button_label,button_url,image_path,image_alt FROM banners WHERE is_active=1 ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC); }

    public function product(string $slug): ?array
    { $q=$this->db->prepare("SELECT * FROM products WHERE slug=? AND status='active' AND deleted_at IS NULL LIMIT 1");$q->execute([$slug]);$r=$q->fetch();return $r?:null; }

    public function productImages(int $id): array
    { $q=$this->db->prepare('SELECT file_path,alt_text,is_primary FROM product_images WHERE product_id=? ORDER BY is_primary DESC,position,id');$q->execute([$id]);return $q->fetchAll(); }

    public function variants(int $id): array
    { $q=$this->db->prepare('SELECT id,name,color_hex,image_path,image_thumbnail_path,option_values,sku,price,price_adjustment,inventory_mode,stock_quantity,is_available FROM product_variants WHERE product_id=? AND is_active=1 ORDER BY name,id');$q->execute([$id]);return $q->fetchAll(); }

    public function customizationFields(int $id): array
    { $q=$this->db->prepare('SELECT id,name,field_type,is_required,validation_rules,options,additional_price FROM product_customization_fields WHERE product_id=? AND is_active=1 ORDER BY position,id');$q->execute([$id]);return $q->fetchAll(); }

    public function productCategories(int $id): array
    { $q=$this->db->prepare("SELECT c.id,c.parent_id,c.name,c.slug FROM product_categories pc JOIN categories c ON c.id=pc.category_id WHERE pc.product_id=? AND c.is_active=1 AND c.deleted_at IS NULL ORDER BY c.sort_order,c.name");$q->execute([$id]);return $q->fetchAll(); }

    public function redirect(string $path): ?string
    { $q=$this->db->prepare('SELECT new_path FROM seo_url_redirects WHERE old_path=? LIMIT 1');$q->execute([$path]);$r=$q->fetchColumn();return is_string($r)?$r:null; }

    public function sitemapRows(): array
    { $categories=$this->db->query("SELECT CONCAT('/category/',slug) path,updated_at FROM categories WHERE is_active=1 AND deleted_at IS NULL")->fetchAll();$products=$this->db->query("SELECT CONCAT('/product/',slug) path,updated_at FROM products WHERE status='active' AND deleted_at IS NULL")->fetchAll();return [...$categories,...$products]; }

    private function publicPriceExpression(): string
    { return "COALESCE((SELECT MIN(COALESCE(v.price,COALESCE(p.sale_price,p.price)+v.price_adjustment)) FROM product_variants v WHERE v.product_id=p.id AND v.is_active=1 AND v.is_available=1 AND (v.inventory_mode='boolean' OR v.stock_quantity>0)),COALESCE(p.sale_price,p.price))"; }

    private function regularPublicPriceExpression(): string
    { return "COALESCE((SELECT MIN(COALESCE(v.price,p.price+v.price_adjustment)) FROM product_variants v WHERE v.product_id=p.id AND v.is_active=1 AND v.is_available=1 AND (v.inventory_mode='boolean' OR v.stock_quantity>0)),p.price)"; }

    private function productCardFlags(): string
    { return ",EXISTS(SELECT 1 FROM product_variants cv WHERE cv.product_id=p.id AND cv.is_active=1) has_active_variants,EXISTS(SELECT 1 FROM product_variants av WHERE av.product_id=p.id AND av.is_active=1 AND av.is_available=1 AND (av.inventory_mode='boolean' OR av.stock_quantity>0)) has_available_variant,EXISTS(SELECT 1 FROM product_customization_fields cf WHERE cf.product_id=p.id AND cf.is_active=1 AND cf.is_required=1) has_required_customizations"; }

    private function attachVariantOptions(array $items): array
    {
        $ids=array_values(array_unique(array_map(static fn(array $item):int=>(int)$item['id'],$items)));
        if(!$ids)return $items;
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $q=$this->db->prepare("SELECT id,product_id,option_values,color_hex,is_available,inventory_mode,stock_quantity,price,price_adjustment FROM product_variants WHERE is_active=1 AND product_id IN ({$marks}) ORDER BY product_id,id");$q->execute($ids);
        $options=[];
        $productById=[];foreach($items as $item)$productById[(int)$item['id']]=$item;
        foreach($ids as $id)$options[$id]=['colors'=>[],'sizes'=>[],'combinations'=>[]];
        foreach($q->fetchAll() as $variant){
            $values=json_decode((string)$variant['option_values'],true);if(!is_array($values))continue;
            $colorValue=null;$sizeValue=null;$otherValues=[];
            foreach($values as $key=>$value){
                if(!is_scalar($value))continue;$label=trim((string)$value);if($label==='')continue;
                $normalized=function_exists('mb_strtolower')?mb_strtolower((string)$key,'UTF-8'):strtolower((string)$key);$target=null;
                if(str_contains($normalized,'цвят')||str_contains($normalized,'color'))$target='colors';
                elseif(str_contains($normalized,'размер')||str_contains($normalized,'size'))$target='sizes';
                if($target===null){$otherValues[(string)$key]=$label;continue;}
                if($target==='colors')$colorValue=$label;else $sizeValue=$label;
                $available=(bool)$variant['is_available']&&(($variant['inventory_mode']??'boolean')!=='tracked'||(int)$variant['stock_quantity']>0);
                if(!$available)continue;
                $identity=function_exists('mb_strtolower')?mb_strtolower($label,'UTF-8'):strtolower($label);
                $options[(int)$variant['product_id']][$target][$identity]=['label'=>$label,'hex'=>$target==='colors'&&preg_match('/^#[0-9A-F]{6}$/i',(string)$variant['color_hex'])?(string)$variant['color_hex']:null];
            }
            $available=(bool)$variant['is_available']&&(($variant['inventory_mode']??'boolean')!=='tracked'||(int)$variant['stock_quantity']>0);
            if($available){$product=$productById[(int)$variant['product_id']]??[];$basePrice=(float)($product['price']??0);$salePrice=(float)($product['sale_price']??0);$options[(int)$variant['product_id']]['combinations'][]=['id'=>(int)$variant['id'],'color'=>$colorValue,'size'=>$sizeValue,'other'=>$otherValues,'price'=>$variant['price']!==null?(float)$variant['price']:($salePrice>0?$salePrice:$basePrice)+(float)$variant['price_adjustment'],'regular_price'=>$variant['price']!==null?(float)$variant['price']:$basePrice+(float)$variant['price_adjustment']];}
        }
        foreach($items as &$item){$productOptions=$options[(int)$item['id']]??['colors'=>[],'sizes'=>[],'combinations'=>[]];$item['variant_colors']=array_values($productOptions['colors']);$item['variant_sizes']=array_values($productOptions['sizes']);$item['variant_combinations']=$productOptions['combinations'];$defaultId=(int)($item['default_variant_id']??0);$defaultCombination=null;foreach($item['variant_combinations'] as $combination)if($defaultId>0&&$combination['id']===$defaultId){$defaultCombination=$combination;break;}if(!$defaultCombination)$defaultCombination=$item['variant_combinations'][0]??null;$item['default_variant_id']=$defaultCombination['id']??null;$item['default_variant_color']=$defaultCombination['color']??null;$item['default_variant_size']=$defaultCombination['size']??null;$item['default_variant_other']=$defaultCombination['other']??[];$item['can_direct_variant_add']=!empty($item['variant_combinations']);}unset($item);
        return $items;
    }
}
