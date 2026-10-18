<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Admin;

use Giftcheta\Core\Config;
use Giftcheta\Http\Request;
use Giftcheta\Http\Response;
use PDO;
use Throwable;

final class CatalogApi
{
    public function __construct(private readonly PDO $db, private readonly AuthApi $auth, private readonly string $projectRoot) {}

    public function categories(Request $r): Response
    {
        if ($e = $this->auth->guard($r, 'categories.read', false)) return $e;
        $trashOnly = ($r->query['trash'] ?? '') === 'only';
        $statement = $this->db->prepare('SELECT c.*, (SELECT COUNT(*) FROM product_categories pc JOIN products p ON p.id=pc.product_id WHERE pc.category_id=c.id AND p.deleted_at IS NULL) AS product_count FROM categories c WHERE c.deleted_at ' . ($trashOnly ? 'IS NOT NULL' : 'IS NULL') . ' ORDER BY c.sort_order,c.name');
        $statement->execute(); $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        return Response::json(['data' => $rows]);
    }

    public function stats(Request $r): Response
    {
        if($e=$this->auth->guard($r,'products.read',false))return $e;
        $products=$this->db->query("SELECT COUNT(*) total, SUM(status='active') active, SUM(status='draft') drafts FROM products WHERE deleted_at IS NULL")->fetch(PDO::FETCH_ASSOC);
        $categories=(int)$this->db->query('SELECT COUNT(*) FROM categories WHERE deleted_at IS NULL')->fetchColumn();
        return Response::json(['data'=>['products'=>(int)$products['total'],'active'=>(int)$products['active'],'drafts'=>(int)$products['drafts'],'categories'=>$categories]]);
    }

    public function bulkProducts(Request $r): Response
    {
        if($e=$this->auth->guard($r,'products.write',true))return $e;
        $d=$r->json();$ids=is_array($d['ids']??null)?array_values(array_unique(array_filter(array_map('intval',$d['ids']),fn($id)=>$id>0))):[];
        $status=$d['status']??null;if(!$ids||count($ids)>100||!in_array($status,['active','draft','archived'],true))return $this->error('validation_error','Choose up to 100 products and a supported status.',422);
        $placeholders=implode(',',array_fill(0,count($ids),'?'));$q=$this->db->prepare("UPDATE products SET status=? WHERE id IN ({$placeholders})");$q->execute([$status,...$ids]);
        return Response::json(['data'=>['updated'=>$q->rowCount()]]);
    }

