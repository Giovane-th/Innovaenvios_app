-- Execute uma vez no phpMyAdmin do banco de produção antes de publicar
-- o novo fluxo assíncrono de emissão (api/shipments.php + api/correios-client.php).
-- A emissão real de etiqueta passou a ser dividida em uma etapa de criação
-- rápida (pré-postagem + recibo do rótulo assíncrono) seguida de sondagens
-- curtas do navegador, para nenhuma requisição HTTP ficar presa por dezenas
-- de segundos esperando os Correios e disparar 504 no gateway/proxy.
CREATE TABLE IF NOT EXISTS shipment_emissions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 status ENUM('processing','finalizing','ready','error') NOT NULL DEFAULT 'processing',
 prepost_id VARCHAR(80) NOT NULL,
 receipt_id VARCHAR(80) NOT NULL,
 tracking_code VARCHAR(32) NULL,
 cost_cents BIGINT NOT NULL DEFAULT 0,
 price_cents BIGINT NOT NULL DEFAULT 0,
 payload_json LONGTEXT NOT NULL,
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 error_message TEXT NULL,
 shipment_id BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_ship_emission_user(user_id,created_at),
 CONSTRAINT fk_ship_emission_user FOREIGN KEY(user_id) REFERENCES users(id),
 CONSTRAINT fk_ship_emission_shipment FOREIGN KEY(shipment_id) REFERENCES shipments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
