<?php
// Copie para config.php somente no servidor. Nunca envie config.php ao GitHub.
return [
 'db_host'=>'localhost','db_name'=>'u594323539_IUt1u','db_user'=>'u594323539_MN2ZE','db_pass'=>'SENHA_MYSQL',
 'app_url'=>'https://innovaenvios.app',
 'correios_usuario'=>'','correios_codigo_acesso'=>'','correios_chave_acesso'=>'',
 'correios_contrato'=>'','correios_dr'=>'','correios_cartao_postagem'=>'',
 // Usado apenas se a autenticação dos Correios não devolver o id do contrato.
 'correios_id'=>'',
 'correios_servico_pac'=>'03298','correios_servico_sedex'=>'03220',
 'correios_servico_pac_balcao'=>'03220','correios_servico_sedex_balcao'=>'03220',
 // Habilite somente depois que a API Pré-Postagem estiver homologada no contrato.
 'correios_live_emission'=>false,
 'pagarme_secret_key'=>'','pagarme_public_key'=>'','pagarme_webhook_secret'=>'',
 'google_client_id'=>'','google_client_secret'=>'',
 'freight_markup_percent'=>40,
 // Multiplicador mínimo usado para calcular o preço de referência exibido (desconto).
 'freight_reference_multiplier'=>2,
 // Diretório fora da webroot onde os PDFs de etiqueta/declaração são salvos.
 'shipping_documents_dir'=>'',
 // E-mail transacional (etiqueta + declaração em anexo) enviado ao cliente.
 'smtp_host'=>'smtp.hostinger.com','smtp_port'=>465,'smtp_secure'=>'ssl',
 'smtp_user'=>'','smtp_pass'=>'',
 'mail_from'=>'suporte@innovaenvios.app','mail_from_name'=>"In'Nova Envios",
 'admin_notification_email'=>'',
];