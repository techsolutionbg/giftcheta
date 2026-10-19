<?php
declare(strict_types=1);
namespace Giftcheta\Tests;

use Giftcheta\Core\Config;
use Giftcheta\Core\Database;
use Giftcheta\Core\Logger;
use Giftcheta\Core\MigrationRunner;
use Giftcheta\Http\Request;
use Giftcheta\Modules\Admin\AuthApi;
use Giftcheta\Modules\Admin\OrdersApi;
use PHPUnit\Framework\TestCase;
use PDO;

final class AdminOrdersIntegrationTest extends TestCase
{
    private static PDO $db;
    private static Config $config;
    private AuthApi $auth;
    private OrdersApi $orders;
    private array $headers=[];

    public static function setUpBeforeClass(): void
    {
        Config::loadEnvironment(dirname(__DIR__).'/.env');
        self::$config=Config::fromDirectory(dirname(__DIR__).'/config');
        $database=(string)(getenv('TEST_DB_NAME')?:'');
        if(!preg_match('/^[A-Za-z0-9_]+_test$/D',$database))self::markTestSkipped('Set TEST_DB_NAME to a dedicated database ending in _test.');
        self::$db=new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',self::$config->get('database.host'),self::$config->get('database.port'),$database),(string)self::$config->get('database.username'),(string)self::$config->get('database.password'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
        (new MigrationRunner(self::$db,dirname(__DIR__).'/database/migrations'))->migrate();
    }

    protected function setUp(): void
    {
        if(session_status()===PHP_SESSION_ACTIVE){$_SESSION=[];session_destroy();}
        $this->auth=new AuthApi(self::$db,self::$config);
        $this->orders=new OrdersApi(self::$db,$this->auth,self::$config,new Logger(dirname(__DIR__).'/storage/logs/app.log','debug'));
        $this->loginAdmin();
    }

    protected function tearDown(): void
    { if(session_status()===PHP_SESSION_ACTIVE){$_SESSION=[];session_destroy();} }

    public function testGuestOrderSnapshotsPricesAndDeductsTrackedStock(): void
    {
        $product=$this->createProduct('25.90','tracked',4);
        $response=$this->createOrder($this->guestOrder($product,2,'5.10'));
        self::assertSame(201,$response->status,$response->body);
        $data=json_decode($response->body,true)['data'];
        $order=$this->row('SELECT * FROM orders WHERE id=?',[$data['id']]);
        self::assertNull($order['customer_id']);self::assertSame('51.80',$order['subtotal']);self::assertSame('56.90',$order['grand_total']);self::assertSame('unpaid',$order['payment_status']);
        self::assertSame(2,(int)$this->scalar('SELECT stock_quantity FROM products WHERE id=?',[$product]));
        self::assertSame(-2,(int)$this->scalar("SELECT quantity_delta FROM product_stock_movements WHERE reason='order_created' AND note LIKE ?",['%'.$data['order_number'].'%']));
        self::assertSame('1',(string)$this->scalar('SELECT COUNT(*) FROM order_addresses WHERE order_id=? AND address_type=\'shipping\'',[$data['id']]));
        self::assertSame('sent',(string)$this->scalar('SELECT status FROM order_email_outbox WHERE order_id=?',[$data['id']]));
    }

