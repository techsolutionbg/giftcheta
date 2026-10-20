<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Commerce;

use Dompdf\Dompdf;
use Dompdf\Options;
use PDO;
use RuntimeException;

/** Creates private PDF snapshots for customer orders. */
final class InvoiceService
{
    public function __construct(private readonly PDO $db, private readonly string $projectRoot) {}

    public function ensure(int $orderId, bool $customerRequested = false, ?array $billingOverride = null): array
    {
        $query=$this->db->prepare('SELECT * FROM order_invoices WHERE order_id=?');$query->execute([$orderId]);$invoice=$query->fetch(PDO::FETCH_ASSOC);
        if(!$invoice){
            $order=$this->db->prepare('SELECT * FROM orders WHERE id=?');$order->execute([$orderId]);$order=$order->fetch(PDO::FETCH_ASSOC);if(!$order)throw new RuntimeException('Поръчката не е намерена.');
            $items=$this->db->prepare('SELECT product_name,sku,variant_name,quantity,unit_price,customization_total,discount_total,line_total FROM order_items WHERE order_id=? ORDER BY id');$items->execute([$orderId]);
            $addresses=$this->db->prepare('SELECT address_type,recipient_name,phone,country_code,city,postal_code,address_line1,address_line2 FROM order_addresses WHERE order_id=? ORDER BY address_type');$addresses->execute([$orderId]);
            $profile=$this->db->prepare('SELECT entity_type,recipient_name,company_name,company_id,vat_number,address_line1,address_line2,city,postal_code,country_code FROM customer_billing_profiles WHERE customer_id=?');$profile->execute([(int)($order['customer_id']??0)]);$billing=$profile->fetch(PDO::FETCH_ASSOC)?:null;
            $snapshot=['order'=>['number'=>$order['order_number'],'created_at'=>$order['created_at'],'currency'=>$order['currency_code'],'subtotal'=>$order['subtotal'],'discount_total'=>$order['discount_total'],'shipping_total'=>$order['shipping_total'],'tax_total'=>$order['tax_total'],'grand_total'=>$order['grand_total'],'payment_method'=>$order['payment_method']],'customer'=>['name'=>trim($order['customer_first_name'].' '.$order['customer_last_name']),'email'=>$order['customer_email'],'phone'=>$order['customer_phone']],'billing'=>$billingOverride??$billing,'addresses'=>$addresses->fetchAll(PDO::FETCH_ASSOC),'items'=>$items->fetchAll(PDO::FETCH_ASSOC)];
            $this->db->prepare('INSERT INTO order_invoices (order_id,customer_requested,recipient_email,snapshot_json) VALUES (?,?,?,?)')->execute([$orderId,$customerRequested?1:0,$order['customer_email'],json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            $query->execute([$orderId]);$invoice=$query->fetch(PDO::FETCH_ASSOC);
        } elseif($customerRequested && !(int)$invoice['customer_requested']) { $this->db->prepare('UPDATE order_invoices SET customer_requested=1 WHERE order_id=?')->execute([$orderId]);$invoice['customer_requested']=1; }
        $issuer=$this->db->query('SELECT legal_name,company_id,vat_number,address,phone,email FROM invoice_issuer_settings WHERE setting_id=1')->fetch(PDO::FETCH_ASSOC)?:[];
        $ready=trim((string)($issuer['legal_name']??''))!==''&&trim((string)($issuer['company_id']??''))!==''&&trim((string)($issuer['address']??''))!=='';
        $snapshot=json_decode((string)$invoice['snapshot_json'],true,512,JSON_THROW_ON_ERROR);$snapshot['issuer']=((string)$invoice['status']==='issued'&&isset($snapshot['issuer']))?$snapshot['issuer']:$issuer;
        $year=(int)gmdate('Y',strtotime((string)$snapshot['order']['created_at']));$number=$invoice['invoice_number'];
        if($ready&&$number===null){$this->db->beginTransaction();try{$this->db->prepare('INSERT IGNORE INTO invoice_sequences (sequence_year,last_number) VALUES (?,0)')->execute([$year]);$seq=$this->db->prepare('SELECT last_number FROM invoice_sequences WHERE sequence_year=? FOR UPDATE');$seq->execute([$year]);$next=(int)$seq->fetchColumn()+1;$this->db->prepare('UPDATE invoice_sequences SET last_number=? WHERE sequence_year=?')->execute([$next,$year]);$number=sprintf('INV-%d-%06d',$year,$next);$this->db->prepare("UPDATE order_invoices SET invoice_number=?,status='issued',issued_at=COALESCE(issued_at,UTC_TIMESTAMP()) WHERE order_id=?")->execute([$number,$orderId]);$this->db->commit();}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}}
        if(!$ready)$snapshot['draft_notice']='Чернова — попълнете данните на издателя в администрацията.';
        $path=(string)($invoice['private_file_path']??'');if($path===''||!is_file($this->projectRoot.DIRECTORY_SEPARATOR.$path)||($ready&&(string)$invoice['status']!=='issued')){$path=$this->render($orderId,$snapshot,$number);$this->db->prepare('UPDATE order_invoices SET private_file_path=?,snapshot_json=? WHERE order_id=?')->execute([$path,json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$orderId]);}
        if($ready&&$number!==null&&(int)$invoice['customer_requested']===1){$this->queueEmail($orderId,(string)$invoice['recipient_email'],$number,$path);}
        return ['invoice_number'=>$number,'status'=>$ready?'issued':'draft','customer_requested'=>(bool)$invoice['customer_requested'],'path'=>$path,'file_name'=>($number??'DRAFT-'.$orderId).'.pdf'];
    }

    private function render(int $orderId,array $data,?string $number): string
    {
        $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$o=$data['order'];$customer=$data['customer'];$rows='';foreach($data['items'] as $item)$rows.='<tr><td>'.$e($item['product_name']).($item['variant_name']?' <small>'.$e($item['variant_name']).'</small>':'').'</td><td>'.$e($item['quantity']).'</td><td>'.$e($item['unit_price']).'</td><td>'.$e($item['line_total']).'</td></tr>';
        $billing=$data['billing']??null;$recipient=$billing?($billing['entity_type']==='company'?($billing['company_name']??''):$billing['recipient_name']):$customer['name'];$addr=$billing?trim(implode(', ',array_filter([$billing['address_line1']??'',$billing['address_line2']??'',$billing['postal_code']??'',$billing['city']??'',$billing['country_code']??'']))):'';
        $html='<!doctype html><html lang="bg"><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;color:#30272b;font-size:12px}h1{color:#8f1738}table{width:100%;border-collapse:collapse;margin:24px 0}th,td{padding:9px;border-bottom:1px solid #eadbde;text-align:left}th{background:#fff4f5}.total{text-align:right;font-size:16px}.draft{color:#b42348;border:2px solid #b42348;padding:8px}</style><h1>Фактура '.($number?$e($number):'').'</h1>'.(isset($data['draft_notice'])?'<p class="draft">'.$e($data['draft_notice']).'</p>':'').'<p>Дата: '.$e($o['created_at']).' · Поръчка: '.$e($o['number']).'</p><h3>Доставчик</h3><p>'.$e($data['issuer']['legal_name']??'').'<br>ЕИК: '.$e($data['issuer']['company_id']??'').'<br>ДДС №: '.$e($data['issuer']['vat_number']??'').'<br>'.$e($data['issuer']['address']??'').'</p><h3>Получател</h3><p>'.$e($recipient).'<br>'.$e($billing['company_id']??'').'<br>'.$e($billing['vat_number']??'').'<br>'.$e($addr).'<br>'.$e($customer['email']).'</p><table><thead><tr><th>Продукт</th><th>Количество</th><th>Ед. цена</th><th>Общо</th></tr></thead><tbody>'.$rows.'</tbody></table><p>Данъчна основа: '.$e($o['subtotal']).' '.$e($o['currency']).'<br>Отстъпка: '.$e($o['discount_total']).'<br>Доставка: '.$e($o['shipping_total']).'<br>ДДС: '.$e($o['tax_total']).'</p><p class="total">Общо: '.$e($o['grand_total']).' '.$e($o['currency']).'</p></html>';
        $options=new Options();$options->set('defaultFont','DejaVu Sans');$dompdf=new Dompdf($options);$dompdf->loadHtml($html,'UTF-8');$dompdf->setPaper('A4');$dompdf->render();$relative='storage/private/invoices/'.gmdate('Y').'/'.bin2hex(random_bytes(16)).'.pdf';$target=$this->projectRoot.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relative);if(!is_dir(dirname($target))&&!mkdir(dirname($target),0750,true)&&!is_dir(dirname($target)))throw new RuntimeException('Invoice storage unavailable');if(file_put_contents($target,$dompdf->output(),LOCK_EX)===false)throw new RuntimeException('Invoice PDF could not be saved');chmod($target,0640);return $relative;
    }
    private function queueEmail(int $id,string $email,string $number,string $path): void
    {$text='Изпращаме Ви фактура '.$number.' за поръчката Ви.';$html='<p>Изпращаме Ви фактура <strong>'.htmlspecialchars($number,ENT_QUOTES,'UTF-8').'</strong> за поръчката Ви.</p>';$this->db->prepare("INSERT IGNORE INTO order_email_outbox (order_id,event_key,recipient_email,subject,body_text,body_html,attachment_path,attachment_name,status,next_attempt_at) VALUES (?,?,?,?,?,?,?,?,'pending',UTC_TIMESTAMP())")->execute([$id,'invoice.issued',$email,'Фактура '.$number,$text,$html,$path,$number.'.pdf']);}
}
