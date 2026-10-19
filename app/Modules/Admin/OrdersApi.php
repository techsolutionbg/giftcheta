<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Admin;

use Giftcheta\Core\Config;
use Giftcheta\Core\Logger;
use Giftcheta\Core\SmtpMailer;
use Giftcheta\Http\Request;
use Giftcheta\Http\Response;
use PDO;
use PDOException;
use Throwable;

/** Administrative order workflows. Order writes and stock ledger movements are transactional. */
final class OrdersApi
{
    private const MAX_ORDER_CENTS = 999_999_999_999;
    private const STATUS_TRANSITIONS = [
        'new' => ['confirmed'],
        'confirmed' => ['processing'],
        'processing' => ['shipped'],
        'shipped' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public function __construct(private readonly PDO $db, private readonly AuthApi $auth, private readonly Config $config, private readonly Logger $logger) {}

    public function index(Request $request): Response
    {
        if ($error = $this->auth->guard($request, 'orders.view', false)) return $error;
        $this->dispatchOutbox(3);
        $page = max(1, (int) ($request->query['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($request->query['per_page'] ?? 20)));
        $where = [];
        $args = [];
        $search = trim((string) ($request->query['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(o.order_number LIKE ? OR o.customer_email LIKE ? OR o.customer_phone LIKE ? OR CONCAT_WS(" ",o.customer_first_name,o.customer_last_name) LIKE ?)';
            $like = '%' . mb_substr($search, 0, 120) . '%';
            array_push($args, $like, $like, $like, $like);
        }
        if (in_array($request->query['status'] ?? '', ['new','confirmed','processing','shipped','delivered','cancelled'], true)) { $where[] = 'o.status=?'; $args[] = $request->query['status']; }
        if (in_array($request->query['payment_status'] ?? '', ['unpaid','paid','refunded'], true)) { $where[] = 'o.payment_status=?'; $args[] = $request->query['payment_status']; }
        $from = $this->dateValue($request->query['date_from'] ?? null);
        $to = $this->dateValue($request->query['date_to'] ?? null);
        if (($request->query['date_from'] ?? '') !== '' && $from === null || ($request->query['date_to'] ?? '') !== '' && $to === null) return $this->error('validation_error', 'Филтърът по дата е невалиден.', 422);
        if ($from !== null) { $where[] = 'o.created_at >= ?'; $args[] = $from . ' 00:00:00'; }
        if ($to !== null) { $where[] = 'o.created_at < DATE_ADD(?, INTERVAL 1 DAY)'; $args[] = $to; }
        if ($from !== null && $to !== null && $from > $to) return $this->error('validation_error', 'Началната дата не може да е след крайната.', 422);
        $sorts = ['order_number'=>'o.order_number','customer'=>'o.customer_last_name','created_at'=>'o.created_at','grand_total'=>'o.grand_total','status'=>'o.status','payment_status'=>'o.payment_status'];
        $sort = $sorts[(string) ($request->query['sort'] ?? 'created_at')] ?? 'o.created_at';
        $direction = strtolower((string) ($request->query['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $clause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = $this->db->prepare('SELECT COUNT(*) FROM orders o' . $clause);
        $count->execute($args);
        $total = (int) $count->fetchColumn();
        $offset = ($page - 1) * $perPage;
        $query = $this->db->prepare('SELECT o.id,o.order_number,o.customer_id,o.customer_email,o.customer_phone,o.customer_first_name,o.customer_last_name,o.status,o.payment_status,o.fulfillment_status,o.payment_method,o.shipping_method,o.currency_code,o.subtotal,o.discount_total,o.shipping_total,o.tax_total,o.grand_total,o.created_at,o.updated_at,(SELECT COUNT(*) FROM order_items oi WHERE oi.order_id=o.id) AS item_count FROM orders o' . $clause . " ORDER BY {$sort} {$direction},o.id DESC LIMIT {$perPage} OFFSET {$offset}");
        $query->execute($args);
        return Response::json(['data' => $query->fetchAll(PDO::FETCH_ASSOC), 'meta' => ['page'=>$page,'per_page'=>$perPage,'total'=>$total,'pages'=>(int) ceil($total/$perPage)]]);
    }

    public function show(Request $request, array $params): Response
    {
        if ($error = $this->auth->guard($request, 'orders.view', false)) return $error;
        $this->dispatchOutbox(3);
        $order = $this->findOrder((int) ($params['id'] ?? 0));
        if (!$order) return $this->notFound();
        $addresses = $this->db->prepare('SELECT id,address_type,recipient_name,phone,country_code,city,postal_code,address_line1,address_line2 FROM order_addresses WHERE order_id=? ORDER BY address_type,id');
        $addresses->execute([$order['id']]);
        $order['addresses'] = $addresses->fetchAll(PDO::FETCH_ASSOC);
        $items = $this->db->prepare('SELECT id,product_id,product_variant_id,product_name,sku,variant_name,quantity,unit_price,customization_total,discount_total,line_total FROM order_items WHERE order_id=? ORDER BY id');
        $items->execute([$order['id']]);
        $order['items'] = $items->fetchAll(PDO::FETCH_ASSOC);
        $customizations = $this->db->prepare('SELECT c.id,c.order_item_id,c.field_identifier,c.field_label,c.field_type,c.value_text,c.file_reference,c.price_adjustment FROM order_item_customizations c JOIN order_items i ON i.id=c.order_item_id WHERE i.order_id=? ORDER BY c.order_item_id,c.id');
        $customizations->execute([$order['id']]);
        $byItem = [];
        foreach ($customizations->fetchAll(PDO::FETCH_ASSOC) as $customization) $byItem[(int)$customization['order_item_id']][] = $customization;
        foreach ($order['items'] as &$item) $item['customizations'] = $byItem[(int)$item['id']] ?? [];
        unset($item);
        $outbox = $this->db->prepare('SELECT event_key,status,attempt_count,last_error,sent_at,created_at FROM order_email_outbox WHERE order_id=? ORDER BY id');
        $outbox->execute([$order['id']]);
        $order['notifications'] = $outbox->fetchAll(PDO::FETCH_ASSOC);
        if ($this->hasPermission('orders.view_history')) {
            $history = $this->db->prepare('SELECT h.id,h.previous_status,h.new_status,h.note,h.changed_by_admin_id,h.created_at,a.name AS admin_name FROM order_status_history h LEFT JOIN admins a ON a.id=h.changed_by_admin_id WHERE h.order_id=? ORDER BY h.id DESC');
            $history->execute([$order['id']]);
            $order['history'] = $history->fetchAll(PDO::FETCH_ASSOC);
        }
        if ($this->hasPermission('orders.view_history')) {
            $paymentHistory=$this->db->prepare('SELECT h.id,h.previous_payment_status,h.new_payment_status,h.note,h.changed_by_admin_id,h.created_at,a.name AS admin_name FROM order_payment_history h LEFT JOIN admins a ON a.id=h.changed_by_admin_id WHERE h.order_id=? ORDER BY h.id DESC');
            $paymentHistory->execute([$order['id']]);
            $order['payment_history']=$paymentHistory->fetchAll(PDO::FETCH_ASSOC);
        }
        return Response::json(['data' => $order]);
    }

    public function history(Request $request, array $params): Response
    {
        if ($error = $this->auth->guard($request, 'orders.view_history', false)) return $error;
        $id = $this->positiveId($params['id'] ?? null);
        if ($id === null || !$this->findOrder($id)) return $this->notFound();
        $query = $this->db->prepare("SELECT 'status' AS event_type,h.id,h.previous_status AS previous_value,h.new_status AS new_value,h.note,h.changed_by_admin_id,h.created_at,a.name AS admin_name FROM order_status_history h LEFT JOIN admins a ON a.id=h.changed_by_admin_id WHERE h.order_id=? UNION ALL SELECT 'payment' AS event_type,p.id,p.previous_payment_status AS previous_value,p.new_payment_status AS new_value,p.note,p.changed_by_admin_id,p.created_at,a.name AS admin_name FROM order_payment_history p LEFT JOIN admins a ON a.id=p.changed_by_admin_id WHERE p.order_id=? ORDER BY created_at DESC,id DESC");
        $query->execute([$id,$id]);
        return Response::json(['data' => $query->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function uploadCustomizationImage(Request $request): Response
    {
        if ($error = $this->auth->guard($request, 'orders.create', true)) return $error;
        $file=$request->files['file']??null;
        if(!is_array($file)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name']))return $this->error('upload_invalid','Изберете изображение за персонализация.',422);
        $size=(int)($file['size']??0);if($size<1||$size>10*1024*1024)return $this->error('upload_too_large','Изображението трябва да е до 10 MB.',422);
        $finfo=new \finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($file['tmp_name']);$extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($extensions[$mime]))return $this->error('upload_invalid_type','Поддържат се JPEG, PNG и WebP изображения.',422);
        $dimensions=@getimagesize($file['tmp_name']);if(!$dimensions||$dimensions[0]<1||$dimensions[1]<1||$dimensions[0]>6000||$dimensions[1]>6000)return $this->error('upload_invalid_image','Изображението има невалидни размери.',422);
        $directory=$this->privateOrderFilesDirectory();if(!is_dir($directory)&&!mkdir($directory,0750,true)&&!is_dir($directory))return $this->error('upload_storage_unavailable','Частното хранилище за файлове не е достъпно.',503);
        $this->cleanupOldUnlinkedFiles($directory);
        $reference=bin2hex(random_bytes(32)).'.'.$extensions[$mime];$target=$directory.DIRECTORY_SEPARATOR.$reference;
        if(!move_uploaded_file($file['tmp_name'],$target))return $this->error('upload_failed','Изображението не можа да бъде запазено.',500);
        chmod($target,0640);
        return Response::json(['data'=>['file_reference'=>$reference,'mime_type'=>$mime,'file_size'=>$size]],201);
    }

    public function customizationFile(Request $request,array $params): Response
    {
        if($error=$this->auth->guard($request,'orders.view',false))return $error;
        $id=$this->positiveId($params['customization_id']??null);if($id===null)return $this->notFound();
        $query=$this->db->prepare('SELECT c.file_reference FROM order_item_customizations c JOIN order_items i ON i.id=c.order_item_id JOIN orders o ON o.id=i.order_id WHERE c.id=? AND c.field_type=\'image\' AND c.file_reference IS NOT NULL');$query->execute([$id]);$reference=$query->fetchColumn();
        if(!is_string($reference)||!preg_match('/^[a-f0-9]{64}\.(?:jpg|png|webp)$/D',$reference))return $this->notFound();
        $path=$this->privateOrderFilesDirectory().DIRECTORY_SEPARATOR.$reference;if(!is_file($path))return $this->notFound();
        $mime=match(pathinfo($reference,PATHINFO_EXTENSION)){'jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp',default=>'application/octet-stream'};
        return new Response((string)file_get_contents($path),200,['Content-Type'=>$mime,'Content-Length'=>(string)filesize($path),'Content-Disposition'=>'inline; filename="customization.'.pathinfo($reference,PATHINFO_EXTENSION).'"','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff','Content-Security-Policy'=>"default-src 'none'; sandbox"]);
    }

    public function create(Request $request): Response
    {
        if ($error = $this->auth->guard($request, 'orders.create', true)) return $error;
        $input = $request->json();
        $key = $this->header($request, 'Idempotency-Key');
        if ($key === '' || !preg_match('/^[A-Za-z0-9._:-]{16,100}$/D', $key)) return $this->error('idempotency_key_required', 'Липсва валиден ключ за еднократно създаване на поръчката.', 400);
        $bodyHash = hash('sha256', $request->body);
        $keyHash = hash('sha256', $key);
        $existing = $this->findIdempotentOrder($keyHash);
        if ($existing) return hash_equals($existing['idempotency_hash'], $bodyHash)
            ? Response::json(['data'=>['id'=>(int)$existing['id'],'order_number'=>$existing['order_number'],'replayed'=>true]], 200)
            : $this->error('idempotency_conflict', 'Този ключ вече е използван с различни данни.', 409);

        $contact = $this->contactInput($input);
        $address = $this->validateAddress($input['shipping_address'] ?? null, $contact);
        if ($address instanceof Response) return $address;
        $shipping = $this->moneyCents($input['shipping_total'] ?? '0.00');
        if ($shipping === null || $shipping < 0) return $this->error('validation_error', 'Цената за доставка трябва да е неотрицателна сума с до два знака след десетичната запетая.', 422);
        $shippingMethod = trim((string) ($input['shipping_method'] ?? 'Ръчно определена доставка'));
        if ($shippingMethod === '' || mb_strlen($shippingMethod) > 100) return $this->error('validation_error', 'Посочете валиден метод за доставка.', 422);
        $itemsInput = $input['items'] ?? null;
        if (!is_array($itemsInput) || count($itemsInput) < 1 || count($itemsInput) > 100) return $this->error('validation_error', 'Поръчката трябва да съдържа от 1 до 100 продукта.', 422);

        $this->db->beginTransaction();
        try {
            $existing = $this->findIdempotentOrder($keyHash, true);
            if ($existing) {
                $this->db->rollBack();
                return hash_equals($existing['idempotency_hash'], $bodyHash)
                    ? Response::json(['data'=>['id'=>(int)$existing['id'],'order_number'=>$existing['order_number'],'replayed'=>true]], 200)
                    : $this->error('idempotency_conflict', 'Този ключ вече е използван с различни данни.', 409);
            }
            $customerId = $this->positiveId($input['customer_id'] ?? null);
            if (($input['customer_id'] ?? null) !== null && $customerId === null) return $this->rollbackError('validation_error', 'Невалиден клиент.', 422);
            if ($customerId !== null) {
                $customerQuery = $this->db->prepare('SELECT id,first_name,last_name,email,phone,status FROM customers WHERE id=? AND deleted_at IS NULL LIMIT 1 FOR UPDATE');
                $customerQuery->execute([$customerId]);
                $customer = $customerQuery->fetch(PDO::FETCH_ASSOC);
                if (!$customer) return $this->rollbackError('customer_not_found', 'Клиентът не е намерен.', 404);
                $contact = $this->mergeContact($contact, $customer);
            }
            $contact=$this->validateContact($contact);
            if($contact instanceof Response)return $this->rollbackResponse($contact);
            if (!filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) return $this->rollbackError('validation_error', 'Въведете валиден имейл за поръчката.', 422);
            $address = $this->resolveAddress($address, $customerId, $contact);
            if ($address instanceof Response) return $this->rollbackResponse($address);

            $preparedItems = $this->prepareItems($itemsInput);
            if ($preparedItems instanceof Response) return $this->rollbackResponse($preparedItems);
            $productIds = array_values(array_unique(array_column($preparedItems, 'product_id')));
            sort($productIds, SORT_NUMERIC);
            $products = $this->lockProducts($productIds);
            if ($products instanceof Response) return $this->rollbackResponse($products);
            $variantIds = array_values(array_unique(array_filter(array_column($preparedItems, 'variant_id'))));
            sort($variantIds, SORT_NUMERIC);
            $variants = $this->lockVariants($variantIds);
            if ($variants instanceof Response) return $this->rollbackResponse($variants);

            $hydrated = [];
            $stockRequirements = [];
            $subtotal = 0;
            foreach ($preparedItems as $line) {
                $product = $products[$line['product_id']] ?? null;
                if (!$product || $product['deleted_at'] !== null || $product['status'] !== 'active') return $this->rollbackError('product_unavailable', 'Един от избраните продукти вече не е активен.', 409);
                $variant = $line['variant_id'] === null ? null : ($variants[$line['variant_id']] ?? null);
                if ($line['variant_id'] !== null && (!$variant || (int)$variant['product_id'] !== $line['product_id'] || !(int)$variant['is_active'])) return $this->rollbackError('variant_unavailable', 'Един от избраните варианти вече не е наличен.', 409);
                $hasVariants = $this->hasActiveVariants($line['product_id']);
                if ($hasVariants && $variant === null) return $this->rollbackError('variant_required', 'Изберете вариант за всеки продукт, който има варианти.', 422);
                if ($variant !== null && !(int)$variant['is_available']) return $this->rollbackError('out_of_stock', 'Избран вариант е изчерпан.', 409);
                if ($variant === null && !(int)$product['is_available']) return $this->rollbackError('out_of_stock', 'Избран продукт е изчерпан.', 409);
                $unitCents = $this->effectiveUnitPrice($product, $variant);
                if ($unitCents === null || $unitCents < 0) return $this->rollbackError('invalid_price', 'Цената на продукт от каталога е невалидна.', 409);
                $customizations = $this->validateCustomizations($line['product_id'], $line['customizations']);
                if ($customizations instanceof Response) return $this->rollbackResponse($customizations);
                $customPerUnit = array_sum(array_column($customizations, 'price_cents'));
                $lineSubtotal = ($unitCents + $customPerUnit) * $line['quantity'];
                if ($lineSubtotal > self::MAX_ORDER_CENTS) return $this->rollbackError('order_total_too_large', 'Сумата на поръчката надвишава допустимия размер.', 422);
                $subtotal += $lineSubtotal;
                if ($subtotal > self::MAX_ORDER_CENTS) return $this->rollbackError('order_total_too_large', 'Сумата на поръчката надвишава допустимия размер.', 422);
                $hydrated[] = [
                    ...$line,
                    'product_name'=>(string)$product['name'],
                    'sku'=>$variant['sku'] ?? $product['sku'],
                    'variant_name'=>$variant['name'] ?? null,
                    'unit_cents'=>$unitCents,
                    'custom_cents'=>$customPerUnit * $line['quantity'],
                    'line_cents'=>$lineSubtotal,
                    'customizations'=>$customizations,
                ];
                if ($variant !== null && $variant['inventory_mode'] === 'tracked') $stockRequirements['v:' . $line['variant_id']] = ['variant_id'=>$line['variant_id'],'product_id'=>$line['product_id'],'qty'=>($stockRequirements['v:' . $line['variant_id']]['qty'] ?? 0) + $line['quantity']];
                elseif ($variant === null && $product['inventory_mode'] === 'tracked') $stockRequirements['p:' . $line['product_id']] = ['variant_id'=>null,'product_id'=>$line['product_id'],'qty'=>($stockRequirements['p:' . $line['product_id']]['qty'] ?? 0) + $line['quantity']];
            }
            $customerNote = $this->optionalText($input['customer_note'] ?? null, 4000);
            $adminNote = $this->optionalText($input['admin_note'] ?? null, 4000);
            if ($customerNote instanceof Response || $adminNote instanceof Response) return $this->rollbackResponse($customerNote instanceof Response ? $customerNote : $adminNote);
            if($subtotal+$shipping>self::MAX_ORDER_CENTS)return $this->rollbackError('order_total_too_large','Сумата на поръчката надвишава допустимия размер.',422);
            $orderNumber = $this->newOrderNumber();
            $shippingName = trim($contact['first_name'] . ' ' . $contact['last_name']);
            if ($address['recipient_name'] === '') $address['recipient_name'] = $shippingName;
            $insert = $this->db->prepare('INSERT INTO orders (order_number,idempotency_key,idempotency_hash,customer_id,customer_email,customer_phone,customer_first_name,customer_last_name,status,payment_status,fulfillment_status,payment_method,shipping_method,currency_code,subtotal,discount_total,shipping_total,tax_total,grand_total,customer_note,admin_note,created_by_admin_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $insert->execute([$orderNumber,$keyHash,$bodyHash,$customerId,$contact['email'],$contact['phone'],$contact['first_name'],$contact['last_name'],'new','unpaid','unfulfilled','cash_on_delivery',$shippingMethod,'EUR',$this->formatCents($subtotal),'0.00',$this->formatCents($shipping),'0.00',$this->formatCents($subtotal+$shipping),$customerNote,$adminNote,$_SESSION['admin_id']]);
            $orderId = (int) $this->db->lastInsertId();
            $addressInsert = $this->db->prepare('INSERT INTO order_addresses (order_id,address_type,recipient_name,phone,country_code,city,postal_code,address_line1,address_line2) VALUES (?,\'shipping\',?,?,?,?,?,?,?)');
            $addressInsert->execute([$orderId,$address['recipient_name'],$address['phone'],$address['country_code'],$address['city'],$address['postal_code'],$address['address_line1'],$address['address_line2']]);
            $itemInsert = $this->db->prepare('INSERT INTO order_items (order_id,product_id,product_variant_id,product_name,sku,variant_name,quantity,unit_price,customization_total,discount_total,line_total) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $customInsert = $this->db->prepare('INSERT INTO order_item_customizations (order_item_id,field_identifier,field_label,field_type,value_text,file_reference,price_adjustment) VALUES (?,?,?,?,?,?,?)');
            foreach ($hydrated as $line) {
                $itemInsert->execute([$orderId,$line['product_id'],$line['variant_id'],$line['product_name'],$line['sku'],$line['variant_name'],$line['quantity'],$this->formatCents($line['unit_cents']),$this->formatCents($line['custom_cents']),'0.00',$this->formatCents($line['line_cents'])]);
                $itemId=(int)$this->db->lastInsertId();
                foreach ($line['customizations'] as $customization) $customInsert->execute([$itemId,$customization['identifier'],$customization['label'],$customization['type'],$customization['value'],$customization['file_reference'],$this->formatCents($customization['price_cents'])]);
            }
            $this->db->prepare('INSERT INTO order_status_history (order_id,previous_status,new_status,changed_by_admin_id,note) VALUES (?,NULL,\'new\',?,?)')->execute([$orderId,$_SESSION['admin_id'],'Поръчката е създадена от администрацията.']);
            $this->applyStockMovements($stockRequirements, $orderNumber, (int) $_SESSION['admin_id']);
            $this->enqueueOrderEmail($orderId, 'order.created', 'Нова поръчка ' . $orderNumber, $this->emailText($orderNumber, $contact, $hydrated, $subtotal, $shipping, 'Получихме поръчката Ви и тя очаква потвърждение.'), $this->emailHtml($orderNumber, $contact, $hydrated, $subtotal, $shipping, 'Получихме поръчката Ви и тя очаква потвърждение.'));
            $this->db->commit();
        } catch (StockShortageException $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->error('insufficient_stock', $error->getMessage(), 409);
        } catch (PDOException $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if ($error->getCode() === '23000') {
                $existing=$this->findIdempotentOrder($keyHash);
                if ($existing) return hash_equals($existing['idempotency_hash'],$bodyHash)
                    ? Response::json(['data'=>['id'=>(int)$existing['id'],'order_number'=>$existing['order_number'],'replayed'=>true]],200)
                    : $this->error('idempotency_conflict','Този ключ вече е използван с различни данни.',409);
            }
            throw $error;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
        $this->dispatchOutbox(1, $orderId);
        return Response::json(['data'=>['id'=>$orderId,'order_number'=>$orderNumber]],201);
    }

    public function update(Request $request, array $params): Response
    {
        if ($error = $this->auth->guard($request, 'orders.update', true)) return $error;
        $id=$this->positiveId($params['id']??null);if($id===null)return $this->notFound();$input=$request->json();
        $allowed=['customer_email','customer_phone','customer_first_name','customer_last_name','customer_note','admin_note','shipping_method','shipping_total','shipping_address'];
        if (!$input || array_diff(array_keys($input),$allowed)) return $this->error('validation_error','Промените съдържат неподдържани полета.',422);
        $this->db->beginTransaction();
        try {
            $order=$this->findOrder($id,true);if(!$order)return $this->rollbackError('not_found','Поръчката не е намерена.',404);
            if (!in_array($order['status'],['new','confirmed'],true) || $order['payment_status']!=='unpaid') return $this->rollbackError('order_not_editable','Контактните данни и доставката могат да се променят само при нова или потвърдена неплатена поръчка.',409);
            $contact=['first_name'=>(string)$order['customer_first_name'],'last_name'=>(string)$order['customer_last_name'],'email'=>(string)$order['customer_email'],'phone'=>$order['customer_phone']];
            foreach(['customer_first_name'=>'first_name','customer_last_name'=>'last_name','customer_email'=>'email','customer_phone'=>'phone'] as $key=>$field) if(array_key_exists($key,$input))$contact[$field]=$input[$key];
            $valid=$this->validateContact($contact);if($valid instanceof Response)return $this->rollbackResponse($valid);$contact=$valid;
            $shippingMethod=trim((string)($input['shipping_method']??$order['shipping_method']));if($shippingMethod===''||mb_strlen($shippingMethod)>100)return $this->rollbackError('validation_error','Посочете валиден метод за доставка.',422);
            $shipping=$this->moneyCents($input['shipping_total']??$order['shipping_total']);if($shipping===null||$shipping<0)return $this->rollbackError('validation_error','Невалидна цена за доставка.',422);
            $customerNote=array_key_exists('customer_note',$input)?$this->optionalText($input['customer_note'],4000):$order['customer_note'];
            $adminNote=array_key_exists('admin_note',$input)?$this->optionalText($input['admin_note'],4000):$order['admin_note'];
            if($customerNote instanceof Response||$adminNote instanceof Response)return $this->rollbackResponse($customerNote instanceof Response?$customerNote:$adminNote);
            $total=(int)$this->moneyCents($order['subtotal'])-(int)$this->moneyCents($order['discount_total'])+$shipping+(int)$this->moneyCents($order['tax_total']);if($total<0||$total>self::MAX_ORDER_CENTS)return $this->rollbackError('order_total_too_large','Общата сума на поръчката е невалидна.',422);
            $this->db->prepare('UPDATE orders SET customer_email=?,customer_phone=?,customer_first_name=?,customer_last_name=?,shipping_method=?,shipping_total=?,grand_total=?,customer_note=?,admin_note=? WHERE id=?')->execute([$contact['email'],$contact['phone'],$contact['first_name'],$contact['last_name'],$shippingMethod,$this->formatCents($shipping),$this->formatCents($total),$customerNote,$adminNote,$id]);
            if(isset($input['shipping_address'])) {
                $address=$this->validateAddress($input['shipping_address'],$contact);if($address instanceof Response)return $this->rollbackResponse($address);
                $address=$this->resolveAddress($address,$order['customer_id']===null?null:(int)$order['customer_id'],$contact);if($address instanceof Response)return $this->rollbackResponse($address);
                $this->db->prepare("UPDATE order_addresses SET recipient_name=?,phone=?,country_code=?,city=?,postal_code=?,address_line1=?,address_line2=? WHERE order_id=? AND address_type='shipping'")->execute([$address['recipient_name']?:trim($contact['first_name'].' '.$contact['last_name']),$address['phone'],$address['country_code'],$address['city'],$address['postal_code'],$address['address_line1'],$address['address_line2'],$id]);
            }
            $this->db->commit();
        } catch(Throwable $error){if($this->db->inTransaction())$this->db->rollBack();throw $error;}
        return Response::json(['data'=>['updated'=>true]]);
    }

    public function changeStatus(Request $request, array $params): Response
    {
        if ($error = $this->auth->guard($request, 'orders.change_status', true)) return $error;
        $id=$this->positiveId($params['id']??null);if($id===null)return $this->notFound();$input=$request->json();$next=(string)($input['status']??'');$note=$this->optionalText($input['note']??null,1000);if($note instanceof Response)return $note;
        if(!in_array($next,['confirmed','processing','shipped','delivered'],true))return $this->error('invalid_status','Използвайте разрешен преход на статуса. Анулирането е отделно действие.',422);
        $response=$this->transitionStatus($id,$next,$note);
        if($response->status===200)$this->dispatchOutbox(1,$id);
        return $response;
    }

    public function changePaymentStatus(Request $request, array $params): Response
    {
        if ($error = $this->auth->guard($request, 'orders.manage_payment', true)) return $error;
        $id=$this->positiveId($params['id']??null);if($id===null)return $this->notFound();$input=$request->json();$next=(string)($input['payment_status']??'');$note=$this->optionalText($input['note']??null,1000);if($note instanceof Response)return $note;
        if(!in_array($next,['paid','refunded'],true))return $this->error('invalid_payment_status','Допустими действия са отбелязване като платена или възстановена сума.',422);
        $this->db->beginTransaction();
        try {
            $order=$this->findOrder($id,true);if(!$order)return $this->rollbackError('not_found','Поръчката не е намерена.',404);
            $current=(string)$order['payment_status'];
            if($current===$next){$this->db->commit();return Response::json(['data'=>['payment_status'=>$current,'unchanged'=>true]]);}
            $valid=($next==='paid'&&$current==='unpaid')||($next==='refunded'&&$current==='paid');
            if(!$valid)return $this->rollbackError('invalid_payment_transition','Платежният статус не може да премине в избраното състояние.',409);
            if($order['status']==='cancelled'&&$next==='paid')return $this->rollbackError('order_cancelled','Анулирана поръчка не може да бъде маркирана като платена.',409);
            $this->db->prepare('UPDATE orders SET payment_status=? WHERE id=?')->execute([$next,$id]);
            $this->db->prepare('INSERT INTO order_payment_history (order_id,previous_payment_status,new_payment_status,changed_by_admin_id,note) VALUES (?,?,?,?,?)')->execute([$id,$current,$next,$_SESSION['admin_id'],$note]);
            $this->db->commit();
        }catch(Throwable $error){if($this->db->inTransaction())$this->db->rollBack();throw $error;}
        return Response::json(['data'=>['payment_status'=>$next]]);
    }

    public function cancel(Request $request, array $params): Response
    {
        if ($error = $this->auth->guard($request, 'orders.cancel', true)) return $error;
        $id=$this->positiveId($params['id']??null);if($id===null)return $this->notFound();$note=$this->optionalText($request->json()['note']??null,1000);if($note instanceof Response)return $note;
        $this->db->beginTransaction();
        try {
            $order=$this->findOrder($id,true);if(!$order)return $this->rollbackError('not_found','Поръчката не е намерена.',404);
            if($order['status']==='cancelled'){$this->db->commit();return Response::json(['data'=>['status'=>'cancelled','already_cancelled'=>true]]);}
            if(!in_array($order['status'],['new','confirmed','processing'],true))return $this->rollbackError('order_cannot_be_cancelled','Изпратена или доставена поръчка не може да се анулира от този екран.',409);
            if($order['payment_status']==='paid')return $this->rollbackError('refund_required','Първо отбележете възстановяване на сумата, след което анулирайте поръчката.',409);
            $items=$this->db->prepare('SELECT product_id,product_variant_id,quantity FROM order_items WHERE order_id=? ORDER BY product_id,product_variant_id,id');$items->execute([$id]);$rows=$items->fetchAll(PDO::FETCH_ASSOC);
            $productIds=array_values(array_unique(array_filter(array_map(static fn($row)=>$row['product_variant_id']===null?(int)$row['product_id']:null,$rows))));sort($productIds,SORT_NUMERIC);
            $variantIds=array_values(array_unique(array_filter(array_map(static fn($row)=>$row['product_variant_id']===null?null:(int)$row['product_variant_id'],$rows))));sort($variantIds,SORT_NUMERIC);
            $products=$this->lockProducts($productIds,true);if($products instanceof Response)return $this->rollbackResponse($products);
            $variants=$this->lockVariants($variantIds,true);if($variants instanceof Response)return $this->rollbackResponse($variants);
            $this->restoreStockForOrderItems($rows,$order['order_number'],(int)$_SESSION['admin_id']);
            $this->db->prepare("UPDATE orders SET status='cancelled',cancelled_at=UTC_TIMESTAMP(),stock_restored_at=UTC_TIMESTAMP() WHERE id=? AND status<>'cancelled'")->execute([$id]);
            $this->db->prepare('INSERT INTO order_status_history (order_id,previous_status,new_status,changed_by_admin_id,note) VALUES (?, ?, \'cancelled\', ?, ?)')->execute([$id,$order['status'],$_SESSION['admin_id'],$note]);
            $contact=['first_name'=>$order['customer_first_name'],'last_name'=>$order['customer_last_name'],'email'=>$order['customer_email']];
            $this->enqueueOrderEmail($id,'order.cancelled','Поръчка '.$order['order_number'].' е анулирана','Поръчка '.$order['order_number'].' беше анулирана.',$this->simpleEmailHtml('Поръчка '.$order['order_number'].' е анулирана','Поръчката Ви беше анулирана.'));
            $this->db->commit();
        }catch(Throwable $error){if($this->db->inTransaction())$this->db->rollBack();throw $error;}
        $this->dispatchOutbox(1,$id);
        return Response::json(['data'=>['status'=>'cancelled']]);
    }

    public function bulk(Request $request): Response
    {
        if ($error = $this->auth->guard($request, 'orders.change_status', true)) return $error;
        $input=$request->json();$action=(string)($input['action']??'');$ids=$input['ids']??null;
        if($action!=='confirm'||!is_array($ids)||count($ids)<1||count($ids)>100)return $this->error('validation_error','Изберете от 1 до 100 поръчки за потвърждение.',422);
        $ids=array_values(array_unique(array_filter(array_map(fn($value)=>$this->positiveId($value),$ids))));if(!$ids)return $this->error('validation_error','Няма валидни поръчки.',422);sort($ids,SORT_NUMERIC);
        $this->db->beginTransaction();$affected=[];
        try {
            foreach($ids as $id){$order=$this->findOrder($id,true);if(!$order)return $this->rollbackError('order_not_found','Една от поръчките не е намерена.',404);if($order['status']!=='new')return $this->rollbackError('invalid_status_transition','Всички избрани поръчки трябва да са със статус „Нова“.',409);$affected[]=$order;}
            foreach($affected as $order){$this->db->prepare("UPDATE orders SET status='confirmed' WHERE id=? AND status='new'")->execute([$order['id']]);$this->db->prepare('INSERT INTO order_status_history (order_id,previous_status,new_status,changed_by_admin_id,note) VALUES (?,\'new\',\'confirmed\',?,\'Масово потвърждение от администрацията.\')')->execute([$order['id'],$_SESSION['admin_id']]);$this->enqueueOrderEmail((int)$order['id'],'order.confirmed','Поръчка '.$order['order_number'].' е потвърдена','Поръчка '.$order['order_number'].' е потвърдена.',$this->simpleEmailHtml('Поръчката е потвърдена','Поръчка '.$order['order_number'].' е потвърдена.'));}
            $this->db->commit();
        }catch(Throwable $error){if($this->db->inTransaction())$this->db->rollBack();throw $error;}
        $this->dispatchOutbox(3);
        return Response::json(['data'=>['affected'=>count($affected)]]);
    }

    public function retryEmails(Request $request, array $params): Response
    {
        if ($error = $this->auth->guard($request, 'orders.update', true)) return $error;
        $id=$this->positiveId($params['id']??null);if($id===null||!$this->findOrder($id))return $this->notFound();
        $this->db->prepare("UPDATE order_email_outbox SET status='pending',attempt_count=0,next_attempt_at=UTC_TIMESTAMP(),locked_at=NULL,last_error=NULL WHERE order_id=? AND status='failed'")->execute([$id]);
        $this->dispatchOutbox(3,$id);
        return Response::json(['data'=>['queued'=>true]]);
    }

    private function transitionStatus(int $id,string $next,?string $note): Response
    {
        $this->db->beginTransaction();
        try {
            $order=$this->findOrder($id,true);if(!$order)return $this->rollbackError('not_found','Поръчката не е намерена.',404);
            $current=(string)$order['status'];if($current===$next){$this->db->commit();return Response::json(['data'=>['status'=>$current,'unchanged'=>true]]);}
            if(!in_array($next,self::STATUS_TRANSITIONS[$current]??[],true))return $this->rollbackError('invalid_status_transition','Промяната на статуса не е разрешена.',409);
            $fulfillment=match($next){'processing'=>'in_progress','shipped'=>'shipped','delivered'=>'fulfilled',default=>$order['fulfillment_status']};
            $this->db->prepare('UPDATE orders SET status=?,fulfillment_status=? WHERE id=?')->execute([$next,$fulfillment,$id]);
            $this->db->prepare('INSERT INTO order_status_history (order_id,previous_status,new_status,changed_by_admin_id,note) VALUES (?,?,?,?,?)')->execute([$id,$current,$next,$_SESSION['admin_id'],$note]);
            if($next==='confirmed')$this->enqueueOrderEmail($id,'order.confirmed','Поръчка '.$order['order_number'].' е потвърдена','Поръчка '.$order['order_number'].' е потвърдена.',$this->simpleEmailHtml('Поръчката е потвърдена','Поръчка '.$order['order_number'].' е потвърдена.'));
            else $this->enqueueOrderEmail($id,'order.status.'.$next,'Промяна на поръчка '.$order['order_number'],'Поръчка '.$order['order_number'].' е със статус '.$this->statusLabel($next).'.',$this->simpleEmailHtml('Статус на поръчката: '.$this->statusLabel($next),'Поръчка '.$order['order_number'].' е със статус '.$this->statusLabel($next).'.'));
            $this->db->commit();
        }catch(Throwable $error){if($this->db->inTransaction())$this->db->rollBack();throw $error;}
        return Response::json(['data'=>['status'=>$next]]);
    }

    private function prepareItems(array $items): array|Response
    {
        $result=[];
        foreach($items as $line){
            if(!is_array($line))return $this->error('validation_error','Невалиден ред в поръчката.',422);
            $productId=$this->positiveId($line['product_id']??null);$variantId=($line['variant_id']??null)===null||($line['variant_id']??'')===''?null:$this->positiveId($line['variant_id']);$quantity=filter_var($line['quantity']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>999]]);
            if($productId===null||(($line['variant_id']??null)!==null&&($line['variant_id']??'')!==''&&$variantId===null)||$quantity===false)return $this->error('validation_error','Продуктът, вариантът или количеството е невалидно.',422);
            $custom=$line['customizations']??[];if(!is_array($custom)||count($custom)>30)return $this->error('validation_error','Невалидни персонализации.',422);
            $result[]=['product_id'=>$productId,'variant_id'=>$variantId,'quantity'=>(int)$quantity,'customizations'=>$custom];
        }
        return $result;
    }

    private function lockProducts(array $ids,bool $includeInactive=false): array|Response
    {
        if(!$ids)return [];$placeholders=implode(',',array_fill(0,count($ids),'?'));
        $query=$this->db->prepare('SELECT id,name,sku,price,sale_price,status,inventory_mode,stock_quantity,is_available,deleted_at FROM products WHERE id IN ('.$placeholders.') ORDER BY id FOR UPDATE');$query->execute($ids);$rows=$query->fetchAll(PDO::FETCH_ASSOC);$result=[];foreach($rows as $row)$result[(int)$row['id']]=$row;
        if(count($result)!==count($ids))return $this->error('product_not_found','Един от продуктите не е намерен.',404);return $result;
    }

    private function lockVariants(array $ids,bool $includeInactive=false): array|Response
    {
        if(!$ids)return [];$placeholders=implode(',',array_fill(0,count($ids),'?'));
        $query=$this->db->prepare('SELECT id,product_id,name,sku,price,price_adjustment,inventory_mode,stock_quantity,is_available,is_active FROM product_variants WHERE id IN ('.$placeholders.') ORDER BY id FOR UPDATE');$query->execute($ids);$rows=$query->fetchAll(PDO::FETCH_ASSOC);$result=[];foreach($rows as $row)$result[(int)$row['id']]=$row;
        if(count($result)!==count($ids))return $this->error('variant_not_found','Един от продуктовите варианти не е намерен.',404);return $result;
    }

    private function validateCustomizations(int $productId,array $input): array|Response
    {
        $query=$this->db->prepare('SELECT id,name,field_type,is_required,validation_rules,options,additional_price FROM product_customization_fields WHERE product_id=? AND is_active=1 ORDER BY position,id');$query->execute([$productId]);$fields=$query->fetchAll(PDO::FETCH_ASSOC);$byId=[];foreach($fields as $field)$byId[(int)$field['id']]=$field;
        $values=[];foreach($input as $row){if(!is_array($row))return $this->error('validation_error','Невалидна персонализация.',422);$id=$this->positiveId($row['field_id']??null);if($id===null||!isset($byId[$id])||isset($values[$id]))return $this->error('validation_error','Персонализацията не съответства на продукта.',422);$values[$id]=$row;}
        $result=[];
        foreach($fields as $field){$id=(int)$field['id'];$row=$values[$id]??null;$type=(string)$field['field_type'];$value=$row['value']??null;$file=null;
            if($type==='checkbox'){$value=filter_var($value,FILTER_VALIDATE_BOOLEAN)?'1':'';}
            elseif($type==='image'){$file=is_string($row['file_reference']??null)?trim($row['file_reference']):null;$value=null;if((int)$field['is_required']&&(!$file||!preg_match('/^[a-f0-9]{64}\.(?:jpg|png|webp)$/D',$file)))return $this->error('validation_error','Добавете валидно изображение за задължителното поле „'.$field['name'].'“.',422);if($file!==null&&(!preg_match('/^[a-f0-9]{64}\.(?:jpg|png|webp)$/D',$file)||!is_file($this->privateOrderFilesDirectory().DIRECTORY_SEPARATOR.$file)))return $this->error('validation_error','Невалидно изображение за персонализация.',422);}
            else {$value=is_string($value)?trim($value):'';if(mb_strlen($value)>10000)return $this->error('validation_error','Персонализацията е прекалено дълга.',422);if($type==='select'&&$value!==''&&!in_array($value,json_decode((string)$field['options'],true)?:[],true))return $this->error('validation_error','Изберете валидна стойност за „'.$field['name'].'“.',422);}
            if((int)$field['is_required']&&($type==='image'?!$file:$value===''))return $this->error('validation_error','Попълнете задължителното поле „'.$field['name'].'“.',422);
            if(!$row)continue;
            if(($type==='image'&&!$file)||($type==='checkbox'&&$value==='')||($type!=='image'&&$type!=='checkbox'&&$value===''))continue;
            $price=$this->moneyCents($field['additional_price']);if($price===null)return $this->error('invalid_price','Доплащането за персонализацията е невалидно.',409);
            $result[]=['identifier'=>(string)$id,'label'=>(string)$field['name'],'type'=>$type,'value'=>$value,'file_reference'=>$file,'price_cents'=>($type==='checkbox'&&$value==='')?0:$price];
        }
        return $result;
    }

    private function applyStockMovements(array $requirements,string $orderNumber,int $adminId): void
    {
        foreach($requirements as $requirement){$variantId=$requirement['variant_id'];$table=$variantId===null?'products':'product_variants';$id=$variantId??$requirement['product_id'];$statement=$this->db->prepare('SELECT stock_quantity FROM '.$table.' WHERE id=? FOR UPDATE');$statement->execute([$id]);$stock=$statement->fetchColumn();if($stock===false||$stock===null||(int)$stock<$requirement['qty'])throw new StockShortageException('Няма достатъчна наличност за един от избраните продукти.');$after=(int)$stock-$requirement['qty'];$this->db->prepare('UPDATE '.$table.' SET stock_quantity=?,is_available=? WHERE id=?')->execute([$after,(int)($after>0),$id]);$this->db->prepare('INSERT INTO product_stock_movements (product_id,variant_id,quantity_delta,quantity_after,reason,note,admin_id) VALUES (?,?,?,?,?,?,?)')->execute([$requirement['product_id'],$variantId,-$requirement['qty'],$after,'order_created','Поръчка '.$orderNumber,$adminId]);}
    }

    private function restoreStockForOrderItems(array $items,string $orderNumber,int $adminId): void
    {
        $requirements=[];foreach($items as $item){if($item['product_variant_id']!==null){$id=(int)$item['product_variant_id'];$requirements['v:'.$id]=($requirements['v:'.$id]??0)+(int)$item['quantity'];}elseif($item['product_id']!==null){$id=(int)$item['product_id'];$requirements['p:'.$id]=($requirements['p:'.$id]??0)+(int)$item['quantity'];}}
        foreach($requirements as $key=>$quantity){[$kind,$raw]=explode(':',$key,2);$id=(int)$raw;$variant=$kind==='v';$table=$variant?'product_variants':'products';$select=$this->db->prepare('SELECT '.($variant?'product_id,':'').'inventory_mode,stock_quantity FROM '.$table.' WHERE id=? FOR UPDATE');$select->execute([$id]);$row=$select->fetch(PDO::FETCH_ASSOC);if(!$row||$row['inventory_mode']!=='tracked')continue;$after=(int)$row['stock_quantity']+$quantity;if($after>4_294_967_295)throw new \RuntimeException('Restored quantity exceeds the stock column limit.');$this->db->prepare('UPDATE '.$table.' SET stock_quantity=?,is_available=1 WHERE id=?')->execute([$after,$id]);$productId=$variant?(int)$row['product_id']:$id;$this->db->prepare('INSERT INTO product_stock_movements (product_id,variant_id,quantity_delta,quantity_after,reason,note,admin_id) VALUES (?,?,?,?,?,?,?)')->execute([$productId,$variant?$id:null,$quantity,$after,'order_cancelled','Възстановена наличност за поръчка '.$orderNumber,$adminId]);}
    }

    private function validateContact(array $input): array|Response
    {
        $first=trim((string)($input['first_name']??$input['customer_first_name']??''));$last=trim((string)($input['last_name']??$input['customer_last_name']??''));$email=strtolower(trim((string)($input['email']??$input['customer_email']??'')));$phone=trim((string)($input['phone']??$input['customer_phone']??''));
        if($first===''||mb_strlen($first)>100||$last===''||mb_strlen($last)>100)return $this->error('validation_error','Името и фамилията са задължителни и трябва да са до 100 знака.',422);
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>254)return $this->error('validation_error','Въведете валиден имейл адрес до 254 знака.',422);
        if($phone!==''&&(!preg_match('/^\+?[0-9 ()\-.]{5,32}$/D',$phone)))return $this->error('validation_error','Телефонът е невалиден.',422);
        return ['first_name'=>$first,'last_name'=>$last,'email'=>$email,'phone'=>$phone===''?null:$phone];
    }

    private function validateAddress(mixed $input,array $contact): array|Response
    {
        if(!is_array($input))return $this->error('validation_error','Въведете адрес за доставка.',422);
        if(array_key_exists('customer_address_id',$input)){
            $addressId=$this->positiveId($input['customer_address_id']);
            return $addressId===null?$this->error('validation_error','Невалиден адрес на клиента.',422):['customer_address_id'=>$addressId];
        }
        $address=['recipient_name'=>trim((string)($input['recipient_name']??'')),'phone'=>trim((string)($input['phone']??$contact['phone']??'')),'country_code'=>strtoupper(trim((string)($input['country_code']??'BG'))),'city'=>trim((string)($input['city']??'')),'postal_code'=>trim((string)($input['postal_code']??'')),'address_line1'=>trim((string)($input['address_line1']??'')),'address_line2'=>trim((string)($input['address_line2']??''))];
        if(mb_strlen($address['recipient_name'])>160||mb_strlen($address['phone'])>32||!preg_match('/^[A-Z]{2}$/D',$address['country_code'])||$address['city']===''||mb_strlen($address['city'])>100||mb_strlen($address['postal_code'])>20||$address['address_line1']===''||mb_strlen($address['address_line1'])>200||mb_strlen($address['address_line2'])>200)return $this->error('validation_error','Попълнете валиден адрес за доставка.',422);
        return $address;
    }

    private function resolveAddress(array $address,?int $customerId,array $contact): array|Response
    {
        if(isset($address['customer_address_id'])){
            if($customerId===null)return $this->error('validation_error','Гост поръчка не може да използва адрес от клиентски профил.',422);
            $id=$this->positiveId($address['customer_address_id']);if($id===null)return $this->error('validation_error','Невалиден адрес на клиента.',422);
            $query=$this->db->prepare('SELECT recipient_name,phone,country_code,city,postal_code,address_line1,address_line2 FROM customer_addresses WHERE id=? AND customer_id=?');$query->execute([$id,$customerId]);$source=$query->fetch(PDO::FETCH_ASSOC);if(!$source)return $this->error('address_not_found','Адресът не принадлежи на избрания клиент.',404);
            return ['recipient_name'=>$source['recipient_name'],'phone'=>$source['phone'],'country_code'=>$source['country_code'],'city'=>$source['city'],'postal_code'=>$source['postal_code'],'address_line1'=>$source['address_line1'],'address_line2'=>$source['address_line2']];
        }
        return $address;
    }

    private function mergeContact(array $contact,array $customer): array
    {
        if($contact['first_name']==='')$contact['first_name']=(string)$customer['first_name'];if($contact['last_name']==='')$contact['last_name']=(string)$customer['last_name'];if($contact['email']===''&&$customer['email']!==null)$contact['email']=strtolower((string)$customer['email']);if($contact['phone']===null&&$customer['phone']!==null)$contact['phone']=(string)$customer['phone'];return $contact;
    }

    private function effectiveUnitPrice(array $product,?array $variant): ?int
    {
        $base=$this->moneyCents($product['sale_price']!==null?$product['sale_price']:$product['price']);if($base===null)return null;
        if($variant===null)return $base;$override=$variant['price']===null?null:$this->moneyCents($variant['price']);if($variant['price']!==null)return $override;
        $adjustment=$this->moneyCents($variant['price_adjustment'],true);return $adjustment===null?null:$base+$adjustment;
    }

    private function hasActiveVariants(int $productId): bool
    { $query=$this->db->prepare('SELECT 1 FROM product_variants WHERE product_id=? AND is_active=1 LIMIT 1');$query->execute([$productId]);return (bool)$query->fetchColumn(); }

    private function findOrder(int $id,bool $lock=false): ?array
    { if($id<1)return null;$query=$this->db->prepare('SELECT * FROM orders WHERE id=?'.($lock?' FOR UPDATE':''));$query->execute([$id]);$row=$query->fetch(PDO::FETCH_ASSOC);return $row?:null; }

    private function findIdempotentOrder(string $hash,bool $lock=false): ?array
    { $query=$this->db->prepare('SELECT id,order_number,idempotency_hash FROM orders WHERE idempotency_key=?'.($lock?' FOR UPDATE':''));$query->execute([$hash]);$row=$query->fetch(PDO::FETCH_ASSOC);return $row?:null; }

    private function contactInput(array $input): array
    { return ['first_name'=>trim((string)($input['first_name']??$input['customer_first_name']??'')),'last_name'=>trim((string)($input['last_name']??$input['customer_last_name']??'')),'email'=>strtolower(trim((string)($input['email']??$input['customer_email']??''))),'phone'=>trim((string)($input['phone']??$input['customer_phone']??''))?:null]; }

    private function enqueueOrderEmail(int $id,string $event,string $subject,string $text,string $html): void
    { $query=$this->db->prepare('SELECT customer_email FROM orders WHERE id=?');$query->execute([$id]);$email=(string)$query->fetchColumn();$this->db->prepare("INSERT IGNORE INTO order_email_outbox (order_id,event_key,recipient_email,subject,body_text,body_html,status,next_attempt_at) VALUES (?,?,?,?,?,?,'pending',UTC_TIMESTAMP())")->execute([$id,$event,$email,$subject,$text,$html]); }

    private function dispatchOutbox(int $max=3,?int $orderId=null): void
    {
        for($i=0;$i<$max;$i++){
            $this->db->beginTransaction();
            try {
                $this->db->exec("UPDATE order_email_outbox SET status='pending',locked_at=NULL WHERE status='sending' AND locked_at < UTC_TIMESTAMP() - INTERVAL 10 MINUTE");
                $sql="SELECT * FROM order_email_outbox WHERE status IN ('pending','failed') AND attempt_count<5 AND next_attempt_at<=UTC_TIMESTAMP()".($orderId!==null?' AND order_id=?':'').' ORDER BY id LIMIT 1 FOR UPDATE';$query=$this->db->prepare($sql);$query->execute($orderId!==null?[$orderId]:[]);$message=$query->fetch(PDO::FETCH_ASSOC);
                if(!$message){$this->db->commit();return;}
                $claim=$this->db->prepare("UPDATE order_email_outbox SET status='sending',locked_at=UTC_TIMESTAMP(),attempt_count=attempt_count+1 WHERE id=? AND status IN ('pending','failed')");$claim->execute([$message['id']]);if($claim->rowCount()!==1){$this->db->commit();continue;}$message['attempt_count']=(int)$message['attempt_count']+1;$this->db->commit();
            }catch(Throwable $error){if($this->db->inTransaction())$this->db->rollBack();$this->logger->log('error','Order email queue claim failed',['exception'=>$error::class]);return;}
            try {
                (new SmtpMailer($this->config))->sendHtml((string)$message['recipient_email'],(string)$message['subject'],(string)$message['body_text'],(string)$message['body_html']);
                $this->db->prepare("UPDATE order_email_outbox SET status='sent',sent_at=UTC_TIMESTAMP(),locked_at=NULL,last_error=NULL WHERE id=? AND status='sending'")->execute([$message['id']]);
            }catch(Throwable $error){$attempt=(int)$message['attempt_count'];$delay=[1=>1,2=>5,3=>15,4=>60,5=>360][$attempt]??1440;$status=$attempt>=5?'failed':'pending';$this->db->prepare('UPDATE order_email_outbox SET status=?,next_attempt_at=UTC_TIMESTAMP() + INTERVAL '.$delay.' MINUTE,locked_at=NULL,last_error=? WHERE id=? AND status=\'sending\'')->execute([$status,mb_substr($error::class.': '.$error->getMessage(),0,500),(int)$message['id']]);$this->logger->log('error','Order email delivery failed',['order_id'=>(int)$message['order_id'],'event_key'=>$message['event_key'],'attempt'=>$attempt,'exception'=>$error::class]);}
        }
    }

    private function emailText(string $number,array $contact,array $items,int $subtotal,int $shipping,string $intro): string
    { $lines=[$intro,'Поръчка: '.$number,'Клиент: '.$contact['first_name'].' '.$contact['last_name'],'','Продукти:'];foreach($items as $item)$lines[]=$item['product_name'].($item['variant_name']?' — '.$item['variant_name']:'').' × '.$item['quantity'].' — '.$this->formatCents($item['line_cents']).' EUR';$lines[]='';$lines[]='Междинна сума: '.$this->formatCents($subtotal).' EUR';$lines[]='Доставка: '.$this->formatCents($shipping).' EUR';$lines[]='Общо: '.$this->formatCents($subtotal+$shipping).' EUR';return implode("\n",$lines); }

    private function emailHtml(string $number,array $contact,array $items,int $subtotal,int $shipping,string $intro): string
    { $escape=static fn(string $value):string=>htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$rows='';foreach($items as $item)$rows.='<tr><td style="padding:8px;border-bottom:1px solid #ddd">'.$escape($item['product_name'].($item['variant_name']?' — '.$item['variant_name']:'' )).'</td><td style="padding:8px;border-bottom:1px solid #ddd">'.$item['quantity'].'</td><td style="padding:8px;border-bottom:1px solid #ddd;text-align:right">'.$this->formatCents($item['line_cents']).' EUR</td></tr>';return '<!doctype html><html lang="bg"><body style="font:15px Arial,sans-serif;color:#222"><main style="max-width:640px;margin:auto;padding:24px"><h1 style="font-size:22px">Поръчка '.$escape($number).'</h1><p>'.$escape($intro).'</p><p>Здравейте, '.$escape($contact['first_name'].' '.$contact['last_name']).'</p><table style="width:100%;border-collapse:collapse"><thead><tr><th align="left">Продукт</th><th align="left">Брой</th><th align="right">Сума</th></tr></thead><tbody>'.$rows.'</tbody></table><p style="text-align:right">Междинна сума: '.$this->formatCents($subtotal).' EUR<br>Доставка: '.$this->formatCents($shipping).' EUR<br><strong>Общо: '.$this->formatCents($subtotal+$shipping).' EUR</strong></p></main></body></html>'; }

    private function simpleEmailHtml(string $heading,string $text): string
    { return '<!doctype html><html lang="bg"><body style="font:15px Arial,sans-serif;color:#222"><main style="max-width:640px;margin:auto;padding:24px"><h1 style="font-size:22px">'.htmlspecialchars($heading,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</h1><p>'.htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p></main></body></html>'; }

    private function hasPermission(string $permission): bool
    { $query=$this->db->prepare('SELECT p.name FROM admins a JOIN role_permissions rp ON rp.role_id=a.role_id JOIN permissions p ON p.id=rp.permission_id WHERE a.id=? AND a.active=1');$query->execute([$_SESSION['admin_id']??0]);$permissions=$query->fetchAll(PDO::FETCH_COLUMN);return in_array('*',$permissions,true)||in_array($permission,$permissions,true); }

    private function positiveId(mixed $value): ?int
    { $id=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);return $id===false?null:(int)$id; }

    private function moneyCents(mixed $value,bool $signed=false): ?int
    { if(!is_string($value)&&!is_int($value))return null;$value=trim((string)$value);$pattern=$signed?'/^-?(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/D':'/^(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/D';if(!preg_match($pattern,$value))return null;$negative=str_starts_with($value,'-');if($negative)$value=substr($value,1);[$whole,$fraction]=array_pad(explode('.',$value,2),2,'');$cents=((int)$whole*100)+(int)str_pad($fraction,2,'0');return $negative?-$cents:$cents; }

    private function formatCents(int $value): string
    { return ($value<0?'-':'').intdiv(abs($value),100).'.'.str_pad((string)(abs($value)%100),2,'0',STR_PAD_LEFT); }

    private function optionalText(mixed $value,int $max): string|null|Response
    { if($value===null)return null;if(!is_string($value)||mb_strlen($value)>$max)return $this->error('validation_error','Текстът надвишава допустимата дължина.',422);$value=trim($value);return $value===''?null:$value; }

    private function dateValue(mixed $value): ?string
    { if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value))return null;$date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);return $date&&$date->format('Y-m-d')===$value?$value:null; }

    private function header(Request $request,string $name): string
    { foreach($request->headers as $key=>$value)if(strcasecmp((string)$key,$name)===0)return trim((string)$value);return ''; }

    private function rollbackError(string $code,string $message,int $status): Response
    { if($this->db->inTransaction())$this->db->rollBack();return $this->error($code,$message,$status); }
    private function rollbackResponse(Response $response): Response
    { if($this->db->inTransaction())$this->db->rollBack();return $response; }
    private function error(string $code,string $message,int $status): Response { return Response::json(['error'=>['code'=>$code,'message'=>$message]],$status); }
    private function notFound(): Response { return $this->error('not_found','Поръчката не е намерена.',404); }
    private function statusLabel(string $status): string { return ['new'=>'Нова','confirmed'=>'Потвърдена','processing'=>'Обработва се','shipped'=>'Изпратена','delivered'=>'Доставена','cancelled'=>'Анулирана'][$status]??$status; }
    private function newOrderNumber(): string { return 'GH-'.gmdate('Ymd').'-'.strtoupper(bin2hex(random_bytes(3))); }

    private function privateOrderFilesDirectory(): string { return dirname(__DIR__,3).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'order-customizations'; }

    private function cleanupOldUnlinkedFiles(string $directory): void
    {
        $files=glob($directory.DIRECTORY_SEPARATOR.'*')?:[];
        foreach(array_slice($files,0,100) as $path){if(!is_file($path)||filemtime($path)>time()-86400)continue;$name=basename($path);$query=$this->db->prepare('SELECT 1 FROM order_item_customizations WHERE file_reference=? LIMIT 1');$query->execute([$name]);if(!$query->fetchColumn())@unlink($path);}
    }
}

final class StockShortageException extends \RuntimeException {}
