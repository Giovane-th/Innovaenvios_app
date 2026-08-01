-- Execute uma vez no phpMyAdmin antes de ativar o Google OAuth.
ALTER TABLE users
  MODIFY phone VARCHAR(20) NULL,
  MODIFY password_hash VARCHAR(255) NULL,
  ADD COLUMN email VARCHAR(190) NULL AFTER phone,
  ADD COLUMN google_sub VARCHAR(255) NULL AFTER email,
  ADD UNIQUE KEY uq_users_email (email),
  ADD UNIQUE KEY uq_users_google_sub (google_sub);
