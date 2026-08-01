-- Execute uma vez no phpMyAdmin do banco de produção antes de publicar
-- o fluxo real de emissão de etiquetas (api/shipments.php).
-- Colunas exigidas pelo INSERT INTO shipments em api/shipments.php que ainda
-- não existiam em nenhuma migração versionada do repositório.
ALTER TABLE shipments
 ADD COLUMN IF NOT EXISTS recipient_name VARCHAR(120) NULL AFTER destination_zip,
 ADD COLUMN IF NOT EXISTS recipient_email VARCHAR(190) NULL AFTER recipient_name,
 ADD COLUMN IF NOT EXISTS recipient_phone VARCHAR(20) NULL AFTER recipient_email,
 ADD COLUMN IF NOT EXISTS recipient_document VARCHAR(20) NULL AFTER recipient_phone,
 ADD COLUMN IF NOT EXISTS recipient_street VARCHAR(160) NULL AFTER recipient_document,
 ADD COLUMN IF NOT EXISTS recipient_number VARCHAR(30) NULL AFTER recipient_street,
 ADD COLUMN IF NOT EXISTS recipient_complement VARCHAR(100) NULL AFTER recipient_number,
 ADD COLUMN IF NOT EXISTS recipient_neighborhood VARCHAR(100) NULL AFTER recipient_complement,
 ADD COLUMN IF NOT EXISTS recipient_city VARCHAR(100) NULL AFTER recipient_neighborhood,
 ADD COLUMN IF NOT EXISTS recipient_state CHAR(2) NULL AFTER recipient_city,
 ADD COLUMN IF NOT EXISTS package_weight_grams INT UNSIGNED NULL AFTER recipient_state,
 ADD COLUMN IF NOT EXISTS package_height_cm DECIMAL(10,2) NULL AFTER package_weight_grams,
 ADD COLUMN IF NOT EXISTS package_width_cm DECIMAL(10,2) NULL AFTER package_height_cm,
 ADD COLUMN IF NOT EXISTS package_length_cm DECIMAL(10,2) NULL AFTER package_width_cm,
 ADD COLUMN IF NOT EXISTS contents_json LONGTEXT NULL AFTER package_length_cm,
 ADD COLUMN IF NOT EXISTS label_file VARCHAR(255) NULL AFTER correios_prepost_id,
 ADD COLUMN IF NOT EXISTS declaration_file VARCHAR(255) NULL AFTER label_file,
 ADD COLUMN IF NOT EXISTS customer_emailed_at TIMESTAMP NULL AFTER declaration_file,
 ADD COLUMN IF NOT EXISTS admin_emailed_at TIMESTAMP NULL AFTER customer_emailed_at;
