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
        $where=["p.status='active'",'p.deleted_at IS NULL','c.is_active=1','c.deleted_at IS NULL','c.id=?'];$args=[$categoryId];
        $price=$this->publicPriceExpression();
        if($filters['min_price']!==null){$where[]="{$price}>=?";$args[]=$filters['min_price'];}
        if($filters['max_price']!==null){$where[]="{$price}<=?";$args[]=$filters['max_price'];}
        if($filters['in_stock'])$where[]="((EXISTS(SELECT 1 FROM product_variants va WHERE va.product_id=p.id AND va.is_active=1) AND EXISTS(SELECT 1 FROM product_variants vb WHERE vb.product_id=p.id AND vb.is_active=1 AND vb.is_available=1 AND (vb.inventory_mode='boolean' OR vb.stock_quantity>0)) AND c.id IS NOT NULL) OR (NOT EXISTS(SELECT 1 FROM product_variants vc WHERE vc.product_id=p.id AND vc.is_active=1) AND p.is_available=1 AND (p.inventory_mode='boolean' OR p.stock_quantity>0)))";
        $sort=match($filters['sort']){'price_asc'=>"public_price ASC,p.id DESC",'price_desc'=>"public_price DESC,p.id DESC",'name'=>"p.name ASC,p.id DESC",default=>'p.created_at DESC,p.id DESC'};
        $clause=' WHERE '.implode(' AND ',$where);
        $base=" FROM products p JOIN product_categories pc ON pc.product_id=p.id JOIN categories c ON c.id=pc.category_id{$clause}";
        $count=$this->db->prepare('SELECT COUNT(DISTINCT p.id)'.$base);$count->execute($args);$total=(int)$count->fetchColumn();
        $sql="SELECT DISTINCT p.id,p.name,p.slug,p.short_description,p.price,p.sale_price,p.is_available,p.inventory_mode,p.stock_quantity,p.created_at,{$price} public_price,(SELECT i.file_path FROM product_images i WHERE i.product_id=p.id ORDER BY i.is_primary DESC,i.position,i.id LIMIT 1) image_path,(SELECT i.alt_text FROM product_images i WHERE i.product_id=p.id ORDER BY i.is_primary DESC,i.position,i.id LIMIT 1) image_alt{$base} ORDER BY {$sort} LIMIT {$filters['per_page']} OFFSET ".(($filters['page']-1)*$filters['per_page']);
        $q=$this->db->prepare($sql);$q->execute($args);return ['items'=>$q->fetchAll(),'total'=>$total];
    }

    public function search(string $term,array $filters): array
    {
        $where=["p.status='active'",'p.deleted_at IS NULL'];$args=[];$price=$this->publicPriceExpression();
        $like='%'.strtr($term,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$where[]="(p.name LIKE ? ESCAPE '!' OR p.sku LIKE ? ESCAPE '!' OR p.short_description LIKE ? ESCAPE '!')";array_push($args,$like,$like,$like);
        if($filters['min_price']!==null){$where[]="{$price}>=?";$args[]=$filters['min_price'];}if($filters['max_price']!==null){$where[]="{$price}<=?";$args[]=$filters['max_price'];}
        if($filters['in_stock'])$where[]="((EXISTS(SELECT 1 FROM product_variants va WHERE va.product_id=p.id AND va.is_active=1) AND EXISTS(SELECT 1 FROM product_variants vb WHERE vb.product_id=p.id AND vb.is_active=1 AND vb.is_available=1 AND (vb.inventory_mode='boolean' OR vb.stock_quantity>0)) ) OR (NOT EXISTS(SELECT 1 FROM product_variants vc WHERE vc.product_id=p.id AND vc.is_active=1) AND p.is_available=1 AND (p.inventory_mode='boolean' OR p.stock_quantity>0)))";
        $sort=match($filters['sort']){'price_asc'=>'public_price ASC,p.id DESC','price_desc'=>'public_price DESC,p.id DESC','name'=>'p.name ASC,p.id DESC',default=>'p.created_at DESC,p.id DESC'};$clause=' WHERE '.implode(' AND ',$where);
        $count=$this->db->prepare('SELECT COUNT(*) FROM products p'.$clause);$count->execute($args);$total=(int)$count->fetchColumn();
        $sql="SELECT p.id,p.name,p.slug,p.short_description,p.price,p.sale_price,p.is_available,p.inventory_mode,p.stock_quantity,p.created_at,{$price} public_price,(SELECT i.file_path FROM product_images i WHERE i.product_id=p.id ORDER BY i.is_primary DESC,i.position,i.id LIMIT 1) image_path,(SELECT i.alt_text FROM product_images i WHERE i.product_id=p.id ORDER BY i.is_primary DESC,i.position,i.id LIMIT 1) image_alt FROM products p{$clause} ORDER BY {$sort} LIMIT {$filters['per_page']} OFFSET ".(($filters['page']-1)*$filters['per_page']);$q=$this->db->prepare($sql);$q->execute($args);return ['items'=>$q->fetchAll(),'total'=>$total];
    }

    public function latestProducts(int $limit=8): array
    { $limit=max(1,min(100,$limit));$price=$this->publicPriceExpression();return $this->db->query("SELECT p.id,p.name,p.slug,p.short_description,p.price,p.sale_price,p.is_available,p.inventory_mode,p.stock_quantity,{$price} public_price,(SELECT i.file_path FROM product_images i WHERE i.product_id=p.id ORDER BY i.is_primary DESC,i.position,i.id LIMIT 1) image_path,(SELECT i.alt_text FROM product_images i WHERE i.product_id=p.id ORDER BY i.is_primary DESC,i.position,i.id LIMIT 1) image_alt FROM products p WHERE p.status='active' AND p.deleted_at IS NULL ORDER BY p.created_at DESC,p.id DESC LIMIT {$limit}")->fetchAll(); }

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
}
