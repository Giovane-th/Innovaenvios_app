<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require __DIR__.'/correios-client.php';

function correiosCatalogRequest(array $config,string $baseUrl,array $payload,?string $token=null):array{
 $token=$token?:correiosTokenValue($config);
 $ch=curl_init(rtrim($baseUrl,'/').'/v1/nacional');
 curl_setopt_array($ch,[
  CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>40,
  CURLOPT_HTTPHEADER=>[
   'Authorization: Bearer '.$token,
   'Accept: application/json','Content-Type: application/json'
  ],
  CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
 ]);
 $raw=curl_exec($ch);$networkError=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 if($raw===false)throw new RuntimeException('Falha de rede com os Correios: '.$networkError);
 $data=json_decode((string)$raw,true);
 if($code<200||$code>=300){
  $message='Correios respondeu HTTP '.$code;
  if(is_array($data)){
   $message=(string)($data['causa']??$data['mensagem']??$data['message']??($data['msgs'][0]??$message));
  }
  throw new RuntimeException($message);
 }
 if(!is_array($data))throw new RuntimeException('Resposta inválida dos Correios');
 return $data;
}
function correiosMoneyToCents(mixed $value):int{
 $text=trim((string)$value);
 if($text==='')return 0;
 $text=str_replace(['R$',' '],'',$text);
 if(str_contains($text,','))$text=str_replace(['.',','],['','.'],$text);
 return (int)round(((float)$text)*100);
}
function formatCode(string $format):string{
 $format=mb_strtolower($format);
 if(str_contains($format,'envelope'))return '1';
 if(str_contains($format,'rolo'))return '3';
 return '2';
}
function publicCorreiosError(Throwable $e):string{
 $message=trim($e->getMessage());
 return $message!==''?$message:'Falha na consulta aos Correios';
}

$action=$_GET['action']??'status';
if($action==='status'){
 userId();
 try{correiosAuth($config);out(['ok'=>true,'service'=>'Correios CWS']);}
 catch(Throwable $e){out(['error'=>publicCorreiosError($e)],502);}
}
if($action!=='quote')out(['error'=>'Operação não disponível'],404);
requireSameOrigin($config);
if($_SERVER['REQUEST_METHOD']!=='POST')out(['error'=>'Método não permitido'],405);
$uid=!empty($_SESSION['user_id'])?(int)$_SESSION['user_id']:0;

$input=body();
$origin=preg_replace('/\D+/','',(string)($input['origin_zip']??''));
if($uid>0){
 $profileQ=$pdo->prepare('SELECT postal_code FROM user_shipping_profiles WHERE user_id=? LIMIT 1');
 $profileQ->execute([$uid]);
 $profileOrigin=preg_replace('/\D+/','',(string)$profileQ->fetchColumn());
 if(strlen($profileOrigin)===8)$origin=$profileOrigin;
}
$destination=preg_replace('/\D+/','',(string)($input['destination_zip']??''));
$weightGrams=(int)round(((float)($input['weight_kg']??0))*1000);
$height=(float)($input['height_cm']??0);$width=(float)($input['width_cm']??0);$length=(float)($input['length_cm']??0);
if(strlen($origin)!==8)out(['error'=>'Informe um CEP de origem válido'],422);
if(strlen($destination)!==8)out(['error'=>'Informe um CEP de destino válido'],422);
if($weightGrams<1||$height<=0||$width<=0||$length<=0)out(['error'=>'Informe peso e medidas válidas'],422);

$services=[];
$serviceDefaults=['correios_servico_pac'=>'03298','correios_servico_sedex'=>'03220'];
foreach(['PAC'=>'correios_servico_pac','SEDEX'=>'correios_servico_sedex'] as $name=>$key){
 $code=preg_replace('/\D+/','',(string)($config[$key]??$serviceDefaults[$key]));
 if($code!=='')$services[$code]=$name;
}
if(!$services)out(['error'=>'Configure os códigos PAC e SEDEX do seu contrato'],503);

try{$auth=correiosAuth($config);}catch(Throwable $e){out(['error'=>publicCorreiosError($e)],502);}
$token=(string)$auth['token'];
$tokenCard=is_array($auth['cartaoPostagem']??null)?$auth['cartaoPostagem']:[];
$contract=trim((string)($tokenCard['contrato']??$config['correios_contrato']??''));
$dr=(int)($tokenCard['dr']??$config['correios_dr']??0);

