-- Execute uma vez no banco da In'Nova Envios antes de ativar a nova compra de créditos.
ALTER TABLE users
  ADD COLUMN pagarme_customer_id VARCHAR(100) NULL AFTER google_sub,
  ADD UNIQUE KEY uq_users_pagarme_customer (pagarme_customer_id);
