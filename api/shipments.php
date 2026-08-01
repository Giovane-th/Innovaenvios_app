<?php
declare(strict_types=1);

require __DIR__.'/bootstrap.php';
$uid=userId();
requireSameOrigin($config);
require __DIR__.'/correios-client.php';
require __DIR__.'/documents.php';
require __DIR__.'/mailer.php';

function shipmentCorreiosMoneyToCents(mixed $value):int{
 $text=trim((string)$value);
 if($text==='')return 0;
 $text=str_replace(['R$',' '],'',$text);
 if(str_contains($text,','))$text=str_replace(['.',','],['','.'],$text);
 return (int)round(((float)$text)*100);
}

function shipmentFormatCode(string $format):string{
 $format=mb_strtolower($format);
 if(str_contains($format,'envelope'))return '1';
 if(str_contains($format,'rolo'))return '3';
 return '2';
}

function verifiedCorreiosPrice(
 array $config,
 string $serviceCode,
 string $origin,
 string $dest,
 int $weight,
 float $height,
 float $width,
 float $length,
 string $format
):array{
 $auth=correiosAuth($config);
 $token=(string)$auth['token'];
 $card=is_array($auth['cartaoPostagem']??null)?$auth['cartaoPostagem']:[];
 $contract=trim((string)($card['contrato']??$config['correios_contrato']??''));
 $dr=(int)($card['dr']??$config['correios_dr']??0);
 $item=[
  'coProduto'=>$serviceCode,
  'nuRequisicao'=>'EMISSAO-'.date('YmdHis').'-'.bin2hex(random_bytes(3)),
  'cepOrigem'=>$origin,
  'cepDestino'=>$dest,
  'psObjeto'=>(string)$weight,
  'tpObjeto'=>shipmentFormatCode($format),
  'comprimento'=>(string)$length,
  'largura'=>(string)$width,
  'altura'=>(string)$height,
  'diametro'=>'0',
  'servicosAdicionais'=>[],
  'vlDeclarado'=>'0'
 ];
 if($contract!==''&&$dr>0){
  $item['nuContrato']=$contract;
  $item['nuDR']=$dr;
 }
 $ch=curl_init('https://api.correios.com.br/preco/v1/nacional');
 curl_setopt_array($ch,[
  CURLOPT_POST=>true,
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_TIMEOUT=>40,
  CURLOPT_HTTPHEADER=>[
   'Authorization: Bearer '.$token,
   'Accept: application/json',
   'Content-Type: application/json'
  ],
  CURLOPT_POSTFIELDS=>json_encode([
   'idLote'=>'EMISSAO-'.date('YmdHis'),
   'parametrosProduto'=>[$item]
  ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
 ]);
 $raw=curl_exec($ch);
 $networkError=curl_error($ch);
 $httpCode=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
 curl_close($ch);
 if($raw===false)throw new RuntimeException('Falha de rede com os Correios: '.$networkError);
 $data=json_decode((string)$raw,true);
 if($httpCode<200||$httpCode>=300){
  $detail=is_array($data)
   ?($data['causa']??$data['mensagem']??$data['message']??($data['msgs'][0]??null))
   :null;
  throw new RuntimeException(trim((string)$detail)?:('Correios respondeu HTTP '.$httpCode));
 }
 $row=is_array($data)&&isset($data[0])&&is_array($data[0])?$data[0]:[];
 if(!empty($row['txErro']))throw new RuntimeException((string)$row['txErro']);
 $cost=shipmentCorreiosMoneyToCents($row['pcFinal']??$row['pcProduto']??0);
 if($cost<1)throw new RuntimeException('Preço inválido retornado pelos Correios');
 $markup=max(0,(float)($config['freight_markup_percent']??40));
 return [
  'cost_cents'=>$cost,
  'price_cents'=>(int)ceil($cost*(1+($markup/100)))
 ];
}

if($_SERVER['REQUEST_METHOD']==='GET'){
 $roleQ=$pdo->prepare('SELECT role FROM users WHERE id=?');
 $roleQ->execute([$uid]);
 $isAdmin=$roleQ->fetchColumn()==='admin';
 if($isAdmin){
  $q=$pdo->query(
   'SELECT s.id,s.tracking_code,u.name AS customer_name,u.email AS customer_email,'.
   's.service,s.origin_zip,s.destination_zip,s.price_cents,s.billing_type,'.
   's.wallet_charged_cents,s.status,s.created_at '.
   'FROM shipments s JOIN users u ON u.id=s.user_id '.
   'ORDER BY s.id DESC LIMIT 500'
  );
 }else{
  $q=$pdo->prepare(
   'SELECT s.id,s.tracking_code,u.name AS customer_name,u.email AS customer_email,'.
   's.service,s.origin_zip,s.destination_zip,s.price_cents,s.billing_type,'.
   's.wallet_charged_cents,s.status,s.created_at '.
   'FROM shipments s JOIN users u ON u.id=s.user_id '.
   'WHERE s.user_id=? ORDER BY s.id DESC LIMIT 100'
  );
  $q->execute([$uid]);
 }
 out(['shipments'=>$q->fetchAll(),'scope'=>$isAdmin?'all':'own']);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
 $d=body();
 $service=trim((string)($d['service']??''));
 $serviceCode=preg_replace('/\D+/','',(string)($d['service_code']??''));
 $origin=phone((string)($d['origin_zip']??''));
 $dest=phone((string)($d['destination_zip']??''));
 $recipientName=trim((string)($d['recipient_name']??''));
 $recipientEmail=strtolower(trim((string)($d['recipient_email']??'')));
 $recipientPhone=phone((string)($d['recipient_phone']??''));
 $recipientDocument=phone((string)($d['recipient_document']??''));
 $recipientStreet=trim((string)($d['recipient_street']??''));
 $recipientNumber=trim((string)($d['recipient_number']??''));
 $recipientComplement=trim((string)($d['recipient_complement']??''));
 $recipientNeighborhood=trim((string)($d['recipient_neighborhood']??''));
 $recipientCity=trim((string)($d['recipient_city']??''));
 $recipientState=strtoupper(trim((string)($d['recipient_state']??'')));
 $weight=(int)($d['package_weight_grams']??0);
 $height=(float)($d['package_height_cm']??0);
 $width=(float)($d['package_width_cm']??0);
 $length=(float)($d['package_length_cm']??0);
 $contents=is_array($d['contents']??null)?$d['contents']:[];
 $format=(string)($d['package_format']??'Caixa / pacote');

 if(!$service||strlen($origin)!==8||strlen($dest)!==8){
  out(['error'=>'Dados básicos do envio inválidos'],422);
 }
 $allowedCodes=[
  preg_replace('/\D+/','',(string)($config['correios_servico_pac']??'03298')),
  preg_replace('/\D+/','',(string)($config['correios_servico_sedex']??'03220'))
 ];
 if($serviceCode===''||!in_array($serviceCode,$allowedCodes,true)){
  out(['error'=>'Serviço dos Correios inválido'],422);
 }
 if(
  mb_strlen($recipientName)<2||
  !in_array(strlen($recipientDocument),[11,14],true)||
  strlen($recipientPhone)<10||
  strlen($recipientPhone)>11
 ){
  out(['error'=>'Dados do destinatário inválidos'],422);
 }
 if($recipientEmail!==''&&!filter_var($recipientEmail,FILTER_VALIDATE_EMAIL)){
  out(['error'=>'E-mail do destinatário inválido'],422);
 }
 if(
  !$recipientStreet||
  !$recipientNumber||
  !$recipientNeighborhood||
  !$recipientCity||
  !preg_match('/^[A-Z]{2}$/',$recipientState)
 ){
  out(['error'=>'Endereço do destinatário incompleto'],422);
 }
 if($weight<1||$height<=0||$width<=0||$length<=0){
  out(['error'=>'Peso ou dimensões inválidos'],422);
 }
 if(count($contents)<1)out(['error'=>'Informe o conteúdo da encomenda'],422);
 foreach($contents as $item){
  if(
   mb_strlen(trim((string)($item['description']??'')))<2||
   (int)($item['quantity']??0)<1||
   (int)($item['value_cents']??0)<1
  ){
   out(['error'=>'Declaração de conteúdo inválida'],422);
  }
 }

 $q=$pdo->prepare(
  'SELECT document,phone,postal_code,street,number,complement,neighborhood,city,state '.
  'FROM user_shipping_profiles WHERE user_id=?'
 );
 $q->execute([$uid]);
 $sender=$q->fetch();
 if(!$sender){
  out(['error'=>'Cadastre seus dados de remetente em Meus dados de envio antes de emitir'],422);
 }
 $origin=phone((string)$sender['postal_code']);
 if(strlen($origin)!==8)out(['error'=>'CEP do remetente inválido'],422);
 if(empty($config['correios_live_emission'])){
  out([
   'error'=>'Emissão real aguardando homologação da API Pré-Postagem dos Correios. '.
   'Nenhum saldo foi descontado.'
  ],503);
 }

 $userQ=$pdo->prepare(
  'SELECT id,name,email,phone,status,role,allow_postpaid FROM users WHERE id=? LIMIT 1'
 );
 $userQ->execute([$uid]);
 $user=$userQ->fetch();
 if(!$user||$user['status']!=='active'){
  out(['error'=>'Conta não encontrada ou bloqueada'],403);
 }
 $internal=(int)$user['allow_postpaid']===1;
 $q=$pdo->prepare('SELECT balance_cents FROM wallets WHERE user_id=?');
 $q->execute([$uid]);
 $currentBalance=(int)$q->fetchColumn();

 try{
  $verifiedPrice=verifiedCorreiosPrice(
   $config,$serviceCode,$origin,$dest,$weight,$height,$width,$length,$format
  );
  $cost=(int)$verifiedPrice['cost_cents'];
  $price=(int)$verifiedPrice['price_cents'];
 }catch(Throwable $e){
  out(['error'=>$e->getMessage().'. Nenhum saldo foi descontado.'],502);
 }
 if(!$internal&&$currentBalance<$price){
  out(['error'=>'Saldo insuficiente para o preço atualizado'],409);
 }

 $shipmentData=[
  'service_code'=>$serviceCode,
  'format_code'=>shipmentFormatCode($format),
  // Máximo de 25 caracteres exigido em pedidoExternoOrigem pelos Correios.
  'external_id'=>'IN'.date('ymdHis').str_pad((string)($uid%10000),4,'0',STR_PAD_LEFT).bin2hex(random_bytes(3)),
  'destination_zip'=>$dest,
  'recipient_name'=>$recipientName,
  'recipient_email'=>$recipientEmail,
  'recipient_phone'=>$recipientPhone,
  'recipient_document'=>$recipientDocument,
  'recipient_street'=>$recipientStreet,
  'recipient_number'=>$recipientNumber,
  'recipient_complement'=>$recipientComplement,
  'recipient_neighborhood'=>$recipientNeighborhood,
  'recipient_city'=>$recipientCity,
  'recipient_state'=>$recipientState,
  'package_weight_grams'=>$weight,
  'package_height_cm'=>$height,
  'package_width_cm'=>$width,
  'package_length_cm'=>$length,
  'contents'=>$contents
 ];
 $prepostId='';
 $tracking='';
 $labelPath='';
 $declarationPath='';

 $stage='autenticação no contrato dos Correios';
 try{
  $auth=correiosAuth($config);
  $stage='montagem dos dados da pré-postagem';
  $payload=correiosBuildPrepostPayload($config,$auth,$user,$sender,$shipmentData);
  if(empty($payload['idCorreios'])){
   throw new RuntimeException('Os Correios não retornaram o idCorreios do contrato');
  }
  $stage='criação da pré-postagem nos Correios';
  $prepost=correiosCreatePrepost($config,$payload);
  $prepostId=$prepost['id'];
  $tracking=$prepost['tracking_code'];
  // Os Correios atribuem o código do objeto somente quando o rótulo é emitido.
  $stage='solicitação e processamento do PDF da etiqueta';
  $labelPath=saveLabelPdf(
   $config,$prepostId,correiosLabelPdf($config,$prepostId)
  );
  if($tracking==='')$tracking=correiosRefreshTracking($config,$prepostId);
  if($tracking==='')throw new RuntimeException(
   'O rótulo foi processado, mas os Correios ainda não disponibilizaram o código de rastreio'
  );
  $stage='geração da declaração de conteúdo';
  $declarationPath=declarationHtmlToPdf(
   $config,$prepostId,correiosContentDeclaration($config,$prepostId)
  );

  $stage='gravação do envio e cobrança da carteira';
  $pdo->beginTransaction();
  $q=$pdo->prepare(
   'SELECT w.balance_cents,u.allow_postpaid FROM wallets w '.
   'JOIN users u ON u.id=w.user_id WHERE w.user_id=? FOR UPDATE'
  );
  $q->execute([$uid]);
  $wallet=$q->fetch();
  $internal=$wallet&&(int)$wallet['allow_postpaid']===1;
  if(!$wallet||(!$internal&&(int)$wallet['balance_cents']<$price)){
   throw new RuntimeException('Saldo alterado durante a emissão; tente novamente');
  }
  $billingType=$internal?'internal':'wallet';
  $charged=$internal?0:$price;
  if($charged>0){
   $pdo->prepare(
    'UPDATE wallets SET balance_cents=balance_cents-? WHERE user_id=?'
   )->execute([$charged,$uid]);
  }

  $sql=
   'INSERT INTO shipments('.
   'user_id,tracking_code,service,origin_zip,destination_zip,'.
   'recipient_name,recipient_email,recipient_phone,recipient_document,'.
   'recipient_street,recipient_number,recipient_complement,recipient_neighborhood,'.
   'recipient_city,recipient_state,package_weight_grams,package_height_cm,'.
   'package_width_cm,package_length_cm,contents_json,cost_cents,price_cents,'.
   'billing_type,wallet_charged_cents,status,correios_prepost_id,label_file,'.
   'declaration_file'.
   ') VALUES('.
   ':user_id,:tracking_code,:service,:origin_zip,:destination_zip,'.
   ':recipient_name,:recipient_email,:recipient_phone,:recipient_document,'.
   ':recipient_street,:recipient_number,:recipient_complement,:recipient_neighborhood,'.
   ':recipient_city,:recipient_state,:package_weight_grams,:package_height_cm,'.
   ':package_width_cm,:package_length_cm,:contents_json,:cost_cents,:price_cents,'.
   ':billing_type,:wallet_charged_cents,"label_generated",:correios_prepost_id,'.
   ':label_file,:declaration_file'.
   ')';
  $q=$pdo->prepare($sql);
  $q->execute([
   'user_id'=>$uid,
   'tracking_code'=>$tracking?:null,
   'service'=>$service,
   'origin_zip'=>$origin,
   'destination_zip'=>$dest,
   'recipient_name'=>$recipientName,
   'recipient_email'=>$recipientEmail?:null,
   'recipient_phone'=>$recipientPhone,
   'recipient_document'=>$recipientDocument,
   'recipient_street'=>$recipientStreet,
   'recipient_number'=>$recipientNumber,
   'recipient_complement'=>$recipientComplement?:null,
   'recipient_neighborhood'=>$recipientNeighborhood,
   'recipient_city'=>$recipientCity,
   'recipient_state'=>$recipientState,
   'package_weight_grams'=>$weight,
   'package_height_cm'=>$height,
   'package_width_cm'=>$width,
   'package_length_cm'=>$length,
   'contents_json'=>json_encode($contents,JSON_UNESCAPED_UNICODE),
   'cost_cents'=>$cost,
   'price_cents'=>$price,
   'billing_type'=>$billingType,
   'wallet_charged_cents'=>$charged,
   'correios_prepost_id'=>$prepostId,
   'label_file'=>$labelPath,
   'declaration_file'=>$declarationPath
  ]);
  $shipmentId=(int)$pdo->lastInsertId();
  if($charged>0){
   $pdo->prepare(
    'INSERT INTO wallet_transactions('.
    'user_id,type,amount_cents,reference_type,reference_id'.
    ') VALUES(?,"shipment",?,"shipment",?)'
   )->execute([$uid,-$charged,$shipmentId]);
  }
  $pdo->commit();
 }catch(Throwable $e){
  if($pdo->inTransaction())$pdo->rollBack();
  if($prepostId!==''){
   try{correiosCancelPrepost($config,$prepostId);}catch(Throwable $ignored){}
  }
  if($labelPath&&is_file($labelPath))unlink($labelPath);
  if($declarationPath&&is_file($declarationPath))unlink($declarationPath);
  $detail=trim($e->getMessage());
  if($detail==='')$detail='erro não informado';
  error_log('Shipment emission ['.$stage.']: '.$detail);
  out(['error'=>'Falha na etapa de '.$stage.': '.$detail.'. Nenhum saldo foi descontado.'],502);
 }

 $attachments=[
  [
   'path'=>$labelPath,
   'name'=>'etiqueta-'.$tracking.'.pdf',
   'type'=>'application/pdf'
  ],
  [
   'path'=>$declarationPath,
   'name'=>'declaracao-conteudo-'.$tracking.'.pdf',
   'type'=>'application/pdf'
  ]
 ];
 $mailHtml=
  '<h2>Seu envio foi gerado</h2>'.
  '<p>Código de rastreio: <strong>'.htmlspecialchars($tracking).'</strong></p>'.
  '<p>A etiqueta e a declaração de conteúdo estão anexadas.</p>';

 $customerEmailed=false;$adminEmailed=false;
 try{
  if(sendDocumentEmail(
   $config,(string)$user['email'],'Etiqueta de envio '.$tracking,$mailHtml,$attachments
  )){
   $pdo->prepare(
    'UPDATE shipments SET customer_emailed_at=NOW() WHERE id=? AND user_id=?'
   )->execute([$shipmentId,$uid]);
   $customerEmailed=true;
  }
 }catch(Throwable $e){
  error_log('Customer shipment email: '.$e->getMessage());
 }
 if($internal&&!empty($config['admin_notification_email'])){
  try{
   if(sendDocumentEmail(
    $config,
    (string)$config['admin_notification_email'],
    'Envio interno gerado '.$tracking,
    $mailHtml,
    $attachments
   )){
    $pdo->prepare(
     'UPDATE shipments SET admin_emailed_at=NOW() WHERE id=?'
    )->execute([$shipmentId]);
    $adminEmailed=true;
   }
  }catch(Throwable $e){
   error_log('Admin shipment email: '.$e->getMessage());
  }
 }

 out([
  'shipment'=>[
   'id'=>$shipmentId,
   'tracking_code'=>$tracking,
   'status'=>'label_generated',
   'billing_type'=>$billingType,
   'cost_cents'=>$cost,
   'price_cents'=>$price,
   'wallet_charged_cents'=>$charged,
   'label_url'=>'/api/shipment-document.php?id='.$shipmentId.'&type=label',
   'declaration_url'=>'/api/shipment-document.php?id='.$shipmentId.'&type=declaration',
   'customer_emailed'=>$customerEmailed,
   'admin_emailed'=>$adminEmailed
  ]
 ],201);
}

out(['error'=>'Método não permitido'],405);
