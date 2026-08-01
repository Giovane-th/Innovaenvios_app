<?php
require __DIR__.'/bootstrap.php';
requireSameOrigin($config);
$uid=userId();

if($_SERVER['REQUEST_METHOD']==='GET'){
 $q=$pdo->prepare("SELECT COALESCE(NULLIF(p.sender_name,''),u.name) AS name,COALESCE(NULLIF(p.sender_email,''),u.email) AS email,u.phone,p.document,p.postal_code,p.street,p.number,p.complement,p.neighborhood,p.city,p.state,p.updated_at FROM users u LEFT JOIN user_shipping_profiles p ON p.user_id=u.id WHERE u.id=?");
 $q->execute([$uid]);
 out(['profile'=>$q->fetch()]);
}
if(!in_array($_SERVER['REQUEST_METHOD'],['POST','PUT'],true))out(['error'=>'Método não permitido'],405);

$d=body();
$senderName=trim((string)($d['sender_name']??''));
$senderEmail=strtolower(trim((string)($d['sender_email']??'')));
$document=phone((string)($d['document']??''));
$contactPhone=phone((string)($d['phone']??''));
$postalCode=phone((string)($d['postal_code']??''));
$street=trim((string)($d['street']??''));
$number=trim((string)($d['number']??''));
$complement=trim((string)($d['complement']??''));
$neighborhood=trim((string)($d['neighborhood']??''));
$city=trim((string)($d['city']??''));
$state=strtoupper(trim((string)($d['state']??'')));

if(mb_strlen($senderName)<2)out(['error'=>'Informe o nome do remetente'],422);
if(!filter_var($senderEmail,FILTER_VALIDATE_EMAIL))out(['error'=>'Informe o e-mail do remetente'],422);
if(!in_array(strlen($document),[11,14],true))out(['error'=>'Informe CPF ou CNPJ válido'],422);
if(strlen($contactPhone)<10||strlen($contactPhone)>11)out(['error'=>'Informe telefone válido'],422);
if(strlen($postalCode)!==8)out(['error'=>'Informe CEP válido'],422);
if(mb_strlen($street)<2||mb_strlen($number)<1||mb_strlen($neighborhood)<2||mb_strlen($city)<2||!preg_match('/^[A-Z]{2}$/',$state))out(['error'=>'Preencha o endereço completo'],422);

$q=$pdo->prepare('INSERT INTO user_shipping_profiles(user_id,sender_name,sender_email,document,phone,postal_code,street,number,complement,neighborhood,city,state) VALUES(?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE sender_name=VALUES(sender_name),sender_email=VALUES(sender_email),document=VALUES(document),phone=VALUES(phone),postal_code=VALUES(postal_code),street=VALUES(street),number=VALUES(number),complement=VALUES(complement),neighborhood=VALUES(neighborhood),city=VALUES(city),state=VALUES(state)');
$q->execute([$uid,$senderName,$senderEmail,$document,$contactPhone,$postalCode,$street,$number,$complement?:null,$neighborhood,$city,$state]);
$pdo->prepare('UPDATE users SET phone=? WHERE id=?')->execute([$contactPhone,$uid]);
out(['ok'=>true,'has_shipping_profile'=>1]);
