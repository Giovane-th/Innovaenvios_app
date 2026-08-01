<?php
require __DIR__.'/bootstrap.php';
require __DIR__.'/payment-service.php';
$uid=userId();
if(empty($config['pagarme_secret_key']))out(['error'=>'Integração Pagar.me ainda não configurada'],503);

if($_SERVER['REQUEST_METHOD']==='GET'){
  $orderId=trim((string)($_GET['order_id']??''));
  if(!$orderId)out(['error'=>'Cobrança não informada'],422);
  $q=$pdo->prepare('SELECT id FROM payment_orders WHERE provider_order_id=? AND user_id=?');
  $q->execute([$orderId,$uid]);if(!$q->fetch())out(['error'=>'Cobrança não encontrada'],404);
  try{$result=syncPaidOrder($pdo,$config,$orderId);}
  catch(Throwable $e){out(['error'=>$e->getMessage()],502);}
  $q=$pdo->prepare('SELECT balance_cents FROM wallets WHERE user_id=?');$q->execute([$uid]);
  out($result+['balance_cents'=>(int)$q->fetchColumn()]);
}

if($_SERVER['REQUEST_METHOD']!=='POST')out(['error'=>'Método não permitido'],405);
$d=body();$amount=(int)($d['amount_cents']??0);$method=(string)($d['method']??'pix');
$document=preg_replace('/\D+/','',(string)($d['document']??''));$paymentPhone=preg_replace('/\D+/','',(string)($d['phone']??''));
if($amount<500||$amount>500000||!in_array($method,['pix','credit_card'],true))out(['error'=>'Pagamento inválido'],422);
if($method==='credit_card')out(['error'=>'Cartão temporariamente indisponível. Use Pix.'],422);
function validCpf(string $cpf):bool{if(strlen($cpf)!==11||preg_match('/^(\d)\1{10}$/',$cpf))return false;for($t=9;$t<11;$t++){$sum=0;for($i=0;$i<$t;$i++)$sum+=(int)$cpf[$i]*(($t+1)-$i);$digit=(10*($sum%11))%11;if($digit===10)$digit=0;if((int)$cpf[$t]!==$digit)return false;}return true;}
$q=$pdo->prepare('SELECT name,phone,email,pagarme_customer_id FROM users WHERE id=?');$q->execute([$uid]);$u=$q->fetch();
if(!$u)out(['error'=>'Usuário não encontrado'],404);
$email=strtolower(trim((string)($u['email']??'')));
$tel=$paymentPhone?:preg_replace('/\D+/','',(string)($u['phone']??''));
$customerId=trim((string)($u['pagarme_customer_id']??''));
if(!filter_var($email,FILTER_VALIDATE_EMAIL))out(['error'=>'Sua conta precisa ter um e-mail válido'],422);
if(!$customerId){
 if(!validCpf($document))out(['error'=>'Informe um CPF válido somente nesta primeira compra'],422);
 if(strlen($tel)<10||strlen($tel)>11)out(['error'=>'Informe um telefone válido somente nesta primeira compra'],422);
 $customer=[
  'name'=>$u['name'],'type'=>'individual','email'=>$email,'document'=>$document,'document_type'=>'CPF',
  'phones'=>['mobile_phone'=>['country_code'=>'55','area_code'=>substr($tel,0,2),'number'=>substr($tel,2)]]
 ];
}

$code='CREDIT-'.$uid.'-'.time().'-'.bin2hex(random_bytes(2));
$payload=[
 'code'=>$code,
 'items'=>[['amount'=>$amount,'description'=>'Créditos InNova Envios','quantity'=>1,'code'=>'CREDIT']],
 'payments'=>[['payment_method'=>'pix','pix'=>['expires_in'=>3600]]],
 'metadata'=>['user_id'=>(string)$uid,'purpose'=>'wallet_credit']
];
if($customerId)$payload['customer_id']=$customerId;else $payload['customer']=$customer;
try{$order=pagarmeRequest($config,'POST','/orders',$payload);}
catch(Throwable $e){out(['error'=>$e->getMessage()],502);}
$orderId=(string)($order['id']??'');
if(!$orderId)out(['error'=>'A Pagar.me não retornou o pedido'],502);
if(!$customerId){
 $newCustomerId=trim((string)($order['customer']['id']??''));
 if($newCustomerId){$q=$pdo->prepare('UPDATE users SET pagarme_customer_id=?,phone=? WHERE id=? AND pagarme_customer_id IS NULL');$q->execute([$newCustomerId,$tel,$uid]);}
}
try{
 $pdo->prepare('INSERT INTO payment_orders(user_id,provider_order_id,amount_cents,method,status) VALUES(?,?,?,?,?)')
   ->execute([$uid,$orderId,$amount,'pix',(string)($order['status']??'pending')]);
}catch(Throwable $e){error_log('PagarMe order save: '.$e->getMessage());out(['error'=>'Cobrança criada, mas não foi possível registrá-la'],500);}
$charge=$order['charges'][0]??[];$tx=$charge['last_transaction']??[];
out([
 'order_id'=>$orderId,'status'=>$order['status']??'pending',
 'qr_code'=>$tx['qr_code']??null,'qr_code_url'=>$tx['qr_code_url']??null,
 'expires_at'=>$tx['expires_at']??null
],201);
