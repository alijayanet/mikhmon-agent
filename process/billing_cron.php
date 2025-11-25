<?php
/**
 * Billing automation cron script.
 *
 * Tasks handled:
 *  - Generate monthly invoices for active customers.
 *  - Send WhatsApp reminders before due date.
 *  - Mark invoices overdue & apply isolation after grace period.
 *  - Restore customer status after payment.
 *
 * Usage:
 *   php process/billing_cron.php
 */

require_once __DIR__ . '/../include/db_config.php';
require_once __DIR__ . '/../lib/BillingService.class.php';
require_once __DIR__ . '/../lib/WhatsAppNotification.class.php';

default_timezone_check();

$service = new BillingService();
$notification = new WhatsAppNotification();

$settings = loadBillingSettings($service);

$now = new DateTime('today');

logMessage('Starting billing cron');

generateMonthlyInvoices($service, $now);
sendReminders($service, $notification, $settings, $now);
handleIsolation($service, $notification, $settings, $now);

logMessage('Billing cron completed');
exit(0);

/* -------------------------------------------------------------------------- */

function default_timezone_check(): void
{
    if (!ini_get('date.timezone')) {
        date_default_timezone_set('Asia/Jakarta');
    }
}

function logMessage(string $message): void
{
    echo '[' . date('Y-m-d H:i:s') . "] {$message}" . PHP_EOL;
}

function loadBillingSettings(BillingService $service): array
{
    $daysBefore = $service->getSetting('billing_reminder_days_before', '3,1');
    $template = $service->getSetting('billing_reminder_template', null);
    $isolationDelay = (int)$service->getSetting('billing_isolation_delay', '1');
    $portalBase = $service->getSetting('billing_portal_base_url', '');

    $parsedDays = array_filter(array_map(static function ($value) {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        return (int)$value;
    }, explode(',', $daysBefore)), static function ($value) {
        return $value !== null && $value >= 0;
    });

    return [
        'reminder_days' => array_values(array_unique($parsedDays)),
        'reminder_template' => $template,
        'isolation_delay' => max(0, $isolationDelay),
        'portal_base_url' => $portalBase,
    ];
}

function computeDueDate(DateTime $reference, int $billingDay): DateTime
{
    $year = (int)$reference->format('Y');
    $month = (int)$reference->format('m');

    $lastDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $day = min(max(1, $billingDay), $lastDay);

    return new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
}

function generateMonthlyInvoices(BillingService $service, DateTime $today): void
{
    $period = $today->format('Y-m');
    $customers = $service->getActiveCustomersWithProfile();

    foreach ($customers as $customer) {
        $existing = $service->getInvoiceByPeriod((int)$customer['id'], $period);
        if ($existing) {
            continue;
        }

        $dueDate = computeDueDate($today, (int)$customer['billing_day']);

        $invoiceId = $service->generateInvoice(
            (int)$customer['id'],
            $period,
            $dueDate->format('Y-m-d'),
            (float)($customer['price_monthly'] ?? 0),
            [
                'profile_name' => $customer['profile_name'] ?? '',
                'price_monthly' => $customer['price_monthly'] ?? 0,
                'mikrotik_profile_normal' => $customer['mikrotik_profile_normal'] ?? '',
                'mikrotik_profile_isolation' => $customer['mikrotik_profile_isolation'] ?? '',
            ]
        );

        $service->logEvent((int)$customer['id'], $invoiceId, 'invoice_generated', [
            'period' => $period,
            'due_date' => $dueDate->format('Y-m-d'),
        ]);

        logMessage("Generated invoice {$invoiceId} for customer {$customer['id']} (period {$period})");
    }
}

