-- Additional System Settings for Seller Module
-- Add this to your database after running upgrade_v3.sql

INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES
-- Demo Mode (set to 0 in production)
('payment_demo_mode', '1', NOW())
ON DUPLICATE KEY UPDATE setting_value=setting_value;

-- Update this setting to 0 when deploying to production
-- UPDATE system_settings SET setting_value = '0' WHERE setting_key = 'payment_demo_mode';
