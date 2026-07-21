-- Execute uma vez no banco da In'Nova Envios.
ALTER TABLE users
  ADD COLUMN role ENUM('customer','admin') NOT NULL DEFAULT 'customer' AFTER password_hash,
  ADD COLUMN allow_postpaid TINYINT(1) NOT NULL DEFAULT 0 AFTER role;
UPDATE users SET role='admin',status='active' WHERE email='innovaeducpro@gmail.com';
