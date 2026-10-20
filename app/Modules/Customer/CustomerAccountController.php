<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Customer;

use Giftcheta\Core\Config;
use Giftcheta\Http\Request;
use Giftcheta\Http\Response;
use Giftcheta\Modules\Cart\CartFileStorage;
use Giftcheta\Modules\Cart\CartRepository;
use Giftcheta\Modules\Cart\CartService;
use Giftcheta\Modules\Cart\CartSessionManager;
use Giftcheta\Modules\Storefront\CatalogRepository;
use Giftcheta\Modules\Storefront\StoreSettingsRepository;
use Giftcheta\Modules\Storefront\StorefrontView;
use PDO;
use PDOException;

final class CustomerAccountController
{
    private CartSessionManager $sessions;
    private CartRepository $cartRepository;
    private CartService $cart;
    private CatalogRepository $catalog;
    private StoreSettingsRepository $settings;
    private StorefrontView $view;
    private string $baseUrl;

    public function __construct(private readonly PDO $db,string $projectRoot,Config $config)
    {
        $this->sessions=new CartSessionManager();
        $this->cartRepository=new CartRepository($db);
        $this->cart=new CartService($db,$this->cartRepository,new CartFileStorage($db,$projectRoot));
        $this->catalog=new CatalogRepository($db);
        $this->settings=new StoreSettingsRepository($db);
        $this->view=new StorefrontView($projectRoot.'/app/Modules/Storefront/Views');
        $this->baseUrl=rtrim((string)$config->get('app.url',''),'/');
    }

    public function show(Request $request): Response
    {
        $this->sessions->start();
        $customer=$this->currentCustomer();
        if($customer)return $this->page('account',['customer'=>$customer,'accountFlash'=>$this->consumeFlash(),'profileData'=>$this->profileData((int)$customer['id'])],$request);
        $tab=($request->query['form']??'')==='register'?'register':'login';
        $notice=isset($request->query['registered'])?'Регистрацията е готова. Влезте с имейла и паролата си.':null;
        return $this->authPage($request,$tab,null,[],$notice);
    }

    public function register(Request $request): Response
    {
        $context=$this->sessions->start();
        if($this->currentCustomer())return $this->redirect('/account');
        if(!$this->sessions->validCsrf($request))return $this->authPage($request,'register','Заявката изтече. Обновете страницата и опитайте отново.',$this->safeValues($request),null,419);
        $first=trim((string)($request->form['first_name']??''));
        $last=trim((string)($request->form['last_name']??''));
        $email=strtolower(trim((string)($request->form['email']??'')));
        $phone=trim((string)($request->form['phone']??''));
        $password=is_string($request->form['password']??null)?$request->form['password']:'';
        $confirmation=is_string($request->form['password_confirmation']??null)?$request->form['password_confirmation']:'';
        $values=['first_name'=>$first,'last_name'=>$last,'email'=>$email,'phone'=>$phone];
        if($first===''||mb_strlen($first,'UTF-8')>100||$last===''||mb_strlen($last,'UTF-8')>100)return $this->authPage($request,'register','Попълнете име и фамилия (до 100 знака).',$values);
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)return $this->authPage($request,'register','Въведете валиден имейл адрес.',$values);
        if($phone!==''&&!preg_match('/^\+?[0-9 ()\-.]{5,32}$/D',$phone))return $this->authPage($request,'register','Въведете валиден телефонен номер.',$values);
        if(mb_strlen($password,'UTF-8')<12||mb_strlen($password,'UTF-8')>72||strlen($password)>72)return $this->authPage($request,'register','Паролата трябва да е между 12 и 72 знака.',$values);
        if(!hash_equals($password,$confirmation))return $this->authPage($request,'register','Двете въведени пароли не съвпадат.',$values);

