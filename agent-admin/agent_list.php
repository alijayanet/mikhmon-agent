<?php
/*
 * Admin Panel - Daftar Agent
 * Integrated with MikhMon Sidebar
 */

// No session_start() needed - already started in index.php
// No auth check needed - already checked in index.php

include_once('./include/db_config.php');
include_once('./lib/Agent.class.php');

$agent = new Agent();
$agents = $agent->getAllAgents();

// Handle actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $agentId = $_GET['agent_id'] ?? 0;
    
    if ($action == 'activate' && $agentId) {
        $agent->updateAgent($agentId, ['status' => 'active']);
        echo "<script>alert('Agent berhasil diaktifkan'); window.location='?hotspot=agent-list&session=$session';</script>";
        exit;
    } elseif ($action == 'deactivate' && $agentId) {
        $agent->updateAgent($agentId, ['status' => 'inactive']);
        echo "<script>alert('Agent berhasil dinonaktifkan'); window.location='?hotspot=agent-list&session=$session';</script>";
        exit;
    } elseif ($action == 'delete' && $agentId) {
        // Confirmation already handled in onclick event
        $result = $agent->deleteAgent($agentId);
        if ($result['success']) {
            echo "<script>alert('Agent berhasil dihapus'); window.location='?hotspot=agent-list&session=$session';</script>";
        } else {
            echo "<script>alert('Gagal menghapus agent: " . addslashes($result['message']) . "'); window.location='?hotspot=agent-list&session=$session';</script>";
        }
        exit;
    }
}

function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Get session from URL or global
$session = $_GET['session'] ?? (isset($session) ? $session : '');
?>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
    <h3><i class="fa fa-users"></i> Daftar Agent/Reseller
        <span style="font-size: 14px">
            &nbsp; | &nbsp; <a href="./?hotspot=agent-add&session=<?= $session; ?>" title="Tambah Agent"><i class="fa fa-user-plus"></i> Tambah Agent</a>
        </span>
    </h3>
