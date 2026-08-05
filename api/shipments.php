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
 $dr=trim((string)($card['dr']??$config['correios_dr']??''));
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
 if($contract!==''&&$dr!==''){
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
 $forceOwn=($_GET['scope']??'')==='own';
 if($isAdmin&&!$forceOwn){
  $q=$pdo->query(
   'SELECT s.id,s.tracking_code,u.name AS customer_name,u.email AS customer_email,'.
   's.service,s.origin_zip,s.destination_zip,s.price_cents,s.billing_type,'.
   's.wallet_charged_cents,s.status,s.created_at,'.
   "(s.label_file IS NOT NULL AND s.label_file<>'') AS has_label ".
   'FROM shipments s JOIN users u ON u.id=s.user_id '.
   'ORDER BY s.id DESC LIMIT 500'
  );
 }else{
  $q=$pdo->prepare(
   'SELECT s.id,s.tracking_code,u.name AS customer_name,u.email AS customer_email,'.
   's.service,s.origin_zip,s.destination_zip,s.price_cents,s.billing_type,'.
   's.wallet_charged_cents,s.status,s.created_at,'.
   "(s.label_file IS NOT NULL AND s.label_file<>'') AS has_label ".
   'FROM shipments s JOIN users u ON u.id=s.user_id '.
   'WHERE s.user_id=? ORDER BY s.id DESC LIMIT 100'
  );
  $q->execute([$uid]);
 }
 out(['shipments'=>$q->fetchAll(),'scope'=>($isAdmin&&!$forceOwn)?'all':'own']);
}

if($_SERVER['REQUEST_METHOD']==='POST'&&($_GET['action']??'')===''){
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
  // Guarda a resposta bruta dos Correios na criação para diagnosticar, se o
  // rótulo não sair do status "Pendente" (ver PPN-288), o que exatamente eles
  // devolveram (ex.: algum campo de situação de pagamento) sem precisar
  // reproduzir o problema de novo.
  $shipmentData['correios_creation_response']=$prepost['raw']??null;
  // A geração do PDF em si é assíncrona e pode levar bem mais que alguns
  // segundos; este pedido só reserva o recibo. O PDF é buscado depois, por
  // sondagens curtas do navegador (action=poll), para esta requisição de
  // criação não ficar presa esperando os Correios e estourar o tempo limite
  // do proxy/gateway em produção (era a causa do 504 observado).
  $stage='solicitação do rótulo assíncrono aos Correios';
  $labelReceiptRaw=null;
  $receipt=correiosRequestLabelReceipt($config,$prepostId,$labelReceiptRaw,(string)($prepost['tipo_rotulo']??''));
  $shipmentData['correios_label_receipt_response']=$labelReceiptRaw;
 }catch(Throwable $e){
  if($prepostId!==''){
   try{correiosCancelPrepost($config,$prepostId);}catch(Throwable $ignored){}
  }
  $detail=trim($e->getMessage());
  if($detail==='')$detail='erro não informado';
  error_log('Shipment emission ['.$stage.']: '.$detail);
  out(['error'=>'Falha na etapa de '.$stage.': '.$detail.'. Nenhum saldo foi descontado.'],502);
 }

 $shipmentData['service']=$service;
 $shipmentData['origin_zip']=$origin;
 $q=$pdo->prepare(
  'INSERT INTO shipment_emissions(user_id,prepost_id,receipt_id,tracking_code,cost_cents,price_cents,payload_json) '.
  'VALUES(?,?,?,?,?,?,?)'
 );
 $q->execute([
  $uid,$prepostId,$receipt,$tracking?:null,$cost,$price,
  json_encode($shipmentData,JSON_UNESCAPED_UNICODE)
 ]);
 out(['emission'=>['id'=>(int)$pdo->lastInsertId(),'status'=>'processing']],202);
}

function shipmentEmissionResponse(array $s):array{
 return [
  'id'=>$s['id'],
  'tracking_code'=>$s['tracking_code'],
  'status'=>'label_generated',
  'billing_type'=>$s['billing_type'],
  'cost_cents'=>$s['cost_cents'],
  'price_cents'=>$s['price_cents'],
  'wallet_charged_cents'=>$s['wallet_charged_cents'],
  'label_url'=>'/api/shipment-document.php?id='.$s['id'].'&type=label',
  'declaration_url'=>'/api/shipment-document.php?id='.$s['id'].'&type=declaration',
  'customer_emailed'=>$s['customer_emailed'],
  'admin_emailed'=>$s['admin_emailed']
 ];
}