    public function testExistingCustomerAddressVariantAndCustomizationAreSnapshotted(): void
    {
        $customerId=$this->createCustomer();$addressId=$this->createAddress($customerId);$product=$this->createProduct('10.00','boolean',null);$variant=$this->createVariant($product,'3.00',5);$field=$this->createCustomization($product,'1.50');
        $payload=$this->guestOrder($product,2,'0.00');$payload['customer_id']=$customerId;unset($payload['first_name'],$payload['last_name'],$payload['email'],$payload['phone']);$payload['shipping_address']=['customer_address_id'=>$addressId];$payload['items'][0]['variant_id']=$variant;$payload['items'][0]['customizations']=[['field_id'=>$field,'value'=>'Надпис за клиента']];
        $response=$this->createOrder($payload);self::assertSame(201,$response->status,$response->body);$orderId=json_decode($response->body,true)['data']['id'];
        $order=$this->row('SELECT * FROM orders WHERE id=?',[$orderId]);self::assertSame((string)$customerId,(string)$order['customer_id']);self::assertSame('29.00',$order['subtotal']);
        $item=$this->row('SELECT * FROM order_items WHERE order_id=?',[$orderId]);self::assertSame('29.00',$item['line_total']);self::assertSame('13.00',$item['unit_price']);self::assertSame('3.00',$item['customization_total']);
        self::assertSame('Надпис за клиента',(string)$this->scalar('SELECT value_text FROM order_item_customizations WHERE order_item_id=?',[$item['id']]));
        self::assertSame('Клиентски адрес 4',(string)$this->scalar('SELECT city FROM order_addresses WHERE order_id=?',[$orderId]));
        self::assertSame(3,(int)$this->scalar('SELECT stock_quantity FROM product_variants WHERE id=?',[$variant]));
    }

    public function testInsufficientStockRollsBackOrderAndLedger(): void
    {
        $product=$this->createProduct('4.25','tracked',1);$response=$this->createOrder($this->guestOrder($product,2));
        self::assertSame(409,$response->status);self::assertSame(0,(int)$this->scalar('SELECT COUNT(*) FROM order_items WHERE product_id=?',[$product]));self::assertSame(1,(int)$this->scalar('SELECT stock_quantity FROM products WHERE id=?',[$product]));self::assertSame(0,(int)$this->scalar("SELECT COUNT(*) FROM product_stock_movements WHERE product_id=? AND reason='order_created'",[$product]));
    }

    public function testIdempotencyDoesNotCreateOrDeductTwice(): void
    {
        $product=$this->createProduct('5.00','tracked',5);$payload=$this->guestOrder($product,2);$key='test-key-'.bin2hex(random_bytes(12));$first=$this->createOrder($payload,$key);$second=$this->createOrder($payload,$key);
        self::assertSame(201,$first->status);self::assertSame(200,$second->status);self::assertTrue(json_decode($second->body,true)['data']['replayed']);self::assertSame(3,(int)$this->scalar('SELECT stock_quantity FROM products WHERE id=?',[$product]));
        $payload['items'][0]['quantity']=1;$conflict=$this->createOrder($payload,$key);self::assertSame(409,$conflict->status);
    }

    public function testStatusPaymentAndCancellationRestoreStockOnlyOnce(): void
    {
        $product=$this->createProduct('7.00','tracked',6);$created=$this->createOrder($this->guestOrder($product,2));$id=(int)json_decode($created->body,true)['data']['id'];
        self::assertSame(409,$this->request('POST',['status'=>'shipped'],'/orders/'.$id.'/status')->status);
        self::assertSame(200,$this->request('POST',['status'=>'confirmed'],'/orders/'.$id.'/status')->status);
        self::assertSame(200,$this->request('POST',['status'=>'confirmed'],'/orders/'.$id.'/status')->status);
        self::assertSame(200,$this->request('POST',['payment_status'=>'paid'],'/orders/'.$id.'/payment-status')->status);
        self::assertSame(200,$this->request('POST',['payment_status'=>'refunded'],'/orders/'.$id.'/payment-status')->status);
        self::assertSame(200,$this->request('POST',['note'=>'Отказана поръчка'],'/orders/'.$id.'/cancel')->status);
        self::assertSame(200,$this->request('POST',[],'/orders/'.$id.'/cancel')->status);
        self::assertSame(6,(int)$this->scalar('SELECT stock_quantity FROM products WHERE id=?',[$product]));
        self::assertSame(1,(int)$this->scalar("SELECT COUNT(*) FROM product_stock_movements WHERE product_id=? AND reason='order_cancelled'",[$product]));
        self::assertSame(1,(int)$this->scalar('SELECT COUNT(*) FROM order_status_history WHERE order_id=? AND new_status=\'confirmed\'',[$id]));
        self::assertSame(2,(int)$this->scalar('SELECT COUNT(*) FROM order_payment_history WHERE order_id=?',[$id]));
    }