function sendReminders(BillingService $service, WhatsAppNotification $notification, array $settings, DateTime $today): void
{
    $allowedDays = $settings['reminder_days'];
    if (empty($allowedDays)) {
        return;
    }

    $invoices = $service->listInvoices([], 1000);
    foreach ($invoices as $invoice) {
        $status = strtolower($invoice['status'] ?? '');
        if (!in_array($status, ['unpaid', 'overdue'], true)) {
            continue;
        }

        $dueDate = new DateTime($invoice['due_date']);
        $diff = $today->diff($dueDate);
        $daysRemaining = (int)$diff->format('%a');
        if ($dueDate < $today) {
            $daysRemaining = -$daysRemaining;
        }

        if ($daysRemaining < 0 || !in_array($daysRemaining, $allowedDays, true)) {
            continue;
        }

        $eventKey = 'reminder_day_' . $daysRemaining;
        if ($service->hasLogEvent((int)$invoice['customer_id'], (int)$invoice['id'], $eventKey)) {
            continue;
        }

        $phone = $invoice['phone'] ?? '';
        if (empty($phone)) {
            continue;
        }

        $payload = [
            'template' => $settings['reminder_template'],
            'customer_name' => $invoice['customer_name'] ?? 'Pelanggan',
            'period' => $invoice['period'] ?? '',
            'due_date' => $dueDate->format('d M Y'),
            'amount_formatted' => 'Rp ' . number_format($invoice['amount'] ?? 0, 0, ',', '.'),
            'service_number' => $invoice['service_number'] ?? '-',
            'status' => ucfirst($status),
            'days_remaining' => $daysRemaining,
            'portal_url' => buildPortalUrl($settings['portal_base_url'], $invoice['service_number'] ?? null),
        ];

        $sent = $notification->notifyBillingReminder($phone, $payload);
        if ($sent) {
            $service->logEvent((int)$invoice['customer_id'], (int)$invoice['id'], $eventKey, [
                'days_remaining' => $daysRemaining,
            ]);
            $service->markInvoiceReminderSent((int)$invoice['id']);
            logMessage("Reminder sent for invoice {$invoice['id']} (days remaining {$daysRemaining})");
        }
    }
}

function handleIsolation(BillingService $service, WhatsAppNotification $notification, array $settings, DateTime $today): void
{
    $isolationDelay = (int)$settings['isolation_delay'];
    $invoices = $service->listInvoices([], 1000);

    foreach ($invoices as $invoice) {
        $status = strtolower($invoice['status'] ?? '');
        if (!in_array($status, ['unpaid', 'overdue'], true)) {
            continue;
        }

        $dueDate = new DateTime($invoice['due_date']);
        if ($today <= $dueDate) {
            continue;
        }

        $daysOverdue = (int)$dueDate->diff($today)->format('%a');

        if ($status === 'unpaid') {
            $service->setInvoiceStatus((int)$invoice['id'], 'overdue');
            $status = 'overdue';
        }

        if ($daysOverdue < $isolationDelay) {
            continue;
        }

        $customer = $service->getCustomerById((int)$invoice['customer_id']);
        if (!$customer || (int)$customer['is_isolated'] === 1) {
            continue;
        }

        $service->updateCustomerIsolation((int)$customer['id'], 1);
        $service->logEvent((int)$customer['id'], (int)$invoice['id'], 'billing_isolation_applied', [
            'days_overdue' => $daysOverdue,
        ]);

        if (!empty($customer['phone'])) {
            $notification->notifyBillingIsolation($customer['phone'], [
                'customer_name' => $customer['name'] ?? 'Pelanggan',
                'period' => $invoice['period'] ?? '-',
                'amount_formatted' => 'Rp ' . number_format($invoice['amount'] ?? 0, 0, ',', '.'),
            ]);
        }

        logMessage("Isolation applied for customer {$customer['id']} (invoice {$invoice['id']})");
    }

    // Restore customers whose latest invoice is paid
    $customers = $service->getActiveCustomersWithProfile();
    foreach ($customers as $customer) {
        if ((int)$customer['is_isolated'] !== 1) {
            continue;
        }

        $latestInvoice = $service->getLatestInvoiceForCustomer((int)$customer['id']);
        if (!$latestInvoice || strtolower($latestInvoice['status'] ?? '') !== 'paid') {
            continue;
        }

        $service->updateCustomerIsolation((int)$customer['id'], 0);
        $service->logEvent((int)$customer['id'], (int)$latestInvoice['id'], 'billing_isolation_released');

        if (!empty($customer['phone'])) {
            $notification->notifyBillingRestored($customer['phone'], [
                'customer_name' => $customer['name'] ?? 'Pelanggan',
                'period' => $latestInvoice['period'] ?? '-',
            ]);
        }

        logMessage("Isolation lifted for customer {$customer['id']}");
    }
}

function buildPortalUrl(string $baseUrl, ?string $serviceNumber): string
{
    if (empty($baseUrl) || empty($serviceNumber)) {
        return '';
    }

    $separator = (parse_url($baseUrl, PHP_URL_QUERY) === null) ? '?' : '&';
    return $baseUrl . $separator . 'service=' . urlencode($serviceNumber);
}
*** End of File
