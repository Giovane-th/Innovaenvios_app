<?php
require __DIR__.'/bootstrap.php';

$clientId=(string)($config['google_client_id']??'');
$clientSecret=(string)($config['google_client_secret']??'');
$redirectUri=rtrim((string)($config['app_url']??'https://innovaenvios.app'),'/').'/api/google-auth.php?action=callback';
$action=$_GET['action']??'start';

function googleFail(string $message):never{
  header('Location: /?oauth=error&message='.rawurlencode($message));
  exit;
}
function postForm(string $url,array $fields):array{
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($fields),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>['Accept: application/json']]);
  $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
  $data=json_decode((string)$raw,true);
  if($error) throw new RuntimeException('Não foi possível conectar ao Google');
  if($code<200 || $code>=300){
    $oauthError=is_array($data)?(string)($data['error']??'erro_desconhecido'):'resposta_invalida';
    $known=[
      'invalid_client'=>'Credencial Google inválida: confira o Client ID e a nova chave secreta',
      'invalid_grant'=>'Código do Google expirado: tente entrar novamente',
      'redirect_uri_mismatch'=>'URI de retorno diferente da cadastrada no Google Cloud'
    ];
    throw new RuntimeException($known[$oauthError]??('Google OAuth: '.$oauthError));
  }
  if(!is_array($data)) throw new RuntimeException('Resposta inválida recebida do Google');
  return $data;
}
function getJson(string $url,string $token):array{
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/json']]);
  $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
  $data=json_decode((string)$raw,true);
  if($error || $code<200 || $code>=300 || !is_array($data)) throw new RuntimeException('Não foi possível validar sua conta Google');
  return $data;
}

if(!$clientId || !$clientSecret) googleFail('Login com Google ainda não configurado');

if($action==='start'){
  $state=bin2hex(random_bytes(24));
  $_SESSION['google_oauth_state']=$state;
  $params=[
    'client_id'=>$clientId,'redirect_uri'=>$redirectUri,'response_type'=>'code',
    'scope'=>'openid email profile','state'=>$state,'prompt'=>'select_account',
    'access_type'=>'online','include_granted_scopes'=>'true'
  ];
  header('Location: https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query($params));
  exit;
}

if($action!=='callback') googleFail('Rota de acesso inválida');
if(!empty($_GET['error'])) googleFail('Acesso pelo Google cancelado');
$state=(string)($_GET['state']??'');$expected=(string)($_SESSION['google_oauth_state']??'');
unset($_SESSION['google_oauth_state']);
if(!$state || !$expected || !hash_equals($expected,$state)) googleFail('Sessão de acesso expirada');
$code=(string)($_GET['code']??'');
if(!$code) googleFail('Código do Google não recebido');

try{
  $tokens=postForm('https://oauth2.googleapis.com/token',[
    'code'=>$code,'client_id'=>$clientId,'client_secret'=>$clientSecret,
    'redirect_uri'=>$redirectUri,'grant_type'=>'authorization_code'
  ]);
  $accessToken=(string)($tokens['access_token']??'');
  if(!$accessToken) throw new RuntimeException('Token do Google não recebido');
  $profile=getJson('https://openidconnect.googleapis.com/v1/userinfo',$accessToken);
  $sub=(string)($profile['sub']??'');$email=strtolower(trim((string)($profile['email']??'')));
  $name=trim((string)($profile['name']??'Cliente InNova'));
  if(!$sub || !$email || empty($profile['email_verified'])) throw new RuntimeException('Conta Google sem e-mail verificado');

  $q=$pdo->prepare('SELECT id,status FROM users WHERE google_sub=? OR email=? LIMIT 1');
  $q->execute([$sub,$email]);$user=$q->fetch();
  if($user){
    if($user['status']!=='active') throw new RuntimeException('Conta bloqueada');
    $id=(int)$user['id'];
    $pdo->prepare('UPDATE users SET google_sub=?,email=?,name=? WHERE id=?')->execute([$sub,$email,$name,$id]);
  }else{
    $pdo->beginTransaction();
    try{
      $q=$pdo->prepare('INSERT INTO users(name,email,google_sub,password_hash,phone) VALUES(?,?,?,NULL,NULL)');
      $q->execute([$name,$email,$sub]);$id=(int)$pdo->lastInsertId();
      $pdo->prepare('INSERT INTO wallets(user_id,balance_cents) VALUES(?,0)')->execute([$id]);
      $pdo->commit();
    }catch(Throwable $e){$pdo->rollBack();throw $e;}
  }
  session_regenerate_id(true);$_SESSION['user_id']=$id;
  header('Location: /?oauth=success');exit;
}catch(Throwable $e){
  googleFail($e instanceof RuntimeException?$e->getMessage():'Não foi possível entrar com Google');
}
