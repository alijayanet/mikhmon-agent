<?php
/*
 * Admin Panel - Kelola Harga Agent
 */

// No session_start() needed - already started in index.php
// No auth check needed - already checked in index.php

include_once('./include/db_config.php');
include_once('./include/config.php');
include_once('./lib/Agent.class.php');
include_once('./lib/routeros_api.class.php');

$agent = new Agent();
$agents = $agent->getAllAgents('active');

// Get MikroTik profiles
$sessions = array_keys($data);
$session_name = null;
foreach ($sessions as $s) {
    if ($s != 'mikhmon') {
        $session_name = $s;
        break;
    }
}

$profiles = [];
if ($session_name) {
    $iphost = explode('!', $data[$session_name][1])[1];
    $userhost = explode('@|@', $data[$session_name][2])[1];
    $passwdhost = explode('#|#', $data[$session_name][3])[1];
    
    $API = new RouterosAPI();
    $API->debug = false;
    if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
        $profiles = $API->comm("/ip/hotspot/user/profile/print");
        $API->disconnect();
    }
}

$error = '';
$success = '';

// Handle delete
if (isset($_GET['delete'])) {
    $priceId = $_GET['delete'];
    $result = $agent->deleteAgentPrice($priceId);
    if ($result['success']) {
        $success = 'Harga berhasil dihapus!';
    } else {
        $error = $result['message'];
    }
}

// Handle set/update price (regular POST - non-AJAX)
if (isset($_POST['set_price'])) {
    $action = $_POST['action'] ?? 'create';
    $agentId = $_POST['agent_id'];
    $profileName = $_POST['profile_name'];
    $buyPrice = floatval($_POST['buy_price']);
    $sellPrice = floatval($_POST['sell_price']);
    
    error_log("Processing price action: $action for agent $agentId, profile $profileName");
    
    if ($action === 'update') {
        // For update, we still use setAgentPrice as it handles both create and update
        $result = $agent->setAgentPrice($agentId, $profileName, $buyPrice, $sellPrice);
        
        if ($result['success']) {
            $success = 'Harga berhasil diupdate!';
        } else {
            $error = $result['message'];
        }
    } else {
        // For create
        $result = $agent->setAgentPrice($agentId, $profileName, $buyPrice, $sellPrice);
        
        if ($result['success']) {
            $success = 'Harga berhasil diset!';
        } else {
            $error = $result['message'];
        }
    }
}

// Get session from URL or global
$session = $_GET['session'] ?? (isset($session) ? $session : '');
?>

<style>
/* Minimal custom styles - using MikhMon classes */
.form-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 15px;
    align-items: end;
    margin-bottom: 15px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}

/* Modal styles */
#priceAddModal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 10000 !important;
}

#priceAddModal > div {
    background: white;
    width: 80%;
    max-width: 800px;
    margin: 50px auto;
    padding: 20px;
    border-radius: 10px;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    z-index: 10001 !important;
}

/* Ensure buttons are clickable */
.table .btn {
    pointer-events: auto;
    cursor: pointer;
    position: relative;
    z-index: 10;
}

.table .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
</style>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
    <h3><i class="fa fa-tags"></i> Kelola Harga Agent</h3>
    <div class="btn-group" style="margin-left: auto;">
        <button type="button" class="btn btn-sm btn-primary" onclick="showAddPriceModal()" title="Tambah harga baru">
            <i class="fa fa-plus"></i> Tambah Harga
        </button>
    </div>
</div>
<div class="card-body">
    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= $success; ?></div>
    <?php endif; ?>
</div>
</div>
</div>
</div>

