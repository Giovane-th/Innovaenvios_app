<?php
require __DIR__.'/bootstrap.php';$uid=userId();
if(empty($config['pagarme_secret_key']))out(['error'=>'Integração Pagar.me ainda não configurada'],503);
if($_SERVER['REQUEST_METHOD']!=='POST')out(['error'=>'Método não permitido'],405);
$d=body();$amount=(int)($d['amount_cents']??0);$method=(string)($d['method']??'pix');
if($amount<2000||!in_array($method,['pix','credit_card'],true))out(['error'=>'Pagamento inválido'],422);
if($method==='credit_card')out(['error'=>'O cartão deve ser tokenizado no navegador com a chave pública Pagar.me'],422);
$q=$pdo->prepare('SELECT name,phone FROM users WHERE id=?');$q->execute([$uid]);$u=$q->fetch();
$payload=['items'=>[['amount'=>$amount,'description'=>'Créditos InNova Envios','quantity'=>1,'code'=>'CREDIT']],
 'customer'=>['name'=>$u['name'],'type'=>'individual','phones'=>['mobile_phone'=>['country_code'=>'55','area_code'=>substr($u['phone'],0,2),'number'=>substr($u['phone'],2)]]],
 'payments'=>[['payment_method'=>'pix','pix'=>['expires_in'=>3600]]],
 'metadata'=>['user_id'=>(string)$uid]];
$ch=curl_init('https://api.pagar.me/core/v5/orders');
curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,
 CURLOPT_HTTPHEADER=>['Authorization: Basic '.base64_encode($config['pagarme_secret_key'].':'),'Content-Type: application/json'],
 CURLOPT_POSTFIELDS=>json_encode($payload)]);
$raw=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$order=json_decode((string)$raw,true);
if($code<200||$code>=300)out(['error'=>'Falha ao criar cobrança Pagar.me','details'=>$order['message']??null],502);
$pdo->prepare('INSERT INTO payment_orders(user_id,provider_order_id,amount_cents,method,status) VALUES(?, ?, ?, "pix", "pending")')->execute([$uid,$order['id'],$amount]);
$charge=$order['charges'][0]??[];$tx=$charge['last_transaction']??[];
out(['order_id'=>$order['id'],'status'=>$order['status']??'pending','qr_code'=>$tx['qr_code']??null,'qr_code_url'=>$tx['qr_code_url']??null],201);
