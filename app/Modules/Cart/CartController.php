<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Cart;

use Giftcheta\Core\Config;
use Giftcheta\Http\Request;
use Giftcheta\Http\Response;
use Giftcheta\Modules\Storefront\CatalogRepository;
use Giftcheta\Modules\Storefront\StoreSettingsRepository;
use Giftcheta\Modules\Storefront\StorefrontView;
use PDO;

final class CartController
{
    private CartRepository $repository;private CatalogRepository $catalog;private StoreSettingsRepository $storeSettings;private CartFileStorage $files;private CartService $service;private CartSessionManager $sessions;private StorefrontView $view;private string $baseUrl;
    public function __construct(PDO $db,string $projectRoot,Config $config)
    { $this->repository=new CartRepository($db);$this->catalog=new CatalogRepository($db);$this->storeSettings=new StoreSettingsRepository($db);$this->files=new CartFileStorage($db,$projectRoot);$this->service=new CartService($db,$this->repository,$this->files);$this->sessions=new CartSessionManager();$this->view=new StorefrontView($projectRoot.'/app/Modules/Storefront/Views');$this->baseUrl=rtrim((string)$config->get('app.url',''),'/'); }

    public function show(Request $request): Response
    { $context=$this->sessions->start();$summary=$this->service->summary($context['key']);foreach($summary['items'] as &$item)$item['variants']=$this->repository->variants((int)$item['product_id']);unset($item);$data=['title'=>'Количка','description'=>'Вашите избрани продукти.','canonical'=>$this->baseUrl.'/cart','schema'=>null,'noindex'=>true,'items'=>$summary['items'],'count'=>$summary['count'],'cartCount'=>$summary['count'],'subtotal'=>$summary['subtotal_cents'],'csrfToken'=>$context['csrf'],'cartFlash'=>$this->sessions->consumeFlash()];return $this->html('cart',$data,$request); }

    public function snapshot(Request $request): Response
    { $context=$this->sessions->start();return new Response(json_encode(['data'=>['cart'=>$this->cartSnapshot($context['key'])]],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),200,['Content-Type'=>'application/json; charset=utf-8','X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store']); }

