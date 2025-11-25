-- ========================================
-- VOUCHER SETTINGS UPDATE
-- Add voucher generation settings to agent_settings table
-- ========================================

-- Insert voucher generation settings
INSERT INTO agent_settings (agent_id, setting_key, setting_value, setting_type, description, updated_by) VALUES
(1, 'voucher_username_password_same', '0', 'boolean', 'Username dan password sama atau berbeda', 'system'),
(1, 'voucher_username_type', 'alphanumeric', 'string', 'Tipe karakter username: numeric, alpha, alphanumeric', 'system'),
(1, 'voucher_username_length', '8', 'number', 'Panjang karakter username', 'system'),
(1, 'voucher_password_type', 'alphanumeric', 'string', 'Tipe karakter password: numeric, alpha, alphanumeric', 'system'),
(1, 'voucher_password_length', '6', 'number', 'Panjang karakter password', 'system'),
(1, 'voucher_prefix_enabled', '1', 'boolean', 'Gunakan prefix untuk username', 'system'),
(1, 'voucher_prefix', 'AG', 'string', 'Prefix untuk username', 'system'),
(1, 'voucher_uppercase', '1', 'boolean', 'Gunakan huruf kapital', 'system')
ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    description = VALUES(description);

-- ========================================
-- Verification Query
-- ========================================
-- Run this to verify settings are inserted:
-- SELECT * FROM agent_settings WHERE setting_key LIKE 'voucher_%';