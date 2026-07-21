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
 $q=$pdo->query("SELECT u.id,u.name,u.email,u.phone,u.status,u.role,u.allow_postpaid,u.created_at,COALESCE(w.balance_cents,0) balance_cents,(SELECT COUNT(*) FROM shipments s WHERE s.user_id=u.id) shipment_count,(SELECT COUNT(*) FROM shipping_simulations ss WHERE ss.user_id=u.id) simulation_count FROM users u LEFT JOIN wallets w ON w.user_id=u.id ORDER BY u.id DESC LIMIT 200");
 out(['stats'=>$stats,'users'=>$q->fetchAll()]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')out(['error'=>'Método não permitido'],405);
$d=body();$id=(int)($d['user_id']??0);$action=(string)($d['action']??'');
if($id<1)out(['error'=>'Cliente inválido'],422);
$q=$pdo->prepare('SELECT id,role,status,allow_postpaid FROM users WHERE id=?');$q->execute([$id]);$target=$q->fetch();
if(!$target)out(['error'=>'Cliente não encontrado'],404);
if($target['role']==='admin')out(['error'=>'Outra conta administrativa não pode ser alterada aqui'],422);
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
