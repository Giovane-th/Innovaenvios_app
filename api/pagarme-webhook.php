<?php
require __DIR__.'/bootstrap.php';
require __DIR__.'/payment-service.php';
if($_SERVER['REQUEST_METHOD']!=='POST')out(['error'=>'Método não permitido'],405);
$payload=body();$type=(string)($payload['type']??'');
if($type!=='order.paid')out(['ok'=>true,'ignored'=>true]);
$orderId=(string)($payload['data']['id']??'');
if(!$orderId)out(['error'=>'Pedido não informado'],422);
try{
  $q=$pdo->prepare('SELECT id FROM payment_orders WHERE provider_order_id=? LIMIT 1');$q->execute([$orderId]);
  if(!$q->fetch()){
    $orderCode=trim((string)($payload['data']['code']??''));
    if($orderCode){$q=$pdo->prepare('UPDATE payment_orders SET provider_order_id=? WHERE provider_order_id=? AND method="credit_card"');$q->execute([$orderId,$orderCode]);}
  }
  $result=syncPaidOrder($pdo,$config,$orderId);
  out(['ok'=>true]+$result);
}catch(Throwable $e){
  error_log('PagarMe webhook: '.$e->getMessage());
  out(['error'=>'Não foi possível processar a confirmação'],500);
}
