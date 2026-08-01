<?php
require __DIR__.'/bootstrap.php';
$uid=userId();
if($_SERVER['REQUEST_METHOD']==='GET'){
 $q=$pdo->prepare('SELECT id,origin_zip,destination_zip,package_format,weight_kg,height_cm,width_cm,length_cm,created_at FROM shipping_simulations WHERE user_id=? ORDER BY id DESC LIMIT 50');
 $q->execute([$uid]);out(['simulations'=>$q->fetchAll()]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')out(['error'=>'Método não permitido'],405);
$d=body();
$origin=preg_replace('/\D+/','',(string)($d['origin_zip']??''));
$destination=preg_replace('/\D+/','',(string)($d['destination_zip']??''));
$format=trim((string)($d['package_format']??''));
$weight=(float)($d['weight_kg']??0);$height=(float)($d['height_cm']??0);$width=(float)($d['width_cm']??0);$length=(float)($d['length_cm']??0);
if(strlen($origin)!==8||strlen($destination)!==8)out(['error'=>'CEPs inválidos'],422);
if(!in_array($format,['Caixa / pacote','Envelope','Rolo'],true))out(['error'=>'Formato inválido'],422);
if($weight<=0||$weight>100||$height<=0||$height>200||$width<=0||$width>200||$length<=0||$length>200)out(['error'=>'Peso ou dimensões inválidos'],422);
$q=$pdo->prepare('INSERT INTO shipping_simulations(user_id,origin_zip,destination_zip,package_format,weight_kg,height_cm,width_cm,length_cm) VALUES(?,?,?,?,?,?,?,?)');
$q->execute([$uid,$origin,$destination,$format,$weight,$height,$width,$length]);
out(['ok'=>true,'simulation_id'=>(int)$pdo->lastInsertId()],201);
