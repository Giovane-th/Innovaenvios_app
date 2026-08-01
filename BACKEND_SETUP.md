# Backend multiusuário na Hostinger

1. No hPanel, crie um banco MySQL e um usuário.
2. Abra o phpMyAdmin e importe database.sql.
3. Copie api/config.example.php para api/config.php no servidor.
4. Preencha apenas no servidor: banco, usuário e senha.
5. Confirme que o domínio usa HTTPS.
6. Teste cadastro em /api/auth.php?action=register.

Nunca envie api/config.php para o GitHub. As integrações Correios CWS e Pagar.me devem ser adicionadas no servidor e seus webhooks precisam validar autenticidade e idempotência antes de alterar saldo.
