<?php
declare(strict_types=1);

function correiosAuth(array $config):array{
 static $cache=[];
 $cacheKey=md5(
  ($config['correios_usuario']??'').'|'.
  ($config['correios_cartao_postagem']??'').'|'.
  ($config['correios_chave_acesso']??$config['correios_codigo_acesso']??'')
 );
 // Reaproveita o token durante toda a emissão em vez de reautenticar a cada
 // chamada (o polling de status/rótulo faz dezenas de requisições e reautenticar
 // em todas elas esbarra no limite de taxa da API de autenticação dos Correios).
 if(isset($cache[$cacheKey])&&(time()-$cache[$cacheKey]['at'])<3000)return $cache[$cacheKey]['data'];
 $required=['correios_usuario','correios_cartao_postagem'];
 foreach($required as $key)if(empty($config[$key]))throw new RuntimeException('Configuração Correios incompleta');
 $credential=trim((string)($config['correios_chave_acesso']??''));
 if($credential==='')$credential=trim((string)($config['correios_codigo_acesso']??''));
 if($credential==='')throw new RuntimeException('Credencial Correios não configurada');
 if(str_starts_with($credential,'cws-')){
  $data=['token'=>$credential,'id'=>(string)$config['correios_usuario'],'cartaoPostagem'=>[
   'numero'=>(string)$config['correios_cartao_postagem'],
   'contrato'=>(string)($config['correios_contrato']??''),
   'dr'=>trim((string)($config['correios_dr']??''))
  ]];
  $cache[$cacheKey]=['data'=>$data,'at'=>time()];
  return $data;
 }
 $ch=curl_init('https://api.correios.com.br/token/v1/autentica/cartaopostagem');
 curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>['Authorization: Basic '.base64_encode($config['correios_usuario'].':'.$credential),'Content-Type: application/json','Accept: application/json'],CURLOPT_POSTFIELDS=>json_encode(['numero'=>$config['correios_cartao_postagem']],JSON_UNESCAPED_UNICODE)]);
 $raw=curl_exec($ch);$error=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 if($raw===false)throw new RuntimeException('Falha de rede com os Correios: '.$error);
 $data=json_decode((string)$raw,true);
 if($code<200||$code>=300||empty($data['token'])){
  $detail=is_array($data)?($data['causa']??$data['mensagem']??$data['message']??$data['erro']??($data['msgs'][0]??null)):null;
  throw new RuntimeException(trim((string)$detail)?:('Falha ao autenticar nos Correios (HTTP '.$code.')'));
 }
 $cache[$cacheKey]=['data'=>$data,'at'=>time()];
 return $data;
}
function correiosTokenValue(array $config):string{return (string)correiosAuth($config)['token'];}

