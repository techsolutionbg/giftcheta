<?php
declare(strict_types=1);
namespace Giftcheta\Tests;

use Giftcheta\Core\Config;
use Giftcheta\Core\MigrationRunner;
use Giftcheta\Modules\Cart\CartFileStorage;
use Giftcheta\Modules\Cart\CartRepository;
use Giftcheta\Modules\Cart\CartService;
use PDO;
use PHPUnit\Framework\TestCase;

final class CartIntegrationTest extends TestCase
{
    private static PDO $db;
    private CartService $cart;
    private array $created=[];
    private int $cartId=0;
    private string $key='';
    private array $additionalCartIds=[];

    public static function setUpBeforeClass(): void
    {
        Config::loadEnvironment(dirname(__DIR__).'/.env');$config=Config::fromDirectory(dirname(__DIR__).'/config');$name=(string)(getenv('TEST_DB_NAME')?:'');
        if(!preg_match('/^[A-Za-z0-9_]+_test$/D',$name))self::markTestSkipped('Set TEST_DB_NAME to a dedicated database ending in _test.');
        self::$db=new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',$config->get('database.host'),$config->get('database.port'),$name),(string)$config->get('database.username'),(string)$config->get('database.password'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
        (new MigrationRunner(self::$db,dirname(__DIR__).'/database/migrations'))->migrate();
    }

    protected function setUp(): void
    { $repo=new CartRepository(self::$db);$files=new CartFileStorage(self::$db,dirname(__DIR__));$this->cart=new CartService(self::$db,$repo,$files);$this->key=hash('sha256',bin2hex(random_bytes(32)));$this->cartId=$repo->cartId($this->key,true)??0; }

    protected function tearDown(): void
    { foreach([$this->cartId,...$this->additionalCartIds] as $id)if($id)self::$db->prepare('DELETE FROM carts WHERE id=?')->execute([$id]);foreach(array_reverse($this->created) as [$table,$id])self::$db->prepare("DELETE FROM {$table} WHERE id=?")->execute([$id]); }

    public function testCartRepricesAndMergesOnlyMatchingCustomization(): void
    {
        $product=$this->product('10.00','boolean',null);$field=$this->insert('product_customization_fields',['product_id'=>$product,'name'=>'Цвят','field_type'=>'radio','is_required'=>1,'position'=>0,'validation_rules'=>null,'options'=>json_encode([['value'=>'червен','label'=>'Червен','additional_price'=>'2.50'],['value'=>'син','label'=>'Син','additional_price'=>'0.00']],JSON_UNESCAPED_UNICODE),'additional_price'=>'0.00','is_active'=>1]);
        self::assertTrue($this->cart->add($this->key,$product,null,2,[['field_id'=>$field,'value'=>'червен']])['ok']);
        self::assertTrue($this->cart->add($this->key,$product,null,1,[['field_id'=>$field,'value'=>'червен']])['ok']);
        self::assertTrue($this->cart->add($this->key,$product,null,1,[['field_id'=>$field,'value'=>'син']])['ok']);
        $summary=$this->cart->summary($this->key);self::assertCount(2,$summary['items']);self::assertSame(4,$summary['count']);self::assertSame(4_750,$summary['subtotal_cents']);
        self::assertSame([3,1],array_map(static fn(array $item):int=>(int)$item['quantity'],$summary['items']));
    }

    public function testCartChecksTrackedStockWithoutReservingIt(): void
    {
        $product=$this->product('5.00','tracked',3);
        self::assertTrue($this->cart->add($this->key,$product,null,2,[])['ok']);
        self::assertSame(3,(int)$this->scalar('SELECT stock_quantity FROM products WHERE id=?',[$product]));
        $result=$this->cart->add($this->key,$product,null,2,[]);self::assertFalse($result['ok']);self::assertSame(409,$result['status']);
    }

    public function testExpiredGuestCartCanBeRecreatedForSameSessionKey(): void
    {
        self::$db->prepare('UPDATE carts SET expires_at=DATE_SUB(NOW(),INTERVAL 1 DAY) WHERE id=?')->execute([$this->cartId]);$newId=(new CartRepository(self::$db))->cartId($this->key,true);self::assertNotNull($newId);self::assertNotSame($this->cartId,$newId);$this->additionalCartIds[]=$newId;
        self::assertSame('abandoned',(string)$this->scalar('SELECT status FROM carts WHERE id=?',[$this->cartId]));self::assertNull($this->scalar('SELECT guest_key_hash FROM carts WHERE id=?',[$this->cartId]));
    }

    private function product(string $price,string $mode,?int $stock): int
    { $suffix=bin2hex(random_bytes(6));return $this->insert('products',['name'=>'Cart test '.$suffix,'slug'=>'cart-test-'.$suffix,'sku'=>'cart-'.$suffix,'short_description'=>'','description'=>'','price'=>$price,'sale_price'=>null,'status'=>'active','inventory_mode'=>$mode,'stock_quantity'=>$stock,'is_available'=>1,'seo_title'=>null,'seo_description'=>null]); }
    private function insert(string $table,array $data): int
    { $columns=array_keys($data);$q=self::$db->prepare('INSERT INTO '.$table.' ('.implode(',',$columns).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')');$q->execute(array_values($data));$id=(int)self::$db->lastInsertId();$this->created[]=[$table,$id];return $id; }
    private function scalar(string $sql,array $values): mixed { $q=self::$db->prepare($sql);$q->execute($values);return $q->fetchColumn(); }
}