<!-- Modal Tambah Harga -->
<div id="priceAddModal">
    <div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;"><i class="fa fa-plus-circle"></i> Set Harga Baru</h3>
            <button type="button" onclick="hideAddPriceModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="price_id" value="">
            <div class="form-row">
                <div class="form-group">
                    <label>Pilih Agent</label>
                    <select name="agent_id" class="form-control" required>
                        <option value="">-- Pilih Agent --</option>
                        <?php foreach ($agents as $agt): ?>
                        <option value="<?= $agt['id']; ?>"><?= $agt['agent_name']; ?> (<?= $agt['agent_code']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Profile</label>
                    <select name="profile_name" class="form-control" required>
                        <option value="">-- Pilih Profile --</option>
                        <?php foreach ($profiles as $prof): ?>
                        <?php if ($prof['name'] != 'default' && $prof['name'] != 'default-encryption'): ?>
                        <option value="<?= $prof['name']; ?>"><?= $prof['name']; ?></option>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Harga Beli</label>
                    <input type="number" name="buy_price" class="form-control" placeholder="5000" required>
                </div>
                <div class="form-group">
                    <label>Harga Jual</label>
                    <input type="number" name="sell_price" class="form-control" placeholder="7000" required>
                </div>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="hideAddPriceModal()">Batal</button>
                <button type="submit" name="set_price" class="btn btn-primary">
                    <i class="fa fa-save"></i> Simpan Harga
                </button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($agents as $agt): ?>
<?php $agentPrices = $agent->getAllAgentPrices($agt['id']); ?>
<?php if (!empty($agentPrices)): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fa fa-user"></i> <?= htmlspecialchars($agt['agent_name']); ?> (<?= htmlspecialchars($agt['agent_code']); ?>)</h3>
    </div>
    <div class="card-body">
    <div class="table-responsive">
    <table class="table table-bordered table-hover text-nowrap">
        <thead>
            <tr>
                <th>Profile</th>
                <th>Harga Beli</th>
                <th>Harga Jual</th>
                <th>Profit</th>
                <th>Update</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($agentPrices as $price): ?>
            <tr>
                <td><strong><?= $price['profile_name']; ?></strong></td>
                <td>Rp <?= number_format($price['buy_price'], 0, ',', '.'); ?></td>
                <td>Rp <?= number_format($price['sell_price'], 0, ',', '.'); ?></td>
                <td style="color: #10b981; font-weight: bold;">Rp <?= number_format($price['sell_price'] - $price['buy_price'], 0, ',', '.'); ?></td>
                <td><?= date('d M Y', strtotime($price['updated_at'])); ?></td>
                <td>
                    <button onclick="editPrice(<?= $agt['id']; ?>, '<?= $price['profile_name']; ?>', <?= $price['buy_price']; ?>, <?= $price['sell_price']; ?>)" 
                            class="btn btn-sm btn-warning" title="Edit">
                        <i class="fa fa-edit"></i>
                    </button>
                    <a href="?hotspot=agent-prices&delete=<?= $price['id']; ?>&session=<?= $session; ?>" 
                       onclick="return confirm('Yakin ingin menghapus harga untuk profile <?= $price['profile_name']; ?>?')"
                       class="btn btn-sm btn-danger" title="Hapus">
                        <i class="fa fa-trash"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    </div>
</div>
<?php endif; ?>
<?php endforeach; ?>

<script src="./js/billing_forms.js"></script>
<script>
function showAddPriceModal() {
    console.log('showAddPriceModal called');
    
    // Show modal
    var modal = document.getElementById('priceAddModal');
    if (modal) {
        modal.style.display = 'block';
    }
    
    // Clear form
    var agentSelect = document.querySelector('select[name="agent_id"]');
    var profileSelect = document.querySelector('select[name="profile_name"]');
    var buyPriceInput = document.querySelector('input[name="buy_price"]');
    var sellPriceInput = document.querySelector('input[name="sell_price"]');
    var actionInput = document.querySelector('input[name="action"]');
    
    if (agentSelect) agentSelect.value = '';
    if (profileSelect) profileSelect.value = '';
    if (buyPriceInput) buyPriceInput.value = '';
    if (sellPriceInput) sellPriceInput.value = '';
    if (actionInput) actionInput.value = 'create';
    
    // Focus on agent
    if (agentSelect) agentSelect.focus();
    
    // Change button text and style
    var btn = document.querySelector('button[name="set_price"]');
    if (btn) {
        btn.innerHTML = '<i class="fa fa-save"></i> Simpan Harga';
        btn.classList.remove('btn-warning');
        btn.classList.add('btn-primary');
    }
    
    // Change modal title
    var modalTitle = document.querySelector('#priceAddModal h3');
    if (modalTitle) {
        modalTitle.innerHTML = '<i class="fa fa-plus-circle"></i> Set Harga Baru';
    }
}

function hideAddPriceModal() {
    var modal = document.getElementById('priceAddModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function editPrice(agentId, profileName, buyPrice, sellPrice) {
    console.log('editPrice called with:', agentId, profileName, buyPrice, sellPrice);
    
    // Show modal first
    showAddPriceModal();
    
    // Wait a bit for modal to show, then fill form
    setTimeout(function() {
        try {
            // Fill form
            const agentSelect = document.querySelector('select[name="agent_id"]');
            const profileSelect = document.querySelector('select[name="profile_name"]');
            const buyPriceInput = document.querySelector('input[name="buy_price"]');
            const sellPriceInput = document.querySelector('input[name="sell_price"]');
            
            console.log('Form elements found:', {
                agentSelect: !!agentSelect,
                profileSelect: !!profileSelect,
                buyPriceInput: !!buyPriceInput,
                sellPriceInput: !!sellPriceInput
            });
            
            if (agentSelect) agentSelect.value = agentId;
            if (profileSelect) profileSelect.value = profileName;
            if (buyPriceInput) buyPriceInput.value = buyPrice;
            if (sellPriceInput) sellPriceInput.value = sellPrice;
            
            // Focus on buy price
            if (buyPriceInput) buyPriceInput.focus();
            
            // Change button text and style
            const btn = document.querySelector('button[name="set_price"]');
            if (btn) {
                btn.innerHTML = '<i class="fa fa-save"></i> Update Harga';
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-warning');
                console.log('Button updated for edit mode');
            }
            
            // Change form action
            const actionInput = document.querySelector('input[name="action"]');
            if (actionInput) {
                actionInput.value = 'update';
            }
            
            console.log('Edit form populated successfully');
            
        } catch (e) {
            console.error('Error in editPrice:', e);
            alert('Error loading edit form: ' + e.message);
        }
    }, 100);
}

// Add event handlers when document is ready
$(document).ready(function() {
    console.log('Document ready, adding event handlers');
    
    // Alternative event handler for edit buttons
    $(document).on('click', '.btn-warning', function(e) {
        const onclick = $(this).attr('onclick');
        if (onclick && onclick.includes('editPrice')) {
            console.log('Edit button clicked via event handler');
            e.preventDefault();
            e.stopPropagation();
            
            // Extract parameters from onclick attribute
            const match = onclick.match(/editPrice\((\d+),\s*'([^']+)',\s*(\d+(?:\.\d+)?),\s*(\d+(?:\.\d+)?)\)/);
            if (match) {
                const agentId = parseInt(match[1]);
                const profileName = match[2];
                const buyPrice = parseFloat(match[3]);
                const sellPrice = parseFloat(match[4]);
                editPrice(agentId, profileName, buyPrice, sellPrice);
            }
        }
    });
    
    // Add click handler for modal backdrop
    $(document).on('click', '#priceAddModal', function(e) {
        if (e.target === this) {
            hideAddPriceModal();
        }
    });
    
    // Add ESC key handler
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#priceAddModal').is(':visible')) {
            hideAddPriceModal();
        }
    });
});
</script>