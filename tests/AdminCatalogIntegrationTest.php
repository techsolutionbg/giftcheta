<?php
declare(strict_types=1);
namespace Giftcheta\Tests;

use Giftcheta\Core\Config;
use Giftcheta\Core\Database;
use Giftcheta\Core\MigrationRunner;
use Giftcheta\Http\Request;
use Giftcheta\Modules\Admin\AuthApi;
use Giftcheta\Modules\Admin\CatalogApi;
use PHPUnit\Framework\TestCase;
use PDO;

final class AdminCatalogIntegrationTest extends TestCase
{
    private static PDO $db;
    private static Config $config;
    private static string $testName;
    private AuthApi $auth;
    private CatalogApi $catalog;
    private array $headers = [];

    public static function setUpBeforeClass(): void
    {
        Config::loadEnvironment(dirname(__DIR__) . '/.env');
        self::$config = Config::fromDirectory(dirname(__DIR__) . '/config');
        self::$testName = (string) (getenv('TEST_DB_NAME') ?: '');
        if (!str_ends_with(self::$testName, '_test')) self::markTestSkipped('Set TEST_DB_NAME to a dedicated database ending in _test.');
        self::$db = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', self::$config->get('database.host'), self::$config->get('database.port'), self::$testName),
            (string) self::$config->get('database.username'),
            (string) self::$config->get('database.password'),
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]
        );
        (new MigrationRunner(self::$db, dirname(__DIR__) . '/database/migrations'))->migrate();
    }

    protected function setUp(): void
    {
        $this->auth = new AuthApi(self::$db, self::$config);
        $this->catalog = new CatalogApi(self::$db, $this->auth, dirname(__DIR__));
        $this->headers = [];
        if (session_status() === PHP_SESSION_ACTIVE) { $_SESSION = []; session_destroy(); }
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) { $_SESSION = []; session_destroy(); }
    }

    public function testAdminRequiresSessionAndLoginUsesCsrf(): void
    {
        $unauthenticated = $this->catalog->categories(new Request('GET','/api/v1/admin/categories'));
        self::assertSame(401,$unauthenticated->status);
        $this->createAdminAndLogin();
        self::assertSame(200,$this->catalog->categories(new Request('GET','/api/v1/admin/categories'))->status);
    }

    public function testCategoryCyclesAndDuplicateVariantsAreRejected(): void
    {
        $this->createAdminAndLogin();
        $a=$this->catalog->createCategory($this->request('POST',['name'=>'Test root '.bin2hex(random_bytes(3))]));
        self::assertSame(201,$a->status);$aId=json_decode($a->body,true)['data']['id'];
        $b=$this->catalog->createCategory($this->request('POST',['name'=>'Test child '.bin2hex(random_bytes(3)),'parent_id'=>$aId]));
        self::assertSame(201,$b->status);$bId=json_decode($b->body,true)['data']['id'];
        self::assertSame(422,$this->catalog->updateCategory($this->request('PATCH',['parent_id'=>$bId]),['id'=>(string)$aId])->status);

        $product=$this->catalog->createProduct($this->request('POST',['name'=>'Test item '.bin2hex(random_bytes(3)),'price'=>'15.00','inventory_mode'=>'tracked','stock_quantity'=>4,'category_ids'=>[$aId]]));
        self::assertSame(201,$product->status);$productId=json_decode($product->body,true)['data']['id'];
        $variant=['option_values'=>['Color'=>'Black','Size'=>'M'],'sku'=>'test-'.bin2hex(random_bytes(3))];
        self::assertSame(201,$this->catalog->createVariant($this->request('POST',$variant),['id'=>(string)$productId])->status);
        self::assertSame(409,$this->catalog->createVariant($this->request('POST',['option_values'=>['Size'=>'m','Color'=>'black']]),['id'=>(string)$productId])->status);
        self::assertSame(200,$this->catalog->deleteProduct($this->request('DELETE',[]),['id'=>(string)$productId])->status);
        self::assertSame(409,$this->catalog->deleteProduct($this->request('DELETE',[]),['id'=>(string)$productId])->status);
    }

    public function testMigrationRunnerIsIdempotent(): void
    {
        self::assertSame([], (new MigrationRunner(self::$db, dirname(__DIR__) . '/database/migrations'))->migrate());
        self::assertSame([], (new MigrationRunner(self::$db, dirname(__DIR__) . '/database/migrations'))->migrate());
    }

    private function createAdminAndLogin(): void
    {
        $email='test-'.bin2hex(random_bytes(6)).'@example.invalid';$role=(int)self::$db->query("SELECT id FROM roles WHERE slug='administrator'")->fetchColumn();
        self::$db->prepare('INSERT INTO admins (role_id,name,email,password_hash) VALUES (?,?,?,?)')->execute([$role,'Integration Test',$email,password_hash('not-a-default-password',PASSWORD_DEFAULT)]);
        $csrf=$this->auth->csrf(new Request('GET','/csrf'));$token=json_decode($csrf->body,true)['data']['csrfToken'];
        $response=$this->auth->login(new Request('POST','/login',[],['X-CSRF-Token'=>$token],json_encode(['email'=>$email,'password'=>'not-a-default-password'],JSON_THROW_ON_ERROR)));
        self::assertSame(200,$response->status);$this->headers=['X-CSRF-Token'=>json_decode($response->body,true)['data']['csrfToken']];
    }

    private function request(string $method,array $data): Request
    { return new Request($method,'/api/v1/admin',[], $this->headers,json_encode($data,JSON_THROW_ON_ERROR)); }
}
