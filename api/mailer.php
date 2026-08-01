<?php
declare(strict_types=1);

function safeMailAddress(string $email):string{
 $email=trim($email);
 if(!filter_var($email,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$email))throw new InvalidArgumentException('E-mail inválido');
 return $email;
}

function sendDocumentEmail(array $config,string $to,string $subject,string $html,array $attachments=[]):bool{
 $to=safeMailAddress($to);$from=safeMailAddress((string)($config['mail_from']??'suporte@innovaenvios.app'));
 $autoload=dirname(__DIR__).'/vendor/autoload.php';if(!is_file($autoload))throw new RuntimeException('Dependências de e-mail ainda não instaladas');
 require_once $autoload;
 $mail=new PHPMailer\PHPMailer\PHPMailer(true);$mail->CharSet='UTF-8';$mail->isSMTP();
 $mail->Host=(string)($config['smtp_host']??'smtp.hostinger.com');$mail->SMTPAuth=true;
 $mail->Username=(string)($config['smtp_user']??$from);$mail->Password=(string)($config['smtp_pass']??'');
 $mail->Port=(int)($config['smtp_port']??465);$mail->SMTPSecure=(string)($config['smtp_secure']??'ssl');
 $mail->setFrom($from,(string)($config['mail_from_name']??"In'Nova Envios"));$mail->addReplyTo($from);$mail->addAddress($to);
 $mail->isHTML(true);$mail->Subject=preg_replace('/[\r\n]+/',' ',trim($subject));$mail->Body=$html;$mail->AltBody=trim(strip_tags(str_replace(['<br>','<br/>','<br />'],"\n",$html)));
 foreach($attachments as $attachment){$path=(string)($attachment['path']??'');if(!is_file($path)||!is_readable($path))throw new RuntimeException('Anexo não encontrado');$mail->addAttachment($path,(string)($attachment['name']??basename($path)));}
 return $mail->send();
}
