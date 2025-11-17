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
            $invoiceId = (int)$_GET['id'];
            $invoice = $service->listInvoices(['id' => $invoiceId], 1);
            if (empty($invoice)) {
                sendJson(['success' => false, 'message' => 'Invoice tidak ditemukan'], 404);
            }
            sendJson(['success' => true, 'data' => $invoice[0]]);
        }

        $filters = [];
        if (!empty($_GET['period'])) {
            $filters['period'] = $_GET['period'];
        }
        if (!empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        if (!empty($_GET['customer_id'])) {
            $filters['customer_id'] = (int)$_GET['customer_id'];
        }

        $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 100;
        $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

        $invoices = $service->listInvoices($filters, $limit, $offset);
        sendJson(['success' => true, 'data' => $invoices]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = getInputData();
        $action = $data['action'] ?? 'create';

        if ($action === 'create') {
            $required = ['customer_id', 'period', 'due_date', 'amount'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    sendJson(['success' => false, 'message' => "Field {$field} wajib diisi"], 400);
                }
            }

            $snapshot = $data['profile_snapshot'] ?? [];
            $invoiceId = $service->generateInvoice(
                (int)$data['customer_id'],
                $data['period'],
                $data['due_date'],
                (float)$data['amount'],
                is_array($snapshot) ? $snapshot : []
            );

            sendJson(['success' => true, 'invoice_id' => $invoiceId]);
        }

        if ($action === 'mark_paid') {
            $invoiceId = isset($data['id']) ? (int)$data['id'] : 0;
            if ($invoiceId <= 0) {
                sendJson(['success' => false, 'message' => 'ID invoice tidak valid'], 400);
            }

            $service->markInvoicePaid($invoiceId, [
                'payment_channel' => $data['payment_channel'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'paid_at' => $data['paid_at'] ?? date('Y-m-d H:i:s'),
            ]);

            if (!empty($data['amount'])) {
                $service->recordPayment($invoiceId, (float)$data['amount'], [
                    'method' => $data['payment_channel'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $data['created_by'] ?? null,
                    'payment_date' => $data['paid_at'] ?? date('Y-m-d H:i:s'),
                ]);
            }

            sendJson(['success' => true]);
        }

        if ($action === 'batch_mark_paid') {
            $invoiceIds = $data['invoice_ids'] ?? [];
            if (empty($invoiceIds) || !is_array($invoiceIds)) {
                sendJson(['success' => false, 'message' => 'Invoice IDs tidak valid'], 400);
            }

            $processed = 0;
            foreach ($invoiceIds as $invoiceId) {
                $id = (int)$invoiceId;
                if ($id <= 0) continue;

                $service->markInvoicePaid($id, [
                    'payment_channel' => $data['payment_channel'] ?? null,
                    'reference_number' => $data['reference_number'] ?? null,
                    'paid_at' => $data['paid_at'] ?? date('Y-m-d H:i:s'),
                ]);

                // Record payment if amount is provided
                $invoice = $service->getInvoiceById($id);
                if ($invoice && !empty($invoice['amount'])) {
                    $service->recordPayment($id, (float)$invoice['amount'], [
                        'method' => $data['payment_channel'] ?? null,
                        'notes' => $data['notes'] ?? null,
                        'created_by' => null,
                        'payment_date' => $data['paid_at'] ?? date('Y-m-d H:i:s'),
                    ]);
                }

                $processed++;
            }

            sendJson(['success' => true, 'processed' => $processed]);
        }

        if ($action === 'update') {
            $invoiceId = isset($data['id']) ? (int)$data['id'] : 0;
            if ($invoiceId <= 0) {
                sendJson(['success' => false, 'message' => 'ID invoice tidak valid'], 400);
            }

            $fields = [];
            if (isset($data['period'])) {
                $fields['period'] = (string)$data['period'];
            }
            if (isset($data['due_date'])) {
                $fields['due_date'] = (string)$data['due_date'];
            }
            if (isset($data['amount'])) {
                $fields['amount'] = (float)$data['amount'];
            }
            if (isset($data['status'])) {
                $fields['status'] = (string)$data['status'];
            }
            if (isset($data['payment_channel'])) {
                $fields['payment_channel'] = $data['payment_channel'] !== '' ? (string)$data['payment_channel'] : null;
            }
            if (isset($data['reference_number'])) {
                $fields['reference_number'] = $data['reference_number'] !== '' ? (string)$data['reference_number'] : null;
            }
            if (isset($data['paid_at'])) {
                $fields['paid_at'] = $data['paid_at'] !== '' ? (string)$data['paid_at'] : null;
            }
            if (isset($data['paid_via'])) {
                $fields['paid_via'] = $data['paid_via'] !== '' ? (string)$data['paid_via'] : null;
            }
            if (isset($data['paid_via_agent_id'])) {
                $fields['paid_via_agent_id'] = $data['paid_via_agent_id'] !== '' ? (int)$data['paid_via_agent_id'] : null;
            }
            if (isset($data['profile_snapshot'])) {
                $snapshot = $data['profile_snapshot'];
                if (is_string($snapshot) && $snapshot !== '') {
                    $decoded = json_decode($snapshot, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        sendJson(['success' => false, 'message' => 'Format profile_snapshot tidak valid (harus JSON)'], 400);
                    }
                    $fields['profile_snapshot'] = $decoded;
                } elseif (is_array($snapshot)) {
                    $fields['profile_snapshot'] = $snapshot;
                } elseif ($snapshot === '' || $snapshot === null) {
                    $fields['profile_snapshot'] = null;
                }
            }

            if (empty($fields)) {
                sendJson(['success' => false, 'message' => 'Tidak ada field yang diperbarui'], 400);
            }

            if (!$service->updateInvoice($invoiceId, $fields)) {
                sendJson(['success' => false, 'message' => 'Gagal memperbarui invoice'], 500);
            }

            sendJson(['success' => true]);
        }

        if ($action === 'delete') {
            $invoiceId = isset($data['id']) ? (int)$data['id'] : 0;
            if ($invoiceId <= 0) {
                sendJson(['success' => false, 'message' => 'ID invoice tidak valid'], 400);
            }

            $invoice = $service->getInvoiceById($invoiceId);
            if (!$invoice) {
                sendJson(['success' => false, 'message' => 'Invoice tidak ditemukan'], 404);
            }

            if (!$service->deleteInvoice($invoiceId)) {
                sendJson(['success' => false, 'message' => 'Gagal menghapus invoice'], 500);
            }

            sendJson(['success' => true]);
        }

        if ($action === 'update_status') {
            $invoiceId = isset($data['id']) ? (int)$data['id'] : 0;
            $status = $data['status'] ?? '';
            if ($invoiceId <= 0 || !in_array($status, ['unpaid', 'paid', 'overdue', 'cancelled', 'draft'], true)) {
                sendJson(['success' => false, 'message' => 'Parameter tidak valid'], 400);
            }

            $service->setInvoiceStatus($invoiceId, $status);
            sendJson(['success' => true]);
        }

        sendJson(['success' => false, 'message' => 'Aksi tidak dikenal'], 400);
    }

    sendJson(['success' => false, 'message' => 'Metode tidak diizinkan'], 405);
} catch (Throwable $e) {
    sendJson(['success' => false, 'message' => $e->getMessage()], 500);
}
