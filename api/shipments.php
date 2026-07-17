<?php
require __DIR__.'/bootstrap.php';$uid=userId();
if($_SERVER['REQUEST_METHOD']==='GET'){
 $q=$pdo->prepare('SELECT id,tracking_code,service,origin_zip,destination_zip,price_cents,status,created_at FROM shipments WHERE user_id=? ORDER BY id DESC LIMIT 100');$q->execute([$uid]);out(['shipments'=>$q->fetchAll()]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
 $d=body();$service=trim((string)($d['service']??''));$origin=phone((string)($d['origin_zip']??''));$dest=phone((string)($d['destination_zip']??''));$price=(int)($d['price_cents']??0);
 if(!$service||strlen($origin)!==8||strlen($dest)!==8||$price<1)out(['error'=>'Dados do envio inválidos'],422);
 $pdo->beginTransaction();
 try{
  $q=$pdo->prepare('SELECT balance_cents FROM wallets WHERE user_id=? FOR UPDATE');$q->execute([$uid]);$wallet=$q->fetch();
  if(!$wallet||$wallet['balance_cents']<$price){$pdo->rollBack();out(['error'=>'Saldo insuficiente'],409);}
  $code='IN'.strtoupper(substr(bin2hex(random_bytes(6)),0,9)).'BR';
  $pdo->prepare('UPDATE wallets SET balance_cents=balance_cents-? WHERE user_id=?')->execute([$price,$uid]);
  $q=$pdo->prepare('INSERT INTO shipments(user_id,tracking_code,service,origin_zip,destination_zip,price_cents,status) VALUES(?,?,?,?,?,?,"created")');
  $q->execute([$uid,$code,$service,$origin,$dest,$price]);
  $pdo->prepare('INSERT INTO wallet_transactions(user_id,type,amount_cents,reference_type,reference_id) VALUES(?,"shipment",?,"shipment",?)')->execute([$uid,-$price,(int)$pdo->lastInsertId()]);
  $pdo->commit();out(['shipment'=>['tracking_code'=>$code,'status'=>'created']],201);
 }catch(Throwable $e){$pdo->rollBack();out(['error'=>'Não foi possível criar o envio'],500);}
}
out(['error'=>'Método não permitido'],405);