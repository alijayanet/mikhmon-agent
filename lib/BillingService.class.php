<?php
/**
 * BillingService
 *
 * Helper class for Billing module CRUD, dashboard summary, invoice generation,
 * and integrations (GenieACS, WhatsApp, payment gateway reuse).
 */

class BillingService
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        if (!function_exists('getDBConnection')) {
            require_once(__DIR__ . '/../include/db_config.php');
        }

        $this->db = getDBConnection();
        if (!$this->db) {
            throw new RuntimeException('Database connection failed');
        }
    }

    /* ------------------------------------------------------------------------
     * Dashboard data helpers
     * --------------------------------------------------------------------- */
    public function getDashboardSummary(string $period): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(status='paid') AS paid,
                SUM(status IN ('unpaid','overdue')) AS unpaid,
                COALESCE(SUM(amount),0) AS total_amount,
                COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END),0) AS paid_amount
            FROM billing_invoices
            WHERE period = :period"
        );
        $stmt->execute([':period' => $period]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $customers = $this->db->query(
            "SELECT
                SUM(status='active') AS active_customers,
                SUM(is_isolated=1) AS isolated_customers,
                COUNT(*) AS total_customers
             FROM billing_customers"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'invoices' => array_map('intval', [
                'total' => (int)($summary['total'] ?? 0),
                'paid' => (int)($summary['paid'] ?? 0),
                'unpaid' => (int)($summary['unpaid'] ?? 0),
            ]),
            'amounts' => [
                'total' => (float)($summary['total_amount'] ?? 0),
                'paid' => (float)($summary['paid_amount'] ?? 0),
            ],
            'customers' => [
                'total' => (int)($customers['total_customers'] ?? 0),
                'active' => (int)($customers['active_customers'] ?? 0),
                'isolated' => (int)($customers['isolated_customers'] ?? 0),
            ],
        ];
    }

    public function getUpcomingDueInvoices(int $days = 7): array
    {
        $stmt = $this->db->prepare(
            "SELECT bi.*, bc.name AS customer_name, bc.phone, bp.profile_name
             FROM billing_invoices bi
             INNER JOIN billing_customers bc ON bi.customer_id = bc.id
             LEFT JOIN billing_profiles bp ON bc.profile_id = bp.id
             WHERE bi.status IN ('unpaid','overdue')
               AND bi.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
             ORDER BY bi.due_date ASC
             LIMIT 25"
        );
        $stmt->execute([':days' => $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRevenueTrend(int $months = 6): array
    {
        $stmt = $this->db->prepare(
            "SELECT period,
                    SUM(amount) AS total_amount,
                    SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) AS paid_amount
             FROM billing_invoices
             WHERE period >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL :months MONTH), '%Y-%m')
             GROUP BY period
             ORDER BY period"
        );
        $stmt->execute([':months' => $months - 1]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ------------------------------------------------------------------------
     * Profiles
     * --------------------------------------------------------------------- */
    public function getProfiles(): array
    {
        $stmt = $this->db->query("SELECT * FROM billing_profiles ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActiveCustomersWithProfile(): array
    {
        $stmt = $this->db->query(
            "SELECT bc.*, bp.profile_name, bp.price_monthly, bp.mikrotik_profile_normal, bp.mikrotik_profile_isolation
             FROM billing_customers bc
             INNER JOIN billing_profiles bp ON bc.profile_id = bp.id
             WHERE bc.status = 'active'
             ORDER BY bc.created_at ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProfileById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM billing_profiles WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        return $profile ?: null;
    }

    public function getInvoiceByPeriod(int $customerId, string $period): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM billing_invoices WHERE customer_id = :customer_id AND period = :period LIMIT 1");
        $stmt->execute([
            ':customer_id' => $customerId,
            ':period' => $period,
        ]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        return $invoice ?: null;
    }

    public function createProfile(array $data): int
    {
        $sql = "INSERT INTO billing_profiles
                    (profile_name, price_monthly, speed_label, mikrotik_profile_normal, mikrotik_profile_isolation, description)
                VALUES
                    (:name, :price, :speed, :profile_normal, :profile_isolation, :description)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $data['profile_name'],
            ':price' => $data['price_monthly'],
            ':speed' => $data['speed_label'] ?? null,
            ':profile_normal' => $data['mikrotik_profile_normal'],
            ':profile_isolation' => $data['mikrotik_profile_isolation'],
            ':description' => $data['description'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateProfile(int $id, array $data): bool
    {
        $sql = "UPDATE billing_profiles SET
                    profile_name = :name,
                    price_monthly = :price,
                    speed_label = :speed,
                    mikrotik_profile_normal = :profile_normal,
                    mikrotik_profile_isolation = :profile_isolation,
                    description = :description
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':name' => $data['profile_name'],
            ':price' => $data['price_monthly'],
            ':speed' => $data['speed_label'] ?? null,
            ':profile_normal' => $data['mikrotik_profile_normal'],
            ':profile_isolation' => $data['mikrotik_profile_isolation'],
            ':description' => $data['description'] ?? null,
        ]);
    }

    public function deleteProfile(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM billing_profiles WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /* ------------------------------------------------------------------------
     * Customers
     * --------------------------------------------------------------------- */
    public function getCustomers(int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            "SELECT bc.*, bp.profile_name
             FROM billing_customers bc
             LEFT JOIN billing_profiles bp ON bc.profile_id = bp.id
             ORDER BY bc.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCustomerById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM billing_customers WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        return $customer ?: null;
    }

    public function getCustomerByServiceNumber(string $serviceNumber): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM billing_customers WHERE service_number = :service_number LIMIT 1");
        $stmt->execute([':service_number' => $serviceNumber]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        return $customer ?: null;
    }

    public function getCustomerByPppoeUsername(string $username): ?array
    {
        $username = trim($username);
        if ($username === '') {
            return null;
        }

        $stmt = $this->db->prepare("SELECT * FROM billing_customers WHERE genieacs_pppoe_username = :pppoe LIMIT 1");
        $stmt->execute([':pppoe' => $username]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($customer) {
            return $customer;
        }

        // Coba variasi lower/upper-case jika username disimpan dengan casing berbeda.
        $altUsername = strtolower($username) !== $username ? strtolower($username) : strtoupper($username);
        if ($altUsername !== $username) {
            $stmt->execute([':pppoe' => $altUsername]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $customer ?: null;
    }

    public function getCustomerByPhone(string $phone): ?array
    {
        $phone = trim($phone);
        if ($phone === '') {
            return null;
        }

        $stmt = $this->db->prepare("SELECT * FROM billing_customers WHERE phone = :phone LIMIT 1");
        $stmt->execute([':phone' => $phone]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($customer) {
            return $customer;
        }

        $normalized = preg_replace('/[^0-9]/', '', $phone);
        if ($normalized === '') {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM billing_customers
             WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '.', '') = :normalized
             LIMIT 1"
        );
        $stmt->execute([':normalized' => $normalized]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($customer) {
            return $customer;
        }

        // Coba variasi awalan 0 vs 62.
        if (substr($normalized, 0, 2) === '62') {
            $alt = '0' . substr($normalized, 2);
        } elseif (substr($normalized, 0, 1) === '0') {
            $alt = '62' . substr($normalized, 1);
        } else {
            $alt = null;
        }

        if ($alt) {
            $stmt->execute([':normalized' => $alt]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $customer ?: null;
    }

    public function createCustomer(array $data): int
    {
        $sql = "INSERT INTO billing_customers
                    (profile_id, name, phone, email, address, service_number, genieacs_match_mode, genieacs_pppoe_username, billing_day, status, is_isolated, notes)
                VALUES
                    (:profile_id, :name, :phone, :email, :address, :service_number, :genieacs_match_mode, :genieacs_pppoe_username, :billing_day, :status, :is_isolated, :notes)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':profile_id' => $data['profile_id'],
            ':name' => $data['name'],
            ':phone' => $data['phone'] ?? null,
            ':email' => $data['email'] ?? null,
            ':address' => $data['address'] ?? null,
            ':service_number' => $data['service_number'] ?? null,
            ':genieacs_match_mode' => 'pppoe_username',
            ':genieacs_pppoe_username' => $data['genieacs_pppoe_username'] ?? null,
            ':billing_day' => $data['billing_day'],
            ':status' => $data['status'] ?? 'active',
            ':is_isolated' => $data['is_isolated'] ?? 0,
            ':notes' => $data['notes'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateCustomer(int $id, array $data): bool
    {
        $sql = "UPDATE billing_customers SET
                    profile_id = :profile_id,
                    name = :name,
                    phone = :phone,
                    email = :email,
                    address = :address,
                    service_number = :service_number,
                    genieacs_match_mode = :genieacs_match_mode,
                    genieacs_pppoe_username = :genieacs_pppoe_username,
                    billing_day = :billing_day,
                    status = :status,
                    is_isolated = :is_isolated,
                    notes = :notes
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':profile_id' => $data['profile_id'],
            ':name' => $data['name'],
            ':phone' => $data['phone'] ?? null,
            ':email' => $data['email'] ?? null,
            ':address' => $data['address'] ?? null,
            ':service_number' => $data['service_number'] ?? null,
            ':genieacs_match_mode' => 'pppoe_username',
            ':genieacs_pppoe_username' => $data['genieacs_pppoe_username'] ?? null,
            ':billing_day' => $data['billing_day'],
            ':status' => $data['status'] ?? 'active',
            ':is_isolated' => $data['is_isolated'] ?? 0,
            ':notes' => $data['notes'] ?? null,
        ]);
    }

    public function deleteCustomer(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM billing_customers WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /* ------------------------------------------------------------------------
     * Invoices & Payments
     * --------------------------------------------------------------------- */
    public function listInvoices(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT bi.*, bc.name AS customer_name, bc.phone, bp.profile_name
                FROM billing_invoices bi
                INNER JOIN billing_customers bc ON bi.customer_id = bc.id
                LEFT JOIN billing_profiles bp ON bc.profile_id = bp.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['id'])) {
            $sql .= " AND bi.id = :id";
            $params[':id'] = (int)$filters['id'];
        }
        if (!empty($filters['period'])) {
            $sql .= " AND bi.period = :period";
            $params[':period'] = $filters['period'];
        }
        if (!empty($filters['statuses']) && is_array($filters['statuses'])) {
            $statuses = array_values(array_filter($filters['statuses'], static function ($value) {
                return is_string($value) && $value !== '';
            }));
            if (!empty($statuses)) {
                $placeholders = [];
                foreach ($statuses as $index => $statusValue) {
                    $key = ':status_' . $index;
                    $placeholders[] = $key;
                    $params[$key] = $statusValue;
                }
                $sql .= ' AND bi.status IN (' . implode(',', $placeholders) . ')';
            }
        } elseif (!empty($filters['status'])) {
            $sql .= " AND bi.status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['customer_id'])) {
            $sql .= " AND bi.customer_id = :customer_id";
            $params[':customer_id'] = $filters['customer_id'];
        }

        $sql .= " ORDER BY bi.created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLatestInvoiceForCustomer(int $customerId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM billing_invoices WHERE customer_id = :customer_id ORDER BY due_date DESC, id DESC LIMIT 1");
        $stmt->execute([':customer_id' => $customerId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        return $invoice ?: null;
    }

    public function getInvoiceById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM billing_invoices WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        return $invoice ?: null;
    }

    public function updateInvoice(int $invoiceId, array $fields): bool
    {
        $allowed = [
            'period',
            'due_date',
            'amount',
            'status',
            'payment_channel',
            'reference_number',
            'paid_at',
            'paid_via',
            'paid_via_agent_id',
            'profile_snapshot',
        ];

        $setClauses = [];
        $params = [':id' => $invoiceId];

        foreach ($allowed as $column) {
            if (array_key_exists($column, $fields)) {
                $setClauses[] = "{$column} = :{$column}";
                $params[":{$column}"] = $column === 'profile_snapshot'
                    ? json_encode($fields[$column])
                    : $fields[$column];
            }
        }

        if (empty($setClauses)) {
            return false;
        }

        $sql = 'UPDATE billing_invoices SET ' . implode(', ', $setClauses) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function deleteInvoice(int $invoiceId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM billing_invoices WHERE id = :id');
        return $stmt->execute([':id' => $invoiceId]);
    }

    public function generateInvoice(int $customerId, string $period, string $dueDate, float $amount, array $snapshot = []): int
    {
        $existing = $this->getInvoiceByPeriod($customerId, $period);
        if ($existing) {
            return (int)$existing['id'];
        }

        $stmt = $this->db->prepare(
            "INSERT INTO billing_invoices
                (customer_id, profile_snapshot, period, due_date, amount, status)
             VALUES (:customer_id, :profile_snapshot, :period, :due_date, :amount, 'unpaid')"
        );

        try {
            $stmt->execute([
                ':customer_id' => $customerId,
                ':profile_snapshot' => json_encode($snapshot),
                ':period' => $period,
                ':due_date' => $dueDate,
                ':amount' => $amount,
            ]);
        } catch (PDOException $e) {
            $errorCode = $e->errorInfo[1] ?? null;
            if ((int)$errorCode === 1062) {
                $existing = $this->getInvoiceByPeriod($customerId, $period);
                if ($existing) {
                    return (int)$existing['id'];
                }
            }

            throw $e;
        }

        return (int)$this->db->lastInsertId();
    }

    public function markInvoicePaid(int $invoiceId, array $paymentData = []): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE billing_invoices SET
                status = 'paid',
                paid_at = :paid_at,
                payment_channel = :channel,
                reference_number = :reference,
                paid_via = :paid_via,
                paid_via_agent_id = :paid_via_agent_id
             WHERE id = :id"
        );
        $result = $stmt->execute([
            ':id' => $invoiceId,
            ':paid_at' => $paymentData['paid_at'] ?? date('Y-m-d H:i:s'),
            ':channel' => $paymentData['payment_channel'] ?? null,
            ':reference' => $paymentData['reference_number'] ?? null,
            ':paid_via' => $paymentData['paid_via'] ?? null,
            ':paid_via_agent_id' => $paymentData['paid_via_agent_id'] ?? null,
        ]);

        if (!$result) {
            return false;
        }

        $invoice = $this->getInvoiceById($invoiceId);
        if ($invoice) {
            $this->logEvent((int)$invoice['customer_id'], $invoiceId, 'invoice_paid', [
                'channel' => $paymentData['payment_channel'] ?? null,
                'reference' => $paymentData['reference_number'] ?? null,
                'paid_via' => $paymentData['paid_via'] ?? null,
                'paid_via_agent_id' => $paymentData['paid_via_agent_id'] ?? null,
            ]);
        }

        return true;
    }

    public function recordPayment(int $invoiceId, float $amount, array $meta = []): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO billing_payments (invoice_id, amount, payment_date, method, notes, created_by)
             VALUES (:invoice_id, :amount, :payment_date, :method, :notes, :created_by)"
        );
        $stmt->execute([
            ':invoice_id' => $invoiceId,
            ':amount' => $amount,
            ':payment_date' => $meta['payment_date'] ?? date('Y-m-d H:i:s'),
            ':method' => $meta['method'] ?? null,
            ':notes' => $meta['notes'] ?? null,
            ':created_by' => $meta['created_by'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function payInvoiceWithAgentBalance(int $agentId, int $invoiceId, float $fee = 0.0): array
    {
        $this->db->beginTransaction();

        try {
            // Get the invoice
            $invoice = $this->getInvoiceById($invoiceId);
            if (!$invoice) {
                throw new Exception("Invoice not found");
            }

            // Check if already paid
            if ($invoice['status'] === 'paid') {
                throw new Exception("Invoice already paid");
            }

            $customerId = $invoice['customer_id'];
            $amount = $invoice['amount'];
            $totalAmount = $amount + $fee;

            // Get the agent
            $agent = new Agent();
            $agentData = $agent->getAgentById($agentId);
            if (!$agentData) {
                throw new Exception("Agent not found");
            }

            // Check balance
            if ($agentData['balance'] < $totalAmount) {
                throw new Exception("Agent balance insufficient");
            }

            // Deduct balance
            $deductResult = $agent->deductBalance(
                $agentId,
                $totalAmount,
                'billing_payment',
                $invoiceId,
                "Payment for invoice $invoiceId",
                'billing_payment',
                $invoiceId,
                'system'
            );

            if (!$deductResult['success']) {
                throw new Exception("Failed to deduct agent balance: " . $deductResult['message']);
            }

            // Record the payment in billing_payments
            $paymentId = $this->recordPayment(
                $invoiceId,
                $amount,
                [
                    'method' => 'agent_balance',
                    'notes' => "Paid by agent: " . $agentData['agent_name'],
                    'created_by' => 'system',
                ]
            );

            // Record in agent_billing_payments
            $stmt = $this->db->prepare("
                INSERT INTO agent_billing_payments (agent_id, invoice_id, amount, fee, status, processed_by)
                VALUES (:agent_id, :invoice_id, :amount, :fee, 'paid', 'system')
            ");
            $stmt->execute([
                ':agent_id' => $agentId,
                ':invoice_id' => $invoiceId,
                ':amount' => $amount,
                ':fee' => $fee,
            ]);

            // Mark invoice as paid and set paid_via, paid_via_agent_id
            $this->markInvoicePaid($invoiceId, [
                'payment_channel' => 'agent_balance',
                'reference_number' => 'AG-' . $agentData['agent_code'],
                'paid_via' => 'agent_balance',
                'paid_via_agent_id' => $agentId,
            ]);

            // Update customer isolation status
            $this->updateCustomerIsolation($customerId, 0);

            // Restore customer profile (Mikrotik and PPPoE if applicable)
            $this->restoreCustomerProfile($customerId);

            // Log the event
            $this->logEvent($customerId, $invoiceId, 'invoice_paid_by_agent', [
                'agent_id' => $agentId,
                'amount' => $amount,
                'fee' => $fee,
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'balance_after' => $deductResult['balance_after'],
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function setInvoiceStatus(int $invoiceId, string $status): bool
    {
        $stmt = $this->db->prepare("UPDATE billing_invoices SET status = :status WHERE id = :id");
        return $stmt->execute([
            ':id' => $invoiceId,
            ':status' => $status,
        ]);
    }

    public function markInvoiceReminderSent(int $invoiceId, ?string $timestamp = null): bool
    {
        $stmt = $this->db->prepare("UPDATE billing_invoices SET whatsapp_sent_at = :sent_at WHERE id = :id");
        return $stmt->execute([
            ':id' => $invoiceId,
            ':sent_at' => $timestamp ?? date('Y-m-d H:i:s'),
        ]);
    }

    /* ------------------------------------------------------------------------
     * Settings
     * --------------------------------------------------------------------- */
    public function getSetting(string $key, $default = null)
    {
        $stmt = $this->db->prepare("SELECT setting_value FROM billing_settings WHERE setting_key = :key");
        $stmt->execute([':key' => $key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : $default;
    }

    public function setSetting(string $key, $value): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO billing_settings (setting_key, setting_value)
             VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        return $stmt->execute([
            ':key' => $key,
            ':value' => $value,
        ]);
    }

    /* ------------------------------------------------------------------------
     * Portal customer lookup & OTP lifecycle
     * --------------------------------------------------------------------- */
    public function findCustomerForPortal(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $customer = $this->getCustomerByPhone($identifier);
        if (!$customer) {
            $customer = $this->getCustomerByServiceNumber($identifier);
        }
        if (!$customer) {
            $customer = $this->getCustomerByPppoeUsername($identifier);
        }

        return $customer ?: null;
    }

    public function cleanupExpiredPortalOtps(): void
    {
        $this->db->exec("DELETE FROM billing_portal_otps WHERE expires_at < NOW()");
    }

    public function createPortalOtp(int $customerId, string $identifier, int $digits = 6, int $expiryMinutes = 5, int $maxAttempts = 5): string
    {
        $digits = max(4, min(8, $digits));
        $expiryMinutes = max(1, min(30, $expiryMinutes));
        $maxAttempts = max(1, min(10, $maxAttempts));

        $identifier = trim($identifier);
        $otp = '';
        for ($i = 0; $i < $digits; $i++) {
            $otp .= (string)random_int(0, 9);
        }

        $hash = password_hash($otp, PASSWORD_DEFAULT);
        $expiresAt = (new DateTimeImmutable())->modify("+{$expiryMinutes} minutes")->format('Y-m-d H:i:s');

        $deleteStmt = $this->db->prepare("DELETE FROM billing_portal_otps WHERE customer_id = :customer AND identifier = :identifier");
        $deleteStmt->execute([
            ':customer' => $customerId,
            ':identifier' => $identifier,
        ]);

        $insertStmt = $this->db->prepare(
            "INSERT INTO billing_portal_otps (customer_id, identifier, otp_code, expires_at, max_attempts, sent_via)
             VALUES (:customer, :identifier, :otp_code, :expires_at, :max_attempts, 'whatsapp')"
        );
        $insertStmt->execute([
            ':customer' => $customerId,
            ':identifier' => $identifier,
            ':otp_code' => $hash,
            ':expires_at' => $expiresAt,
            ':max_attempts' => $maxAttempts,
        ]);

        return $otp;
    }

    public function verifyPortalOtp(int $customerId, string $identifier, string $otpInput): array
    {
        $identifier = trim($identifier);
        $otpInput = trim($otpInput);

        $stmt = $this->db->prepare(
            "SELECT * FROM billing_portal_otps WHERE customer_id = :customer AND identifier = :identifier ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([
            ':customer' => $customerId,
            ':identifier' => $identifier,
        ]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            return ['success' => false, 'reason' => 'not_found', 'message' => 'Silakan minta kode OTP baru.'];
        }

        $now = new DateTimeImmutable();
        $expiresAt = new DateTimeImmutable($record['expires_at']);
        if ($expiresAt < $now) {
            $this->clearPortalOtp($customerId, $identifier);
            return ['success' => false, 'reason' => 'expired', 'message' => 'Kode OTP sudah kedaluwarsa. Silakan minta kembali.'];
        }

        if ((int)$record['attempts'] >= (int)$record['max_attempts']) {
            $this->clearPortalOtp($customerId, $identifier);
            return ['success' => false, 'reason' => 'attempts_exceeded', 'message' => 'Kode OTP diblokir karena terlalu banyak percobaan.'];
        }

        if (!password_verify($otpInput, $record['otp_code'])) {
            $update = $this->db->prepare("UPDATE billing_portal_otps SET attempts = attempts + 1 WHERE id = :id");
            $update->execute([':id' => $record['id']]);

            $remaining = max(0, ((int)$record['max_attempts'] - ((int)$record['attempts'] + 1)));
            $message = $remaining > 0
                ? 'Kode OTP salah. Sisa percobaan: ' . $remaining . '.'
                : 'Kode OTP salah. Silakan minta kode baru.';

            if ($remaining <= 0) {
                $this->clearPortalOtp($customerId, $identifier);
            }

            return ['success' => false, 'reason' => 'invalid', 'message' => $message];
        }

        $this->clearPortalOtp($customerId, $identifier);
        return ['success' => true, 'reason' => 'verified'];
    }

    public function clearPortalOtp(int $customerId, string $identifier): void
    {
        $stmt = $this->db->prepare("DELETE FROM billing_portal_otps WHERE customer_id = :customer AND identifier = :identifier");
        $stmt->execute([
            ':customer' => $customerId,
            ':identifier' => trim($identifier),
        ]);
    }

    public function getActivePaymentGateways(): array
    {
        $stmt = $this->db->query("SELECT name, provider, callback_url FROM payment_gateway_config WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateCustomerIsolation(int $customerId, int $flag): bool
    {
        $stmt = $this->db->prepare("UPDATE billing_customers SET is_isolated = :flag WHERE id = :id");
        return $stmt->execute([
            ':id' => $customerId,
            ':flag' => $flag,
        ]);
    }

    public function restoreCustomerProfile(int $customerId): bool
    {
        $customer = $this->getCustomerById($customerId);
        if (!$customer) {
            return false;
        }

        // Update isolation status
        $this->updateCustomerIsolation($customerId, 0);

        // Clear next isolation date
        $stmt = $this->db->prepare("UPDATE billing_customers SET next_isolation_date = NULL WHERE id = :id");
        $stmt->execute([':id' => $customerId]);

        $this->applyNormalProfile($customer);

        $this->logEvent($customerId, null, 'customer_isolation_restored', [
            'reason' => 'invoice_paid_by_agent',
        ]);

        return true;
    }

    public function applyIsolationProfile(array $customer, array $invoice): void
    {
        if (!empty($customer['service_number'])) {
            // update PPP secret profile to isolation profile
            $this->applyIsolationProfileToPpp($customer['service_number'], $customer['profile_id']);
        }

        $this->logEvent((int)$customer['id'], (int)$invoice['id'], 'customer_isolation_applied', [
            'period' => $invoice['period'] ?? '-',
        ]);
    }

    private function applyIsolationProfileToPpp(string $username, $profileId): void
    {
        $profile = $this->getProfileById((int)$profileId);
        if (!$profile) {
            return;
        }

        $isolationProfile = $profile['mikrotik_profile_isolation'] ?? '';
        if ($isolationProfile === '') {
            return;
        }

        try {
            $mikrotik = new MikrotikService($this->resolveRouterSession());
            $success = $mikrotik->setPppProfile($username, $isolationProfile);
            if ($success) {
                $mikrotik->dropActiveSession($username);
            }
        } catch (Throwable $e) {
            error_log('Failed to apply isolation profile: ' . $e->getMessage());
        }
    }

    private function applyNormalProfile(array $customer): void
    {
        if (empty($customer['profile_id']) || empty($customer['service_number'])) {
            return;
        }

        $profile = $this->getProfileById((int)$customer['profile_id']);
        if (!$profile) {
            return;
        }

        $normalProfile = $profile['mikrotik_profile_normal'] ?? '';
        if ($normalProfile === '') {
            return;
        }

        try {
            $mikrotik = new MikrotikService($this->resolveRouterSession());
            $success = $mikrotik->setPppProfile($customer['service_number'], $normalProfile);
            if ($success) {
                $mikrotik->dropActiveSession($customer['service_number']);
            }
        } catch (Throwable $e) {
            error_log('Failed to restore normal profile: ' . $e->getMessage());
        }
    }

    private function resolveRouterSession(): string
    {
        if (!empty($_GET['session'])) {
            return $_GET['session'];
        }

        if (!empty($_SESSION['session'])) {
            return $_SESSION['session'];
        }

        $keys = array_keys($GLOBALS['data'] ?? []);
        foreach ($keys as $key) {
            if ($key !== 'mikhmon') {
                return $key;
            }
        }

        throw new RuntimeException('No MikroTik session configured.');
    }

    // TODO: add restoreCustomerProfile, payInvoiceWithAgentBalance, etc.

    /* ------------------------------------------------------------------------
     * Logs
     * --------------------------------------------------------------------- */
    public function logEvent(?int $customerId, ?int $invoiceId, string $event, array $metadata = []): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO billing_logs (customer_id, invoice_id, event, metadata)
             VALUES (:customer_id, :invoice_id, :event, :metadata)"
        );
        $stmt->execute([
            ':customer_id' => $customerId,
            ':invoice_id' => $invoiceId,
            ':event' => $event,
            ':metadata' => json_encode($metadata),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function hasLogEvent(?int $customerId, ?int $invoiceId, string $event): bool
    {
        $sql = "SELECT COUNT(*) FROM billing_logs WHERE event = :event";
        $params = [':event' => $event];

        if ($customerId !== null) {
            $sql .= " AND customer_id = :customer_id";
            $params[':customer_id'] = $customerId;
        }

        if ($invoiceId !== null) {
            $sql .= " AND invoice_id = :invoice_id";
            $params[':invoice_id'] = $invoiceId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetchColumn();
    }

    /* ------------------------------------------------------------------------
     * GenieACS integration (WiFi SSID/password change)
     * --------------------------------------------------------------------- */
    public function changeCustomerWifi(int $customerId, ?string $ssid = null, ?string $password = null): array
    {
        $customer = $this->getCustomerById($customerId);
        if (!$customer) {
            throw new InvalidArgumentException('Customer not found');
        }

        $ssid = $ssid !== null ? trim($ssid) : null;
        if ($ssid === '') {
            $ssid = null;
        }

        $password = $password !== null ? trim($password) : null;
        if ($password === '') {
            $password = null;
        }

        if ($ssid === null && $password === null) {
            throw new InvalidArgumentException('Tidak ada perubahan WiFi yang dikirim.');
        }

        $genie = $this->createGenieAcsClient();
        $deviceId = $this->resolveGenieAcsDeviceId($genie, $customer);

        return $genie->changeWiFi($deviceId, $ssid, $password);
    }

    public function getCustomerDeviceSnapshot(int $customerId): ?array
    {
        $customer = $this->getCustomerById($customerId);
        if (!$customer) {
            return null;
        }

        try {
            $genie = $this->createGenieAcsClient();
            $deviceId = $this->resolveGenieAcsDeviceId($genie, $customer);
        } catch (Throwable $e) {
            return null;
        }

        $response = $genie->getDevice($deviceId);
        if (!is_array($response) || !($response['success'] ?? false) || empty($response['data'])) {
            return [
                'device_id' => $deviceId,
                'pppoe_username' => $customer['genieacs_pppoe_username'] ?? 'N/A',
                'connected_devices' => null,
                'rx_power' => null,
                'status' => null,
                'temperature' => null,
            ];
        }

        $snapshot = [
            'device_id' => $deviceId,
            'pppoe_username' => $customer['genieacs_pppoe_username'] ?? 'N/A',
            'connected_devices' => null,
                $parsed = GenieACS_Fast::parseDeviceDataFast($response['data']);
                $snapshot['pppoe_username'] = $parsed['pppoe_username'] ?? $snapshot['pppoe_username'];
                $snapshot['connected_devices'] = $parsed['connected_devices_count'] ?? null;
                $snapshot['rx_power'] = $parsed['rx_power'] ?? null;
                $snapshot['status'] = $parsed['status'] ?? null;
                $snapshot['temperature'] = $parsed['temperature'] ?? null;
            } catch (Throwable $e) {
                // Ignore parsing errors and fall back to defaults.
            }
        }

        return $snapshot;
    }

    private function createGenieAcsClient(): GenieACS
    {
        $genieacsPath = __DIR__ . '/../genieacs/lib/GenieACS.class.php';
        if (!file_exists($genieacsPath)) {
            throw new RuntimeException('GenieACS library not found');
        }

        require_once($genieacsPath);
        $genie = new GenieACS();

        if (!$genie->isEnabled()) {
            throw new RuntimeException('GenieACS integration disabled');
        }

        return $genie;
    }

    private function resolveGenieAcsDeviceId(GenieACS $genie, array $customer): string
    {
        $username = trim((string)($customer['genieacs_pppoe_username'] ?? ''));
        if ($username === '') {
            throw new InvalidArgumentException('PPPoE username pelanggan kosong.');
        }

        try {
            return $this->findDeviceIdByPppoeUsername($genie, $username);
        } catch (RuntimeException $pppoeError) {
            $phone = trim((string)($customer['phone'] ?? ''));
            if ($phone === '') {
                throw $pppoeError;
            }

            try {
                return $this->findDeviceIdByPhoneTag($genie, $phone);
            } catch (RuntimeException $phoneError) {
                $serviceNumber = trim((string)($customer['service_number'] ?? ''));
                if ($serviceNumber !== '') {
                    return $serviceNumber;
                }

                throw new RuntimeException(
                    sprintf(
                        'Tidak dapat menemukan perangkat GenieACS untuk pelanggan ini. PPPoE username "%s" dan tag nomor telepon "%s" tidak ditemukan.',
                        $username,
                        $phone
                    ),
                    0,
                    $phoneError
                );
            }
        }
    }

    private function findDeviceIdByPhoneTag(GenieACS $genie, string $phone): string
    {
        $candidates = array_values(array_unique(array_filter([
            $phone,
            preg_replace('/[^0-9+]/', '', $phone),
            preg_replace('/[^0-9]/', '', $phone),
        ])));

        foreach ($candidates as $tag) {
            if ($tag === '') {
                continue;
            }

            $devices = $this->queryGenieAcsDevices($genie, ['_tags' => [$tag]]);
            if (!empty($devices)) {
                $first = $devices[0];
                if (isset($first['_id'])) {
                    return (string)$first['_id'];
                }
            }
        }

        throw new RuntimeException('Perangkat dengan tag nomor telepon tersebut tidak ditemukan di GenieACS.');
    }

    private function findDeviceIdByPppoeUsername(GenieACS $genie, string $username): string
    {
        $devices = $this->queryGenieAcsDevices($genie, [
            'VirtualParameters.pppoeUsername' => $username,
        ]);

        if (!empty($devices)) {
            $first = $devices[0];
            if (isset($first['_id'])) {
                return (string)$first['_id'];
            }
        }

        // Coba variasi username lain (huruf kecil/besar)
        $altUsername = strtolower($username) !== $username ? strtolower($username) : strtoupper($username);
        if ($altUsername !== $username) {
            $altDevices = $this->queryGenieAcsDevices($genie, [
                'VirtualParameters.pppoeUsername' => $altUsername,
            ]);
            if (!empty($altDevices) && isset($altDevices[0]['_id'])) {
                return (string)$altDevices[0]['_id'];
            }
        }

        throw new RuntimeException('Perangkat dengan PPPoE username tersebut tidak ditemukan di GenieACS.');
    }

    private function queryGenieAcsDevices(GenieACS $genie, array $query): array
    {
        $response = $genie->getDevices($query);

        if (!is_array($response) || !($response['success'] ?? false)) {
            $message = $response['message'] ?? 'Tidak dapat terhubung ke GenieACS.';
            throw new RuntimeException($message);
        }

        $devices = $response['data'] ?? [];
        return is_array($devices) ? $devices : [];
    }

    public function getConnection(): PDO
    {
        return $this->db;
    }
}