function correiosRequest(array $config,string $method,string $path,?array $json=null,array $accept=['application/json'],int $timeout=60):array{
 $url='https://api.correios.com.br/prepostagem/v1/'.ltrim($path,'/');
 $headers=['Authorization: Bearer '.correiosTokenValue($config),'Accept: '.implode(', ',$accept)];
 if($json!==null)$headers[]='Content-Type: application/json';
 $ch=curl_init($url);
 curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_TIMEOUT=>$timeout,CURLOPT_HTTPHEADER=>$headers]);
 if($json!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($json,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
 $raw=curl_exec($ch);$error=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$headerSize=(int)curl_getinfo($ch,CURLINFO_HEADER_SIZE);curl_close($ch);
 if($raw===false)throw new RuntimeException('Falha de rede com os Correios: '.$error);
 $headerText=substr((string)$raw,0,$headerSize);$body=substr((string)$raw,$headerSize);
 preg_match('/^Content-Type:\s*([^;\r\n]+)/mi',$headerText,$match);$contentType=strtolower(trim($match[1]??''));
 if($code<200||$code>=300){
  $data=json_decode($body,true);
  $details=[];
  if(is_array($data)){
   foreach(['causa','mensagem','message','erro'] as $key)if(isset($data[$key])&&trim((string)$data[$key])!=='')$details[]=trim((string)$data[$key]);
   if(!empty($data['msgs'])&&is_array($data['msgs']))foreach($data['msgs'] as $msg)if(trim((string)$msg)!=='')$details[]=trim((string)$msg);
  }
  $detail=implode(' | ',array_values(array_unique($details)));
  if($detail===''||preg_match('/ApiNegocioRuntimeException:\\s*$/',$detail)){
   $plain=trim(strip_tags($body));
   $detail=$plain!==''?mb_substr($plain,0,700):('Correios respondeu HTTP '.$code);
  }
  throw new RuntimeException($detail.' (HTTP '.$code.')');
 }
 return ['status'=>$code,'content_type'=>$contentType,'body'=>$body,'json'=>json_decode($body,true)];
}

function correiosRequestLabelReceipt(array $config,string $prepostId,?array &$rawResponse=null):string{
 // GET /prepostagem/v1/prepostagens/{id} responde 405 (Method Not Allowed) nesta
 // conta/contrato — não existe forma de consultar o status antes de pedir o rótulo.
 // A emissão é assíncrona: este POST só devolve um recibo; o PDF em si é buscado
 // depois, em chamadas HTTP separadas (ver correiosFetchLabelOnce), para não
 // segurar uma única requisição PHP por dezenas de segundos.
 //
 // A pré-postagem recém-criada pode levar um instante para sair do status
 // "Pendente" do lado dos Correios antes de aceitar o pedido de rótulo
 // (PPN-288). Por isso, algumas tentativas curtas com pausa antes de desistir.
 $maxAttempts=5;
 $lastError='';
 for($attempt=1;$attempt<=$maxAttempts;$attempt++){
  try{
   $request=correiosRequest($config,'POST','prepostagens/rotulo/assincrono/pdf',[
    'idsPrePostagem'=>[$prepostId],'tipoRotulo'=>'P','formatoRotulo'=>'ET'
   ],['application/json']);
   $rawResponse=is_array($request['json'])?$request['json']:null;
   $receipt=correiosNestedValue($request['json'],['idrecibo','recibo','id']);
   if($receipt==='')throw new RuntimeException('Os Correios não retornaram o recibo de geração do rótulo');
   return $receipt;
  }catch(Throwable $e){
   $lastError=$e->getMessage();
   $isPendingStatus=str_contains($lastError,'PPN-288')||str_contains(mb_strtolower($lastError),'status igual a pendente');
   if(!$isPendingStatus||$attempt>=$maxAttempts)throw $e;
   usleep(700000);
  }
 }
 throw new RuntimeException($lastError?:'Falha ao solicitar o rótulo após múltiplas tentativas');
}

function correiosFetchLabelOnce(array $config,string $receipt):array{
 // Uma única tentativa de leitura do PDF assíncrono. O chamador (api/shipments.php,
 // action=poll) repete isso a cada sondagem vinda do navegador, em requisições
 // HTTP curtas e independentes, em vez de um laço de sleep() preso numa única
 // requisição — o que estourava o tempo limite do proxy/gateway em produção
 // (504) mesmo com max_execution_time do PHP configurado com folga.
 // Timeout curto (bem abaixo do padrão de 60s de proxies/gateways) porque o
 // chamador repete esta chamada a cada sondagem — uma única tentativa nunca
 // deve arriscar segurar a requisição até o limite do gateway.
 $response=correiosRequest(
  $config,'GET','prepostagens/rotulo/download/assincrono/'.rawurlencode($receipt),
  null,['application/pdf','application/json'],20
 );
 if(str_contains($response['content_type'],'pdf')||str_starts_with($response['body'],'%PDF')){
  return ['ready'=>true,'pdf'=>$response['body']];
 }
 $detail='processamento pendente';
 $data=$response['json'];
 if(is_array($data)){
  $encoded=correiosNestedValue($data,['dados','arquivo','pdf','conteudo']);
  if($encoded!==''){
   $pdf=base64_decode(preg_replace('/\\s+/','',$encoded),true);
   if(is_string($pdf)&&str_starts_with($pdf,'%PDF'))return ['ready'=>true,'pdf'=>$pdf];
  }
  $detail=correiosNestedValue($data,['mensagem','message','status','situacao'])?:$detail;
 }
 return ['ready'=>false,'detail'=>$detail];
}

function correiosRefreshTracking(array $config,string $prepostId):string{
 for($attempt=1;$attempt<=6;$attempt++){
  if($attempt>1)usleep(350000);
  try{
   $detail=correiosRequest($config,'GET','prepostagens/'.rawurlencode($prepostId),null,['application/json']);
   $tracking=correiosTrackingValue($detail['json']);
   if($tracking!=='')return $tracking;
  }catch(Throwable $ignored){}
 }
 return '';
}

function correiosContentDeclaration(array $config,string $prepostId):string{
 $response=correiosRequest($config,'GET','prepostagens/declaracaoconteudo/'.rawurlencode($prepostId),null,['text/html']);
 if(trim($response['body'])==='')throw new RuntimeException('Declaração de conteúdo vazia');
 return $response['body'];
}

function correiosNestedValue(mixed $data,array $keys):string{
 if(!is_array($data))return '';
 foreach($data as $key=>$value){
  if(is_string($key)&&in_array(mb_strtolower($key),$keys,true)&&is_scalar($value)){
   $found=trim((string)$value);if($found!=='')return $found;
  }
 }
 foreach($data as $value){
  if(is_array($value)){
   $found=correiosNestedValue($value,$keys);if($found!=='')return $found;
  }
 }
 return '';
}
function correiosTrackingValue(mixed $data):string{
 $tracking=correiosNestedValue($data,['codigoobjeto','codigorastreio','trackingcode','tracking_code']);
 if($tracking!=='')return strtoupper(preg_replace('/[^A-Za-z0-9]/','',$tracking));
 $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
 if(is_string($json)&&preg_match('/\\b[A-Z]{2}\\d{9}[A-Z]{2}\\b/i',$json,$match))return strtoupper($match[0]);
 return '';
}
function correiosCreatePrepost(array $config,array $payload):array{
 $response=correiosRequest($config,'POST','prepostagens',$payload,['application/json']);$data=$response['json'];
 if(!is_array($data))throw new RuntimeException('Resposta inválida ao criar pré-postagem');
 $id=correiosNestedValue($data,['id','idprepostagem','codigoprepostagem']);
 $tracking=correiosTrackingValue($data);
 if($id==='')throw new RuntimeException((string)($data['mensagem']??'Os Correios não retornaram o identificador da pré-postagem'));
 if($tracking===''){
  try{
   $detail=correiosRequest($config,'GET','prepostagens/'.rawurlencode($id),null,['application/json']);
   if(is_array($detail['json'])){
    $tracking=correiosTrackingValue($detail['json']);
    $data=['criacao'=>$data,'consulta'=>$detail['json']];
   }
  }catch(Throwable $ignored){}
 }
 return ['id'=>$id,'tracking_code'=>$tracking,'raw'=>$data];
}
function correiosBuildPrepostPayload(array $config,array $auth,array $user,array $sender,array $shipment):array{
 $serviceCode=(string)($shipment['service_code']??'');if($serviceCode==='')throw new RuntimeException('Código do serviço dos Correios não configurado');
 $senderPhone=preg_replace('/\D+/','',(string)$sender['phone']);$recipientPhone=preg_replace('/\D+/','',(string)$shipment['recipient_phone']);$items=[];
 foreach($shipment['contents'] as $item)$items[]=['conteudo'=>(string)$item['description'],'quantidade'=>(string)(int)$item['quantity'],'valor'=>number_format(((int)$item['value_cents'])/100,2,'.','')];
 return [
  'idCorreios'=>(string)($auth['id']??$auth['idCorreios']??$config['correios_id']??''),
  'remetente'=>['nome'=>(string)$user['name'],'codigo'=>'','indicadorMalote'=>'N','dddTelefone'=>'','telefone'=>'','dddCelular'=>substr($senderPhone,0,2),'celular'=>substr($senderPhone,2),'email'=>(string)$user['email'],'cpfCnpj'=>(string)$sender['document'],'documentoEstrangeiro'=>'','obs'=>'','endereco'=>['cep'=>(string)$sender['postal_code'],'logradouro'=>(string)$sender['street'],'numero'=>(string)$sender['number'],'complemento'=>(string)($sender['complement']??''),'bairro'=>(string)$sender['neighborhood'],'cidade'=>(string)$sender['city'],'uf'=>(string)$sender['state']]],
  'destinatario'=>['nome'=>(string)$shipment['recipient_name'],'codigo'=>'','indicadorMalote'=>'N','dddTelefone'=>'','ddiTelefone'=>'','telefone'=>'','dddCelular'=>substr($recipientPhone,0,2),'ddiCelular'=>'55','celular'=>substr($recipientPhone,2),'email'=>(string)($shipment['recipient_email']??''),'cpfCnpj'=>(string)$shipment['recipient_document'],'documentoEstrangeiro'=>'','obs'=>'','endereco'=>['cep'=>(string)$shipment['destination_zip'],'logradouro'=>(string)$shipment['recipient_street'],'numero'=>(string)$shipment['recipient_number'],'complemento'=>(string)($shipment['recipient_complement']??''),'bairro'=>(string)$shipment['recipient_neighborhood'],'cidade'=>(string)$shipment['recipient_city'],'uf'=>(string)$shipment['recipient_state'],'regiao'=>'']],
  'codigoServico'=>$serviceCode,'numeroCartaoPostagem'=>(string)$config['correios_cartao_postagem'],'listaServicoAdicional'=>[],'itensDeclaracaoConteudo'=>$items,
  'pesoInformado'=>(string)(int)$shipment['package_weight_grams'],'codigoFormatoObjetoInformado'=>(string)($shipment['format_code']??'1'),'alturaInformada'=>(string)$shipment['package_height_cm'],'larguraInformada'=>(string)$shipment['package_width_cm'],'comprimentoInformado'=>(string)$shipment['package_length_cm'],'diametroInformado'=>'0',
  'cienteObjetoNaoProibido'=>'1','solicitarColeta'=>'N','dataPrevistaPostagem'=>date('d/m/Y'),'modalidadePagamento'=>trim((string)($config['correios_modalidade_pagamento']??''))?:'1','logisticaReversa'=>'N','pedidoExternoOrigem'=>(string)($shipment['external_id']??'')
 ];
}
function correiosCancelPrepost(array $config,string $prepostId):void{
 correiosRequest($config,'DELETE','prepostagens/'.rawurlencode($prepostId),null,['application/json']);
}