</div>
<div class="card-body">
    <!-- Statistics - MikhMon Style -->
    <div class="row mb-3">
        <div class="col-3 col-box-6">
            <div class="box bg-blue bmh-75">
                <h1><?= count($agents); ?>
                    <span style="font-size: 15px;">agents</span>
                </h1>
                <div>
                    <i class="fa fa-users"></i> Total Agent
                </div>
            </div>
        </div>
        <div class="col-3 col-box-6">
            <div class="box bg-green bmh-75">
                <h1><?= count(array_filter($agents, function($a) { return $a['status'] == 'active'; })); ?>
                    <span style="font-size: 15px;">active</span>
                </h1>
                <div>
                    <i class="fa fa-check-circle"></i> Agent Aktif
                </div>
            </div>
        </div>
        <div class="col-3 col-box-6">
            <div class="box bg-red bmh-75">
                <h1 style="font-size: 18px;"><?= formatRupiah(array_sum(array_column($agents, 'balance'))); ?></h1>
                <div>
                    <i class="fa fa-money"></i> Total Saldo
                </div>
            </div>
        </div>
        <div class="col-3 col-box-6">
            <div class="box bg-yellow bmh-75">
                <h1><?= count(array_filter($agents, function($a) { 
                    return date('Y-m', strtotime($a['created_at'])) == date('Y-m'); 
                })); ?>
                    <span style="font-size: 15px;">new</span>
                </h1>
                <div>
                    <i class="fa fa-calendar"></i> Agent Baru
                </div>
            </div>
        </div>
    </div>

    <div class="row pd-t-10">
        <div class="col-12">
            <div class="input-group">
                <div class="input-group-4 col-box-4">
                    <input id="filterTable" type="text" style="padding:5.8px;" class="group-item group-item-l" placeholder="Cari agent...">
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($agents)): ?>
    <div class="row pd-t-10">
        <div class="col-12 text-center" style="padding: 60px 20px; color: #999;">
            <i class="fa fa-users" style="font-size: 64px; opacity: 0.3; margin-bottom: 20px;"></i>
            <h3>Belum Ada Agent</h3>
            <p>Klik tombol "Tambah Agent" untuk menambahkan agent baru</p>
        </div>
    </div>
    <?php else: ?>
    <!-- Mobile Scroll Hint -->
    <div class="d-block d-md-none" style="background: #fff3cd; padding: 8px 12px; margin-bottom: 10px; border-radius: 3px; font-size: 12px; color: #856404;">
        <i class="fa fa-hand-o-right"></i> Geser tabel ke kanan untuk melihat semua kolom
    </div>
    
    <div class="table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <table id="dataTable" class="table table-bordered table-hover text-nowrap" style="min-width: 800px;">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama</th>
                    <th>No. WhatsApp</th>
                    <th>Level</th>
                    <th>Saldo</th>
                    <th>Komisi</th>
                    <th>Status</th>
                    <th>Terdaftar</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($agents as $agt): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($agt['agent_code']); ?></strong></td>
                    <td><?= htmlspecialchars($agt['agent_name']); ?></td>
                    <td><?= htmlspecialchars($agt['phone']); ?></td>
                    <td>
                        <span class="badge badge-<?= $agt['level']; ?>" style="padding: 4px 8px; border-radius: 3px;">
                            <?= ucfirst($agt['level']); ?>
                        </span>
                    </td>
                    <td><strong><?= formatRupiah($agt['balance']); ?></strong></td>
                    <td><?= $agt['commission_percent']; ?>%</td>
                    <td>
                        <span class="badge badge-<?= $agt['status']; ?>" style="padding: 4px 8px; border-radius: 3px;">
                            <?= ucfirst($agt['status']); ?>
                        </span>
                    </td>
                    <td><?= date('d M Y', strtotime($agt['created_at'])); ?></td>
                    <td>
                        <a href="./?hotspot=agent-edit&agent_id=<?= $agt['id']; ?>&session=<?= $session; ?>" 
                           class="btn btn-sm btn-warning" title="Edit">
                            <i class="fa fa-edit"></i>
                        </a>
                        <a href="./?hotspot=agent-topup&agent_id=<?= $agt['id']; ?>&session=<?= $session; ?>" 
                           class="btn btn-sm btn-success" title="Topup">
                            <i class="fa fa-money"></i>
                        </a>
                        <?php if ($agt['status'] == 'active'): ?>
                        <a href="?hotspot=agent-list&action=deactivate&agent_id=<?= $agt['id']; ?>&session=<?= $session; ?>" 
                           class="btn btn-sm btn-danger" title="Nonaktifkan"
                           onclick="return confirm('Nonaktifkan agent ini?')">
                            <i class="fa fa-ban"></i>
                        </a>
                        <?php else: ?>
                        <a href="?hotspot=agent-list&action=activate&agent_id=<?= $agt['id']; ?>&session=<?= $session; ?>" 
                           class="btn btn-sm btn-success" title="Aktifkan"
                           onclick="return confirm('Aktifkan agent ini?')">
                            <i class="fa fa-check"></i>
                        </a>
                        <?php endif; ?>
                        <a href="?hotspot=agent-list&action=delete&agent_id=<?= $agt['id']; ?>&session=<?= $session; ?>" 
                           class="btn btn-sm btn-danger" title="Hapus"
                           onclick="return confirm('⚠️ PERINGATAN!\n\nApakah Anda yakin ingin menghapus agent ini?\n\nAgent: <?= htmlspecialchars($agt['agent_name']); ?>\nKode: <?= htmlspecialchars($agt['agent_code']); ?>\n\nData yang akan dihapus:\n- Semua transaksi agent\n- Semua pricing agent\n- Semua penjualan public\n\nTindakan ini TIDAK DAPAT DIBATALKAN!')">
                            <i class="fa fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
</div>
</div>
</div>

<style>
/* Mobile Table Scroll */
@media (max-width: 768px) {
    .table-responsive {
        border: 1px solid #ddd;
        border-radius: 3px;
        box-shadow: inset 0 0 5px rgba(0,0,0,0.05);
    }
    
    .table-responsive table {
        margin-bottom: 0;
    }
    
    /* Sticky first column on mobile */
    .table-responsive th:first-child,
    .table-responsive td:first-child {
        position: sticky;
        left: 0;
        background: white;
        z-index: 1;
        box-shadow: 2px 0 3px rgba(0,0,0,0.1);
    }
    
    .table-responsive thead th:first-child {
        z-index: 2;
    }
}
</style>

<script>
$(document).ready(function() {
    // Use MikhMon's built-in filterTable function
    $("#filterTable").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#dataTable tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
});
</script>
