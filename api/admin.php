<?php
require __DIR__.'/bootstrap.php';
$adminId=requireAdmin($pdo);
if($_SERVER['REQUEST_METHOD']==='GET'){
 $stats=[
  'customers'=>(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn(),
  'active_customers'=>(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='customer' AND status='active'")->fetchColumn(),
  'shipments'=>(int)$pdo->query('SELECT COUNT(*) FROM shipments')->fetchColumn(),
  'simulations'=>(int)$pdo->query('SELECT COUNT(*) FROM shipping_simulations')->fetchColumn(),
  'paid_cents'=>(int)$pdo->query("SELECT COALESCE(SUM(amount_cents),0) FROM payment_orders WHERE status='paid'")->fetchColumn()
 ];
 $q=$pdo->query("SELECT u.id,u.name,u.email,u.phone,u.status,u.role,u.allow_postpaid,u.created_at,COALESCE(w.balance_cents,0) balance_cents,GREATEST(0,-COALESCE(w.balance_cents,0)) debt_cents,(SELECT COUNT(*) FROM shipments s WHERE s.user_id=u.id) shipment_count,(SELECT COUNT(*) FROM shipping_simulations ss WHERE ss.user_id=u.id) simulation_count FROM users u LEFT JOIN wallets w ON w.user_id=u.id ORDER BY u.id DESC LIMIT 200");
 out(['stats'=>$stats,'users'=>$q->fetchAll()]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')out(['error'=>'Método não permitido'],405);
requireSameOrigin($config);
$d=body();$id=(int)($d['user_id']??0);$action=(string)($d['action']??'');

if(in_array($action,['reset_customers','reset_shipments','reset_simulations'],true)){
 try{
  $pdo->beginTransaction();
  if($action==='reset_simulations'){
   $pdo->exec('DELETE FROM shipping_simulations');
  }
  if($action==='reset_shipments'){
   $pdo->exec('DELETE FROM shipment_emissions');
   $pdo->exec('DELETE FROM shipments');
  }
  if($action==='reset_customers'){
   $pdo->exec("DELETE FROM shipment_emissions WHERE user_id IN (SELECT id FROM users WHERE role='customer')");
   $pdo->exec("DELETE FROM shipments WHERE user_id IN (SELECT id FROM users WHERE role='customer')");
   $pdo->exec("DELETE FROM shipping_simulations WHERE user_id IN (SELECT id FROM users WHERE role='customer')");
   $pdo->exec("DELETE FROM wallet_transactions WHERE user_id IN (SELECT id FROM users WHERE role='customer')");
   $pdo->exec("DELETE FROM payment_orders WHERE user_id IN (SELECT id FROM users WHERE role='customer')");
   $pdo->exec("DELETE FROM user_shipping_profiles WHERE user_id IN (SELECT id FROM users WHERE role='customer')");
   $pdo->exec("DELETE FROM wallets WHERE user_id IN (SELECT id FROM users WHERE role='customer')");
   $pdo->exec("DELETE FROM users WHERE role='customer'");
  }
  $pdo->commit();
  out(['ok'=>true,'message'=>'Dados zerados com sucesso']);
 }catch(Throwable $e){
  if($pdo->inTransaction())$pdo->rollBack();
  error_log('Admin reset ['.$action.']: '.$e->getMessage());
  out(['error'=>'Não foi possível zerar os dados'],500);
 }
}

if($id<1)out(['error'=>'Cliente inválido'],422);
$q=$pdo->prepare('SELECT id,role,status,allow_postpaid FROM users WHERE id=?');$q->execute([$id]);$target=$q->fetch();
if(!$target)out(['error'=>'Cliente não encontrado'],404);
if($target['role']==='admin')out(['error'=>'Outra conta administrativa não pode ser alterada aqui'],422);
if(in_array($action,['reset_wallet','clear_debt','set_wallet'],true)){
 if($action==='set_wallet'&&!array_key_exists('value_cents',$d))out(['error'=>'Informe o novo valor da carteira'],422);
 $target_cents=$action==='set_wallet'?(int)round((float)$d['value_cents']):0;
 if($action==='set_wallet'&&(!is_finite((float)$d['value_cents'])||abs($target_cents)>100000000))out(['error'=>'Valor de carteira inválido'],422);
 try{
  $pdo->beginTransaction();
  $walletQ=$pdo->prepare('SELECT balance_cents FROM wallets WHERE user_id=? FOR UPDATE');
  $walletQ->execute([$id]);
  $current=$walletQ->fetchColumn();
  if($current===false){
   $pdo->prepare('INSERT INTO wallets(user_id,balance_cents) VALUES(?,0)')->execute([$id]);
   $current=0;
  }
  $current=(int)$current;
  if($action==='clear_debt'&&$current>=0){
   $pdo->commit();
   out(['ok'=>true,'balance_cents'=>$current,'message'=>'Cliente não possui saldo devedor']);
  }
  $delta=$target_cents-$current;
  if($current!==$target_cents){
   $pdo->prepare('UPDATE wallets SET balance_cents=? WHERE user_id=?')->execute([$target_cents,$id]);
   $pdo->prepare("INSERT INTO wallet_transactions(user_id,type,amount_cents,reference_type,reference_id) VALUES(?,'adjustment',?,'admin_balance_set',?)")->execute([$id,$delta,$adminId]);
  }
  $pdo->commit();
  $message=$action==='clear_debt'?'Saldo devedor zerado':($action==='set_wallet'?'Carteira atualizada':'Carteira zerada');
  out(['ok'=>true,'balance_cents'=>$target_cents,'message'=>$message]);
 }catch(Throwable $e){
  if($pdo->inTransaction())$pdo->rollBack();
  out(['error'=>'Não foi possível ajustar o saldo do cliente'],500);
 }
}
if($action==='toggle_postpaid'){
 $value=(int)!((int)$target['allow_postpaid']);
 $pdo->prepare('UPDATE users SET allow_postpaid=? WHERE id=?')->execute([$value,$id]);
 out(['ok'=>true,'allow_postpaid'=>$value]);
}
if($action==='toggle_status'){
 $status=$target['status']==='active'?'blocked':'active';
 $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$status,$id]);
 out(['ok'=>true,'status'=>$status]);
}
out(['error'=>'Ação administrativa inválida'],422);
