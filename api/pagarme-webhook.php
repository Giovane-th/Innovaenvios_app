<?php
require __DIR__.'/bootstrap.php';
require __DIR__.'/payment-service.php';
if($_SERVER['REQUEST_METHOD']!=='POST')out(['error'=>'Método não permitido'],405);
$payload=body();$type=(string)($payload['type']??'');
if(!in_array($type,['order.paid','charge.paid'],true))out(['ok'=>true,'ignored'=>true]);
$orderId=(string)($payload['data']['id']??$payload['data']['order']['id']??'');
if(!$orderId)out(['error'=>'Pedido não informado'],422);
try{
  $result=syncPaidOrder($pdo,$config,$orderId);
  out(['ok'=>true]+$result);
}catch(Throwable $e){
  error_log('PagarMe webhook: '.$e->getMessage());
  out(['error'=>'Não foi possível processar a confirmação'],500);
}
