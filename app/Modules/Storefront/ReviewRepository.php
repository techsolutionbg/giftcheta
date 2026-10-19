<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Storefront;

use PDO;

final class ReviewRepository
{
    public function __construct(private readonly PDO $db) {}

    public function forProduct(int $productId,int $page=1,int $perPage=10): array
    {
        $statement=$this->db->prepare("SELECT COUNT(*) review_count,COALESCE(AVG(rating),0) average_rating FROM product_reviews WHERE product_id=? AND status='published'");
        $statement->execute([$productId]);
        $summary=$statement->fetch(PDO::FETCH_ASSOC)?:[];
        $count=(int)($summary['review_count']??0);$pages=max(1,(int)ceil($count/$perPage));$page=max(1,min($pages,$page));$offset=($page-1)*$perPage;
        $statement=$this->db->prepare("SELECT id,author_name,description,rating,created_at FROM product_reviews WHERE product_id=? AND status='published' ORDER BY created_at DESC,id DESC LIMIT {$perPage} OFFSET {$offset}");
        $statement->execute([$productId]);
        return ['items'=>$statement->fetchAll(PDO::FETCH_ASSOC),'count'=>$count,'average'=>round((float)($summary['average_rating']??0),1),'page'=>$page,'pages'=>$pages,'per_page'=>$perPage];
    }

    public function create(int $productId,string $name,string $description,int $rating): array
    {
        $statement=$this->db->prepare("INSERT INTO product_reviews (product_id,author_name,description,rating,status) VALUES (:product_id,:author_name,:description,:rating,'published')");
        $statement->execute(['product_id'=>$productId,'author_name'=>$name,'description'=>$description,'rating'=>$rating]);
        $id=(int)$this->db->lastInsertId();
        $statement=$this->db->prepare('SELECT id,author_name,description,rating,created_at FROM product_reviews WHERE id=?');
        $statement->execute([$id]);
        return $statement->fetch(PDO::FETCH_ASSOC)?:[];
    }
}
