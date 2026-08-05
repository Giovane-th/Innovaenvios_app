<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
$adminId=requireAdmin($pdo);

if(empty($config['settings_encryption_key'])){
 out(['error'=>'Configure settings_encryption_key no config.php do servidor antes de usar esta aba.'],503);
}

$fields=appSettingsFields();

if($_SERVER['REQUEST_METHOD']==='GET'){
 $out=[];
 foreach($fields as $key=>$def){
  if($def['secret']){
   $out[$key]=['secret'=>true,'is_set'=>($config[$key]??'')!=='','value'=>null];
  }else{
   $out[$key]=['secret'=>false,'is_set'=>true,'value'=>$config[$key]??''];
  }
 }
 out(['fields'=>$out]);
}

if($_SERVER['REQUEST_METHOD']!=='POST')out(['error'=>'Método não permitido'],405);
requireSameOrigin($config);
$d=body();
$updates=[];

foreach($fields as $key=>$def){
 if(!array_key_exists($key,$d))continue;
 $raw=$d[$key];

 if($def['secret']){
  $value=trim((string)$raw);
  if($value==='')continue; // em branco = mantém o valor já salvo
  $updates[$key]=$value;
  continue;
 }

 switch($def['type']){
  case 'bool':
   $updates[$key]=((bool)$raw)?'1':'0';
   break;
  case 'int':
   $value=trim((string)$raw);
   if($value!==''&&!preg_match('/^-?\d+$/',$value))out(['error'=>"Valor inválido para $key: informe um número inteiro"],422);
   $updates[$key]=$value==='' ? '0' : $value;
   break;
  case 'float':
   $value=trim((string)$raw);
   if($value!==''&&!is_numeric($value))out(['error'=>"Valor inválido para $key: informe um número"],422);
   $updates[$key]=$value===''?'0':(string)(float)$value;
   break;
  default:
   $value=trim((string)$raw);
   if(($key==='mail_from'||$key==='admin_notification_email')&&$value!==''&&!filter_var($value,FILTER_VALIDATE_EMAIL)){
    out(['error'=>"Valor inválido para $key: informe um e-mail válido"],422);
   }
   if($key==='smtp_secure'&&$value!==''&&!in_array($value,['ssl','tls'],true)){
    out(['error'=>'smtp_secure deve ser "ssl" ou "tls"'],422);
   }
   $updates[$key]=$value;
 }
}

if(!$updates)out(['error'=>'Nenhuma alteração informada'],422);

try{
 saveAppSettings($pdo,$config,$updates,$adminId);
}catch(Throwable $e){
 error_log('Admin settings save: '.$e->getMessage());
 out(['error'=>'Não foi possível salvar as configurações'],500);
}

out(['ok'=>true,'updated'=>array_keys($updates)]);
