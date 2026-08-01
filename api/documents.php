<?php
declare(strict_types=1);

function shippingDocumentsDir(array $config):string{
 $dir=rtrim((string)($config['shipping_documents_dir']??''),'/');
 if($dir==='')throw new RuntimeException('Diretório de documentos não configurado');
 if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('Não foi possível criar o diretório de documentos');
 if(!is_writable($dir))throw new RuntimeException('Diretório de documentos sem permissão de escrita');
 return $dir;
}
function saveLabelPdf(array $config,string $shipmentKey,string $pdf):string{
 if(!str_starts_with($pdf,'%PDF'))throw new RuntimeException('Rótulo recebido não é um PDF válido');
 $path=shippingDocumentsDir($config).'/label_'.preg_replace('/[^A-Za-z0-9_-]/','',$shipmentKey).'_'.bin2hex(random_bytes(5)).'.pdf';
 if(file_put_contents($path,$pdf,LOCK_EX)===false)throw new RuntimeException('Não foi possível salvar o rótulo');
 chmod($path,0600);return $path;
}
function declarationHtmlToPdf(array $config,string $shipmentKey,string $html):string{
 $autoload=dirname(__DIR__).'/vendor/autoload.php';if(!is_file($autoload))throw new RuntimeException('Dependências de PDF ainda não instaladas');
 require_once $autoload;
 $options=new Dompdf\Options();$options->set('isRemoteEnabled',false);$options->set('defaultFont','DejaVu Sans');
 $dompdf=new Dompdf\Dompdf($options);$dompdf->loadHtml($html,'UTF-8');$dompdf->setPaper('A4','portrait');$dompdf->render();$pdf=$dompdf->output();
 $path=shippingDocumentsDir($config).'/declaration_'.preg_replace('/[^A-Za-z0-9_-]/','',$shipmentKey).'_'.bin2hex(random_bytes(5)).'.pdf';
 if(file_put_contents($path,$pdf,LOCK_EX)===false)throw new RuntimeException('Não foi possível salvar a declaração');
 chmod($path,0600);return $path;
}
