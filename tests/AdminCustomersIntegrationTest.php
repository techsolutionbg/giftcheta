<?php
declare(strict_types=1);
namespace Giftcheta\Tests;

use Giftcheta\Core\Config;
use Giftcheta\Core\MigrationRunner;
use Giftcheta\Http\Request;
use Giftcheta\Modules\Admin\AuthApi;
use Giftcheta\Modules\Admin\CustomersApi;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class AdminCustomersIntegrationTest extends TestCase
{
    private static PDO $db;
    private static Config $config;
    private AuthApi $auth;
    private CustomersApi $customers;
    private array $headers = [];

    public static function setUpBeforeClass(): void
    {
        Config::loadEnvironment(dirname(__DIR__) . '/.env');
        self::$config = Config::fromDirectory(dirname(__DIR__) . '/config');
        $databaseName = (string) (getenv('TEST_DB_NAME') ?: '');
        if (!str_ends_with($databaseName, '_test')) self::markTestSkipped('Set TEST_DB_NAME to a dedicated database ending in _test.');
        self::$db = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', self::$config->get('database.host'), self::$config->get('database.port'), $databaseName),
            (string) self::$config->get('database.username'),
            (string) self::$config->get('database.password'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
        );
        (new MigrationRunner(self::$db, dirname(__DIR__) . '/database/migrations'))->migrate();
    }

    protected function setUp(): void
    {
        $this->auth = new AuthApi(self::$db, self::$config);
        $this->customers = new CustomersApi(self::$db, $this->auth);
        $this->headers = [];
        if (session_status() === PHP_SESSION_ACTIVE) { $_SESSION = []; session_destroy(); }
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) { $_SESSION = []; session_destroy(); }
    }

    public function testCustomerEndpointsRequireAnAdminSessionAndPermission(): void
    {
        self::assertSame(401, $this->customers->index(new Request('GET', '/api/v1/admin/customers'))->status);
        $this->loginAsRole('test-customer-no-access-' . bin2hex(random_bytes(3)), []);
        self::assertSame(403, $this->customers->index($this->request('GET', []))->status);
    }

    public function testCustomerCrudAddressDefaultsStatusAndTrashLifecycle(): void
    {
        $this->loginAsAdministrator();
        $email = 'customer-' . bin2hex(random_bytes(5)) . '@example.invalid';
        $created = $this->customers->create($this->request('POST', ['first_name' => 'Иван', 'last_name' => 'Петров', 'email' => $email, 'phone' => '+359 88 123 45 67', 'admin_notes' => 'Вътрешна бележка', 'password_hash' => 'must-be-ignored']));
        self::assertSame(201, $created->status);
        $id = (int) json_decode($created->body, true)['data']['id'];
        $detail = $this->customers->show($this->request('GET', []), ['id' => (string) $id]);
        $customer = json_decode($detail->body, true)['data'];
        self::assertSame('+359881234567', $customer['phone']);
        self::assertArrayNotHasKey('password_hash', $customer);
        self::assertNull(self::$db->query('SELECT password_hash FROM customers WHERE id=' . $id)->fetchColumn());

        $first = $this->customers->createAddress($this->request('POST', ['recipient_name' => 'Иван Петров', 'phone' => '+359 88 123 45 67', 'country_code' => 'bg', 'city' => 'София', 'postal_code' => '1000', 'address_line1' => 'ул. Пример 1', 'is_default' => false]), ['id' => (string) $id]);
        self::assertSame(201, $first->status);
        $firstId = (int) json_decode($first->body, true)['data']['id'];
        $second = $this->customers->createAddress($this->request('POST', ['recipient_name' => 'Иван Петров', 'country_code' => 'BG', 'city' => 'Пловдив', 'address_line1' => 'ул. Втора 2']), ['id' => (string) $id]);
        self::assertSame(201, $second->status);
        $secondId = (int) json_decode($second->body, true)['data']['id'];
        $this->customers->updateAddress($this->request('PATCH', ['is_default' => true]), ['id' => (string) $id, 'address_id' => (string) $secondId]);
        $addresses = $this->customers->addresses($this->request('GET', []), ['id' => (string) $id]);
        $addressRows = json_decode($addresses->body, true)['data'];
        self::assertCount(1, array_filter($addressRows, static fn (array $address): bool => (bool) $address['is_default']));
        self::assertSame($secondId, (int) $addressRows[0]['id']);
        self::assertSame(404, $this->customers->updateAddress($this->request('PATCH', ['city' => 'Друг град']), ['id' => (string) ($id + 999), 'address_id' => (string) $secondId])->status);

        self::assertSame(200, $this->customers->update($this->request('PATCH', ['status' => 'blocked', 'phone' => '+44 (20) 1234 5678']), ['id' => (string) $id])->status);
        $updated = json_decode($this->customers->show($this->request('GET', []), ['id' => (string) $id])->body, true)['data'];
        self::assertSame('blocked', $updated['status']);
        self::assertSame('+442012345678', $updated['phone']);
        self::assertSame(200, $this->customers->deleteAddress($this->request('DELETE', []), ['id' => (string) $id, 'address_id' => (string) $secondId])->status);
        $remaining = json_decode($this->customers->addresses($this->request('GET', []), ['id' => (string) $id])->body, true)['data'];
        self::assertCount(1, array_filter($remaining, static fn (array $address): bool => (bool) $address['is_default']));

        self::assertSame(200, $this->customers->trash($this->request('DELETE', []), ['id' => (string) $id])->status);
        self::assertSame(404, $this->customers->show($this->request('GET', []), ['id' => (string) $id])->status);
        $trashQuery = new Request('GET', '/api/v1/admin/customers', ['trash' => 'only'], $this->headers);
        self::assertSame($id, (int) json_decode($this->customers->index($trashQuery)->body, true)['data'][0]['id']);
        self::assertSame(200, $this->customers->restore($this->request('POST', []), ['id' => (string) $id])->status);
        self::assertSame(200, $this->customers->trash($this->request('DELETE', []), ['id' => (string) $id])->status);
        self::assertSame(200, $this->customers->permanentlyDelete($this->request('DELETE', []), ['id' => (string) $id])->status);
    }

    public function testValidationAndRegisteredEmailUniqueness(): void
    {
        $this->loginAsAdministrator();
        self::assertSame(422, $this->customers->create($this->request('POST', ['first_name' => 'Test', 'last_name' => 'User', 'email' => 'not-an-email']))->status);
        self::assertSame(422, $this->customers->create($this->request('POST', ['first_name' => ['unexpected'], 'last_name' => 'User']))->status);
        $email = 'registered-' . bin2hex(random_bytes(5)) . '@example.invalid';
        self::$db->prepare('INSERT INTO customers (first_name,last_name,email,password_hash) VALUES (?,?,?,?)')->execute(['Registered', 'Account', $email, password_hash('test-only-password', PASSWORD_DEFAULT)]);
        $conflict = false;
        try {
            self::$db->prepare('INSERT INTO customers (first_name,last_name,email,password_hash) VALUES (?,?,?,?)')->execute(['Second', 'Account', strtoupper($email), password_hash('another-test-password', PASSWORD_DEFAULT)]);
        } catch (PDOException $exception) {
            $conflict = $exception->getCode() === '23000';
        }
        self::assertTrue($conflict, 'Registered customer emails must be unique case-insensitively.');
    }

    public function testWritesRequireCsrf(): void
    {
        $this->loginAsAdministrator();
        self::assertSame(419, $this->customers->create(new Request('POST', '/api/v1/admin/customers', [], [], json_encode(['first_name' => 'A', 'last_name' => 'B'], JSON_THROW_ON_ERROR)))->status);
    }

    private function loginAsAdministrator(): void
    {
        $roleId = (int) self::$db->query("SELECT id FROM roles WHERE slug='administrator'")->fetchColumn();
        $this->loginWithRoleId($roleId);
    }

    private function loginAsRole(string $slug, array $permissionNames): void
    {
        self::$db->prepare('INSERT INTO roles (name,slug) VALUES (?,?)')->execute(['Customer Test Role', $slug]);
        $roleId = (int) self::$db->lastInsertId();
        foreach ($permissionNames as $permissionName) {
            $statement = self::$db->prepare('SELECT id FROM permissions WHERE name=?');
            $statement->execute([$permissionName]);
            $permissionId = (int) $statement->fetchColumn();
            self::$db->prepare('INSERT INTO role_permissions (role_id,permission_id) VALUES (?,?)')->execute([$roleId, $permissionId]);
        }
        $this->loginWithRoleId($roleId);
    }

    private function loginWithRoleId(int $roleId): void
    {
        $email = 'customer-admin-' . bin2hex(random_bytes(5)) . '@example.invalid';
        self::$db->prepare('INSERT INTO admins (role_id,name,email,password_hash) VALUES (?,?,?,?)')->execute([$roleId, 'Customer Test Admin', $email, password_hash('only-for-tests', PASSWORD_DEFAULT)]);
        $csrf = $this->auth->csrf(new Request('GET', '/csrf'));
        $token = json_decode($csrf->body, true)['data']['csrfToken'];
        $login = $this->auth->login(new Request('POST', '/login', [], ['X-CSRF-Token' => $token], json_encode(['email' => $email, 'password' => 'only-for-tests'], JSON_THROW_ON_ERROR)));
        self::assertSame(200, $login->status);
        $this->headers = ['X-CSRF-Token' => json_decode($login->body, true)['data']['csrfToken']];
    }

    private function request(string $method, array $data): Request
    {
        return new Request($method, '/api/v1/admin/customers', [], $this->headers, json_encode($data, JSON_THROW_ON_ERROR));
    }
}
