<?php
require __DIR__.'/bootstrap.php'; userId();
$required=['correios_usuario','correios_codigo_acesso','correios_cartao_postagem'];
foreach($required as $key){if(empty($config[$key]))out(['error'=>'Integração Correios ainda não configurada'],503);}
function correiosToken(array $config):string{
 $url='https://api.correios.com.br/token/v1/autentica/cartaopostagem';
 $ch=curl_init($url);
 curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,
  CURLOPT_HTTPHEADER=>['Authorization: Basic '.base64_encode($config['correios_usuario'].':'.$config['correios_codigo_acesso']),'Content-Type: application/json'],
  CURLOPT_POSTFIELDS=>json_encode(['numero'=>$config['correios_cartao_postagem']])]);
 $raw=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 $data=json_decode((string)$raw,true);
 if($code<200||$code>=300||empty($data['token']))out(['error'=>'Falha ao autenticar nos Correios','details'=>$data['mensagem']??null],502);
 return $data['token'];
}
$action=$_GET['action']??'status';
if($action==='status'){correiosToken($config);out(['ok'=>true,'service'=>'Correios CWS']);}
out(['error'=>'Operação ainda não disponível'],404);
