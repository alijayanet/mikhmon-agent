<?php
if (!defined('DIGIFLAZZ_PRINT_COMPONENT')) {
    define('DIGIFLAZZ_PRINT_COMPONENT', true);

    $digiflazzPrintAgentName = $agentData['agent_name'] ?? ($_SESSION['agent_name'] ?? 'Agent');
    $digiflazzPrintAgentCode = $agentData['agent_code'] ?? ($_SESSION['agent_code'] ?? '');
    ?>
    <style>
    .print-btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 6px 10px;
        border-radius: 6px;
        border: none;
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        color: #fff;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .print-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 12px rgba(37, 99, 235, 0.25);
        color: #fff;
    }

    .print-btn i {
        font-size: 12px;
    }

    .print-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(15, 23, 42, 0.55);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 3000;
        padding: 20px;
    }

    .print-modal {
        background: #fff;
        border-radius: 12px;
        max-width: 460px;
        width: 100%;
        padding: 24px;
        position: relative;
        box-shadow: 0 18px 45px rgba(15, 23, 42, 0.25);
        color: #0f172a;
    }

    .print-modal h4 {
        margin-bottom: 16px;
        font-weight: 700;
        color: inherit;
    }

    .print-close {
        position: absolute;
        top: 14px;
        right: 16px;
        border: none;
        background: none;
        font-size: 22px;
        color: #64748b;
        cursor: pointer;
    }

    .print-summary {
        background: #f8fafc;
        border-radius: 10px;
        padding: 16px;
        margin-bottom: 18px;
        color: inherit;
    }

    .print-summary dl {
        display: grid;
        grid-template-columns: 120px 1fr;
        row-gap: 6px;
        column-gap: 10px;
        margin: 0;
    }

    .print-summary dt {
        font-size: 12px;
        color: #475569;
        font-weight: 600;
        text-transform: uppercase;
    }

    .print-summary dd {
        margin: 0;
        font-size: 13px;
        font-weight: 600;
        color: inherit;
    }

    .print-modal .form-group {
        margin-bottom: 14px;
    }

    .print-modal label {
        font-size: 12px;
        color: #475569;
        font-weight: 600;
        text-transform: uppercase;
        display: block;
        margin-bottom: 6px;
    }

    .print-modal input.form-control,
    .print-modal textarea.form-control {
        border-radius: 8px;
        border: 1px solid #cbd5f5;
        padding: 10px 12px;
        font-size: 14px;
        font-weight: 600;
        color: #0f172a;
        background: #ffffff;
        box-shadow: none;
    }

    .print-modal textarea.form-control {
        font-size: 13px;
        min-height: 70px;
        resize: vertical;
    }

    .print-modal input.form-control::placeholder,
    .print-modal textarea.form-control::placeholder {
        color: #94a3b8;
        font-weight: 500;
    }

    .print-modal .print-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 18px;
    }

    .print-modal .btn-secondary,
    .print-modal .btn-success,
    .print-modal .btn-default {
        border: none;
        border-radius: 8px;
        padding: 9px 14px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }

    .print-modal .btn-secondary {
        background: #475569;
        color: #fff;
    }

    .print-modal .btn-success {
        background: #0f9d58;
        color: #fff;
    }

    .print-modal .btn-default {
        background: transparent;
        color: #ef4444;
    }

    .dark .print-modal {
        background: #111827;
        color: #e2e8f0;
    }

    .dark .print-close {
        color: #cbd5f5;
    }

    .dark .print-summary {
        background: rgba(15,23,42,0.75);
    }

    .dark .print-summary dt {
        color: #cbd5f5;
    }

    .dark .print-modal label {
        color: #cbd5f5;
    }

    .dark .print-modal input.form-control,
    .dark .print-modal textarea.form-control {
        background: #1f2937;
        color: #f8fafc;
        border-color: #334155;
    }

    .dark .print-modal input.form-control::placeholder,
    .dark .print-modal textarea.form-control::placeholder {
        color: #94a3b8;
    }
    </style>

    <div class="print-modal-overlay" id="digiflazzPrintOverlay">
        <div class="print-modal" id="digiflazzPrintModal">
            <button class="print-close" id="digiflazzPrintClose" aria-label="Tutup">&times;</button>
            <h4><i class="fa fa-print"></i> Print Struk Digiflazz</h4>
            <div class="print-summary">
                <dl>
                    <dt>Ref ID</dt><dd id="printSummaryRef">-</dd>
                    <dt>Produk</dt><dd id="printSummaryProduct">-</dd>
                    <dt>Nomor Tujuan</dt><dd id="printSummaryCustomer">-</dd>
                    <dt>Status</dt><dd><span id="printSummaryStatus" class="badge-status status-pending">PENDING</span></dd>
                    <dt>Harga Jual</dt><dd id="printSummaryPrice">Rp 0</dd>
                    <dt>Serial Number</dt><dd id="printSummarySerial">-</dd>
                </dl>
                <div id="printSummaryMessage" style="margin-top:10px; font-size:12px; color:#475569; display:none;"></div>
            </div>
            <form>
                <div class="form-group">
                    <label>Harga Jual (editable)</label>
                    <input type="text" class="form-control" id="printSellPriceInput" autocomplete="off" inputmode="numeric" placeholder="Contoh: 55000">
                </div>
                <div class="form-group">
                    <label>Catatan Tambahan (opsional)</label>
                    <textarea class="form-control" id="printNotesInput" placeholder="Contoh: Terima kasih sudah berbelanja"></textarea>
                </div>
                <div class="print-actions">
                    <button type="button" class="btn-secondary" id="printNormalBtn"><i class="fa fa-print"></i> Print Normal</button>
                    <button type="button" class="btn-success" id="printThermalBtn"><i class="fa fa-print"></i> Thermal 58mm</button>
                    <button type="button" class="btn-default" id="printCancelBtn">Tutup</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function() {
        if (window.__digiflazzPrintInitialized) {
            return;
        }
        window.__digiflazzPrintInitialized = true;

        const agentName = <?= json_encode($digiflazzPrintAgentName); ?>;
        const agentCode = <?= json_encode($digiflazzPrintAgentCode); ?>;

        let overlay;
        let modal;
        let closeBtn;
        let cancelBtn;
        let normalBtn;
        let thermalBtn;
        let sellPriceInput;
        let notesInput;
        let summaryRef;
        let summaryProduct;
        let summaryCustomer;
        let summaryStatus;
        let summaryPrice;
        let summarySerial;
        let summaryMessage;
        const failureStatuses = ['failed','fail','gagal','refund','refunded','cancel','cancelled','canceled','expired','error'];

        const payloadStore = {};

        function formatCurrency(value) {
            const number = Number(value || 0);
            return new Intl.NumberFormat('id-ID').format(number);
        }

        function parseCurrencyString(value) {
            if (!value) return 0;
            const sanitized = String(value).replace(/[^0-9]/g, '');
            return sanitized ? parseInt(sanitized, 10) : 0;
        }

        function getPayloadFromDataset(dataset) {
            return {
                ref: dataset.ref || '-',
                product: dataset.product || dataset.description || '-',
                description: dataset.description || '-',
                customerNo: dataset.customerNo || '-',
                customerName: dataset.customerName || '',
                serial: dataset.serial || '',
                statusLabel: dataset.status || 'PENDING',
                statusClass: dataset.statusClass || 'status-pending',
                message: dataset.message || '',
                sellPrice: parseInt(dataset.sellPrice || '0', 10) || 0,
                basePrice: parseInt(dataset.basePrice || '0', 10) || 0,
                createdAt: dataset.createdAt || new Date().toISOString()
            };
        }

        function updatePriceSummary(payload, sell) {
            summaryPrice.textContent = 'Rp ' + formatCurrency(sell);
        }

        function openModal(payload) {
            const sellValue = parseCurrencyString(sellPriceInput.value) || payload.sellPrice;

            summaryRef.textContent = payload.ref;
            summaryProduct.textContent = payload.product || '-';
            summaryCustomer.textContent = payload.customerNo || '-';
            summarySerial.textContent = payload.serial || '-';

            summaryStatus.textContent = payload.statusLabel || 'PENDING';
            summaryStatus.className = 'badge-status ' + (payload.statusClass || 'status-pending');

            if (payload.message) {
                summaryMessage.style.display = 'block';
                summaryMessage.textContent = payload.message;
            } else {
                summaryMessage.style.display = 'none';
                summaryMessage.textContent = '';
            }

            sellPriceInput.value = formatCurrency(payload.sellPrice);
            notesInput.value = '';
            updatePriceSummary(payload, payload.sellPrice);

            overlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';

            payloadStore.current = payload;
        }

        function closeModal() {
            overlay.style.display = 'none';
            document.body.style.overflow = '';
            payloadStore.current = null;
        }

        function buildReceiptHTML(payload, sellPrice, notes, options) {
            const createdAt = new Date(payload.createdAt);
            const formattedDate = createdAt.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
            const formattedTime = createdAt.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            const noteBlock = notes ? `<div class="note">${notes}</div>` : '';
            const messageBlock = payload.message ? `<div class="note">${payload.message}</div>` : '';

            if (options.thermal) {
                return `<!DOCTYPE html>
<html>
<head>
    <title>Struk Digiflazz ${payload.ref}</title>
    <style>
        @page { size: 58mm auto; margin: 3mm; }
        body { font-family: 'Courier New', monospace; width: 58mm; margin: 0; padding: 0; font-size: 11px; }
        .wrapper { padding: 6px; text-align: center; }
        .title { font-size: 14px; font-weight: bold; margin-bottom: 6px; }
        .sub { font-size: 10px; margin-bottom: 8px; }
        .separator { border-top: 1px dashed #000; margin: 6px 0; }
        .section { text-align: left; }
        .row { display: flex; justify-content: space-between; margin-bottom: 4px; }
        .label { font-weight: bold; text-align: left; }
        .value { text-align: right; word-break: break-all; max-width: 34mm; }
        .status { font-weight: bold; margin: 6px 0; text-transform: uppercase; }
        .note { margin-top: 6px; font-size: 10px; text-align: left; }
        .footer { margin-top: 10px; font-size: 10px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="title">STRUK DIGIFLAZZ</div>
        <div class="sub">${agentName || 'Agent'} ${agentCode ? '(' + agentCode + ')' : ''}</div>
        <div class="separator"></div>
        <div class="section">
            <div class="row"><span class="label">Tanggal</span><span class="value">${formattedDate} ${formattedTime}</span></div>
            <div class="row"><span class="label">Ref ID</span><span class="value">${payload.ref}</span></div>
            <div class="row"><span class="label">Produk</span><span class="value">${payload.product || '-'}</span></div>
            <div class="row"><span class="label">Nomor</span><span class="value">${payload.customerNo || '-'}</span></div>
            <div class="row"><span class="label">Harga</span><span class="value">Rp ${formatCurrency(sellPrice)}</span></div>
            ${payload.serial ? `<div class="row"><span class="label">Serial</span><span class="value">${payload.serial}</span></div>` : ''}
        </div>
        <div class="separator"></div>
        <div class="status">Status: ${payload.statusLabel}</div>
        ${messageBlock}
        ${noteBlock}
        <div class="separator"></div>
        <div class="footer">Terima kasih telah bertransaksi.</div>
    </div>
</body>
</html>`;
            }

            return `<!DOCTYPE html>
<html>
<head>
    <title>Struk Digiflazz ${payload.ref}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; padding: 24px; color: #0f172a; }
        h1 { margin: 0 0 12px; font-size: 22px; }
        .meta { margin-bottom: 20px; }
        .meta div { margin-bottom: 4px; font-size: 13px; }
        .bold { font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        td { padding: 8px 6px; border: 1px solid #e2e8f0; font-size: 13px; word-break: break-word; }
        .note { background: #f8fafc; padding: 12px; border-radius: 8px; font-size: 12px; margin-bottom: 10px; }
        .footer { margin-top: 30px; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <h1>Struk Transaksi Digiflazz</h1>
    <div class="meta">
        <div><span class="bold">Agent:</span> ${agentName || 'Agent'} ${agentCode ? '(' + agentCode + ')' : ''}</div>
        <div><span class="bold">Tanggal:</span> ${formattedDate} ${formattedTime}</div>
        <div><span class="bold">Ref ID:</span> ${payload.ref}</div>
    </div>
    <table>
        <tr><td>Produk</td><td>${payload.product || '-'}</td></tr>
        <tr><td>Nomor Tujuan</td><td>${payload.customerNo || '-'}</td></tr>
        <tr><td>Status</td><td>${payload.statusLabel}</td></tr>
        <tr><td>Harga</td><td>Rp ${formatCurrency(sellPrice)}</td></tr>
        ${payload.serial ? `<tr><td>Serial Number</td><td class="sn-value">${payload.serial}</td></tr>` : ''}
    </table>
    ${messageBlock}
    ${noteBlock}
    <div class="footer">Dicetak otomatis dari sistem MikhMon Agent.</div>
</body>
</html>`;
        }

        function handlePrint(options) {
            if (!payloadStore.current) {
                return;
            }
            const sellValue = parseCurrencyString(sellPriceInput.value) || payloadStore.current.sellPrice;
            const notes = notesInput.value.trim();
            const html = buildReceiptHTML(payloadStore.current, sellValue, notes, options);
            const printWindow = window.open('', '_blank');
            if (!printWindow) {
                alert('Popup diblokir oleh browser. Harap izinkan popup untuk mencetak.');
                return;
            }
            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
        }

        function init() {
            overlay = document.getElementById('digiflazzPrintOverlay');
            if (!overlay) {
                return;
            }
            modal = document.getElementById('digiflazzPrintModal');
            closeBtn = document.getElementById('digiflazzPrintClose');
            cancelBtn = document.getElementById('printCancelBtn');
            normalBtn = document.getElementById('printNormalBtn');
            thermalBtn = document.getElementById('printThermalBtn');
            sellPriceInput = document.getElementById('printSellPriceInput');
            notesInput = document.getElementById('printNotesInput');
            summaryRef = document.getElementById('printSummaryRef');
            summaryProduct = document.getElementById('printSummaryProduct');
            summaryCustomer = document.getElementById('printSummaryCustomer');
            summaryStatus = document.getElementById('printSummaryStatus');
            summaryPrice = document.getElementById('printSummaryPrice');
            summarySerial = document.getElementById('printSummarySerial');
            summaryMessage = document.getElementById('printSummaryMessage');

            overlay.addEventListener('click', function(event) {
                if (event.target === overlay) {
                    closeModal();
                }
            });

            [closeBtn, cancelBtn].forEach(function(btn) {
                if (btn) {
                    btn.addEventListener('click', closeModal);
                }
            });

            if (sellPriceInput) {
                sellPriceInput.addEventListener('blur', function() {
                    const value = parseCurrencyString(sellPriceInput.value);
                    const payload = payloadStore.current;
                    if (!payload) {
                        return;
                    }
                    if (value > 0) {
                        sellPriceInput.value = formatCurrency(value);
                        updatePriceSummary(payload, value);
                    } else {
                        sellPriceInput.value = formatCurrency(payload.sellPrice);
                        updatePriceSummary(payload, payload.sellPrice);
                    }
                });

                sellPriceInput.addEventListener('input', function() {
                    const payload = payloadStore.current;
                    if (!payload) {
                        return;
                    }
                    const value = parseCurrencyString(sellPriceInput.value);
                    updatePriceSummary(payload, value || payload.sellPrice);
                });
            }

            if (normalBtn) {
                normalBtn.addEventListener('click', function() {
                    handlePrint({ thermal: false });
                });
            }

            if (thermalBtn) {
                thermalBtn.addEventListener('click', function() {
                    handlePrint({ thermal: true });
                });
            }

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && overlay.style.display === 'flex') {
                    closeModal();
                }
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }

        window.triggerDigiflazzPrint = function(refId) {
            if (!overlay) {
                init();
            }
            const selector = refId ? `[data-digiflazz-print][data-ref="${CSS.escape(refId)}"]` : '[data-digiflazz-print]';
            const target = document.querySelector(selector);
            if (!target) {
                return false;
            }
            const payload = getPayloadFromDataset(target.dataset);
            if (!overlay) {
                alert('Komponen cetak belum siap. Muat ulang halaman.');
                return false;
            }
            openModal(payload);
            return true;
        };

        document.addEventListener('click', function(event) {
            const button = event.target.closest('[data-digiflazz-print]');
            if (!button) {
                return;
            }
            event.preventDefault();
            if (!overlay) {
                init();
            }
            if (!overlay) {
                alert('Komponen cetak belum siap. Muat ulang halaman.');
                return;
            }
            const payload = getPayloadFromDataset(button.dataset);
            openModal(payload);
        });
    })();
    </script>
<?php
}
?>
