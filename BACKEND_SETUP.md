# Backend multiusuário na Hostinger

## Deploy

Este projeto é PHP puro servido como está (sem build): `api/*.php` no backend e HTML/assets estáticos na raiz. Dependências PHP (`dompdf`, `phpmailer`) já vêm versionadas em `vendor/`, então não é preciso rodar `composer install` no servidor.

- **Deploy**: Auto Deploy do Hostinger (hPanel → Git) puxa automaticamente a branch `main` a cada push.
- **CI**: todo push/PR para `main` roda `.github/workflows/php-ci.yml` (`composer validate`, `composer install`, `php -l` em `api/*.php` nas versões 8.1/8.2/8.3) antes do merge.

## Configuração inicial

1. No hPanel, crie um banco MySQL e um usuário.
2. Abra o phpMyAdmin e importe database.sql.
3. Copie api/config.example.php para api/config.php no servidor.
4. Preencha apenas no servidor: banco, usuário e senha.
5. Confirme que o domínio usa HTTPS.
6. Teste cadastro em /api/auth.php?action=register.

Nunca envie api/config.php para o GitHub. As integrações Correios CWS e Pagar.me devem ser adicionadas no servidor e seus webhooks precisam validar autenticidade e idempotência antes de alterar saldo.

## Configurações de implantação pelo admin

Correios, Pagar.me, e-mail/SMTP e Google OAuth podem ser preenchidos direto pelo painel (Administração → Configurações de implantação), em vez de editar `config.php` no servidor toda vez. Pra ativar:

1. No phpMyAdmin, importe `database_app_settings.sql`.
2. No `config.php` do servidor, defina `settings_encryption_key` com uma chave gerada por:
   ```
   php -r "echo base64_encode(random_bytes(32));"
   ```
3. Faça login como admin e preencha a aba. Campos de senha/chave em branco mantêm o valor já salvo — só sobrescrevem quando você digita algo novo.

Sem `settings_encryption_key` configurada, a aba fica desativada (erro 503) e o app continua usando só os valores fixos de `config.php`, sem quebrar nada.