function finalizeShipmentEmission(PDO $pdo,array $config,array $emission,string $pdf):array{
 $uid=(int)$emission['user_id'];
 $prepostId=(string)$emission['prepost_id'];
 $tracking=(string)($emission['tracking_code']??'');
 $cost=(int)$emission['cost_cents'];
 $price=(int)$emission['price_cents'];
 $data=json_decode((string)$emission['payload_json'],true);
 if(!is_array($data))throw new RuntimeException('Dados da emissão corrompidos');

 $userQ=$pdo->prepare(
  'SELECT id,name,email,status,allow_postpaid FROM users WHERE id=? LIMIT 1'
 );
 $userQ->execute([$uid]);
 $user=$userQ->fetch();
 if(!$user||$user['status']!=='active')throw new RuntimeException('Conta não encontrada ou bloqueada');

 $labelPath='';$declarationPath='';
 try{
  $labelPath=saveLabelPdf($config,$prepostId,$pdf);
  if($tracking==='')$tracking=correiosRefreshTracking($config,$prepostId);
  if($tracking==='')throw new RuntimeException(
   'O rótulo foi processado, mas os Correios ainda não disponibilizaram o código de rastreio'
  );
  $declarationPath=declarationHtmlToPdf(
   $config,$prepostId,correiosContentDeclaration($config,$prepostId)
  );

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
   'service'=>(string)($data['service']??'Correios'),
   'origin_zip'=>(string)($data['origin_zip']??''),
   'destination_zip'=>(string)($data['destination_zip']??''),
   'recipient_name'=>(string)($data['recipient_name']??''),
   'recipient_email'=>($data['recipient_email']??'')?:null,
   'recipient_phone'=>(string)($data['recipient_phone']??''),
   'recipient_document'=>(string)($data['recipient_document']??''),
   'recipient_street'=>(string)($data['recipient_street']??''),
   'recipient_number'=>(string)($data['recipient_number']??''),
   'recipient_complement'=>($data['recipient_complement']??'')?:null,
   'recipient_neighborhood'=>(string)($data['recipient_neighborhood']??''),
   'recipient_city'=>(string)($data['recipient_city']??''),
   'recipient_state'=>(string)($data['recipient_state']??''),
   'package_weight_grams'=>(int)($data['package_weight_grams']??0),
   'package_height_cm'=>(float)($data['package_height_cm']??0),
   'package_width_cm'=>(float)($data['package_width_cm']??0),
   'package_length_cm'=>(float)($data['package_length_cm']??0),
   'contents_json'=>json_encode($data['contents']??[],JSON_UNESCAPED_UNICODE),
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
  if($labelPath&&is_file($labelPath))unlink($labelPath);
  if($declarationPath&&is_file($declarationPath))unlink($declarationPath);
  throw $e;
 }

 $attachments=[
  ['path'=>$labelPath,'name'=>'etiqueta-'.$tracking.'.pdf','type'=>'application/pdf'],
  ['path'=>$declarationPath,'name'=>'declaracao-conteudo-'.$tracking.'.pdf','type'=>'application/pdf']
 ];
 $mailHtml=
  '<h2>Seu envio foi gerado</h2>'.
  '<p>Código de rastreio: <strong>'.htmlspecialchars($tracking).'</strong></p>'.
  '<p>A etiqueta e a declaração de conteúdo estão anexadas.</p>'.
  '<p><strong>Próximo passo:</strong> imprima a etiqueta e a declaração de conteúdo, '.
  'cole a etiqueta na embalagem e leve até uma agência dos Correios para postagem. '.
  'A postagem física é de responsabilidade do remetente — o In\'Nova Envios não faz a coleta.</p>';

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
    $config,(string)$config['admin_notification_email'],
    'Envio interno gerado '.$tracking,$mailHtml,$attachments
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

 $pdo->prepare(
  'UPDATE shipment_emissions SET status="ready",shipment_id=?,tracking_code=? WHERE id=?'
 )->execute([$shipmentId,$tracking,(int)$emission['id']]);

 return shipmentEmissionResponse([
  'id'=>$shipmentId,'tracking_code'=>$tracking,'billing_type'=>$billingType,
  'cost_cents'=>$cost,'price_cents'=>$price,'wallet_charged_cents'=>$charged,
  'customer_emailed'=>$customerEmailed,'admin_emailed'=>$adminEmailed
 ]);
}

