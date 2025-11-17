<?php
header('Content-Type: application/json');

require_once(__DIR__ . '/../include/db_config.php');
require_once(__DIR__ . '/../lib/BillingService.class.php');

function sendJson($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function getInputData(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}

try {
    $service = new BillingService();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['id'])) {
            $customer = $service->getCustomerById((int)$_GET['id']);
            if (!$customer) {
                sendJson(['success' => false, 'message' => 'Pelanggan tidak ditemukan'], 404);
            }
            sendJson(['success' => true, 'data' => $customer]);
        }

        if (isset($_GET['service_number'])) {
            $customer = $service->getCustomerByServiceNumber(trim($_GET['service_number']));
            if (!$customer) {
                sendJson(['success' => false, 'message' => 'Pelanggan tidak ditemukan'], 404);
            }
            sendJson(['success' => true, 'data' => $customer]);
        }

        $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 100;
        $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
        $customers = $service->getCustomers($limit, $offset);
        sendJson(['success' => true, 'data' => $customers]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = getInputData();
        $action = $data['action'] ?? 'create';

        if ($action === 'create') {
            $required = ['profile_id', 'name', 'billing_day'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    sendJson(['success' => false, 'message' => "Field {$field} wajib diisi"], 400);
                }
            }

            $phone = trim((string)($data['phone'] ?? ''));
            if ($phone === '') {
                sendJson(['success' => false, 'message' => 'Nomor telepon pelanggan wajib diisi.'], 400);
            }

            $pppoe = trim((string)($data['genieacs_pppoe_username'] ?? ''));
            if ($pppoe === '') {
                sendJson(['success' => false, 'message' => 'PPPoE username pelanggan wajib diisi.'], 400);
            }
            $data['phone'] = $phone;
            $data['genieacs_pppoe_username'] = $pppoe;

            $id = $service->createCustomer([
                'profile_id' => (int)$data['profile_id'],
                'name' => trim($data['name']),
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'service_number' => $data['service_number'] ?? null,
                'genieacs_pppoe_username' => $data['genieacs_pppoe_username'],
                'billing_day' => (int)$data['billing_day'],
                'auto_isolation' => (int)($data['auto_isolation'] ?? 1),
                'status' => $data['status'] ?? 'active',
                'is_isolated' => (int)($data['is_isolated'] ?? 0),
                'notes' => $data['notes'] ?? null,
            ]);

            sendJson(['success' => true, 'customer_id' => $id]);
        }

        if ($action === 'update') {
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            if ($id <= 0) {
                sendJson(['success' => false, 'message' => 'ID pelanggan tidak valid'], 400);
            }

            $phone = trim((string)($data['phone'] ?? ''));
            if ($phone === '') {
                sendJson(['success' => false, 'message' => 'Nomor telepon pelanggan wajib diisi.'], 400);
            }

            $pppoe = trim((string)($data['genieacs_pppoe_username'] ?? ''));
            if ($pppoe === '') {
                sendJson(['success' => false, 'message' => 'PPPoE username pelanggan wajib diisi.'], 400);
            }
            $data['phone'] = $phone;
            $data['genieacs_pppoe_username'] = $pppoe;

            $service->updateCustomer($id, [
                'profile_id' => (int)($data['profile_id'] ?? 0),
                'name' => trim($data['name'] ?? ''),
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'service_number' => $data['service_number'] ?? null,
                'genieacs_pppoe_username' => $data['genieacs_pppoe_username'],
                'billing_day' => (int)($data['billing_day'] ?? 20),
                'auto_isolation' => (int)($data['auto_isolation'] ?? 1),
                'status' => $data['status'] ?? 'active',
                'is_isolated' => (int)($data['is_isolated'] ?? 0),
                'notes' => $data['notes'] ?? null,
            ]);

            sendJson(['success' => true]);
        }

        if ($action === 'delete') {
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            if ($id <= 0) {
                sendJson(['success' => false, 'message' => 'ID pelanggan tidak valid'], 400);
            }

            $service->deleteCustomer($id);
            sendJson(['success' => true]);
        }

        if ($action === 'set_isolation') {
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            if ($id <= 0) {
                sendJson(['success' => false, 'message' => 'ID pelanggan tidak valid'], 400);
            }

            $flag = isset($data['is_isolated']) ? (int)$data['is_isolated'] : 0;
            $customer = $service->getCustomerById($id);
            if (!$customer) {
                sendJson(['success' => false, 'message' => 'Pelanggan tidak ditemukan'], 404);
            }

            $service->updateCustomer($id, array_merge($customer, ['is_isolated' => $flag]));
            sendJson(['success' => true]);
        }

        sendJson(['success' => false, 'message' => 'Aksi tidak dikenal'], 400);
    }

    sendJson(['success' => false, 'message' => 'Metode tidak diizinkan'], 405);
} catch (Throwable $e) {
    sendJson(['success' => false, 'message' => $e->getMessage()], 500);
}
