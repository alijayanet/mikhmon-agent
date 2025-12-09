-- ========================================
-- AGENT/RESELLER SYSTEM DATABASE
-- MikhMon WhatsApp Integration
-- ========================================

-- Table: agents
-- Menyimpan data agent/reseller
CREATE TABLE IF NOT EXISTS agents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_code VARCHAR(20) UNIQUE NOT NULL,
    agent_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(100),
    password VARCHAR(255) NOT NULL,
    balance DECIMAL(15,2) DEFAULT 0.00,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    level ENUM('bronze', 'silver', 'gold', 'platinum') DEFAULT 'bronze',
    commission_amount DECIMAL(15,2) DEFAULT 0.00,
    commission_percent DECIMAL(5,2) DEFAULT 0.00,
    created_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    notes TEXT,
    INDEX idx_phone (phone),
    INDEX idx_agent_code (agent_code),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: agent_prices
-- Harga khusus untuk agent per profile
CREATE TABLE IF NOT EXISTS agent_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    profile_name VARCHAR(100) NOT NULL,
    buy_price DECIMAL(15,2) NOT NULL,
    sell_price DECIMAL(15,2) NOT NULL,
    stock_limit INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
    UNIQUE KEY unique_agent_profile (agent_id, profile_name),
    INDEX idx_agent_id (agent_id),
    INDEX idx_profile (profile_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: agent_transactions
-- Riwayat transaksi agent (topup, generate voucher, dll)
CREATE TABLE IF NOT EXISTS agent_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    transaction_type ENUM('topup', 'generate', 'refund', 'commission', 'penalty', 'digiflazz') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    balance_before DECIMAL(15,2) NOT NULL,
    balance_after DECIMAL(15,2) NOT NULL,
    profile_name VARCHAR(100),
    voucher_username VARCHAR(100),
    voucher_password VARCHAR(100),
    quantity INT DEFAULT 1,
    description TEXT,
    reference_id VARCHAR(50),
    created_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
    INDEX idx_agent_id (agent_id),
    INDEX idx_type (transaction_type),
    INDEX idx_date (created_at),
    INDEX idx_reference (reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: agent_vouchers
-- Voucher yang di-generate oleh agent
CREATE TABLE IF NOT EXISTS agent_vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    transaction_id INT,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(100) NOT NULL,
    profile_name VARCHAR(100) NOT NULL,
    buy_price DECIMAL(15,2) NOT NULL,
    sell_price DECIMAL(15,2),
    status ENUM('active', 'used', 'expired', 'deleted') DEFAULT 'active',
    customer_phone VARCHAR(20),
    customer_name VARCHAR(100),
    sent_via ENUM('web', 'whatsapp', 'manual') DEFAULT 'web',
    sent_at TIMESTAMP NULL,
    used_at TIMESTAMP NULL,
    expired_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES agent_transactions(id) ON DELETE SET NULL,
    INDEX idx_agent_id (agent_id),
    INDEX idx_username (username),
    INDEX idx_status (status),
    INDEX idx_customer_phone (customer_phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: agent_topup_requests
-- Request topup saldo dari agent
CREATE TABLE IF NOT EXISTS agent_topup_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_method VARCHAR(50),
    payment_proof VARCHAR(255),
    bank_name VARCHAR(50),
    account_number VARCHAR(50),
    account_name VARCHAR(100),
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    processed_by VARCHAR(50),
    admin_notes TEXT,
    agent_notes TEXT,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
    INDEX idx_agent_id (agent_id),
    INDEX idx_status (status),
    INDEX idx_requested_at (requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: agent_commissions
-- Komisi yang didapat agent
CREATE TABLE IF NOT EXISTS agent_commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    voucher_id INT,
    commission_amount DECIMAL(15,2) NOT NULL,
    commission_percent DECIMAL(5,2) NOT NULL,
    voucher_price DECIMAL(15,2) NOT NULL,
    status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
    FOREIGN KEY (voucher_id) REFERENCES agent_vouchers(id) ON DELETE SET NULL,
    INDEX idx_agent_id (agent_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: digiflazz_products
-- Daftar produk digital yang diambil dari Digiflazz
CREATE TABLE IF NOT EXISTS digiflazz_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_sku_code VARCHAR(50) NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    brand VARCHAR(100),
    category VARCHAR(50),
    type ENUM('prepaid', 'postpaid') DEFAULT 'prepaid',
    price INT NOT NULL,
    buyer_price INT,
    seller_price INT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    desc_header VARCHAR(150),
    desc_footer TEXT,
    icon_url VARCHAR(255),
    allow_markup TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_sku (buyer_sku_code),
    INDEX idx_category (category),
    INDEX idx_brand (brand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: digiflazz_transactions
-- Riwayat transaksi pembelian produk digital via Digiflazz
CREATE TABLE IF NOT EXISTS digiflazz_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT,
    ref_id VARCHAR(60) NOT NULL,
    buyer_sku_code VARCHAR(50) NOT NULL,
    customer_no VARCHAR(50) NOT NULL,
    customer_name VARCHAR(100),
    status ENUM('pending', 'success', 'failed', 'refund') DEFAULT 'pending',
    message VARCHAR(255),
    price INT DEFAULT 0,
    sell_price INT DEFAULT 0,
    serial_number VARCHAR(100),
    response TEXT,
    whatsapp_notified TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE SET NULL,
    INDEX idx_ref (ref_id),
    INDEX idx_agent (agent_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: agent_settings
-- Pengaturan sistem agent
CREATE TABLE IF NOT EXISTS agent_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(20) DEFAULT 'string',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by VARCHAR(50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: payment_methods
-- Metode pembayaran untuk transaksi
CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gateway_name VARCHAR(50) NOT NULL,
    method_code VARCHAR(50) NOT NULL,
    method_name VARCHAR(100) NOT NULL,
    method_type VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL DEFAULT '',
    type VARCHAR(50) NOT NULL DEFAULT '',
    display_name VARCHAR(100) NOT NULL DEFAULT '',
    icon VARCHAR(100) DEFAULT NULL,
    icon_url VARCHAR(255) DEFAULT NULL,
    admin_fee_type ENUM('percentage','fixed','flat','percent') DEFAULT 'fixed',
    admin_fee_value DECIMAL(10,2) DEFAULT 0.00,
    min_amount DECIMAL(10,2) DEFAULT 0.00,
    max_amount DECIMAL(12,2) DEFAULT 999999999.99,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    config TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT INTO agent_settings (setting_key, setting_value, setting_type, description) VALUES
('min_topup_amount', '50000', 'number', 'Minimum amount untuk topup saldo'),
('max_topup_amount', '10000000', 'number', 'Maximum amount untuk topup saldo'),
('auto_approve_topup', '0', 'boolean', 'Auto approve topup request'),
('commission_enabled', '1', 'boolean', 'Enable commission system'),
('default_commission_percent', '5', 'number', 'Default commission percentage'),
('agent_registration_enabled', '1', 'boolean', 'Allow agent self registration'),
('min_balance_alert', '10000', 'number', 'Alert when balance below this amount'),
('whatsapp_notification_enabled', '1', 'boolean', 'Send WhatsApp notification to agents'),
('agent_can_set_sell_price', '1', 'boolean', 'Allow agent to set their own sell price'),
('voucher_prefix_agent', 'AG', 'string', 'Prefix for agent generated vouchers'),
('digiflazz_enabled', '0', 'boolean', 'Enable Digiflazz integration'),
('digiflazz_username', '', 'string', 'Digiflazz buyer username'),
('digiflazz_api_key', '', 'string', 'Digiflazz API key'),
('digiflazz_allow_test', '1', 'boolean', 'Allow Digiflazz testing mode'),
('digiflazz_default_markup_percent', '5', 'number', 'Default markup percent for Digiflazz products'),
('digiflazz_last_sync', NULL, 'datetime', 'Last price list sync timestamp')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- Insert default payment methods
INSERT INTO payment_methods (gateway_name, method_code, method_name, method_type, name, type, display_name, icon, admin_fee_type, admin_fee_value, min_amount, max_amount, is_active, sort_order) VALUES
('tripay', 'QRIS', 'QRIS (Semua Bank & E-Wallet)', 'qris', 'QRIS', 'qris', 'QRIS (Semua Bank & E-Wallet)', 'fa-qrcode', 'percentage', 0.00, 10000.00, 5000000.00, 1, 1),
('tripay', 'BRIVA', 'BRI Virtual Account', 'va', 'BRIVA', 'va', 'BRI Virtual Account', 'fa-bank', 'fixed', 4000.00, 10000.00, 5000000.00, 1, 2),
('tripay', 'OVO', 'OVO', 'ewallet', 'OVO', 'ewallet', 'OVO', 'fa-mobile', 'percentage', 2.50, 10000.00, 2000000.00, 1, 7),
('tripay', 'DANA', 'DANA', 'ewallet', 'DANA', 'ewallet', 'DANA', 'fa-mobile', 'percentage', 2.50, 10000.00, 2000000.00, 1, 8)
ON DUPLICATE KEY UPDATE method_name=VALUES(method_name);

-- ========================================
-- VIEWS untuk reporting
-- ========================================

-- View: agent_summary
CREATE OR REPLACE VIEW agent_summary AS
SELECT 
    a.id,
    a.agent_code,
    a.agent_name,
    a.phone,
    a.balance,
    a.status,
    a.level,
    COUNT(DISTINCT av.id) as total_vouchers,
    COUNT(DISTINCT CASE WHEN av.status = 'used' THEN av.id END) as used_vouchers,
    SUM(CASE WHEN at.transaction_type = 'topup' THEN at.amount ELSE 0 END) as total_topup,
    SUM(CASE WHEN at.transaction_type = 'generate' THEN at.amount ELSE 0 END) as total_spent,
    COALESCE(SUM(ac.commission_amount), 0) as total_commission,
    a.created_at,
    a.last_login
FROM agents a
LEFT JOIN agent_vouchers av ON a.id = av.agent_id
LEFT JOIN agent_transactions at ON a.id = at.agent_id
LEFT JOIN agent_commissions ac ON a.id = ac.agent_id AND ac.status = 'paid'
GROUP BY a.id;

-- View: daily_agent_sales
CREATE OR REPLACE VIEW daily_agent_sales AS
SELECT 
    DATE(av.created_at) as sale_date,
    a.agent_code,
    a.agent_name,
    av.profile_name,
    COUNT(*) as voucher_count,
    SUM(av.buy_price) as total_buy_price,
    SUM(av.sell_price) as total_sell_price,
    SUM(av.sell_price - av.buy_price) as total_profit
FROM agent_vouchers av
JOIN agents a ON av.agent_id = a.id
WHERE av.status != 'deleted'
GROUP BY DATE(av.created_at), a.id, av.profile_name;

-- ========================================
-- STORED PROCEDURES
-- ========================================

DELIMITER //

-- Procedure: topup_agent_balance
CREATE PROCEDURE topup_agent_balance(
    IN p_agent_id INT,
    IN p_amount DECIMAL(15,2),
    IN p_description TEXT,
    IN p_created_by VARCHAR(50)
)
BEGIN
    DECLARE v_balance_before DECIMAL(15,2);
    DECLARE v_balance_after DECIMAL(15,2);
    
    -- Get current balance
    SELECT balance INTO v_balance_before FROM agents WHERE id = p_agent_id;
    
    -- Calculate new balance
    SET v_balance_after = v_balance_before + p_amount;
    
    -- Update agent balance
    UPDATE agents SET balance = v_balance_after WHERE id = p_agent_id;
    
    -- Insert transaction record
    INSERT INTO agent_transactions (
        agent_id, transaction_type, amount, 
        balance_before, balance_after, 
        description, created_by
    ) VALUES (
        p_agent_id, 'topup', p_amount,
        v_balance_before, v_balance_after,
        p_description, p_created_by
    );
END //

-- Procedure: deduct_agent_balance
CREATE PROCEDURE deduct_agent_balance(
    IN p_agent_id INT,
    IN p_amount DECIMAL(15,2),
    IN p_profile_name VARCHAR(100),
    IN p_username VARCHAR(100),
    IN p_description TEXT,
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(255)
)
BEGIN
    DECLARE v_balance_before DECIMAL(15,2);
    DECLARE v_balance_after DECIMAL(15,2);
    
    -- Get current balance
    SELECT balance INTO v_balance_before FROM agents WHERE id = p_agent_id;
    
    -- Check if balance sufficient
    IF v_balance_before < p_amount THEN
        SET p_success = FALSE;
        SET p_message = 'Saldo tidak mencukupi';
    ELSE
        -- Calculate new balance
        SET v_balance_after = v_balance_before - p_amount;
        
        -- Update agent balance
        UPDATE agents SET balance = v_balance_after WHERE id = p_agent_id;
        
        -- Insert transaction record
        INSERT INTO agent_transactions (
            agent_id, transaction_type, amount,
            balance_before, balance_after,
            profile_name, voucher_username,
            description
        ) VALUES (
            p_agent_id, 'generate', p_amount,
            v_balance_before, v_balance_after,
            p_profile_name, p_username,
            p_description
        );
        
        SET p_success = TRUE;
        SET p_message = 'Saldo berhasil dipotong';
    END IF;
END //

DELIMITER ;

-- ========================================
-- TRIGGERS
-- ========================================

DELIMITER //

-- Trigger: after agent voucher insert
CREATE TRIGGER after_agent_voucher_insert
AFTER INSERT ON agent_vouchers
FOR EACH ROW
BEGIN
    -- Calculate commission if enabled
    DECLARE v_commission_enabled BOOLEAN;
    DECLARE v_commission_percent DECIMAL(5,2);
    
    SELECT CAST(setting_value AS UNSIGNED) INTO v_commission_enabled
    FROM agent_settings WHERE setting_key = 'commission_enabled';
    
    IF v_commission_enabled THEN
        SELECT commission_percent INTO v_commission_percent
        FROM agents WHERE id = NEW.agent_id;
        
        IF v_commission_percent > 0 AND NEW.sell_price IS NOT NULL THEN
            INSERT INTO agent_commissions (
                agent_id, voucher_id, commission_amount,
                commission_percent, voucher_price
            ) VALUES (
                NEW.agent_id, NEW.id, (NEW.sell_price * v_commission_percent / 100),
                v_commission_percent, NEW.sell_price
            );
        END IF;
    END IF;
END //

DELIMITER ;

-- ========================================
-- INDEXES untuk performance
-- ========================================

-- Additional indexes for better query performance
CREATE INDEX idx_agent_transactions_date ON agent_transactions(created_at, agent_id);
CREATE INDEX idx_agent_vouchers_date ON agent_vouchers(created_at, agent_id);
CREATE INDEX idx_agent_vouchers_profile ON agent_vouchers(profile_name, status);

-- ========================================
-- SAMPLE DATA (Optional - for testing)
-- ========================================

-- Insert sample agent (password: agent123)
INSERT INTO agents (agent_code, agent_name, phone, email, password, balance, status, level, commission_amount, commission_percent, created_by) VALUES
('AG001', 'Agent Demo', '081234567890', 'agent@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 100000.00, 'active', 'silver', 5000.00, 5.00, 'admin');

-- ========================================
-- END OF SQL SCRIPT
-- ========================================
