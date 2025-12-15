<?php
/*
 * Agent Class for Agent/Reseller System
 * MikhMon WhatsApp Integration
 */

class Agent {
    private $db;
    private $hasCommissionAmountColumn;
    
    public function __construct() {
        // Ensure db_config is included
        if (!function_exists('getDBConnection')) {
            require_once(__DIR__ . '/../include/db_config.php');
        }
        $this->db = getDBConnection();
        
        if (!$this->db) {
            throw new Exception('Database connection failed');
        }

        $this->hasCommissionAmountColumn = $this->checkTableColumnExists('agents', 'commission_amount');
    }
    
    /**
     * Create new agent
     */
    public function createAgent($data) {
        try {
            $fields = [
                'agent_code' => ':agent_code',
                'agent_name' => ':agent_name',
                'phone' => ':phone',
                'email' => ':email',
                'password' => ':password',
                'balance' => ':balance',
                'status' => ':status',
                'level' => ':level',
                'created_by' => ':created_by',
                'notes' => ':notes'
            ];

            if ($this->hasCommissionAmountColumn) {
                $fields['commission_amount'] = ':commission_amount';
            }
            
            // Add Telegram fields if they exist
            try {
                $stmt = $this->db->query("SHOW COLUMNS FROM agents LIKE 'telegram_chat_id'");
                if ($stmt->rowCount() > 0) {
                    $fields['telegram_chat_id'] = ':telegram_chat_id';
                }
            } catch (Exception $e) {
                // Column doesn't exist, skip
            }
            
            try {
                $stmt = $this->db->query("SHOW COLUMNS FROM agents LIKE 'telegram_username'");
                if ($stmt->rowCount() > 0) {
                    $fields['telegram_username'] = ':telegram_username';
                }
            } catch (Exception $e) {
                // Column doesn't exist, skip
            }

            $columns = implode(', ', array_keys($fields));
            $placeholders = implode(', ', array_values($fields));

            $sql = "INSERT INTO agents ($columns) VALUES ($placeholders)";
            $stmt = $this->db->prepare($sql);

            $params = [
                ':agent_code' => $data['agent_code'],
                ':agent_name' => $data['agent_name'],
                ':phone' => $data['phone'],
                ':email' => $data['email'] ?? null,
                ':password' => password_hash($data['password'], PASSWORD_DEFAULT),
                ':balance' => $data['balance'] ?? 0,
                ':status' => $data['status'] ?? 'active',
                ':level' => $data['level'] ?? 'bronze',
                ':created_by' => $data['created_by'] ?? 'admin',
                ':notes' => $data['notes'] ?? null
            ];

            if ($this->hasCommissionAmountColumn) {
                $params[':commission_amount'] = $data['commission_amount'] ?? 0;
            }
            
            // Add Telegram parameters only if fields exist
            if (isset($fields['telegram_chat_id'])) {
                $params[':telegram_chat_id'] = $data['telegram_chat_id'] ?? null;
            }
            if (isset($fields['telegram_username'])) {
                $params[':telegram_username'] = $data['telegram_username'] ?? null;
            }

            $stmt->execute($params);

            return [
                'success' => true,
                'agent_id' => $this->db->lastInsertId(),
                'message' => 'Agent berhasil dibuat'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get agent by ID
     */
    public function getAgentById($id) {
        $sql = "SELECT * FROM agents WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $agent = $stmt->fetch();

        if ($agent && !$this->hasCommissionAmountColumn) {
            $commissionSetting = $this->getAgentSettings($id, 'billing_commission_amount');
            if ($commissionSetting !== false && $commissionSetting !== null) {
                $agent['commission_amount'] = (float)$commissionSetting;
            } else {
                $agent['commission_amount'] = 0;
            }
        }

        return $agent;
    }
    
    /**
     * Get agent by phone
     */
    public function getAgentByPhone($phone) {
        $sql = "SELECT * FROM agents WHERE phone = :phone";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':phone' => $phone]);
        return $stmt->fetch();
    }
    
    /**
     * Get agent by code
     */
    public function getAgentByCode($code) {
        $sql = "SELECT * FROM agents WHERE agent_code = :code";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':code' => $code]);
        return $stmt->fetch();
    }
    
    /**
     * Get agent by Telegram Chat ID
     */
    public function getAgentByTelegramChatId($chatId) {
        $sql = "SELECT * FROM agents WHERE telegram_chat_id = :chat_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':chat_id' => $chatId]);
        return $stmt->fetch();
    }
    
    
    /**
     * Get all agents
     */
    public function getAllAgents($status = null) {
        $sql = "SELECT * FROM agents";
        if ($status) {
            $sql .= " WHERE status = :status";
        }
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        if ($status) {
            $stmt->execute([':status' => $status]);
        } else {
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }
    
    /**
     * Update agent
     */
    public function updateAgent($id, $data) {
        try {
            $fields = [];
            $params = [':id' => $id];
            
            $commissionAmountPending = null;

            foreach ($data as $key => $value) {
                if ($key != 'id' && $key != 'password') {
                    if ($key === 'commission_amount' && !$this->hasCommissionAmountColumn) {
                        $commissionAmountPending = $value;
                        continue;
                    }
                    $fields[] = "$key = :$key";
                    $params[":$key"] = $value;
                }
            }
            
            if (isset($data['password'])) {
                $fields[] = "password = :password";
                $params[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            $sql = "UPDATE agents SET " . implode(', ', $fields) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            if ($commissionAmountPending !== null) {
                $this->setAgentSetting($id, 'billing_commission_amount', $commissionAmountPending);
            } elseif (isset($data['commission_amount'])) {
                // Keep settings table synced for newer schemas as well
                $this->setAgentSetting($id, 'billing_commission_amount', $data['commission_amount']);
            }

            return ['success' => true, 'message' => 'Agent berhasil diupdate'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Delete agent
     */
    public function deleteAgent($id) {
        try {
            $sql = "DELETE FROM agents WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
            return ['success' => true, 'message' => 'Agent berhasil dihapus'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Topup agent balance
     */
    public function topupBalance($agentId, $amount, $description = '', $createdBy = 'admin') {
        $startedTransaction = false;

        try {
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $startedTransaction = true;
            }
            
            // Get current balance
            $agent = $this->getAgentById($agentId);
            $balanceBefore = $agent['balance'];
            $balanceAfter = $balanceBefore + $amount;
            
            // Update balance
            $sql = "UPDATE agents SET balance = :balance WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':balance' => $balanceAfter, ':id' => $agentId]);
            
            // Insert transaction
            $sql = "INSERT INTO agent_transactions (agent_id, transaction_type, amount, balance_before, balance_after, description, created_by) 
                    VALUES (:agent_id, 'topup', :amount, :balance_before, :balance_after, :description, :created_by)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':agent_id' => $agentId,
                ':amount' => $amount,
                ':balance_before' => $balanceBefore,
                ':balance_after' => $balanceAfter,
                ':description' => $description,
                ':created_by' => $createdBy
            ]);
            
            if ($startedTransaction) {
                $this->db->commit();
            }

            return [
                'success' => true,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'message' => 'Topup berhasil'
            ];
        } catch (PDOException $e) {
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Deduct agent balance
     */
    public function deductBalance($agentId, $amount, $profileName, $username, $description = '', $transactionType = 'generate', $referenceId = null, $createdBy = null) {
        $startedTransaction = false;

        try {
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $startedTransaction = true;
            }
            
            // Get current balance
            $agent = $this->getAgentById($agentId);
            $balanceBefore = $agent['balance'];
            
            // Check if balance sufficient
            if ($balanceBefore < $amount) {
                if ($startedTransaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return [
                    'success' => false,
                    'message' => 'Saldo tidak mencukupi. Saldo Anda: Rp ' . number_format($balanceBefore, 0, ',', '.')
                ];
            }
            
            $balanceAfter = $balanceBefore - $amount;
            
            // Update balance
            $sql = "UPDATE agents SET balance = :balance WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':balance' => $balanceAfter, ':id' => $agentId]);
            
            // Insert transaction
            $sql = "INSERT INTO agent_transactions (agent_id, transaction_type, amount, balance_before, balance_after, profile_name, voucher_username, description, reference_id, created_by) 
                    VALUES (:agent_id, :transaction_type, :amount, :balance_before, :balance_after, :profile_name, :username, :description, :reference_id, :created_by)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':agent_id' => $agentId,
                ':transaction_type' => $transactionType,
                ':amount' => $amount,
                ':balance_before' => $balanceBefore,
                ':balance_after' => $balanceAfter,
                ':profile_name' => $profileName,
                ':username' => $username,
                ':description' => $description,
                ':reference_id' => $referenceId,
                ':created_by' => $createdBy
            ]);
            
            $transactionId = $this->db->lastInsertId();
            
            if ($startedTransaction) {
                $this->db->commit();
            }

            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'message' => 'Saldo berhasil dipotong'
            ];
        } catch (PDOException $e) {
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get agent balance
     */
    public function getBalance($agentId) {
        $agent = $this->getAgentById($agentId);
        return $agent ? $agent['balance'] : 0;
    }
    
    /**
     * Get agent transactions
     */
    public function getTransactions($agentId, $limit = 50) {
        $sql = "SELECT at.*, dt.status AS digiflazz_status, dt.serial_number AS digiflazz_serial,
                       dt.message AS digiflazz_message, dt.customer_no AS digiflazz_customer_no,
                       dt.customer_name AS digiflazz_customer_name, dt.price AS digiflazz_base_price,
                       dt.sell_price AS digiflazz_sell_price,
                       bi.amount AS billing_invoice_amount, bi.status AS billing_invoice_status,
                       bi.period AS billing_invoice_period,
                       bc.name AS billing_customer_name,
                       bp.profile_name AS billing_profile_name
                FROM agent_transactions at
                LEFT JOIN digiflazz_transactions dt ON dt.ref_id = at.voucher_username
                LEFT JOIN billing_invoices bi ON bi.id = at.reference_id
                LEFT JOIN billing_customers bc ON bc.id = bi.customer_id
                LEFT JOIN billing_profiles bp ON bp.id = bc.profile_id
                WHERE at.agent_id = :agent_id
                ORDER BY at.created_at DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':agent_id', $agentId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get Digiflazz transactions only
     */
    public function getDigiflazzTransactions($agentId, $limit = 100) {
        $sql = "SELECT at.*, dt.status AS digiflazz_status, dt.serial_number AS digiflazz_serial,
                       dt.message AS digiflazz_message, dt.customer_no AS digiflazz_customer_no,
                       dt.customer_name AS digiflazz_customer_name, dt.price AS digiflazz_base_price,
                       dt.sell_price AS digiflazz_sell_price
                FROM agent_transactions at
                LEFT JOIN digiflazz_transactions dt ON dt.ref_id = at.voucher_username
                WHERE at.agent_id = :agent_id AND at.transaction_type = 'digiflazz'
                ORDER BY at.created_at DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':agent_id', $agentId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get Digiflazz transactions for admin view with optional filters
     */
    public function getDigiflazzTransactionsAdmin($agentId = null, $statusFilter = null, $limit = 200) {
        $sql = "SELECT at.*, dt.status AS digiflazz_status, dt.serial_number AS digiflazz_serial,
                       dt.message AS digiflazz_message, dt.customer_no AS digiflazz_customer_no,
                       dt.customer_name AS digiflazz_customer_name, dt.price AS digiflazz_base_price,
                       dt.sell_price AS digiflazz_sell_price, dt.buyer_sku_code AS digiflazz_sku,
                       a.agent_name, a.agent_code
                FROM agent_transactions at
                INNER JOIN agents a ON a.id = at.agent_id
                LEFT JOIN digiflazz_transactions dt ON dt.ref_id = at.voucher_username
                WHERE at.transaction_type = 'digiflazz'";

        $params = [];

        if ($agentId) {
            $sql .= " AND at.agent_id = :agent_id";
            $params[':agent_id'] = (int)$agentId;
        }

        if ($statusFilter) {
            $normalized = strtolower($statusFilter);
            $statusMap = [
                'success' => ['success', 'sukses', 'berhasil', 'ok'],
                'pending' => ['pending', 'process', 'processing', 'menunggu'],
                'failed' => ['failed', 'gagal', 'refund', 'refunded', 'cancel', 'canceled', 'error']
            ];

            if (isset($statusMap[$normalized])) {
                $placeholders = [];
                $i = 0;
                foreach ($statusMap[$normalized] as $statusValue) {
                    $paramKey = ':status_' . $i++;
                    $placeholders[] = $paramKey;
                    $params[$paramKey] = $statusValue;
                }
                $sql .= " AND LOWER(COALESCE(dt.status, '')) IN (" . implode(',', $placeholders) . ")";
            } elseif ($normalized === 'empty') {
                $sql .= " AND (dt.status IS NULL OR dt.status = '')";
            }
        }

        $sql .= " ORDER BY at.created_at DESC LIMIT :limit";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Set agent price for profile
     */
    public function setAgentPrice($agentId, $profileName, $buyPrice, $sellPrice) {
        try {
            $sql = "INSERT INTO agent_prices (agent_id, profile_name, buy_price, sell_price) 
                    VALUES (:agent_id, :profile_name, :buy_price, :sell_price)
                    ON DUPLICATE KEY UPDATE buy_price = VALUES(buy_price), sell_price = VALUES(sell_price)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':agent_id' => $agentId,
                ':profile_name' => $profileName,
                ':buy_price' => $buyPrice,
                ':sell_price' => $sellPrice
            ]);
            
            return ['success' => true, 'message' => 'Harga berhasil diset'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get agent price for profile
     */
    public function getAgentPrice($agentId, $profileName) {
        $sql = "SELECT * FROM agent_prices WHERE agent_id = :agent_id AND profile_name = :profile_name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':agent_id' => $agentId, ':profile_name' => $profileName]);
        return $stmt->fetch();
    }
    
    /**
     * Get all agent prices
     */
    public function getAllAgentPrices($agentId) {
        $sql = "SELECT profile_name, buy_price, sell_price FROM agent_prices WHERE agent_id = :agent_id ORDER BY profile_name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':agent_id' => $agentId]);
        $prices = $stmt->fetchAll();
        
        // Calculate profit and rename keys for consistency
        $result = [];
        foreach ($prices as $price) {
            $result[] = [
                'profile_name' => $price['profile_name'],
                'agent_price' => $price['buy_price'],    // Harga beli agent
                'sell_price' => $price['sell_price'],     // Harga jual ke customer
                'profit' => $price['sell_price'] - $price['buy_price']  // Profit agent
            ];
        }
        
        return $result;
    }
    
    /**
     * Delete agent price
     */
    public function deleteAgentPrice($priceId) {
        try {
            $sql = "DELETE FROM agent_prices WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $priceId]);
            return ['success' => true, 'message' => 'Harga berhasil dihapus'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Verify agent login
     */
    public function verifyLogin($phone, $password) {
        $agent = $this->getAgentByPhone($phone);
        
        if (!$agent) {
            return ['success' => false, 'message' => 'Agent tidak ditemukan'];
        }
        
        if ($agent['status'] != 'active') {
            return ['success' => false, 'message' => 'Agent tidak aktif'];
        }
        
        if (password_verify($password, $agent['password'])) {
            // Update last login
            $sql = "UPDATE agents SET last_login = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $agent['id']]);
            
            return [
                'success' => true,
                'agent' => $agent,
                'message' => 'Login berhasil'
            ];
        } else {
            return ['success' => false, 'message' => 'Password salah'];
        }
    }
    
    /**
     * Generate unique agent code
     */
    public function generateAgentCode() {
        do {
            $code = 'AG' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $exists = $this->getAgentByCode($code);
        } while ($exists);
        
        return $code;
    }
    
    /**
     * Get agent settings by agent_id and optional key
     */
    public function getAgentSettings($agentId, $key = null) {
        if ($key) {
            $stmt = $this->db->prepare("SELECT setting_value FROM agent_settings WHERE agent_id = :agent_id AND setting_key = :key");
            $stmt->execute([':agent_id' => $agentId, ':key' => $key]);
            return $stmt->fetchColumn();
        } else {
            $stmt = $this->db->prepare("SELECT setting_key, setting_value FROM agent_settings WHERE agent_id = :agent_id");
            $stmt->execute([':agent_id' => $agentId]);
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            return $settings;
        }
    }

    /**
     * Set agent setting value
     */
    public function setAgentSetting($agentId, $key, $value) {
        try {
            $sql = "INSERT INTO agent_settings (agent_id, setting_key, setting_value)
                    VALUES (:agent_id, :setting_key, :setting_value)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':agent_id' => $agentId,
                ':setting_key' => $key,
                ':setting_value' => $value
            ]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Get agent summary
     */
    public function getAgentSummary($agentId) {
        $sql = "SELECT * FROM agent_summary WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $agentId]);
        return $stmt->fetch();
    }

    /**
     * Check if table column exists
     */
    private function checkTableColumnExists($table, $column) {
        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM `{$table}` LIKE :column");
            $stmt->execute([':column' => $column]);
            return (bool) $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }
}