        $existing=$this->db->prepare('SELECT id FROM customers WHERE registered_email=? OR (password_hash IS NOT NULL AND LOWER(email)=?) LIMIT 1');
        $existing->execute([$email,$email]);
        if($existing->fetchColumn())return $this->authPage($request,'register','Този имейл вече има клиентски профил. Влезте в профила си.',$values);
        try{
            $insert=$this->db->prepare("INSERT INTO customers (first_name,last_name,email,phone,password_hash,status) VALUES (?,?,?,?,?,'active')");
            $insert->execute([$first,$last,$email,$phone===''?null:$phone,password_hash($password,PASSWORD_DEFAULT)]);
        }catch(PDOException $error){
            if($error->getCode()==='23000')return $this->authPage($request,'register','Този имейл вече има клиентски профил. Влезте в профила си.',$values);
            throw $error;
        }
        $this->authenticate((int)$this->db->lastInsertId());
        $this->setFlash('Профилът е създаден успешно. Добре дошли в Giftcheta!');
        return $this->redirect('/account');
    }

    public function login(Request $request): Response
    {
        $this->sessions->start();
        if($this->currentCustomer())return $this->redirect('/account');
        if(!$this->sessions->validCsrf($request))return $this->authPage($request,'login','Заявката изтече. Обновете страницата и опитайте отново.',$this->safeValues($request),null,419);
        $email=strtolower(trim((string)($request->form['email']??'')));
        $password=is_string($request->form['password']??null)?$request->form['password']:'';
        $values=['email'=>$email];
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254||$password===''||strlen($password)>4096)return $this->authPage($request,'login','Въведете валиден имейл и парола.',$values);
        $key=hash('sha256','customer|'.strtolower((string)($_SERVER['REMOTE_ADDR']??'unknown')).'|'.$email);
        $attempt=$this->db->prepare('SELECT attempts,locked_until FROM customer_login_attempts WHERE attempt_key=?');
        $attempt->execute([$key]);
        $attemptState=$attempt->fetch(PDO::FETCH_ASSOC);
        if($attemptState&&$attemptState['locked_until']&&strtotime((string)$attemptState['locked_until'])>time())return $this->authPage($request,'login','Има твърде много неуспешни опити. Опитайте отново след 15 минути.',$values,null,429);

        $query=$this->db->prepare("SELECT id,first_name,last_name,email,password_hash,status,deleted_at FROM customers WHERE registered_email=? LIMIT 1");
        $query->execute([$email]);
        $customer=$query->fetch(PDO::FETCH_ASSOC);
        $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $validPassword=password_verify($password,is_array($customer)?(string)$customer['password_hash']:$dummy);
        if(!$customer||!$validPassword||$customer['status']!=='active'||$customer['deleted_at']!==null){
            $this->db->prepare('INSERT INTO customer_login_attempts (attempt_key,attempts,window_started_at,locked_until) VALUES (?,1,UTC_TIMESTAMP(),NULL) ON DUPLICATE KEY UPDATE attempts=IF(window_started_at<UTC_TIMESTAMP()-INTERVAL 15 MINUTE,1,attempts+1),window_started_at=IF(window_started_at<UTC_TIMESTAMP()-INTERVAL 15 MINUTE,UTC_TIMESTAMP(),window_started_at),locked_until=IF(attempts>=4,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 MINUTE),locked_until)')->execute([$key]);
            return $this->authPage($request,'login','Имейлът или паролата не са правилни.',$values,null,401);
        }
        $this->db->prepare('DELETE FROM customer_login_attempts WHERE attempt_key=?')->execute([$key]);
        if(password_needs_rehash((string)$customer['password_hash'],PASSWORD_DEFAULT))$this->db->prepare('UPDATE customers SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$customer['id']]);
        $this->authenticate((int)$customer['id']);
        $this->setFlash('Успешно влязохте в профила си.');
        return $this->redirect('/account');
    }

    public function logout(Request $request): Response
    {
        $this->sessions->start();
        if(!$this->sessions->validCsrf($request))return $this->page('account',['customer'=>$this->currentCustomer(),'accountFlash'=>['kind'=>'error','message'=>'Заявката изтече. Обновете страницата и опитайте отново.']],$request,419);
        unset($_SESSION['customer_id']);
        session_regenerate_id(true);
        $_SESSION['cart_csrf']=bin2hex(random_bytes(32));
        $this->setFlash('Излязохте от профила си.');
        return $this->redirect('/account');
    }

    public function saveProfile(Request $request): Response
    {
        $this->sessions->start();$customer=$this->currentCustomer();if(!$customer)return $this->redirect('/account');
        if(!$this->sessions->validCsrf($request))return $this->accountError('Заявката изтече. Обновете страницата и опитайте отново.',$request,419);
        $first=trim((string)($request->form['first_name']??''));$last=trim((string)($request->form['last_name']??''));$email=strtolower(trim((string)($request->form['email']??'')));$phone=trim((string)($request->form['phone']??''));
        if($first===''||mb_strlen($first,'UTF-8')>100||$last===''||mb_strlen($last,'UTF-8')>100)return $this->accountError('Въведете валидни име и фамилия.',$request);
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)return $this->accountError('Въведете валиден имейл адрес.',$request);
        if($phone!==''&&(!preg_match('/^\+?[0-9 ()\-.]{5,32}$/D',$phone)||strlen($phone)>32))return $this->accountError('Въведете валиден телефонен номер.',$request);
        $duplicate=$this->db->prepare('SELECT id FROM customers WHERE registered_email=? AND id<>? LIMIT 1');$duplicate->execute([$email,$customer['id']]);if($duplicate->fetchColumn())return $this->accountError('Този имейл вече е свързан с друг профил.',$request);
        $this->db->prepare('UPDATE customers SET first_name=?,last_name=?,email=?,phone=? WHERE id=?')->execute([$first,$last,$email,$phone===''?null:$phone,$customer['id']]);
        $this->setFlash('Контактните данни са обновени.');return $this->redirect('/account');
    }

    public function saveDelivery(Request $request): Response
    {
        $this->sessions->start();$customer=$this->currentCustomer();if(!$customer)return $this->redirect('/account');
        if(!$this->sessions->validCsrf($request))return $this->accountError('Заявката изтече. Обновете страницата и опитайте отново.',$request,419);
        $name=trim((string)($request->form['recipient_name']??''));$phone=trim((string)($request->form['delivery_phone']??$customer['phone']??''));$city=trim((string)($request->form['city']??''));$postal=trim((string)($request->form['postal_code']??''));$line1=trim((string)($request->form['address_line1']??''));$line2=trim((string)($request->form['address_line2']??''));
        if($name==='')$name=trim($customer['first_name'].' '.$customer['last_name']);
        if($name===''||mb_strlen($name,'UTF-8')>160||($phone!==''&&!preg_match('/^\+?[0-9 ()\-.]{5,32}$/D',$phone))||mb_strlen($city,'UTF-8')<2||mb_strlen($city,'UTF-8')>100||mb_strlen($postal,'UTF-8')>20||$line1===''||mb_strlen($line1,'UTF-8')>200||mb_strlen($line2,'UTF-8')>200)return $this->accountError('Проверете получателя, телефона и адреса за доставка.',$request);
        $this->db->beginTransaction();try{$q=$this->db->prepare('SELECT id FROM customer_addresses WHERE customer_id=? AND is_default=1 LIMIT 1 FOR UPDATE');$q->execute([$customer['id']]);$id=$q->fetchColumn();$this->db->prepare('UPDATE customer_addresses SET is_default=0 WHERE customer_id=?')->execute([$customer['id']]);if($id!==false){$this->db->prepare('UPDATE customer_addresses SET recipient_name=?,phone=?,country_code=\'BG\',city=?,postal_code=?,address_line1=?,address_line2=?,is_default=1 WHERE id=? AND customer_id=?')->execute([$name,$phone===''?null:$phone,$city,$postal===''?null:$postal,$line1,$line2===''?null:$line2,$id,$customer['id']]);}else{$this->db->prepare('INSERT INTO customer_addresses (customer_id,recipient_name,phone,country_code,city,postal_code,address_line1,address_line2,is_default) VALUES (?,?,?,\'BG\',?,?,?,?,1)')->execute([$customer['id'],$name,$phone===''?null:$phone,$city,$postal===''?null:$postal,$line1,$line2===''?null:$line2]);}$this->db->commit();}catch(\Throwable $error){if($this->db->inTransaction())$this->db->rollBack();throw $error;}
        $this->setFlash('Адресът за доставка е запазен.');return $this->redirect('/account');
    }

    public function saveBilling(Request $request): Response
    {
        $this->sessions->start();$customer=$this->currentCustomer();if(!$customer)return $this->redirect('/account');
        if(!$this->sessions->validCsrf($request))return $this->accountError('Заявката изтече. Обновете страницата и опитайте отново.',$request,419);
        $type=($request->form['entity_type']??'person')==='company'?'company':'person';$recipient=trim((string)($request->form['billing_recipient']??''));$company=trim((string)($request->form['company_name']??''));$companyId=trim((string)($request->form['company_id']??''));$vat=trim((string)($request->form['vat_number']??''));$city=trim((string)($request->form['billing_city']??''));$postal=trim((string)($request->form['billing_postal_code']??''));$line1=trim((string)($request->form['billing_address_line1']??''));$line2=trim((string)($request->form['billing_address_line2']??''));
        if($recipient===''||mb_strlen($recipient,'UTF-8')>160||($type==='company'&&($company===''||mb_strlen($company,'UTF-8')>190||$companyId===''||mb_strlen($companyId,'UTF-8')>32))||mb_strlen($vat,'UTF-8')>32||$city===''||mb_strlen($city,'UTF-8')>100||mb_strlen($postal,'UTF-8')>20||$line1===''||mb_strlen($line1,'UTF-8')>200||mb_strlen($line2,'UTF-8')>200)return $this->accountError('Попълнете получател и валиден адрес за фактуриране. За фирма са нужни име и ЕИК.',$request);
        $sql='INSERT INTO customer_billing_profiles (customer_id,entity_type,recipient_name,company_name,company_id,vat_number,country_code,city,postal_code,address_line1,address_line2) VALUES (?,?,?,?,?,?,\'BG\',?,?,?,?) ON DUPLICATE KEY UPDATE entity_type=VALUES(entity_type),recipient_name=VALUES(recipient_name),company_name=VALUES(company_name),company_id=VALUES(company_id),vat_number=VALUES(vat_number),country_code=VALUES(country_code),city=VALUES(city),postal_code=VALUES(postal_code),address_line1=VALUES(address_line1),address_line2=VALUES(address_line2)';
        $this->db->prepare($sql)->execute([$customer['id'],$type,$recipient,$type==='company'?$company:null,$type==='company'?$companyId:null,$vat===''?null:$vat,$city,$postal===''?null:$postal,$line1,$line2===''?null:$line2]);
        $this->setFlash('Данните за фактуриране са запазени.');return $this->redirect('/account');
    }

    private function accountError(string $message,Request $request,int $status=422): Response
    {
        $customer=$this->currentCustomer();if(!$customer)return $this->redirect('/account');
        return $this->page('account',['customer'=>$customer,'profileData'=>$this->profileData((int)$customer['id']),'accountFlash'=>['kind'=>'error','message'=>$message]],$request,$status);
    }

    private function profileData(int $customerId): array
    {
        $address=$this->db->prepare('SELECT recipient_name,phone,city,postal_code,address_line1,address_line2 FROM customer_addresses WHERE customer_id=? ORDER BY is_default DESC,id LIMIT 1');$address->execute([$customerId]);
        $billing=$this->db->prepare('SELECT entity_type,recipient_name,company_name,company_id,vat_number,city,postal_code,address_line1,address_line2 FROM customer_billing_profiles WHERE customer_id=? LIMIT 1');$billing->execute([$customerId]);
        return ['delivery'=>$address->fetch(PDO::FETCH_ASSOC)?:[],'billing'=>$billing->fetch(PDO::FETCH_ASSOC)?:[]];
    }

    private function currentCustomer(): ?array
    {
        $id=filter_var($_SESSION['customer_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if($id===false||$id===null)return null;
        $query=$this->db->prepare("SELECT id,first_name,last_name,email,phone,created_at FROM customers WHERE id=? AND status='active' AND deleted_at IS NULL AND password_hash IS NOT NULL LIMIT 1");
        $query->execute([(int)$id]);
        $customer=$query->fetch(PDO::FETCH_ASSOC);
        if(!$customer)unset($_SESSION['customer_id']);
        return $customer?:null;
    }

    private function authenticate(int $customerId): void
    {
        session_regenerate_id(true);
        $_SESSION['customer_id']=$customerId;
        $_SESSION['cart_csrf']=bin2hex(random_bytes(32));
    }

    private function authPage(Request $request,string $tab,?string $error,array $values,?string $notice=null,int $status=200): Response
    { return $this->page('account-auth',['activeTab'=>$tab,'authError'=>$error,'authValues'=>$values,'authNotice'=>$notice,'csrfToken'=>$this->sessions->start()['csrf']],$request,$status); }

    private function page(string $template,array $data,Request $request,int $status=200): Response
    {
        $context=$this->sessions->start();
        $cart=$this->cart->summary($context['key']);
        $data+=['title'=>'Клиентски профил','description'=>'Вход и регистрация в Giftcheta.','canonical'=>$this->baseUrl.'/account','schema'=>null,'noindex'=>true,'baseUrl'=>$this->baseUrl,'view'=>$this->view,'headerCategories'=>$this->catalog->categories(),'cartCount'=>$cart['count'],'cartSubtotalCents'=>$cart['subtotal_cents'],'csrfToken'=>$context['csrf']];
        $data+=$this->settings->shipping();
        $body=$this->view->render($template,$data);
        $headers=['Content-Type'=>'text/html; charset=utf-8','X-Content-Type-Options'=>'nosniff','Referrer-Policy'=>'strict-origin-when-cross-origin','Cache-Control'=>'private, no-store, max-age=0'];
        if($this->isNavigationRequest($request)&&preg_match('/<main\b[^>]*>.*?<\/main>/s',$body,$match)){
            $headers['X-Page-Title']=rawurlencode((string)$data['title']);
            $headers['X-Page-Description']=rawurlencode((string)$data['description']);
            $headers['X-Page-Canonical']=rawurlencode((string)$data['canonical']);
            $headers['X-Page-Robots']='noindex,follow';
            return new Response($match[0],$status,$headers);
        }
        return new Response($body,$status,$headers);
    }

    private function isNavigationRequest(Request $request): bool
    { foreach($request->headers as $name=>$value)if(strtolower((string)$name)==='accept'&&str_contains((string)$value,'application/vnd.giftcheta.fragment+html'))return true;return false; }

    private function safeValues(Request $request): array
    { return ['first_name'=>trim((string)($request->form['first_name']??'')),'last_name'=>trim((string)($request->form['last_name']??'')),'email'=>strtolower(trim((string)($request->form['email']??''))),'phone'=>trim((string)($request->form['phone']??''))]; }
    private function setFlash(string $message,string $kind='success'): void { $_SESSION['account_flash']=['message'=>$message,'kind'=>$kind]; }
    private function consumeFlash(): ?array { $flash=$_SESSION['account_flash']??null;unset($_SESSION['account_flash']);return is_array($flash)?$flash:null; }
    private function redirect(string $path): Response { return new Response('',303,['Location'=>$path,'Cache-Control'=>'private, no-store']); }
}
