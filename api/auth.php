<?php
require __DIR__.'/bootstrap.php';
$action=$_GET['action']??'me';
if($_SERVER['REQUEST_METHOD']==='POST' && $action==='register'){
 $d=body(); $name=trim((string)($d['name']??'')); $tel=phone((string)($d['phone']??'')); $pass=(string)($d['password']??'');
 if(mb_strlen($name)<2 || strlen($tel)<10 || strlen($pass)<8) out(['error'=>'Informe nome, telefone válido e senha com 8 caracteres'],422);
 $q=$pdo->prepare('SELECT id FROM users WHERE phone=?');$q->execute([$tel]);if($q->fetch())out(['error'=>'Telefone já cadastrado'],409);
 $pdo->beginTransaction();
 try{
  $q=$pdo->prepare('INSERT INTO users(name,phone,password_hash) VALUES(?,?,?)');
  $q->execute([$name,$tel,password_hash($pass,PASSWORD_DEFAULT)]);$id=(int)$pdo->lastInsertId();
  $pdo->prepare('INSERT INTO wallets(user_id,balance_cents) VALUES(?,0)')->execute([$id]);
  $pdo->commit();session_regenerate_id(true);$_SESSION['user_id']=$id;out(['user'=>['id'=>$id,'name'=>$name,'phone'=>$tel]]);
 }catch(Throwable $e){$pdo->rollBack();out(['error'=>'Não foi possível criar a conta'],500);}
}
if($_SERVER['REQUEST_METHOD']==='POST' && $action==='login'){
 $d=body();$tel=phone((string)($d['phone']??''));$pass=(string)($d['password']??'');
 $q=$pdo->prepare('SELECT id,name,phone,password_hash,status FROM users WHERE phone=? LIMIT 1');$q->execute([$tel]);$u=$q->fetch();
 if(!$u || $u['status']!=='active' || !password_verify($pass,$u['password_hash'])) out(['error'=>'Telefone ou senha incorretos'],401);
 session_regenerate_id(true);$_SESSION['user_id']=(int)$u['id'];out(['user'=>['id'=>(int)$u['id'],'name'=>$u['name'],'phone'=>$u['phone']]]);
}
if($_SERVER['REQUEST_METHOD']==='POST' && $action==='logout'){$_SESSION=[];session_destroy();out(['ok'=>true]);}
if($action==='me'){
 $id=userId();$q=$pdo->prepare('SELECT u.id,u.name,u.phone,u.email,w.balance_cents FROM users u JOIN wallets w ON w.user_id=u.id WHERE u.id=?');$q->execute([$id]);out(['user'=>$q->fetch()]);
}
out(['error'=>'Rota inválida'],404);