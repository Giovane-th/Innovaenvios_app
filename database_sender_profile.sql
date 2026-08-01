-- Execute uma vez no banco da In'Nova Envios.
-- Mantém os dados usados no login separados dos dados impressos como remetente.
ALTER TABLE user_shipping_profiles
  ADD COLUMN IF NOT EXISTS sender_name VARCHAR(120) NULL AFTER user_id,
  ADD COLUMN IF NOT EXISTS sender_email VARCHAR(190) NULL AFTER sender_name;

UPDATE user_shipping_profiles p
JOIN users u ON u.id=p.user_id
SET
  p.sender_name=COALESCE(NULLIF(p.sender_name,''),u.name),
  p.sender_email=COALESCE(NULLIF(p.sender_email,''),u.email)
WHERE p.sender_name IS NULL OR p.sender_name='' OR p.sender_email IS NULL OR p.sender_email='';
