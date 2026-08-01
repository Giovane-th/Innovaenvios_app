-- Execute uma vez no banco da In'Nova Envios.
CREATE TABLE shipping_simulations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 origin_zip CHAR(8) NOT NULL,
 destination_zip CHAR(8) NOT NULL,
 package_format VARCHAR(40) NOT NULL,
 weight_kg DECIMAL(10,3) NOT NULL,
 height_cm DECIMAL(10,2) NOT NULL,
 width_cm DECIMAL(10,2) NOT NULL,
 length_cm DECIMAL(10,2) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_sim_user_created(user_id,created_at),
 CONSTRAINT fk_sim_user FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
