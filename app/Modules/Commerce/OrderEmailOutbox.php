<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Commerce;

use Giftcheta\Core\Config;
use Giftcheta\Core\Logger;
use Giftcheta\Core\SmtpMailer;
use PDO;
use Throwable;

/** Durable, retryable delivery for transactional order email. */
final class OrderEmailOutbox
{
    public function __construct(private readonly PDO $db, private readonly Config $config, private readonly Logger $logger) {}

    public function enqueue(int $orderId,string $event,string $subject,string $text,string $html): void
    {
        $query=$this->db->prepare('SELECT customer_email FROM orders WHERE id=?');
        $query->execute([$orderId]);
        $email=$query->fetchColumn();
        if(!is_string($email)||$email==='')throw new \RuntimeException('Order email address is missing.');
        $this->db->prepare("INSERT IGNORE INTO order_email_outbox (order_id,event_key,recipient_email,subject,body_text,body_html,status,next_attempt_at) VALUES (?,?,?,?,?,?,'pending',UTC_TIMESTAMP())")
            ->execute([$orderId,$event,$email,$subject,$text,$html]);
    }

    public function dispatch(int $max=1,?int $orderId=null): void
    {
        for($i=0;$i<max(1,$max);$i++){
            $this->db->beginTransaction();
            try{
                $this->db->exec("UPDATE order_email_outbox SET status='pending',locked_at=NULL WHERE status='sending' AND locked_at < UTC_TIMESTAMP() - INTERVAL 10 MINUTE");
                $sql="SELECT * FROM order_email_outbox WHERE status IN ('pending','failed') AND attempt_count<5 AND next_attempt_at<=UTC_TIMESTAMP()".($orderId!==null?' AND order_id=?':'').' ORDER BY id LIMIT 1 FOR UPDATE';
                $query=$this->db->prepare($sql);
                $query->execute($orderId!==null?[$orderId]:[]);
                $message=$query->fetch(PDO::FETCH_ASSOC);
                if(!$message){$this->db->commit();return;}
                $claim=$this->db->prepare("UPDATE order_email_outbox SET status='sending',locked_at=UTC_TIMESTAMP(),attempt_count=attempt_count+1 WHERE id=? AND status IN ('pending','failed')");
                $claim->execute([$message['id']]);
                if($claim->rowCount()!==1){$this->db->commit();continue;}
                $message['attempt_count']=(int)$message['attempt_count']+1;
                $this->db->commit();
            }catch(Throwable $error){
                if($this->db->inTransaction())$this->db->rollBack();
                $this->logger->log('error','Order email queue claim failed',['exception'=>$error::class]);
                return;
            }
            try{
                $attachment=null;if(!empty($message['attachment_path']))$attachment=dirname(__DIR__,3).DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,(string)$message['attachment_path']);
                (new SmtpMailer($this->config))->sendHtml((string)$message['recipient_email'],(string)$message['subject'],(string)$message['body_text'],(string)$message['body_html'],$attachment,(string)($message['attachment_name']??'invoice.pdf'));
                $this->db->prepare("UPDATE order_email_outbox SET status='sent',sent_at=UTC_TIMESTAMP(),locked_at=NULL,last_error=NULL WHERE id=? AND status='sending'")->execute([$message['id']]);
            }catch(Throwable $error){
                $attempt=(int)$message['attempt_count'];
                $delay=[1=>1,2=>5,3=>15,4=>60,5=>360][$attempt]??1440;
                $status=$attempt>=5?'failed':'pending';
                $this->db->prepare("UPDATE order_email_outbox SET status=?,next_attempt_at=UTC_TIMESTAMP() + INTERVAL {$delay} MINUTE,locked_at=NULL,last_error=? WHERE id=? AND status='sending'")
                    ->execute([$status,mb_substr($error::class.': '.$error->getMessage(),0,500),(int)$message['id']]);
                $this->logger->log('error','Order email delivery failed',['order_id'=>(int)$message['order_id'],'event_key'=>$message['event_key'],'attempt'=>$attempt,'exception'=>$error::class]);
            }
        }
    }

    public function dispatchSafely(int $max=1,?int $orderId=null): void
    {
        try{$this->dispatch($max,$orderId);}
        catch(Throwable $error){$this->logger->log('error','Order email dispatch could not be completed',['order_id'=>$orderId,'exception'=>$error::class]);}
    }

    public function status(int $orderId,string $event): ?string
    {
        $query=$this->db->prepare('SELECT status FROM order_email_outbox WHERE order_id=? AND event_key=? LIMIT 1');
        $query->execute([$orderId,$event]);
        $status=$query->fetchColumn();
        return is_string($status)?$status:null;
    }
}