    public function testShippedOrderCannotBeCancelledAndTransitionMustBeSequential(): void
    {
        $product=$this->createProduct('2.00','boolean',null);$id=(int)json_decode($this->createOrder($this->guestOrder($product,1))->body,true)['data']['id'];
        foreach(['confirmed','processing','shipped'] as $status)self::assertSame(200,$this->request('POST',['status'=>$status],'/orders/'.$id.'/status')->status);
        self::assertSame(200,$this->request('POST',['status'=>'delivered'],'/orders/'.$id.'/status')->status);
        self::assertSame(409,$this->request('POST',['note'=>'Too late'],'/orders/'.$id.'/cancel')->status);
    }

    public function testOrderCreationRequiresAdminSessionAndCsrf(): void
    {
        if(session_status()===PHP_SESSION_ACTIVE){$_SESSION=[];session_destroy();}
        $unauthenticated=$this->orders->create(new Request('POST','/orders',[],[],json_encode($this->guestOrder($this->createProduct('3.00','boolean',null),1))));self::assertSame(401,$unauthenticated->status);
        $this->loginAdmin();$missingCsrf=$this->orders->create(new Request('POST','/orders',[],[],json_encode($this->guestOrder($this->createProduct('3.00','boolean',null),1))));self::assertSame(419,$missingCsrf->status);
    }

    public function testSmtpFailureDoesNotRollbackOrderOrStock(): void
    {
        $product=$this->createProduct('6.00','tracked',3);$mailConfig=new Config(['app'=>['env'=>'development','url'=>'http://localhost:8080'],'mail'=>['mailer'=>'smtp','host'=>'127.0.0.1','port'=>1,'username'=>null,'password'=>null,'encryption'=>'','from_address'=>'no-reply@giftcheta.local','from_name'=>'Giftcheta']]);$failureApi=new OrdersApi(self::$db,$this->auth,$mailConfig,new Logger(dirname(__DIR__).'/storage/logs/app.log','debug'));
        $response=$failureApi->create($this->makeRequest('POST',$this->guestOrder($product,1),'/orders'));self::assertSame(201,$response->status,$response->body);$id=(int)json_decode($response->body,true)['data']['id'];
        self::assertSame('new',(string)$this->scalar('SELECT status FROM orders WHERE id=?',[$id]));self::assertSame('pending',(string)$this->scalar('SELECT status FROM order_email_outbox WHERE order_id=?',[$id]));self::assertSame(2,(int)$this->scalar('SELECT stock_quantity FROM products WHERE id=?',[$product]));
    }

    private function loginAdmin(): void
    {
        $role=(int)self::$db->query("SELECT id FROM roles WHERE slug='administrator'")->fetchColumn();$email='orders-test-'.bin2hex(random_bytes(6)).'@example.invalid';self::$db->prepare('INSERT INTO admins (role_id,name,email,password_hash) VALUES (?,?,?,?)')->execute([$role,'Orders Integration Test',$email,password_hash('temporary-test-password',PASSWORD_DEFAULT)]);
        $csrfResponse=$this->auth->csrf(new Request('GET','/csrf'));$csrf=json_decode($csrfResponse->body,true)['data']['csrfToken'];$login=$this->auth->login(new Request('POST','/login',[],['X-CSRF-Token'=>$csrf],json_encode(['email'=>$email,'password'=>'temporary-test-password'],JSON_THROW_ON_ERROR)));self::assertSame(200,$login->status,$login->body);$this->headers=['X-CSRF-Token'=>json_decode($login->body,true)['data']['csrfToken']];
    }

    private function createOrder(array $payload,?string $key=null): \Giftcheta\Http\Response
    { return $this->orders->create($this->makeRequest('POST',$payload,'/orders',$key)); }