if($_SERVER['REQUEST_METHOD']==='POST'&&($_GET['action']??'')==='poll'){
 $d=body();
 $emissionId=(int)($d['emission_id']??0);
 if($emissionId<1)out(['error'=>'emission_id inválido'],422);
 $q=$pdo->prepare('SELECT * FROM shipment_emissions WHERE id=? AND user_id=?');
 $q->execute([$emissionId,$uid]);
 $emission=$q->fetch();
 if(!$emission)out(['error'=>'Emissão não encontrada'],404);

 if($emission['status']==='ready'){
  $q=$pdo->prepare(
   'SELECT s.id,s.tracking_code,s.billing_type,s.cost_cents,s.price_cents,'.
   's.wallet_charged_cents,s.customer_emailed_at,s.admin_emailed_at '.
   'FROM shipments s WHERE s.id=?'
  );
  $q->execute([$emission['shipment_id']]);
  $s=$q->fetch();
  if(!$s)out(['error'=>'Envio não encontrado'],404);
  out(['status'=>'ready','shipment'=>shipmentEmissionResponse([
   'id'=>$s['id'],'tracking_code'=>$s['tracking_code'],'billing_type'=>$s['billing_type'],
   'cost_cents'=>$s['cost_cents'],'price_cents'=>$s['price_cents'],
   'wallet_charged_cents'=>$s['wallet_charged_cents'],
   'customer_emailed'=>!empty($s['customer_emailed_at']),'admin_emailed'=>!empty($s['admin_emailed_at'])
  ])]);
 }
 if($emission['status']==='error'){
  out(['status'=>'error','error'=>$emission['error_message']?:'Falha ao emitir a etiqueta']);
 }
 if($emission['status']!=='processing'){
  // Já reivindicada por outra sondagem concorrente (mesma emissão em duas abas); aguarde.
  out(['status'=>'processing']);
 }

 $maxAttempts=60;
 try{
  $result=correiosFetchLabelOnce($config,(string)$emission['receipt_id']);
 }catch(Throwable $e){
  $result=['ready'=>false,'detail'=>$e->getMessage()];
 }

 if(empty($result['ready'])){
  $attempts=(int)$emission['attempts']+1;
  if($attempts>=$maxAttempts){
   $pdo->prepare(
    'UPDATE shipment_emissions SET status="error",attempts=?,error_message=? WHERE id=? AND status="processing"'
   )->execute([
    $attempts,
    'O rótulo não ficou disponível no prazo de processamento: '.($result['detail']??'—'),
    $emissionId
   ]);
   try{correiosCancelPrepost($config,(string)$emission['prepost_id']);}catch(Throwable $ignored){}
   out(['status'=>'error','error'=>'O rótulo não ficou disponível no prazo de processamento dos Correios. Tente novamente mais tarde.']);
  }
  $pdo->prepare(
   'UPDATE shipment_emissions SET attempts=? WHERE id=? AND status="processing"'
  )->execute([$attempts,$emissionId]);
  out(['status'=>'processing','attempt'=>$attempts]);
 }

 // Reivindica a finalização via compare-and-set para não gravar/cobrar duas
 // vezes se duas sondagens da mesma emissão chegarem quase juntas.
 $claim=$pdo->prepare('UPDATE shipment_emissions SET status="finalizing" WHERE id=? AND status="processing"');
 $claim->execute([$emissionId]);
 if($claim->rowCount()<1)out(['status'=>'processing']);

 try{
  $shipment=finalizeShipmentEmission($pdo,$config,$emission,(string)$result['pdf']);
  out(['status'=>'ready','shipment'=>$shipment]);
 }catch(Throwable $e){
  $detail=trim($e->getMessage())?:'erro não informado';
  error_log('Shipment finalize: '.$detail);
  $pdo->prepare(
   'UPDATE shipment_emissions SET status="error",error_message=? WHERE id=?'
  )->execute([$detail,$emissionId]);
  try{correiosCancelPrepost($config,(string)$emission['prepost_id']);}catch(Throwable $ignored){}
  out(['status'=>'error','error'=>'Falha ao finalizar a etiqueta: '.$detail.'. Nenhum saldo foi descontado.']);
 }
}

out(['error'=>'Método não permitido'],405);
