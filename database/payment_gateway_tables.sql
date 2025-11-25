-- ========================================
-- PAYMENT GATEWAY TABLES
-- Support for Midtrans and Xendit
-- ========================================

-- Table: payment_records
CREATE TABLE IF NOT EXISTS payment_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(100) UNIQUE NOT NULL,
    agent_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    gateway VARCHAR(50) NOT NULL,
    status ENUM('pending', 'paid', 'failed', 'expired') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    callback_data TEXT,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
    INDEX idx_order_id (order_id),
    INDEX idx_agent_id (agent_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert payment gateway settings
INSERT INTO agent_settings (agent_id, setting_key, setting_value, setting_type, description, updated_by) VALUES
(1, 'payment_enabled', '0', 'boolean', 'Enable payment gateway integration', 'system'),
(1, 'payment_gateway', 'midtrans', 'string', 'Active payment gateway: midtrans or xendit', 'system'),
(1, 'payment_midtrans_server_key', '', 'string', 'Midtrans server key', 'system'),
(1, 'payment_midtrans_client_key', '', 'string', 'Midtrans client key', 'system'),
(1, 'payment_midtrans_environment', 'sandbox', 'string', 'Midtrans environment: sandbox or production', 'system'),
(1, 'payment_xendit_api_key', '', 'string', 'Xendit API key', 'system'),
(1, 'payment_xendit_callback_token', '', 'string', 'Xendit callback verification token', 'system')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- ========================================
-- END OF SQL SCRIPT
-- ========================================
