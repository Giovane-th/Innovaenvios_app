<?php
function pagarmeRequest(array $config,string $method,string $path,?array $payload=null):array{
  $ch=curl_init('https://api.pagar.me/core/v5'.$path);
  $options=[
    CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,
    CURLOPT_HTTPHEADER=>['Authorization: Basic '.base64_encode($config['pagarme_secret_key'].':'),'Content-Type: application/json','Accept: application/json']
  ];
  if($payload!==null)$options[CURLOPT_POSTFIELDS]=json_encode($payload,JSON_UNESCAPED_UNICODE);
  curl_setopt_array($ch,$options);
  $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
  $data=json_decode((string)$raw,true);
  if($error)throw new RuntimeException('Não foi possível conectar à Pagar.me');
  if($code<200||$code>=300){
    $message=is_array($data)?($data['message']??$data['error']??null):null;
    throw new RuntimeException($message?'Pagar.me: '.$message:'Falha na comunicação com a Pagar.me');
  }
  if(!is_array($data))throw new RuntimeException('Resposta inválida da Pagar.me');
  return $data;
}
function syncPaidOrder(PDO $pdo,array $config,string $providerOrderId):array{
  $q=$pdo->prepare('SELECT id,user_id,amount_cents,status,credited_at FROM payment_orders WHERE provider_order_id=? LIMIT 1');
  $q->execute([$providerOrderId]);$local=$q->fetch();
  if(!$local)throw new RuntimeException('Cobrança não encontrada');
  if($local['credited_at'])return ['status'=>'paid','credited'=>true];

  $remote=pagarmeRequest($config,'GET','/orders/'.rawurlencode($providerOrderId));
  $remoteStatus=(string)($remote['status']??'pending');
  if($remoteStatus!=='paid'){
    $pdo->prepare('UPDATE payment_orders SET status=? WHERE id=?')->execute([$remoteStatus,(int)$local['id']]);
    return ['status'=>$remoteStatus,'credited'=>false];
  }

  $remoteAmount=(int)($remote['amount']??0);
  if($remoteAmount && $remoteAmount!==(int)$local['amount_cents'])throw new RuntimeException('Valor confirmado não corresponde à cobrança');
  $metadataUser=(int)($remote['metadata']['user_id']??0);
  if($metadataUser && $metadataUser!==(int)$local['user_id'])throw new RuntimeException('Cobrança não corresponde ao usuário');

  $pdo->beginTransaction();
  try{
    $lock=$pdo->prepare('SELECT id,user_id,amount_cents,credited_at FROM payment_orders WHERE id=? FOR UPDATE');
    $lock->execute([(int)$local['id']]);$order=$lock->fetch();
    if(!$order)throw new RuntimeException('Cobrança não encontrada');
    if(!$order['credited_at']){
      $pdo->prepare('UPDATE wallets SET balance_cents=balance_cents+? WHERE user_id=?')->execute([(int)$order['amount_cents'],(int)$order['user_id']]);
      $pdo->prepare('INSERT INTO wallet_transactions(user_id,type,amount_cents,reference_type,reference_id) VALUES(?,?,?,?,?)')
        ->execute([(int)$order['user_id'],'credit_purchase',(int)$order['amount_cents'],'payment_order',(int)$order['id']]);
      $pdo->prepare('UPDATE payment_orders SET status="paid",credited_at=NOW() WHERE id=?')->execute([(int)$order['id']]);
    }
    $pdo->commit();
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
  return ['status'=>'paid','credited'=>true];
}
