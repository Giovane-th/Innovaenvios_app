<?php
declare(strict_types=1);

// Campos editáveis pela aba "Configurações de implantação" do admin.
// db_host/db_name/db_user/db_pass, app_url e shipping_documents_dir ficam de
// fora de propósito: são necessários antes mesmo de existir conexão com o
// banco (ou são específicos do sistema de arquivos do servidor), então
// continuam só no config.php.
function appSettingsFields():array{
 return [
  'correios_usuario'=>['secret'=>false,'type'=>'string'],
  'correios_codigo_acesso'=>['secret'=>true,'type'=>'string'],
  'correios_chave_acesso'=>['secret'=>true,'type'=>'string'],
  'correios_contrato'=>['secret'=>false,'type'=>'string'],
  'correios_dr'=>['secret'=>false,'type'=>'string'],
  'correios_cartao_postagem'=>['secret'=>false,'type'=>'string'],
  'correios_id'=>['secret'=>false,'type'=>'string'],
  'correios_servico_pac'=>['secret'=>false,'type'=>'string'],
  'correios_servico_sedex'=>['secret'=>false,'type'=>'string'],
  'correios_servico_pac_balcao'=>['secret'=>false,'type'=>'string'],
  'correios_servico_sedex_balcao'=>['secret'=>false,'type'=>'string'],
  'correios_live_emission'=>['secret'=>false,'type'=>'bool'],
  'freight_markup_percent'=>['secret'=>false,'type'=>'float'],
  'freight_reference_multiplier'=>['secret'=>false,'type'=>'float'],
  'pagarme_secret_key'=>['secret'=>true,'type'=>'string'],
  'pagarme_public_key'=>['secret'=>false,'type'=>'string'],
  'pagarme_webhook_secret'=>['secret'=>true,'type'=>'string'],
  'smtp_host'=>['secret'=>false,'type'=>'string'],
  'smtp_port'=>['secret'=>false,'type'=>'int'],
  'smtp_secure'=>['secret'=>false,'type'=>'string'],
  'smtp_user'=>['secret'=>false,'type'=>'string'],
  'smtp_pass'=>['secret'=>true,'type'=>'string'],
  'mail_from'=>['secret'=>false,'type'=>'string'],
  'mail_from_name'=>['secret'=>false,'type'=>'string'],
  'admin_notification_email'=>['secret'=>false,'type'=>'string'],
  'google_client_id'=>['secret'=>false,'type'=>'string'],
  'google_client_secret'=>['secret'=>true,'type'=>'string'],
 ];
}

function settingsEncryptionKey(array $config):string{
 $raw=base64_decode((string)($config['settings_encryption_key']??''),true);
 if($raw===false||strlen($raw)!==32){
  throw new RuntimeException('settings_encryption_key ausente ou inválida no config.php (precisa ser 32 bytes em base64)');
 }
 return $raw;
}

function settingsEncrypt(string $key,string $plaintext):string{
 $iv=random_bytes(12);$tag='';
 $cipher=openssl_encrypt($plaintext,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);
 if($cipher===false)throw new RuntimeException('Falha ao criptografar configuração');
 return base64_encode($iv.$tag.$cipher);
}

function settingsDecrypt(string $key,string $encoded):string{
 $raw=base64_decode($encoded,true);
 if($raw===false||strlen($raw)<29)throw new RuntimeException('Configuração corrompida');
 $iv=substr($raw,0,12);$tag=substr($raw,12,16);$cipher=substr($raw,28);
 $plain=openssl_decrypt($cipher,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);
 if($plain===false)throw new RuntimeException('Falha ao descriptografar configuração (chave errada?)');
 return $plain;
}

function castAppSettingValue(string $type,string $value):mixed{
 return match($type){
  'bool'=>in_array(strtolower(trim($value)),['1','true','on','yes'],true),
  'int'=>(int)$value,
  'float'=>(float)$value,
  default=>$value,
 };
}

// Chamada pelo bootstrap.php em toda requisição autenticada. Falha de forma
// silenciosa (retorna []) se a chave de criptografia ou a tabela ainda não
// existirem, para não derrubar o app inteiro antes da migração ser aplicada.
function loadAppSettings(PDO $pdo,array $config):array{
 if(empty($config['settings_encryption_key']))return [];
 try{$key=settingsEncryptionKey($config);}catch(Throwable $e){return [];}
 try{$rows=$pdo->query('SELECT setting_key,setting_value FROM app_settings')->fetchAll();}
 catch(Throwable $e){return [];}
 $fields=appSettingsFields();$out=[];
 foreach($rows as $row){
  $k=(string)$row['setting_key'];
  if(!isset($fields[$k])||$row['setting_value']===null)continue;
  try{$value=settingsDecrypt($key,(string)$row['setting_value']);}catch(Throwable $e){continue;}
  $out[$k]=castAppSettingValue($fields[$k]['type'],$value);
 }
 return $out;
}

function saveAppSettings(PDO $pdo,array $config,array $updates,int $adminId):void{
 $key=settingsEncryptionKey($config);
 $fields=appSettingsFields();
 $q=$pdo->prepare(
  'INSERT INTO app_settings(setting_key,setting_value,updated_by) VALUES(?,?,?) '.
  'ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)'
 );
 foreach($updates as $k=>$v){
  if(!isset($fields[$k]))continue;
  $q->execute([$k,settingsEncrypt($key,(string)$v),$adminId]);
 }
}
