-- Execute uma vez no phpMyAdmin antes de publicar o código desta atualização.
ALTER TABLE shipments
  ADD COLUMN billing_type ENUM('wallet','internal') NOT NULL DEFAULT 'wallet' AFTER price_cents,
  ADD COLUMN wallet_charged_cents BIGINT NOT NULL DEFAULT 0 AFTER billing_type;

-- Os envios antigos foram cobrados pela regra anterior.
UPDATE shipments
SET billing_type='wallet',
    wallet_charged_cents=price_cents;
