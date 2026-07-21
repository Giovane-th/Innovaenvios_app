<?php
require __DIR__.'/bootstrap.php';
require __DIR__.'/payment-service.php';
$uid=userId();
if($_SERVER['REQUEST_METHOD']!=='POST')out(['error'=>'Método não permitido'],405);
if(empty($config['pagarme_secret_key']))out(['error'=>'Integração Pagar.me ainda não configurada'],503);
$d=body();$amount=(int)($d['amount_cents']??0);
if($amount<500||$amount>500000)out(['error'=>'Digite um valor entre R$ 5,00 e R$ 5.000,00'],422);
$q=$pdo->prepare('SELECT name,email FROM users WHERE id=?');$q->execute([$uid]);$u=$q->fetch();
if(!$u)out(['error'=>'Usuário não encontrado'],404);
$code='CARD-'.$uid.'-'.time().'-'.bin2hex(random_bytes(3));
$payload=[
 'is_building'=>false,
 'name'=>'Créditos InNova Envios',
 'order_code'=>$code,
 'type'=>'order',
 'expires_in'=>60,
 'max_sessions'=>1,
 'max_paid_sessions'=>1,
 'payment_settings'=>[
  'accepted_payment_methods'=>['credit_card'],
  'credit_card_settings'=>[
   'operation_type'=>'auth_and_capture',
   'installments_setup'=>[
    'interest_type'=>'simple',
    'interest_rate'=>0,
    'max_number_of_installments'=>1,
    'amount_threshold'=>0
   ]
  ]
 ],
 'cart_settings'=>[
  'items'=>[[
   'name'=>'Créditos InNova Envios',
   'amount'=>$amount,
   'default_quantity'=>1
  ]]
 ]
];
try{$link=pagarmeRequest($config,'POST','/paymentlinks',$payload);}
catch(Throwable $e){out(['error'=>$e->getMessage()],502);}
$linkId=trim((string)($link['id']??''));$url=trim((string)($link['url']??''));
if(!$linkId||!filter_var($url,FILTER_VALIDATE_URL))out(['error'=>'A Pagar.me não retornou um checkout válido'],502);
try{
 $pdo->prepare('INSERT INTO payment_orders(user_id,provider_order_id,amount_cents,method,status) VALUES(?,?,?,?,?)')
  ->execute([$uid,$code,$amount,'credit_card','pending']);
}catch(Throwable $e){error_log('PagarMe card link save: '.$e->getMessage());out(['error'=>'Checkout criado, mas não foi possível registrá-lo'],500);}
out(['checkout_url'=>$url,'link_id'=>$linkId,'status'=>'pending'],201);