    public function createCategory(Request $r): Response
    {
        if ($e = $this->auth->guard($r, 'categories.write', true)) return $e;
        $d = $r->json(); $name = trim((string) ($d['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 160) return $this->error('validation_error', 'Category name is required (up to 160 characters).', 422);
        $rawSlug=trim((string)($d['slug']??''));$slug = $this->slug($rawSlug!==''?$rawSlug:$name);
        if ($slug === '' || $this->exists('categories', 'slug', $slug)) return $this->error('slug_conflict', 'This category slug is invalid or already in use.', 409);
        $parent = $this->nullableId($d['parent_id'] ?? null);
        if ($parent !== null && !$this->existsActiveCategory($parent)) return $this->error('validation_error', 'The selected parent category does not exist.', 422);
        $q = $this->db->prepare('INSERT INTO categories (parent_id,name,slug,description,is_active,sort_order,seo_title,seo_description) VALUES (?,?,?,?,?,?,?,?)');
        $q->execute([$parent,$name,$slug,$this->nullableText($d['description'] ?? null),$this->bool($d['is_active'] ?? true), (int) ($d['sort_order'] ?? 0), $this->nullableText($d['seo_title'] ?? null), $this->nullableText($d['seo_description'] ?? null)]);
        return Response::json(['data' => ['id' => (int) $this->db->lastInsertId()]], 201);
    }

    public function updateCategory(Request $r, array $p): Response
    {
        if ($e = $this->auth->guard($r, 'categories.write', true)) return $e;
        $id = (int) ($p['id'] ?? 0); $old = $this->find('categories', $id);
        if (!$old) return $this->notFound();
        $d = $r->json(); $name = trim((string) ($d['name'] ?? $old['name']));
        $rawSlug=trim((string)($d['slug']??''));$slug = $this->slug($rawSlug!==''?$rawSlug:$name);
        if ($name === '' || mb_strlen($name) > 160 || $slug === '') return $this->error('validation_error', 'Category name or slug is invalid.', 422);
        if ($this->exists('categories', 'slug', $slug, $id)) return $this->error('slug_conflict', 'This category slug is already in use.', 409);
        $parent = array_key_exists('parent_id', $d) ? $this->nullableId($d['parent_id']) : ($old['parent_id'] === null ? null : (int) $old['parent_id']);
        if ($parent === $id) return $this->error('category_cycle', 'A category cannot be its own parent.', 422);
        if ($parent !== null) {
            if (!$this->existsActiveCategory($parent)) return $this->error('validation_error', 'The selected parent category does not exist.', 422);
            $q = $this->db->prepare('SELECT id,parent_id FROM categories'); $q->execute(); $parents = [];
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) $parents[(int) $row['id']] = $row['parent_id'] === null ? null : (int) $row['parent_id'];
            $cursor = $parent; $seen = [];
            while ($cursor !== null) { if ($cursor === $id || isset($seen[$cursor])) return $this->error('category_cycle', 'This parent would create a category cycle.', 422); $seen[$cursor] = true; $cursor = $parents[$cursor] ?? null; }
        }
        $q = $this->db->prepare('UPDATE categories SET parent_id=?,name=?,slug=?,description=?,is_active=?,sort_order=?,seo_title=?,seo_description=? WHERE id=?');
        $q->execute([$parent,$name,$slug,$this->nullableText($d['description'] ?? $old['description']),$this->bool($d['is_active'] ?? $old['is_active']), (int) ($d['sort_order'] ?? $old['sort_order']),$this->nullableText($d['seo_title'] ?? $old['seo_title']),$this->nullableText($d['seo_description'] ?? $old['seo_description']),$id]);
        return Response::json(['data' => ['updated' => true]]);
    }

    public function deleteCategory(Request $r, array $p): Response
    {
        if ($e = $this->auth->guard($r, 'categories.delete', true)) return $e;
        $id = (int) ($p['id'] ?? 0);
        $category=$this->find('categories',$id);if(!$category)return $this->notFound();
        if($category['deleted_at']!==null)return $this->error('category_already_trashed','Категорията вече е в кошчето.',409);
        $this->db->prepare('UPDATE categories SET deleted_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$id]);
        return Response::json(['data' => ['trashed' => true]]);
    }

    public function restoreCategory(Request $r,array $p): Response
    {
        if($e=$this->auth->guard($r,'categories.delete',true))return $e;
        $id=(int)($p['id']??0);$category=$this->find('categories',$id);if(!$category)return $this->notFound();
        if($category['deleted_at']===null)return $this->error('category_not_trashed','Категорията не е в кошчето.',409);
        $this->db->prepare('UPDATE categories SET deleted_at=NULL WHERE id=?')->execute([$id]);
        return Response::json(['data'=>['restored'=>true]]);
    }

    public function permanentlyDeleteCategory(Request $r,array $p): Response
    {
        if($e=$this->auth->guard($r,'categories.delete',true))return $e;
        $id=(int)($p['id']??0);$category=$this->find('categories',$id);if(!$category)return $this->notFound();
        if($category['deleted_at']===null)return $this->error('category_not_trashed','Първо преместете категорията в кошчето.',409);
        $q = $this->db->prepare('SELECT (SELECT COUNT(*) FROM categories WHERE parent_id=?) + (SELECT COUNT(*) FROM product_categories WHERE category_id=?)'); $q->execute([$id,$id]);
        if ((int) $q->fetchColumn() > 0) return $this->error('category_in_use', 'Преместете или премахнете подкатегориите и продуктите преди окончателното изтриване.', 409);
        $this->db->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);
        $this->removePublicFile($category['image_path']);
        return Response::json(['data' => ['deleted' => true]]);
    }

    public function uploadCategoryImage(Request $r, array $p): Response
    {
        if ($e = $this->auth->guard($r, 'categories.write', true)) return $e;
        $id=(int)($p['id']??0);$category=$this->find('categories',$id);if(!$category)return $this->notFound();
        $file=$r->files['image']??null;if(!is_array($file)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']))return $this->error('upload_error','Choose a valid image to upload.',422);
        if((int)$file['size']>10*1024*1024)return $this->error('upload_too_large','Images must be 10 MB or smaller.',422);
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
        if(!$ext||!@getimagesize($file['tmp_name']))return $this->error('image_type_invalid','Only valid JPEG, PNG, and WebP images are accepted.',422);
        $name=bin2hex(random_bytes(16)).'.'.$ext;$directory=$this->projectRoot.'/public/uploads/categories';if(!is_dir($directory))mkdir($directory,0750,true);
        if(!move_uploaded_file($file['tmp_name'],$directory.'/'.$name))return $this->error('upload_error','The image could not be saved.',500);
        $relative='uploads/categories/'.$name;$this->db->prepare('UPDATE categories SET image_path=? WHERE id=?')->execute([$relative,$id]);$this->removePublicFile($category['image_path']);
        return Response::json(['data'=>['image_path'=>$relative]],201);
    }

    public function deleteCategoryImage(Request $r,array $p): Response
    {
        if($e=$this->auth->guard($r,'categories.write',true))return $e;$id=(int)($p['id']??0);$category=$this->find('categories',$id);if(!$category)return $this->notFound();
        $this->db->prepare('UPDATE categories SET image_path=NULL WHERE id=?')->execute([$id]);$this->removePublicFile($category['image_path']);return Response::json(['data'=>['deleted'=>true]]);
    }

    public function products(Request $r): Response
    {
        if ($e = $this->auth->guard($r, 'products.read', false)) return $e;
        $page = max(1, (int) ($r->query['page'] ?? 1)); $per = min(100, max(1, (int) ($r->query['per_page'] ?? 20)));
        $sorts = ['name'=>'p.name','sku'=>'p.sku','price'=>'p.price','status'=>'p.status','updated_at'=>'p.updated_at'];
        $sort = $sorts[(string) ($r->query['sort'] ?? 'updated_at')] ?? 'p.updated_at'; $direction = strtolower((string) ($r->query['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $where = []; $args = [];
        $trashOnly = ($r->query['trash'] ?? '') === 'only';
        $where[] = $trashOnly ? 'p.deleted_at IS NOT NULL' : 'p.deleted_at IS NULL';
        $search = trim((string) ($r->query['search'] ?? ''));
        if ($search !== '') { $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.slug LIKE ?)'; $like = '%' . $search . '%'; array_push($args,$like,$like,$like); }
        if (in_array($r->query['status'] ?? '', ['draft','active','archived'], true)) { $where[] = 'p.status=?'; $args[] = $r->query['status']; }
        if (($r->query['promotion'] ?? '') === 'only') { $where[] = 'p.sale_price IS NOT NULL'; }
        elseif (($r->query['promotion'] ?? '') === 'none') { $where[] = 'p.sale_price IS NULL'; }
        if (isset($r->query['category_id']) && ctype_digit((string) $r->query['category_id'])) { $where[] = 'EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id=p.id AND pc.category_id=?)'; $args[] = (int) $r->query['category_id']; }
        $clause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = $this->db->prepare('SELECT COUNT(*) FROM products p' . $clause); $count->execute($args); $total = (int) $count->fetchColumn();
        $sql = 'SELECT p.id,p.name,p.slug,p.sku,p.price,p.sale_price,p.status,p.inventory_mode,p.stock_quantity,p.is_available,p.updated_at,p.deleted_at,(SELECT i.file_path FROM product_images i WHERE i.product_id=p.id ORDER BY i.is_primary DESC,i.position,i.id LIMIT 1) AS image_path,(SELECT GROUP_CONCAT(c.name ORDER BY c.sort_order SEPARATOR ", ") FROM product_categories pc JOIN categories c ON c.id=pc.category_id WHERE pc.product_id=p.id AND c.deleted_at IS NULL) AS categories FROM products p' . $clause . " ORDER BY {$sort} {$direction},p.id DESC LIMIT {$per} OFFSET " . (($page-1)*$per);
        $q = $this->db->prepare($sql); $q->execute($args);
        return Response::json(['data' => $q->fetchAll(PDO::FETCH_ASSOC), 'meta' => ['page'=>$page,'per_page'=>$per,'total'=>$total,'pages'=>(int) ceil($total/$per)]]);
    }

    public function product(Request $r, array $p): Response
    {
        if ($e = $this->auth->guard($r, 'products.read', false)) return $e;
        $id = (int) ($p['id'] ?? 0); $product = $this->find('products', $id); if (!$product) return $this->notFound();
        $product['categories'] = $this->rows('SELECT category_id FROM product_categories WHERE product_id=? ORDER BY category_id', [$id]);
        $product['images'] = $this->rows('SELECT * FROM product_images WHERE product_id=? ORDER BY is_primary DESC,position,id', [$id]);
        $product['variants'] = $this->rows('SELECT * FROM product_variants WHERE product_id=? ORDER BY id', [$id]);
        foreach ($product['variants'] as &$variant) $variant['option_values'] = json_decode((string) $variant['option_values'], true) ?: [];
        unset($variant);
        $product['customization_fields'] = $this->rows('SELECT * FROM product_customization_fields WHERE product_id=? ORDER BY position,id', [$id]);
        foreach ($product['customization_fields'] as &$field) { $field['validation_rules'] = $field['validation_rules'] ? json_decode($field['validation_rules'], true) : null; $field['options'] = $field['options'] ? json_decode($field['options'], true) : null; }
        unset($field);
        return Response::json(['data' => $product]);
    }

    public function createProduct(Request $r): Response
    {
        if ($e = $this->auth->guard($r, 'products.write', true)) return $e;
        $d = $r->json(); $name = trim((string) ($d['name'] ?? ''));
        if ($name === '') $name = 'Нов продукт';
        if (mb_strlen($name) > 190) return $this->error('validation_error','Името може да бъде до 190 символа.',422);
        $rawSlug=trim((string)($d['slug']??''));$autoSlug=$rawSlug==='';$slug=$this->slug($autoSlug?$name:$rawSlug);
        if($slug===''&&$autoSlug)$slug='product';
        if($slug==='')return $this->error('validation_error','URL адресът трябва да съдържа букви или цифри.',422);
        if($autoSlug)$slug=$this->uniqueProductSlug($slug);elseif($this->exists('products','slug',$slug))return $this->error('slug_conflict','Този URL адрес вече се използва. Променете го или оставете полето празно, за да се генерира автоматично.',409);
        $price = trim((string) ($d['price'] ?? ''));
        if ($price === '') $price = '0.00';
        if (!$this->money($price) || (isset($d['sale_price']) && $d['sale_price'] !== '' && !$this->money($d['sale_price']))) return $this->error('validation_error','Цените трябва да са неотрицателни суми с до два знака след десетичната запетая.',422);
        $sku = $this->nullableText($d['sku'] ?? null); if ($sku !== null && $this->exists('products','sku',$sku)) return $this->error('sku_conflict','This SKU is already in use.',409);
        $requestedStatus = $d['status'] ?? 'draft';
        $status = in_array($requestedStatus,['draft','active','archived'],true) ? $requestedStatus : 'draft';
        $mode = ($d['inventory_mode'] ?? 'boolean') === 'tracked' ? 'tracked' : 'boolean';
        $qty = $mode === 'tracked' ? max(0,(int) ($d['stock_quantity'] ?? 0)) : null;
        if (array_key_exists('category_ids', $d) && !$this->validCategoryIds($d['category_ids'])) return $this->error('validation_error','One or more selected categories do not exist.',422);
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare('INSERT INTO products (name,slug,sku,short_description,description,price,sale_price,status,inventory_mode,stock_quantity,is_available,seo_title,seo_description) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $q->execute([$name,$slug,$sku,$this->nullableText($d['short_description'] ?? null),$this->nullableText($d['description'] ?? null),$price,$this->nullableMoney($d['sale_price'] ?? null),$status,$mode,$qty,$mode === 'tracked' ? (int) ($qty > 0) : $this->bool($d['is_available'] ?? true),$this->nullableText($d['seo_title'] ?? null),$this->nullableText($d['seo_description'] ?? null)]);
            $id = (int) $this->db->lastInsertId(); $this->syncCategories($id,$d['category_ids'] ?? []);
            if($mode==='tracked'&&$qty>0)$this->db->prepare("INSERT INTO product_stock_movements (product_id,quantity_delta,quantity_after,reason,note,admin_id) VALUES (?,?,?,'initial_stock','Initial stock on product creation',?)")->execute([$id,$qty,$qty,$_SESSION['admin_id']]);
            $this->db->commit(); return Response::json(['data'=>['id'=>$id]],201);
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }

    public function updateProduct(Request $r,array $p): Response
    {
        if ($e = $this->auth->guard($r,'products.write',true)) return $e;
        $id=(int)($p['id']??0); $old=$this->find('products',$id); if(!$old) return $this->notFound(); $d=$r->json();
        $name=trim((string)($d['name']??$old['name'])); $rawSlug=array_key_exists('slug',$d)?trim((string)$d['slug']):(string)$old['slug'];$autoSlug=$rawSlug==='';$slug=$this->slug($rawSlug!==''?$rawSlug:$name);
        if($slug===''&&$autoSlug)$slug='product';
        if($name===''||mb_strlen($name)>190||$slug==='') return $this->error('validation_error','Product name or slug is invalid.',422);
        if($autoSlug)$slug=$this->uniqueProductSlug($slug,$id);elseif($this->exists('products','slug',$slug,$id)) return $this->error('slug_conflict','Този URL адрес вече се използва. Променете го или оставете полето празно, за да се генерира автоматично.',409);
        if(array_key_exists('price',$d)&&!$this->money($d['price'])) return $this->error('validation_error','Price must be a non-negative decimal amount.',422);
        if(array_key_exists('sale_price',$d)&&$d['sale_price']!==null&&$d['sale_price']!==''&&!$this->money($d['sale_price'])) return $this->error('validation_error','Promotional price must be a non-negative decimal amount.',422);
        if(array_key_exists('category_ids',$d)&&!$this->validCategoryIds($d['category_ids'])) return $this->error('validation_error','One or more selected categories do not exist.',422);
        $sku=array_key_exists('sku',$d)?$this->nullableText($d['sku']):$old['sku']; if($sku!==null&&$this->exists('products','sku',$sku,$id)) return $this->error('sku_conflict','This SKU is already in use.',409);
        $status=in_array($d['status']??$old['status'],['draft','active','archived'],true)?($d['status']??$old['status']):$old['status'];
        $mode=($d['inventory_mode']??$old['inventory_mode'])==='tracked'?'tracked':'boolean';
        $qty=$mode==='tracked'?max(0,(int)($old['inventory_mode']==='tracked'?$old['stock_quantity']:($d['stock_quantity']??0))):null;
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('UPDATE products SET name=?,slug=?,sku=?,short_description=?,description=?,price=?,sale_price=?,status=?,inventory_mode=?,stock_quantity=?,is_available=?,seo_title=?,seo_description=? WHERE id=?');
            $q->execute([$name,$slug,$sku,$this->nullableText($d['short_description']??$old['short_description']),$this->nullableText($d['description']??$old['description']),(string)($d['price']??$old['price']),$this->nullableMoney($d['sale_price']??$old['sale_price']),$status,$mode,$qty,$mode==='tracked'?(int)($qty>0):$this->bool($d['is_available']??$old['is_available']),$this->nullableText($d['seo_title']??$old['seo_title']),$this->nullableText($d['seo_description']??$old['seo_description']),$id]);
            if($mode==='tracked'&&($old['inventory_mode']!=='tracked'||(int)$old['stock_quantity']!==$qty)){$delta=$qty-(int)($old['inventory_mode']==='tracked'?$old['stock_quantity']:0);$this->db->prepare("INSERT INTO product_stock_movements (product_id,quantity_delta,quantity_after,reason,note,admin_id) VALUES (?,?,?,'admin_adjustment','Stock updated while editing product',?)")->execute([$id,$delta,$qty,$_SESSION['admin_id']]);}
            if(array_key_exists('category_ids',$d)) $this->syncCategories($id,$d['category_ids']);
            $this->db->commit();
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
        return Response::json(['data'=>['updated'=>true]]);
    }

    public function deleteProduct(Request $r,array $p): Response
    {
        if($e=$this->auth->guard($r,'products.delete',true))return $e;
        $id=(int)($p['id']??0); if(!$this->existsId('products',$id))return $this->notFound();
        $q=$this->db->prepare('UPDATE products SET deleted_at=CURRENT_TIMESTAMP WHERE id=? AND deleted_at IS NULL');$q->execute([$id]);
        if($q->rowCount()===0)return $this->error('product_already_trashed','Продуктът вече е в кошчето.',409);
        return Response::json(['data'=>['trashed'=>true]]);
    }

    public function restoreProduct(Request $r,array $p): Response
    {
        if($e=$this->auth->guard($r,'products.delete',true))return $e;
        $id=(int)($p['id']??0);$product=$this->find('products',$id);if(!$product)return $this->notFound();
        if($product['deleted_at']===null)return $this->error('product_not_trashed','Продуктът не е в кошчето.',409);
        $this->db->prepare('UPDATE products SET deleted_at=NULL WHERE id=?')->execute([$id]);
        return Response::json(['data'=>['restored'=>true]]);
    }

    public function permanentlyDeleteProduct(Request $r,array $p): Response
    {
        if($e=$this->auth->guard($r,'products.delete',true))return $e;
        $id=(int)($p['id']??0);$product=$this->find('products',$id);if(!$product)return $this->notFound();
        if($product['deleted_at']===null)return $this->error('product_not_trashed','Първо преместете продукта в кошчето.',409);
        $q=$this->db->prepare('SELECT COUNT(*) FROM product_stock_movements WHERE product_id=?');$q->execute([$id]);
        if((int)$q->fetchColumn()>0)return $this->error('product_has_stock_history','Продуктът има история на наличностите и не може да бъде изтрит завинаги. Оставете го в кошчето.',409);
        $images=$this->rows('SELECT file_path,thumbnail_path FROM product_images WHERE product_id=?',[$id]);
        $this->db->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
        foreach($images as $image){$this->removePublicFile($image['file_path']);$this->removePublicFile($image['thumbnail_path']);}
        return Response::json(['data'=>['deleted'=>true]]);
    }

    public function createVariant(Request $r,array $p): Response
    {
        if($e=$this->auth->guard($r,'products.write',true))return $e;
        $product=(int)($p['id']??0); if(!$this->existsId('products',$product))return $this->notFound(); $d=$r->json();
        $options=$d['option_values']??null; if(!is_array($options)||$options===[])return $this->error('validation_error','Provide at least one variant option.',422);
        $key=$this->variantKey($options); if($this->existsVariantKey($product,$key))return $this->error('duplicate_variant','This option combination already exists.',409);
        $sku=$this->nullableText($d['sku']??null); if($sku!==null&&$this->exists('product_variants','sku',$sku))return $this->error('sku_conflict','This SKU is already in use.',409);
        if(isset($d['price'])&&$d['price']!==''&&!$this->money($d['price']))return $this->error('validation_error','Variant price must be a non-negative decimal.',422);
        if(isset($d['price_adjustment'])&&!$this->moneySigned($d['price_adjustment']))return $this->error('validation_error','Price adjustment must be a decimal with up to two places.',422);
        $mode=($d['inventory_mode']??'boolean')==='tracked'?'tracked':'boolean'; $qty=$mode==='tracked'?max(0,(int)($d['stock_quantity']??0)):null;
        $q=$this->db->prepare('INSERT INTO product_variants (product_id,name,option_values,variant_key,sku,price,price_adjustment,inventory_mode,stock_quantity,is_available,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute([$product,trim((string)($d['name']??implode(' / ',array_values($options)))),json_encode($options,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$key,$sku,$this->nullableMoney($d['price']??null),(string)($d['price_adjustment']??'0'),$mode,$qty,$mode==='tracked'?(int)($qty>0):$this->bool($d['is_available']??true),$this->bool($d['is_active']??true)]);
        return Response::json(['data'=>['id'=>(int)$this->db->lastInsertId()]],201);
    }

    public function updateVariant(Request $r,array $p): Response
    {
        if($e=$this->auth->guard($r,'products.write',true))return $e;
        $id=(int)($p['id']??0); $v=$this->find('product_variants',$id); if(!$v)return $this->notFound(); $d=$r->json();
        $opts=$d['option_values']??json_decode($v['option_values'],true); $key=$this->variantKey($opts);
        if($this->existsVariantKey((int)$v['product_id'],$key,$id))return $this->error('duplicate_variant','This option combination already exists.',409);
        $sku=array_key_exists('sku',$d)?$this->nullableText($d['sku']):$v['sku']; if($sku!==null&&$this->exists('product_variants','sku',$sku,$id))return $this->error('sku_conflict','This SKU is already in use.',409);
        if(isset($d['price'])&&$d['price']!==''&&!$this->money($d['price']))return $this->error('validation_error','Variant price must be a non-negative decimal.',422);
        if(isset($d['price_adjustment'])&&!$this->moneySigned($d['price_adjustment']))return $this->error('validation_error','Price adjustment must be a decimal with up to two places.',422);
        $mode=($d['inventory_mode']??$v['inventory_mode'])==='tracked'?'tracked':'boolean'; $qty=$mode==='tracked'?max(0,(int)($d['stock_quantity']??$v['stock_quantity']??0)):null;
        $q=$this->db->prepare('UPDATE product_variants SET name=?,option_values=?,variant_key=?,sku=?,price=?,price_adjustment=?,inventory_mode=?,stock_quantity=?,is_available=?,is_active=? WHERE id=?');
        $q->execute([trim((string)($d['name']??$v['name'])),json_encode($opts,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$key,$sku,$this->nullableMoney($d['price']??$v['price']), (string)($d['price_adjustment']??$v['price_adjustment']),$mode,$qty,$mode==='tracked'?(int)($qty>0):$this->bool($d['is_available']??$v['is_available']),$this->bool($d['is_active']??$v['is_active']),$id]);
        return Response::json(['data'=>['updated'=>true]]);
    }

    public function deleteVariant(Request $r,array $p): Response
    {
        if($e=$this->auth->guard($r,'products.write',true))return $e;
        $id=(int)($p['id']??0); if(!$this->existsId('product_variants',$id))return $this->notFound();
        $this->db->prepare('DELETE FROM product_variants WHERE id=?')->execute([$id]); return Response::json(['data'=>['deleted'=>true]]);
    }

    public function createField(Request $r,array $p): Response
    {
        if($e=$this->auth->guard($r,'products.write',true))return $e;
        $product=(int)($p['id']??0); if(!$this->existsId('products',$product))return $this->notFound(); $d=$r->json();
        $name=trim((string)($d['name']??'')); $type=(string)($d['field_type']??'');
        if($name===''||!in_array($type,['text','textarea','image','select','checkbox'],true))return $this->error('validation_error','Name and a supported field type are required.',422);
        $options=$d['options']??null; if($type==='select'&&(!is_array($options)||$options===[]))return $this->error('validation_error','Select fields need at least one option.',422);
        $price=(string)($d['additional_price']??'0'); if(!$this->money($price))return $this->error('validation_error','Additional price must be a non-negative decimal.',422);
        $q=$this->db->prepare('INSERT INTO product_customization_fields (product_id,name,field_type,is_required,position,validation_rules,options,additional_price,is_active) VALUES (?,?,?,?,?,?,?,?,?)');
        $q->execute([$product,$name,$type,$this->bool($d['is_required']??false),(int)($d['position']??0),isset($d['validation_rules'])?json_encode($d['validation_rules'],JSON_THROW_ON_ERROR):null,$options===null?null:json_encode($options,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$price,$this->bool($d['is_active']??true)]);
        return Response::json(['data'=>['id'=>(int)$this->db->lastInsertId()]],201);
    }

    public function updateField(Request $r,array $p): Response
    {
        if($e=$this->auth->guard($r,'products.write',true))return $e;
        $id=(int)($p['id']??0); $f=$this->find('product_customization_fields',$id); if(!$f)return $this->notFound(); $d=$r->json();
        $type=(string)($d['field_type']??$f['field_type']); if(!in_array($type,['text','textarea','image','select','checkbox'],true))return $this->error('validation_error','Unsupported field type.',422);
        $price=(string)($d['additional_price']??$f['additional_price']); if(!$this->money($price))return $this->error('validation_error','Additional price must be non-negative.',422);
        $options=$d['options']??($f['options']?json_decode($f['options'],true):null);
        if($type==='select'&&(!is_array($options)||$options===[]))return $this->error('validation_error','Select fields need at least one option.',422);
        $rules=$d['validation_rules']??($f['validation_rules']?json_decode($f['validation_rules'],true):null);
        $q=$this->db->prepare('UPDATE product_customization_fields SET name=?,field_type=?,is_required=?,position=?,validation_rules=?,options=?,additional_price=?,is_active=? WHERE id=?');
        $q->execute([trim((string)($d['name']??$f['name'])),$type,$this->bool($d['is_required']??$f['is_required']),(int)($d['position']??$f['position']),$rules===null?null:json_encode($rules,JSON_THROW_ON_ERROR),$options===null?null:json_encode($options,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$price,$this->bool($d['is_active']??$f['is_active']),$id]);
        return Response::json(['data'=>['updated'=>true]]);
    }

    public function deleteField(Request $r,array $p): Response
    {
        if($e=$this->auth->guard($r,'products.write',true))return $e;
        $id=(int)($p['id']??0); if(!$this->existsId('product_customization_fields',$id))return $this->notFound();
        $this->db->prepare('DELETE FROM product_customization_fields WHERE id=?')->execute([$id]); return Response::json(['data'=>['deleted'=>true]]);
    }

    public function adjustStock(Request $r,array $p): Response
    {
        if($e=$this->auth->guard($r,'inventory.write',true))return $e;
        $d=$r->json(); $qty=$d['quantity']??null; if(!is_numeric($qty)||(int)$qty<0)return $this->error('validation_error','Stock quantity must be a non-negative integer.',422);
        $variantId=isset($p['variant_id'])?(int)$p['variant_id']:null; $productId=(int)($p['id']??0);
        if($variantId!==null){$v=$this->find('product_variants',$variantId);if(!$v||(int)$v['product_id']!==$productId)return $this->notFound();$table='product_variants';$target=$variantId;}else{$v=$this->find('products',$productId);if(!$v)return $this->notFound();$table='products';$target=$productId;}
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare("SELECT inventory_mode,stock_quantity,is_available FROM {$table} WHERE id=? FOR UPDATE");$q->execute([$target]);$old=$q->fetch(PDO::FETCH_ASSOC);
            if($old['inventory_mode']!=='tracked')return $this->rollbackError('inventory_mode_mismatch','Enable quantity tracking before setting a stock count.',422);
            $new=(int)$qty;$delta=$new-(int)$old['stock_quantity'];
            $this->db->prepare("UPDATE {$table} SET stock_quantity=?,is_available=? WHERE id=?")->execute([$new,(int)($new>0),$target]);
            $this->db->prepare('INSERT INTO product_stock_movements (product_id,variant_id,quantity_delta,quantity_after,reason,note,admin_id) VALUES (?,?,?,?,?,?,?)')->execute([$productId,$variantId,$delta,$new,'admin_adjustment',$this->nullableText($d['note']??null),$_SESSION['admin_id']]);
            $this->db->commit();return Response::json(['data'=>['quantity'=>$new,'delta'=>$delta]]);
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function uploadImage(Request $r,array $p): Response
    {
        if($e=$this->auth->guard($r,'products.write',true))return $e;
        $product=(int)($p['id']??0);if(!$this->existsId('products',$product))return $this->notFound();
        $file=$r->files['image']??null;if(!is_array($file))return $this->error('upload_error','Choose an image to upload.',422);
        $uploadError=(int)($file['error']??UPLOAD_ERR_NO_FILE);
        if($uploadError!==UPLOAD_ERR_OK){
            if(in_array($uploadError,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true))return $this->error('upload_too_large','Images must be 10 MB or smaller.',422);
            if($uploadError===UPLOAD_ERR_NO_FILE)return $this->error('upload_error','Choose an image to upload.',422);
            return $this->error('upload_error','The image could not be uploaded. Please try again.',422);
        }
        if((int)$file['size']>10*1024*1024)return $this->error('upload_too_large','Images must be 10 MB or smaller.',422);
        if(!is_uploaded_file($file['tmp_name']))return $this->error('upload_error','Invalid uploaded file.',422);
        $finfo=new \finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($file['tmp_name']);$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if(!isset($allowed[$mime])||!@getimagesize($file['tmp_name']))return $this->error('image_type_invalid','Only valid JPEG, PNG, and WebP images are accepted.',422);
        if(!function_exists('imagecreatefromstring'))return $this->error('image_processing_unavailable','Image processing is not enabled on this server.',503);
        $image=@imagecreatefromstring((string)file_get_contents($file['tmp_name']));if(!$image)return $this->error('image_invalid','The image could not be decoded.',422);
        $maxDimension=6000;if(imagesx($image)>$maxDimension||imagesy($image)>$maxDimension){imagedestroy($image);return $this->error('image_dimensions_invalid','Images cannot exceed 6000 pixels per dimension.',422);}
        $base=bin2hex(random_bytes(16));$dir=$this->projectRoot.'/public/uploads/products';$thumbDir=$dir.'/thumbnails';
        if(!is_dir($dir))mkdir($dir,0750,true);if(!is_dir($thumbDir))mkdir($thumbDir,0750,true);
        $webp=function_exists('imagewebp');$extension=$webp?'webp':$allowed[$mime];$mimeOut=$webp?'image/webp':$mime;$fileName=$base.'.'.$extension;
        $ok=$this->saveImage($image,$dir.'/'.$fileName,$webp,$mime);
        if(!$ok){imagedestroy($image);return $this->error('image_processing_failed','The image could not be optimized.',500);}
        $thumb=$this->thumbnail($image,600);$thumbName=$base.'-thumb.'.$extension;$this->saveImage($thumb,$thumbDir.'/'.$thumbName,$webp,$mime);imagedestroy($thumb);imagedestroy($image);
        $pos=(int)$this->db->query('SELECT COALESCE(MAX(position),-1)+1 FROM product_images WHERE product_id='.(int)$product)->fetchColumn();
        $primary=(int)$this->db->query('SELECT COUNT(*) FROM product_images WHERE product_id='.(int)$product.' AND is_primary=1')->fetchColumn()===0;
        $rel='uploads/products/'.$fileName;$thumbRel='uploads/products/thumbnails/'.$thumbName;
        $q=$this->db->prepare('INSERT INTO product_images (product_id,file_path,thumbnail_path,alt_text,position,is_primary,mime_type,file_size) VALUES (?,?,?,?,?,?,?,?)');$q->execute([$product,$rel,$thumbRel,$this->nullableText($_POST['alt_text']??null),$pos,(int)$primary,$mimeOut,(int)filesize($dir.'/'.$fileName)]);
        return Response::json(['data'=>['id'=>(int)$this->db->lastInsertId(),'file_path'=>$rel,'thumbnail_path'=>$thumbRel,'is_primary'=>$primary]],201);
    }

    public function updateImage(Request $r,array $p): Response
    {
        if($e=$this->auth->guard($r,'products.write',true))return $e;
        $id=(int)($p['id']??0);$img=$this->find('product_images',$id);if(!$img)return $this->notFound();$d=$r->json();
        if(isset($d['is_primary'])&&$this->bool($d['is_primary'])){$this->db->beginTransaction();$this->db->prepare('UPDATE product_images SET is_primary=0 WHERE product_id=?')->execute([$img['product_id']]);$this->db->prepare('UPDATE product_images SET is_primary=1 WHERE id=?')->execute([$id]);$this->db->commit();}
        $q=$this->db->prepare('UPDATE product_images SET alt_text=?,position=? WHERE id=?');$q->execute([$this->nullableText($d['alt_text']??$img['alt_text']),(int)($d['position']??$img['position']),$id]);
        return Response::json(['data'=>['updated'=>true]]);
    }

    public function deleteImage(Request $r,array $p): Response
    {
        if($e=$this->auth->guard($r,'products.write',true))return $e;$id=(int)($p['id']??0);$img=$this->find('product_images',$id);if(!$img)return $this->notFound();
        $this->db->prepare('DELETE FROM product_images WHERE id=?')->execute([$id]);$this->removePublicFile($img['file_path']);$this->removePublicFile($img['thumbnail_path']);
        if((int)$img['is_primary']===1){$this->db->prepare('UPDATE product_images SET is_primary=1 WHERE product_id=? ORDER BY position,id LIMIT 1')->execute([$img['product_id']]);}
        return Response::json(['data'=>['deleted'=>true]]);
    }

    private function saveImage(mixed $image,string $path,bool $webp,string $mime): bool
    { if($webp)return imagewebp($image,$path,82); return match($mime){'image/jpeg'=>imagejpeg($image,$path,88),'image/png'=>imagepng($image,$path,7),'image/webp'=>imagewebp($image,$path,82),default=>false}; }
    private function thumbnail(mixed $image,int $bound): mixed
    { $w=imagesx($image);$h=imagesy($image);$scale=min(1,$bound/max($w,$h));$tw=max(1,(int)round($w*$scale));$th=max(1,(int)round($h*$scale));$out=imagecreatetruecolor($tw,$th);imagealphablending($out,false);imagesavealpha($out,true);imagecopyresampled($out,$image,0,0,0,0,$tw,$th,$w,$h);return $out; }
    private function removePublicFile(?string $relative): void
    { if(!$relative||str_contains($relative,'..'))return;$path=$this->projectRoot.'/public/'.ltrim($relative,'/');if(is_file($path))unlink($path); }
    private function syncCategories(int $product,mixed $ids): void
    { if(!is_array($ids))throw new \InvalidArgumentException('Category ids must be an array.');$ids=array_values(array_unique(array_filter(array_map('intval',$ids),fn($id)=>$id>0)));if($ids){$in=implode(',',array_fill(0,count($ids),'?'));$q=$this->db->prepare("SELECT COUNT(*) FROM categories WHERE id IN ({$in}) AND deleted_at IS NULL");$q->execute($ids);if((int)$q->fetchColumn()!==count($ids))throw new \InvalidArgumentException('One or more categories do not exist.');}$this->db->prepare('DELETE FROM product_categories WHERE product_id=?')->execute([$product]);$q=$this->db->prepare('INSERT INTO product_categories (product_id,category_id) VALUES (?,?)');foreach($ids as $id)$q->execute([$product,$id]); }
    private function validCategoryIds(mixed $ids): bool
    { if(!is_array($ids))return false;$normalized=array_values(array_unique(array_filter(array_map('intval',$ids),fn($id)=>$id>0)));if(count($normalized)!==count($ids))return $ids===[];if(!$normalized)return true;$in=implode(',',array_fill(0,count($normalized),'?'));$q=$this->db->prepare("SELECT COUNT(*) FROM categories WHERE id IN ({$in}) AND deleted_at IS NULL");$q->execute($normalized);return (int)$q->fetchColumn()===count($normalized); }
    private function existsActiveCategory(int $id): bool {$q=$this->db->prepare('SELECT 1 FROM categories WHERE id=? AND deleted_at IS NULL');$q->execute([$id]);return (bool)$q->fetchColumn();}
    private function variantKey(array $options): string
    { $clean=[];foreach($options as $name=>$value){$name=mb_strtolower(trim((string)$name));$value=mb_strtolower(trim((string)$value));if($name!==''&&$value!=='')$clean[$name]=$value;}ksort($clean,SORT_STRING);return hash('sha256',json_encode($clean,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)); }
    private function existsVariantKey(int $product,string $key,?int $exclude=null): bool
    { $sql='SELECT 1 FROM product_variants WHERE product_id=? AND variant_key=?'.($exclude?' AND id<>?':'').' LIMIT 1';$q=$this->db->prepare($sql);$q->execute($exclude?[$product,$key,$exclude]:[$product,$key]);return (bool)$q->fetchColumn(); }
    private function exists(string $table,string $column,string $value,?int $exclude=null): bool
    { if(!in_array($table,['products','categories','product_variants'],true)||!in_array($column,['slug','sku'],true))return false;$sql="SELECT 1 FROM {$table} WHERE {$column}=?".($exclude?' AND id<>?':'').' LIMIT 1';$q=$this->db->prepare($sql);$q->execute($exclude?[$value,$exclude]:[$value]);return (bool)$q->fetchColumn(); }
    private function existsId(string $table,int $id): bool { if(!in_array($table,['products','categories','product_variants','product_images','product_customization_fields'],true))return false;$q=$this->db->prepare("SELECT 1 FROM {$table} WHERE id=?");$q->execute([$id]);return (bool)$q->fetchColumn(); }
    private function find(string $table,int $id): ?array { if(!in_array($table,['products','categories','product_variants','product_images','product_customization_fields'],true))return null;$q=$this->db->prepare("SELECT * FROM {$table} WHERE id=?");$q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);return $row?:null; }
    private function rows(string $sql,array $params=[]): array {$q=$this->db->prepare($sql);$q->execute($params);return $q->fetchAll(PDO::FETCH_ASSOC);}
    private function slug(string $value): string
    {
        $value=mb_strtolower(trim($value),'UTF-8');
        $value=strtr($value,['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sht','ъ'=>'a','ь'=>'y','ю'=>'yu','я'=>'ya']);
        $latin=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);$value=strtolower($latin!==false?$latin:$value);
        return trim((string)preg_replace('/[^a-z0-9]+/','-',$value),'-');
    }
    private function uniqueProductSlug(string $base,?int $exclude=null): string
    {
        $candidate=$base;$suffix=2;
        while($this->exists('products','slug',$candidate,$exclude)){$tail='-'.$suffix++;$candidate=substr($base,0,220-strlen($tail)).$tail;}
        return $candidate;
    }
    private function money(mixed $v): bool {return is_string($v)||is_int($v) ? preg_match('/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/',(string)$v)===1 : false;}
    private function moneySigned(mixed $v): bool {return (is_string($v)||is_int($v))&&preg_match('/^-?(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/',(string)$v)===1;}
    private function nullableMoney(mixed $v): ?string {return $v===null||$v===''?null:(string)$v;}
    private function nullableText(mixed $v): ?string {$s=trim((string)($v??''));return $s===''?null:$s;}
    private function nullableId(mixed $v): ?int {return $v===null||$v===''?null:(int)$v;}
    private function bool(mixed $v): int {return filter_var($v,FILTER_VALIDATE_BOOLEAN)?1:0;}
    private function rollbackError(string $code,string $message,int $status): Response {if($this->db->inTransaction())$this->db->rollBack();return $this->error($code,$message,$status);}
    private function notFound(): Response {return $this->error('not_found','The requested resource was not found.',404);}
    private function error(string $code,string $message,int $status): Response {return Response::json(['error'=>['code'=>$code,'message'=>$message]],$status);}
}
