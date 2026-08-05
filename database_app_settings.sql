-- Execute uma vez no phpMyAdmin do banco de produção antes de publicar
-- a aba "Configurações de implantação" do painel administrativo
-- (api/admin-settings.php). Guarda, criptografados, os parâmetros de
-- Correios, Pagar.me, e-mail/SMTP e Google OAuth que hoje só existem
-- no config.php do servidor, para que um administrador possa
-- preenchê-los pelo próprio app em vez de editar o arquivo.
CREATE TABLE IF NOT EXISTS app_settings (
 setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
 setting_value TEXT NULL,
 updated_by BIGINT UNSIGNED NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_app_setting_admin FOREIGN KEY(updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
