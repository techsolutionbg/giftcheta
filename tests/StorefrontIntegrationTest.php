<?php
declare(strict_types=1);
namespace Giftcheta\Tests;

use Giftcheta\Core\Config;
use Giftcheta\Http\Request;
use Giftcheta\Modules\Storefront\HtmlSanitizer;
use Giftcheta\Modules\Storefront\StorefrontController;
use PDO;
use PHPUnit\Framework\TestCase;

final class StorefrontIntegrationTest extends TestCase
{
    private static PDO $db;
    private static Config $config;
    private StorefrontController $storefront;
    private array $created=[];

    public static function setUpBeforeClass(): void
    {
        Config::loadEnvironment(dirname(__DIR__).'/.env');self::$config=Config::fromDirectory(dirname(__DIR__).'/config');$name=(string)(getenv('TEST_DB_NAME')?:'');
        if(!preg_match('/^[A-Za-z0-9_]+_test$/D',$name))self::markTestSkipped('Set TEST_DB_NAME to a dedicated database ending in _test.');
        self::$db=new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',self::$config->get('database.host'),self::$config->get('database.port'),$name),(string)self::$config->get('database.username'),(string)self::$config->get('database.password'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
        (new \Giftcheta\Core\MigrationRunner(self::$db,dirname(__DIR__).'/database/migrations'))->migrate();
    }

    protected function setUp(): void { $this->storefront=new StorefrontController(self::$db,dirname(__DIR__),self::$config); }
    protected function tearDown(): void
    { foreach(array_reverse($this->created) as [$table,$id])self::$db->prepare("DELETE FROM {$table} WHERE id=?")->execute([$id]);$this->created=[]; }

    public function testHtmlSanitizerKeepsEditorFormattingAndRemovesUnsafeMarkup(): void
    { $clean=HtmlSanitizer::clean('<h2>Safe</h2><script>alert(1)</script><p onclick="x()">Text <a href="javascript:alert(1)">link</a><a href="https://example.com">ok</a></p>');self::assertStringContainsString('<h2>Safe</h2>',$clean);self::assertStringNotContainsString('<script>',$clean);self::assertStringNotContainsString('onclick',$clean);self::assertStringNotContainsString('javascript:',$clean);self::assertStringContainsString('https://example.com',$clean); }

    public function testStorefrontRendersActiveCatalogAndHidesInactiveProducts(): void
    {
        [$categoryId,$productId,$categorySlug,$productSlug,$variantId]=$this->fixture();$page=$this->storefront->category(new Request('GET','/category/'.$categorySlug),['slug'=>$categorySlug]);
        self::assertSame(200,$page->status);self::assertStringContainsString('Test Product',$page->body);self::assertStringContainsString('A test item',$page->body);self::assertStringContainsString('/product/'.$productSlug,$page->body);self::assertStringContainsString('application/ld+json',$page->body);
        $productPage=$this->storefront->product(new Request('GET','/product/'.$productSlug),['slug'=>$productSlug]);self::assertSame(200,$productPage->status);self::assertStringContainsString('Test product description',$productPage->body);self::assertStringNotContainsString('<script>alert(1)</script>',$productPage->body);self::assertStringContainsString('Персонално послание',$productPage->body);
        $selectedPage=$this->storefront->product(new Request('GET','/product/'.$productSlug,['variant'=>$variantId]),['slug'=>$productSlug]);self::assertStringContainsString('15,00 €',$selectedPage->body);self::assertStringContainsString('selected',$selectedPage->body);
        self::$db->exec("UPDATE products SET status='draft' WHERE id={$productId}");$hidden=$this->storefront->category(new Request('GET','/category/'.$categorySlug),['slug'=>$categorySlug]);self::assertStringNotContainsString('Test Product',$hidden->body);
        self::assertSame(404,$this->storefront->product(new Request('GET','/product/'.$productSlug),['slug'=>$productSlug])->status);
    }

    public function testSearchFiltersCanonicalSitemapAndRedirects(): void
    {
        [, $productId,,$productSlug]=$this->fixture();$search=$this->storefront->search(new Request('GET','/search',['q'=>'Test Product','min_price'=>'9']));self::assertSame(200,$search->status);self::assertStringContainsString('Test Product',$search->body);self::assertStringContainsString('noindex,follow',$search->body);
        $sitemap=$this->storefront->sitemap(new Request('GET','/sitemap.xml'));self::assertStringContainsString('/product/'.$productSlug,$sitemap->body);
        $oldPath='/product/old-'.$productSlug;$newPath='/product/'.$productSlug;self::$db->prepare('INSERT INTO seo_url_redirects (old_path,new_path) VALUES (?,?)')->execute([$oldPath,$newPath]);$redirect=$this->storefront->redirect(new Request('GET',$oldPath));self::assertSame(301,$redirect?->status);self::assertSame($newPath,$redirect?->headers['Location']);
        self::$db->prepare('DELETE FROM seo_url_redirects WHERE old_path=?')->execute([$oldPath]);
    }

    private function fixture(): array
    {
        $suffix=bin2hex(random_bytes(4));$categorySlug='test-category-'.$suffix;$productSlug='test-product-'.$suffix;$cat=$this->insert('categories',['parent_id'=>null,'name'=>'Test Category '.$suffix,'slug'=>$categorySlug,'description'=>'<p>Category description</p>','image_path'=>null,'is_active'=>1,'sort_order'=>0,'seo_title'=>null,'seo_description'=>null]);
        $prod=$this->insert('products',['name'=>'Test Product '.$suffix,'slug'=>$productSlug,'sku'=>'test-'.$suffix,'short_description'=>'A test item','description'=>'<h2>Details</h2><p>Test product description <script>alert(1)</script></p>','price'=>'12.50','sale_price'=>'10.00','status'=>'active','inventory_mode'=>'boolean','stock_quantity'=>null,'is_available'=>1,'seo_title'=>null,'seo_description'=>null]);
        self::$db->prepare('INSERT INTO product_categories (product_id,category_id) VALUES (?,?)')->execute([$prod,$cat]);
        $variant=$this->insert('product_variants',['product_id'=>$prod,'name'=>'Червен','option_values'=>json_encode(['Цвят'=>'Червен'],JSON_UNESCAPED_UNICODE),'variant_key'=>hash('sha256',$suffix),'sku'=>null,'price'=>null,'price_adjustment'=>'5.00','inventory_mode'=>'boolean','stock_quantity'=>null,'is_available'=>1,'is_active'=>1]);
        $this->insert('product_customization_fields',['product_id'=>$prod,'name'=>'Персонално послание','field_type'=>'text','is_required'=>0,'position'=>0,'validation_rules'=>null,'options'=>null,'additional_price'=>'0.00','is_active'=>1]);return [$cat,$prod,$categorySlug,$productSlug,$variant];
    }

    private function insert(string $table,array $data): int
    { $columns=array_keys($data);$sql='INSERT INTO '.$table.' ('.implode(',',$columns).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')';$q=self::$db->prepare($sql);$q->execute(array_values($data));$id=(int)self::$db->lastInsertId();$this->created[]=[$table,$id];return $id; }
}