    public function add(Request $request): Response
    {
        $context=$this->sessions->start();$ajax=$this->wantsJson($request);$target='/cart';
        if(!$this->sessions->validCsrf($request))return $this->addError('Заявката изтече. Обновете страницата и опитайте отново.',419,'csrf_invalid',$target,$ajax);
        $productId=$this->positive($request->form['product_id']??null);$variantId=$this->optionalPositive($request->form['variant_id']??null);$quantity=filter_var($request->form['quantity']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>999]]);
        if($productId===null||$variantId===false||$quantity===false)return $this->addError('Проверете продукта, варианта и количеството.',422,'validation_error',$target,$ajax);
        $product=$this->repository->product($productId);if(!$product)return $this->addError('Продуктът не е наличен.',404,'product_not_found',$target,$ajax);$target='/product/'.$product['slug'];
        $cartId=$this->service->cartId($context['key'],true);if($cartId===null)return $this->addError('Количката не можа да бъде създадена.',500,'cart_unavailable',$target,$ajax);
        $rows=$this->customizationInput($request->form['customizations']??[]);$imageFields=$this->repository->imageFieldIds($productId);$staged=$this->stageFiles($request,$cartId,$productId,$imageFields);
        if(isset($staged['error'])){$this->service->discardUnattached($cartId);return $this->addError($staged['error'],$staged['status'],'upload_invalid',$target,$ajax);}
        $rows=$this->mergeImageReferences($rows,$staged);$result=$this->service->add($context['key'],$productId,$variantId===null?null:$variantId,(int)$quantity,$rows);
        if(!$result['ok']){$this->service->discardUnattached($cartId);return $this->addError($result['message'],$result['status'],$result['code']??'cart_error',$target,$ajax);}
        if($ajax)return Response::json(['data'=>['message'=>$result['message'],'cart'=>$this->cartSnapshot($context['key'])]],201);
        $this->sessions->setFlash($result['message']);return $this->redirect('/cart');
    }

    public function quantityForm(Request $request,array $params): Response
    { $context=$this->sessions->start();if(!$this->sessions->validCsrf($request))return $this->formError('Заявката изтече. Обновете страницата.',419,'/cart');$quantity=filter_var($request->form['quantity']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>999]]);if($quantity===false)return $this->formError('Количеството трябва да е между 1 и 999.',422,'/cart');$result=$this->service->setQuantity($context['key'],(string)$params['item_key'],(int)$quantity);if(!$result['ok'])return $this->formError($result['message'],$result['status'],'/cart');$this->sessions->setFlash($result['message']);return $this->redirect('/cart'); }

    public function removeForm(Request $request,array $params): Response
    { $context=$this->sessions->start();if(!$this->sessions->validCsrf($request))return $this->formError('Заявката изтече. Обновете страницата.',419,'/cart');$result=$this->service->remove($context['key'],(string)$params['item_key']);if(!$result['ok'])return $this->formError($result['message'],$result['status'],'/cart');$this->sessions->setFlash($result['message']);return $this->redirect('/cart'); }

    public function updateCustomizations(Request $request,array $params): Response
    { $context=$this->sessions->start();if(!$this->sessions->validCsrf($request))return $this->formError('Заявката изтече. Обновете страницата.',419,'/cart');$key=(string)$params['item_key'];$item=$this->service->editItem($context['key'],$key);if(!$item)return $this->formError('Редът в количката не е намерен.',404,'/cart');$variantId=$this->optionalPositive($request->form['variant_id']??null);if($variantId===false)return $this->formError('Избраният вариант е невалиден.',422,'/cart');$rows=$this->customizationInput($request->form['customizations']??[]);$cartId=$this->service->cartId($context['key']);$staged=$this->stageFiles($request,(int)$cartId,(int)$item['product_id'],$this->repository->imageFieldIds((int)$item['product_id']));if(isset($staged['error'])){$this->service->discardUnattached((int)$cartId);return $this->formError($staged['error'],$staged['status'],'/cart');}$rows=$this->mergeImageReferences($rows,$staged);$result=$this->service->updateCustomizations($context['key'],$key,$variantId===null?null:$variantId,$rows);if(!$result['ok']){$this->service->discardUnattached((int)$cartId);return $this->formError($result['message'],$result['status'],'/cart');}$this->sessions->setFlash($result['message']);return $this->redirect('/cart'); }

    public function upload(Request $request): Response
    { $context=$this->sessions->start();if(!$this->sessions->validCsrf($request))return Response::json(['error'=>['code'=>'csrf_invalid','message'=>'Заявката изтече. Обновете страницата.']],419);$productId=$this->positive($request->form['product_id']??null);$fieldId=$this->positive($request->form['field_id']??null);$product=$productId?$this->repository->product($productId):null;if(!$product||$product['status']!=='active'||$product['deleted_at']!==null||$fieldId===null||!in_array($fieldId,$this->repository->imageFieldIds($productId),true))return Response::json(['error'=>['code'=>'field_invalid','message'=>'Полето за изображение не е валидно.']],422);$cartId=$this->service->cartId($context['key'],true);$file=$this->singleFile($request->files['file']??null);if(!$cartId||!$file)return Response::json(['error'=>['code'=>'upload_invalid','message'=>'Изберете валидно изображение.']],422);$result=$this->files->stage($cartId,$productId,$fieldId,$file);if(!$result['ok'])return Response::json(['error'=>['code'=>'upload_failed','message'=>$result['message']]],$result['status']);return Response::json(['data'=>['file_reference'=>$result['file_reference'],'preview_url'=>'/cart/files/'.$result['file_reference']]],201); }

    public function file(Request $request,array $params): Response
    { $context=$this->sessions->start();$file=$this->service->file($context['key'],(string)$params['reference']);if(!$file)return new Response('Not found',404,['Content-Type'=>'text/plain; charset=utf-8','Cache-Control'=>'private, no-store']);return new Response((string)file_get_contents($file['path']),200,['Content-Type'=>$file['mime'],'Content-Length'=>(string)filesize($file['path']),'Content-Disposition'=>'inline; filename="personalization-image"','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff','Content-Security-Policy'=>"default-src 'none'; sandbox"]); }

    public function patchJson(Request $request,array $params): Response
    { $context=$this->sessions->start();if(!$this->sessions->validCsrf($request))return Response::json(['error'=>['code'=>'csrf_invalid','message'=>'Невалиден защитен токен.']],419);$body=$request->json();$quantity=filter_var($body['quantity']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>999]]);if($quantity===false)return Response::json(['error'=>['code'=>'validation_error','message'=>'Количеството трябва да е между 1 и 999.']],422);$result=$this->service->setQuantity($context['key'],(string)$params['item_key'],(int)$quantity);if(!$result['ok'])return $this->jsonResult($result);return Response::json(['data'=>['message'=>$result['message'],'cart'=>$this->cartSnapshot($context['key'])]]); }
    public function deleteJson(Request $request,array $params): Response
    { $context=$this->sessions->start();if(!$this->sessions->validCsrf($request))return Response::json(['error'=>['code'=>'csrf_invalid','message'=>'Невалиден защитен токен.']],419);$result=$this->service->remove($context['key'],(string)$params['item_key']);if(!$result['ok'])return $this->jsonResult($result);return Response::json(['data'=>['message'=>$result['message'],'cart'=>$this->cartSnapshot($context['key'])]]); }

    private function stageFiles(Request $request,int $cartId,int $productId,array $allowedFields): array
    { $files=$this->nestedFiles($request->files['customization_files']??null);if(count($files)>30)return ['error'=>'Може да се качат до 30 изображения наведнъж.','status'=>422];$staged=[];foreach($files as $id=>$file){$fieldId=$this->positive($id);if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)continue;if($fieldId===null||!in_array($fieldId,$allowedFields,true))return ['error'=>'Полето за изображение не е валидно.','status'=>422];$result=$this->files->stage($cartId,$productId,$fieldId,$file);if(!$result['ok'])return ['error'=>$result['message'],'status'=>$result['status']];$staged[$fieldId]=$result['file_reference'];}return $staged; }
    private function customizationInput(mixed $values): array
    { if(!is_array($values))return []; $rows=[];foreach($values as $id=>$value){$fieldId=$this->positive($id);if($fieldId===null)continue;if(is_array($value))$rows[]=['field_id'=>$fieldId,'value'=>is_string($value['value']??null)?$value['value']:'','file_reference'=>is_string($value['file_reference']??null)?$value['file_reference']:null];else $rows[]=['field_id'=>$fieldId,'value'=>is_string($value)?$value:''];}return $rows; }
    private function mergeImageReferences(array $rows,array $staged): array { foreach($staged as $fieldId=>$reference){$rows=array_values(array_filter($rows,static fn(array $row):bool=>(int)$row['field_id']!==(int)$fieldId));$rows[]=['field_id'=>(int)$fieldId,'file_reference'=>$reference];}return $rows; }
    private function nestedFiles(mixed $files): array
    { if(!is_array($files)||!isset($files['name'])||!is_array($files['name']))return [];$result=[];foreach($files['name'] as $key=>$name){$result[(string)$key]=['name'=>$name,'type'=>$files['type'][$key]??'','tmp_name'=>$files['tmp_name'][$key]??'','error'=>$files['error'][$key]??UPLOAD_ERR_NO_FILE,'size'=>$files['size'][$key]??0];}return $result; }
    private function singleFile(mixed $file): ?array { return is_array($file)&&isset($file['name'],$file['tmp_name'],$file['error'],$file['size'])?$file:null; }
    private function html(string $name,array $data,?Request $request=null): Response { $data['baseUrl']=$this->baseUrl;$data['view']=$this->view;$summary=null;if(!isset($data['cartCount'])||!isset($data['cartSubtotalCents']))$summary=$this->service->summary($this->sessions->start()['key']);$data['cartCount']??=$data['count']??$summary['count']??0;$data['cartSubtotalCents']??=$data['subtotal']??$summary['subtotal_cents']??0;$data['headerCategories']=$this->catalog->categories();$data+=$this->storeSettings->shipping();$body=$this->view->render($name,$data);$headers=['Content-Type'=>'text/html; charset=utf-8','X-Content-Type-Options'=>'nosniff','Referrer-Policy'=>'strict-origin-when-cross-origin','Cache-Control'=>'private, no-store, max-age=0'];$navigation=false;if($request)foreach($request->headers as $name=>$value)if(strtolower((string)$name)==='accept'&&str_contains((string)$value,'application/vnd.giftcheta.fragment+html')){$navigation=true;break;}if($navigation&&preg_match('/<main\b[^>]*>.*?<\/main>/s',$body,$match)){$headers['X-Page-Title']=rawurlencode((string)($data['title']??'Giftcheta'));$headers['X-Page-Description']=rawurlencode((string)($data['description']??''));$headers['X-Page-Canonical']=rawurlencode((string)($data['canonical']??''));$headers['X-Page-Robots']=!empty($data['noindex'])?'noindex,follow':'index,follow';return new Response($match[0],200,$headers);}return new Response($body,200,$headers); }
    private function cartSnapshot(string $keyHash): array
    {
        $summary=$this->service->summary($keyHash);
        $items=array_map(function(array $item):array{
            $path=(string)($item['image_path']??'');$imageUrl=preg_match('~^uploads/[a-zA-Z0-9/_-]+\.(?:jpg|jpeg|png|webp)$~',$path)===1?'/'.$path:null;
            $customizations=[];foreach(($item['custom_display']??[]) as $custom){$value=(string)($custom['value_text']??'');if(($custom['field_type']??'')==='image'&&($custom['file_reference']??null))$value='Изображението е добавено';if($value!=='')$customizations[]=['label'=>((string)($custom['field_name_snapshot']??''))==='Опишете желаната персонализация'?'Персонализация':(string)($custom['field_name_snapshot']??''),'value'=>mb_strimwidth($value,0,72,'…')];}
            return ['item_key'=>(string)$item['item_key'],'product_name'=>(string)$item['product_name_snapshot'],'product_slug'=>(string)($item['slug']??''),'image_url'=>$imageUrl,'image_alt'=>(string)($item['image_alt']??$item['product_name_snapshot']),'variant_name'=>(string)($item['variant_name_snapshot']??''),'customizations'=>$customizations,'quantity'=>(int)$item['quantity'],'unit_price_current_cents'=>(int)$item['unit_price_current_cents'],'line_total_cents'=>(int)$item['line_total_cents'],'issue'=>(string)($item['issue']??'')];
        },$summary['items']);
        return ['items'=>$items,'count'=>(int)$summary['count'],'subtotal_cents'=>(int)$summary['subtotal_cents']];
    }
    private function wantsJson(Request $request): bool { foreach($request->headers as $name=>$value)if(strtolower((string)$name)==='accept'&&str_contains(strtolower((string)$value),'application/json'))return true;return false; }
    private function addError(string $message,int $status,string $code,string $target,bool $ajax): Response { if($ajax)return Response::json(['error'=>['code'=>$code,'message'=>$message]],$status);return $this->formError($message,$status,$target); }
    private function formError(string $message,int $status,string $target): Response { $this->sessions->setFlash($message,'error');return $this->redirect($target); }
    private function redirect(string $path): Response { return new Response('',303,['Location'=>$path,'Cache-Control'=>'private, no-store']); }
    private function jsonResult(array $result): Response { return $result['ok']?Response::json(['data'=>['message'=>$result['message']]]):Response::json(['error'=>['code'=>$result['code']??'cart_error','message'=>$result['message']]],$result['status']); }
    private function positive(mixed $value): ?int { $n=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);return $n===false?null:(int)$n; }
    private function optionalPositive(mixed $value): int|false|null { if($value===null||$value==='')return null;return $this->positive($value)??false; }
}
