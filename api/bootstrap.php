<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
session_set_cookie_params([
  'lifetime'=>60*60*24*7,'path'=>'/','secure'=>true,
  'httponly'=>true,'samesite'=>'Lax'
]);
session_start();
$externalConfig=dirname(__DIR__,4).'/innova-secrets/config.php';
$configFile=is_file($externalConfig)?$externalConfig:__DIR__.'/config.php';
if(!is_file($configFile)){ http_response_code(503); echo json_encode(['error'=>'Servidor ainda não configurado']); exit; }
$config=require $configFile;
try{
 $pdo=new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",$config['db_user'],$config['db_pass'],[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES=>false
 ]);
}catch(Throwable $e){ http_response_code(503); echo json_encode(['error'=>'Banco indisponível']); exit; }
function body():array{ $v=json_decode(file_get_contents('php://input'),true); return is_array($v)?$v:[]; }
function out(array $v,int $code=200):never{ http_response_code($code); echo json_encode($v,JSON_UNESCAPED_UNICODE); exit; }
function userId():int{ if(empty($_SESSION['user_id'])) out(['error'=>'Não autenticado'],401); return (int)$_SESSION['user_id']; }
function phone(string $v):string{ return preg_replace('/\D+/','',$v); }
function requireAdmin(PDO $pdo):int{ $id=userId();$q=$pdo->prepare('SELECT role,status FROM users WHERE id=?');$q->execute([$id]);$u=$q->fetch();if(!$u||$u['status']!=='active'||$u['role']!=='admin')out(['error'=>'Acesso administrativo negado'],403);return $id; }