$lote='INNOVA-'.date('YmdHis').'-'.bin2hex(random_bytes(3));
$priceParams=[];$deadlineParams=[];
foreach($services as $code=>$name){
 $requestId=$lote.'-'.$code;
 $item=[
  'coProduto'=>$code,'nuRequisicao'=>$requestId,
  'cepOrigem'=>$origin,'cepDestino'=>$destination,
  'psObjeto'=>(string)$weightGrams,'tpObjeto'=>formatCode((string)($input['package_format']??'')),
  'comprimento'=>(string)$length,'largura'=>(string)$width,'altura'=>(string)$height,'diametro'=>'0',
  'servicosAdicionais'=>[],'vlDeclarado'=>'0'
 ];
 if($contract!==''&&$dr>0){$item['nuContrato']=$contract;$item['nuDR']=$dr;}
 $priceParams[]=$item;
 $deadlineParams[]=[
  'coProduto'=>$code,'nuRequisicao'=>$requestId,'cepOrigem'=>$origin,
  'cepDestino'=>$destination,'dataPostagem'=>date('Y-m-d')
 ];
}
try{
 $prices=correiosCatalogRequest($config,'https://api.correios.com.br/preco',['idLote'=>$lote,'parametrosProduto'=>$priceParams],$token);
 $publicPriceByCode=[];
 try{
  $contractPac=preg_replace('/\D+/','',(string)($config['correios_servico_pac']??$serviceDefaults['correios_servico_pac']));
  $contractSedex=preg_replace('/\D+/','',(string)($config['correios_servico_sedex']??$serviceDefaults['correios_servico_sedex']));
  $counterCodeByContract=[
   $contractPac=>preg_replace('/\D+/','',(string)($config['correios_servico_pac_balcao']??'04510')),
   $contractSedex=>preg_replace('/\D+/','',(string)($config['correios_servico_sedex_balcao']??'04014'))
  ];
  $contractByCounterCode=[];
  $publicParams=[];
  foreach($priceParams as $item){
   $contractCode=(string)$item['coProduto'];
   $counterCode=(string)($counterCodeByContract[$contractCode]??'');
   if($counterCode==='')continue;
   unset($item['nuContrato'],$item['nuDR'],$item['nuUnidade']);
   $item['coProduto']=$counterCode;
   $item['nuRequisicao']=$lote.'-BALCAO-'.$counterCode;
   $publicParams[]=$item;
   $contractByCounterCode[$counterCode]=$contractCode;
  }
  if($publicParams){
   $publicPrices=correiosCatalogRequest($config,'https://api.correios.com.br/preco',['idLote'=>$lote.'-BALCAO','parametrosProduto'=>$publicParams],$token);
   foreach($publicPrices as $publicRow){
    if(!is_array($publicRow))continue;
    $counterCode=(string)($publicRow['coProduto']??'');
    $contractCode=(string)($contractByCounterCode[$counterCode]??'');
    $publicError=trim((string)($publicRow['txErro']??''));
    $publicCents=correiosMoneyToCents($publicRow['pcFinal']??$publicRow['pcProduto']??$publicRow['pcBaseGeral']??0);
    if($contractCode!==''&&$publicError===''&&$publicCents>0)$publicPriceByCode[$contractCode]=$publicCents;
   }
  }
 }catch(Throwable $ignored){
  error_log('Consulta de tarifa de balcão: '.$ignored->getMessage());
 }
 $deadlines=correiosCatalogRequest($config,'https://api.correios.com.br/prazo',['idLote'=>$lote,'parametrosPrazo'=>$deadlineParams],$token);
 $deadlineByCode=[];foreach($deadlines as $row)if(is_array($row))$deadlineByCode[(string)($row['coProduto']??'')]=$row;
 $markup=max(0,(float)($config['freight_markup_percent']??40));$quotes=[];
 foreach($prices as $row){
  if(!is_array($row))continue;
  $code=(string)($row['coProduto']??'');if($code===''||!isset($services[$code]))continue;
  $error=trim((string)($row['txErro']??''));if($error!=='')continue;
  $baseCents=correiosMoneyToCents($row['pcFinal']??$row['pcProduto']??0);if($baseCents<1)continue;
  $saleCents=(int)ceil($baseCents*(1+($markup/100)));
  $referenceCandidates=[
   (int)($publicPriceByCode[$code]??0),
   correiosMoneyToCents($row['pcBaseGeral']??0),
   correiosMoneyToCents($row['pcReferencia']??0),
   correiosMoneyToCents($row['pcBase']??0)
  ];
  // Referência promocional autorizada: não representa tarifa oficial de balcão.
  $referenceMultiplier=max(1.01,(float)($config['freight_reference_multiplier']??2));
  $promotionalReference=(int)ceil($saleCents*$referenceMultiplier);
  $referenceCents=max(max($referenceCandidates),$promotionalReference);
  $discountPercent=$referenceCents>$saleCents
   ?(int)round((1-($saleCents/$referenceCents))*100)
   :0;
  $deadline=$deadlineByCode[$code]??[];
  $deadlineError=trim((string)($deadline['txErro']??''));
  $quotes[]=[
   'service_code'=>$code,
   'service_name'=>(string)($row['noProduto']??$row['nomeProduto']??($services[$code].' Contrato')),
   'price_cents'=>$saleCents,'reference_price_cents'=>$referenceCents,'discount_percent'=>$discountPercent,
   'delivery_days'=>$deadlineError===''?(int)($deadline['prazoEntrega']??0):null,
   'maximum_date'=>$deadlineError===''?($deadline['dataMaxima']??null):null,
   'home_delivery'=>$deadline['entregaDomiciliar']??null,
   'saturday_delivery'=>$deadline['entregaSabado']??null
  ];
 }
 if(!$quotes){
  $errors=[];foreach($prices as $row)if(is_array($row)&&!empty($row['txErro']))$errors[]=(string)$row['txErro'];
  throw new RuntimeException($errors[0]??'Nenhum serviço disponível para este envio');
 }
 out(['quotes'=>$quotes,'markup_percent'=>$markup,'origin_zip'=>$origin]);
}catch(Throwable $e){out(['error'=>publicCorreiosError($e)],502);}