    private function request(string $method,array $payload,string $path): \Giftcheta\Http\Response
    { $request=$this->makeRequest($method,$payload,$path);if(str_ends_with($path,'/payment-status'))return $this->orders->changePaymentStatus($request,['id'=>(string)basename(str_replace('/payment-status','',$path))]);if(str_ends_with($path,'/status'))return $this->orders->changeStatus($request,['id'=>(string)basename(str_replace('/status','',$path))]);if(str_ends_with($path,'/cancel'))return $this->orders->cancel($request,['id'=>(string)basename(str_replace('/cancel','',$path))]);return $this->orders->index($request); }

    private function makeRequest(string $method,array $payload,string $path,?string $key=null): Request
    { $headers=$this->headers;if($path==='/orders')$headers['Idempotency-Key']=$key??'test-create-'.bin2hex(random_bytes(12));return new Request($method,$path,[],$headers,json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)); }

    private function guestOrder(int $product,int $quantity,string $shipping='0.00'): array
    { return ['first_name'=>'Тест','last_name'=>'Гост','email'=>'guest@example.invalid','phone'=>'+359888123456','shipping_address'=>['recipient_name'=>'Тест Гост','phone'=>'+359888123456','country_code'=>'BG','city'=>'София','postal_code'=>'1000','address_line1'=>'Тестова улица 1'],'shipping_method'=>'Тестова доставка','shipping_total'=>$shipping,'items'=>[['product_id'=>$product,'quantity'=>$quantity,'customizations'=>[]]]]; }

    private function createProduct(string $price,string $inventoryMode,?int $quantity): int
    { $slug='orders-test-'.bin2hex(random_bytes(8));self::$db->prepare('INSERT INTO products (name,slug,sku,price,status,inventory_mode,stock_quantity,is_available) VALUES (?,?,?,?,\'active\',?,?,1)')->execute(['Пробен артикул',$slug,$slug,$price,$inventoryMode,$quantity]);return (int)self::$db->lastInsertId(); }

    private function createVariant(int $product,string $adjustment,int $stock): int
    { $options=['Размер'=>'M'];$json=json_encode($options,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$key=hash('sha256',json_encode(['size'=>'m']));self::$db->prepare('INSERT INTO product_variants (product_id,name,option_values,variant_key,price_adjustment,inventory_mode,stock_quantity,is_available,is_active) VALUES (?,?,?,?,?,\'tracked\',?,1,1)')->execute([$product,'Размер M',$json,$key,$adjustment,$stock]);return (int)self::$db->lastInsertId(); }

    private function createCustomization(int $product,string $price): int
    { self::$db->prepare("INSERT INTO product_customization_fields (product_id,name,field_type,is_required,position,options,additional_price,is_active) VALUES (?,'Послание','text',1,1,NULL,?,1)")->execute([$product,$price]);return (int)self::$db->lastInsertId(); }

    private function createCustomer(): int
    { $email='registered-'.bin2hex(random_bytes(5)).'@example.invalid';self::$db->prepare("INSERT INTO customers (first_name,last_name,email,status) VALUES ('Ива','Тестова',?,'active')")->execute([$email]);return (int)self::$db->lastInsertId(); }

    private function createAddress(int $customer): int
    { self::$db->prepare("INSERT INTO customer_addresses (customer_id,recipient_name,country_code,city,postal_code,address_line1,is_default) VALUES (?,'Ива Тестова','BG','Клиентски адрес 4','1234','Тестова улица 4',1)")->execute([$customer]);return (int)self::$db->lastInsertId(); }

    private function row(string $sql,array $args=[]): array
    { $query=self::$db->prepare($sql);$query->execute($args);$row=$query->fetch(PDO::FETCH_ASSOC);return $row?:[]; }
    private function scalar(string $sql,array $args=[]): mixed
    { $query=self::$db->prepare($sql);$query->execute($args);return $query->fetchColumn(); }
}
