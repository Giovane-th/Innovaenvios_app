<?php
require __DIR__.'/bootstrap.php';
$action=$_GET['action']??'me';

if($_SERVER['REQUEST_METHOD']==='POST' && $action==='register'){
 $d=body();$name=trim((string)($d['name']??''));$email=strtolower(trim((string)($d['email']??'')));$pass=(string)($d['password']??'');
 if(mb_strlen($name)<2 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($pass)<8) out(['error'=>'Informe nome, e-mail válido e senha com 8 caracteres'],422);
 $q=$pdo->prepare('SELECT id FROM users WHERE email=?');$q->execute([$email]);if($q->fetch())out(['error'=>'E-mail já cadastrado'],409);
 $pdo->beginTransaction();
 try{
  $q=$pdo->prepare('INSERT INTO users(name,email,password_hash,phone) VALUES(?,?,?,NULL)');
  $q->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT)]);$id=(int)$pdo->lastInsertId();
  $pdo->prepare('INSERT INTO wallets(user_id,balance_cents) VALUES(?,0)')->execute([$id]);
  $pdo->commit();session_regenerate_id(true);$_SESSION['user_id']=$id;
  out(['user'=>['id'=>$id,'name'=>$name,'email'=>$email,'phone'=>null,'balance_cents'=>0]]);
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();out(['error'=>'Não foi possível criar a conta'],500);}
}

if($_SERVER['REQUEST_METHOD']==='POST' && $action==='login'){
 $d=body();$email=strtolower(trim((string)($d['email']??'')));$pass=(string)($d['password']??'');
 if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pass)<8)out(['error'=>'Informe e-mail e senha válidos'],422);
 $q=$pdo->prepare('SELECT id,name,email,phone,password_hash,status FROM users WHERE email=? LIMIT 1');$q->execute([$email]);$u=$q->fetch();
 if(!$u || $u['status']!=='active' || empty($u['password_hash']) || !password_verify($pass,$u['password_hash'])) out(['error'=>'E-mail ou senha incorretos'],401);
 $q=$pdo->prepare('SELECT balance_cents FROM wallets WHERE user_id=?');$q->execute([(int)$u['id']]);
 session_regenerate_id(true);$_SESSION['user_id']=(int)$u['id'];
 out(['user'=>['id'=>(int)$u['id'],'name'=>$u['name'],'email'=>$u['email'],'phone'=>$u['phone'],'balance_cents'=>(int)$q->fetchColumn()]]);
}

if($_SERVER['REQUEST_METHOD']==='POST' && $action==='logout'){$_SESSION=[];session_destroy();out(['ok'=>true]);}
if($action==='me'){
 $id=userId();$q=$pdo->prepare('SELECT u.id,u.name,u.phone,u.email,(u.pagarme_customer_id IS NOT NULL) AS has_payment_profile,w.balance_cents FROM users u JOIN wallets w ON w.user_id=u.id WHERE u.id=?');$q->execute([$id]);out(['user'=>$q->fetch()]);
}
out(['error'=>'Rota inválida'],404);
