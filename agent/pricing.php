<?php
/*
 * Agent Pricing Management
 * Set harga jual voucher untuk public sales
 */

// No session_start() needed - already started in index.php
// No auth check needed - already checked in index.php

include_once('./include/db_config.php');
include_once('./lib/routeros_api.class.php');
include_once('./include/config.php');

// Get agent_id from URL or use default (first agent)
$agent_id = $_GET['agent_id'] ?? null;

if (!$agent_id) {
    // Get first agent as default
    try {
        $conn = getDBConnection();
        $stmt = $conn->query("SELECT id FROM agents WHERE status = 'active' ORDER BY id LIMIT 1");
        $firstAgent = $stmt->fetch(PDO::FETCH_ASSOC);
        $agent_id = $firstAgent['id'] ?? 1;
    } catch (Exception $e) {
        $agent_id = 1;
    }
}

// Get agent code for direct links
$agent_code = 'AG001'; // Default
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT agent_code FROM agents WHERE id = :id AND status = 'active'");
    $stmt->execute([':id' => $agent_id]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($agent) {
        $agent_code = $agent['agent_code'];
    }
} catch (Exception $e) {
    // Keep default
}

// Get MikroTik profiles
$sessions = array_keys($data);
$mikrotik_session = null;
foreach ($sessions as $s) {
    if ($s != 'mikhmon') {
        $mikrotik_session = $s;
        break;
    }
}

$profiles = [];
if ($mikrotik_session) {
    try {
        $iphost = explode('!', $data[$mikrotik_session][1])[1];
        $userhost = explode('@|@', $data[$mikrotik_session][2])[1];
        $passwdhost = explode('#|#', $data[$mikrotik_session][3])[1];
        
        $API = new RouterosAPI();
        $API->debug = false;
        
        if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
            $profilesData = $API->comm("/ip/hotspot/user/profile/print");
            foreach ($profilesData as $profile) {
                if (isset($profile['name'])) {
                    $profiles[] = $profile['name'];
                }
            }
            $API->disconnect();
        }
    } catch (Exception $e) {
        // Handle connection error
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_pricing') {
        $profile_id = $_POST['profile_id'] ?? 0;
        $profile_name = $_POST['profile_name'] ?? ($_POST['profile_name_locked'] ?? '');
        $display_name = $_POST['display_name'];
        $description = $_POST['description'];
        $price = $_POST['price'];
        $original_price = $_POST['original_price'] ?? null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $icon = $_POST['icon'] ?? 'fa-wifi';
        $color = $_POST['color'] ?? 'blue';
        $user_type = isset($_POST['user_type']) && $_POST['user_type'] == 'member' ? 'member' : 'voucher';
        
        try {
            $conn = getDBConnection();
            
            if ($profile_id > 0) {
                // Update
                $sql = "UPDATE agent_profile_pricing SET
                        display_name = :display_name,
                        description = :description,
                        price = :price,
                        original_price = :original_price,
                        is_active = :is_active,
                        is_featured = :is_featured,
                        icon = :icon,
                        color = :color,
                        user_type = :user_type
                        WHERE id = :id AND agent_id = :agent_id";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':display_name' => $display_name,
                    ':description' => $description,
                    ':price' => $price,
                    ':original_price' => $original_price,
                    ':is_active' => $is_active,
                    ':is_featured' => $is_featured,
                    ':icon' => $icon,
                    ':color' => $color,
                    ':user_type' => $user_type,
                    ':id' => $profile_id,
                    ':agent_id' => $agent_id
                ]);
            } else {
                // Insert
                $checkStmt = $conn->prepare("SELECT id FROM agent_profile_pricing WHERE agent_id = :agent_id AND profile_name = :profile_name LIMIT 1");
                $checkStmt->execute([
                    ':agent_id' => $agent_id,
                    ':profile_name' => $profile_name
                ]);

                if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
                    $error_message = "Profil MikroTik tersebut sudah memiliki harga. Gunakan menu edit untuk memperbarui.";
                } else {
                    $sql = "INSERT INTO agent_profile_pricing 
                            (agent_id, profile_name, display_name, description, price, original_price, is_active, is_featured, icon, color, user_type)
                            VALUES (:agent_id, :profile_name, :display_name, :description, :price, :original_price, :is_active, :is_featured, :icon, :color, :user_type)";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        ':agent_id' => $agent_id,
                        ':profile_name' => $profile_name,
                        ':display_name' => $display_name,
                        ':description' => $description,
                        ':price' => $price,
                        ':original_price' => $original_price,
                        ':is_active' => $is_active,
                        ':is_featured' => $is_featured,
                        ':icon' => $icon,
                        ':color' => $color,
                        ':user_type' => $user_type
                    ]);

                    $success_message = "Pricing berhasil disimpan!";
                }
            }
            
            if (!isset($error_message)) {
                $success_message = $success_message ?? "Pricing berhasil disimpan!";
            }
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $profile_id = $_POST['profile_id'];
        
        error_log("Delete Pricing - Profile ID: $profile_id, Agent ID: $agent_id");
        
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("DELETE FROM agent_profile_pricing WHERE id = :id AND agent_id = :agent_id");
            $result = $stmt->execute([':id' => $profile_id, ':agent_id' => $agent_id]);
            
            if ($result && $stmt->rowCount() > 0) {
                $success_message = "Pricing berhasil dihapus!";
                error_log("Delete Pricing - Success: Profile ID $profile_id deleted");
            } else {
                $error_message = "Pricing tidak ditemukan atau tidak dapat dihapus";
                error_log("Delete Pricing - No rows affected for Profile ID $profile_id");
            }
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
            error_log("Delete Pricing - Error: " . $e->getMessage());
        }
    }
}

// Get existing pricing
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM agent_profile_pricing WHERE agent_id = :agent_id ORDER BY sort_order, id");
    $stmt->execute([':agent_id' => $agent_id]);
    $pricings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pricings = [];
}
?>

<style>
.pricing-card {
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    transition: all 0.3s;
}
.pricing-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.pricing-card.featured {
    border-color: #ffc107;
    background-color: #fffbf0;
}
.pricing-card.inactive {
    opacity: 0.6;
    background-color: #f5f5f5;
}

/* Ensure buttons are clickable */
.pricing-card .btn-group {
    position: relative;
    z-index: 10;
}

.pricing-card .btn {
    pointer-events: auto;
    cursor: pointer;
    position: relative;
    z-index: 11;
}

.pricing-card .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

/* Modal styling */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    z-index: 1050;
    width: 100%;
    height: 100%;
    overflow-x: hidden;
    overflow-y: auto;
    outline: 0;
}

.modal.show {
    display: block !important;
}

.modal.fade.show {
    display: block !important;
}

.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    z-index: 1040;
    width: 100vw;
    height: 100vh;
    background-color: #000;
    opacity: 0.5;
}

.modal-dialog {
    position: relative;
    width: auto;
    margin: 1.75rem auto;
    max-width: 600px;
    pointer-events: none;
}

.modal-content {
    position: relative;
    display: flex;
    flex-direction: column;
    width: 100%;
    max-height: calc(100vh - 3.5rem);
    pointer-events: auto;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid rgba(0,0,0,.2);
    border-radius: 0.3rem;
    outline: 0;
}

.modal-body {
    position: relative;
    flex: 1 1 auto;
    padding: 1rem;
    max-height: calc(100vh - 200px);
    overflow-y: auto;
}

.modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 1rem 1rem;
    border-bottom: 1px solid #dee2e6;
    border-top-left-radius: calc(0.3rem - 1px);
    border-top-right-radius: calc(0.3rem - 1px);
}

.modal-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    padding: 0.75rem;
    border-top: 1px solid #dee2e6;
    border-bottom-right-radius: calc(0.3rem - 1px);
    border-bottom-left-radius: calc(0.3rem - 1px);
}

/* Responsive modal */
@media (max-width: 576px) {
    .modal-dialog {
        margin: 0.5rem;
        max-width: none;
    }
    
    .modal-content {
        max-height: calc(100vh - 1rem);
    }
    
    .modal-body {
        max-height: calc(100vh - 150px);
        padding: 0.75rem;
    }
}

/* Consistent styling with other admin pages */
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1.25rem;
    background-color: rgba(0,0,0,.03);
    border-bottom: 1px solid rgba(0,0,0,.125);
}

.card-title {
    margin-bottom: 0;
    font-weight: 600;
    font-size: 1.1rem;
}

.card-tools {
    margin-left: auto;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

/* Alert styling consistency */
.alert {
    border: 0;
    border-radius: 0.25rem;
}

.alert-success {
    background-color: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
}

.alert-danger {
    background-color: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}
</style>

<div class="row">
<div class="col-12">
    
    <?php if (isset($success_message)): ?>
    <div class="alert alert-success alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <i class="fa fa-check-circle"></i> <?= $success_message; ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <i class="fa fa-exclamation-triangle"></i> <?= $error_message; ?>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa fa-tags"></i> Harga Jual Voucher
            </h3>
            <div class="card-tools">
                <button class="btn btn-primary btn-sm" onclick="showAddModal()">
                    <i class="fa fa-plus"></i> Tambah Harga
                </button>
            </div>
        </div>
        <div class="card-body">
            
            <?php if (empty($pricings)): ?>
            <div class="text-center" style="padding: 60px 20px; color: #999;">
                <i class="fa fa-tags" style="font-size: 64px; opacity: 0.3; margin-bottom: 20px;"></i>
                <h3>Belum Ada Harga</h3>
                <p>Klik tombol "Tambah Harga" untuk menambahkan harga jual voucher</p>
            </div>
            <?php else: ?>
            
            <div class="row">
                <?php foreach ($pricings as $pricing): ?>
                <div class="col-md-4">
                    <div class="pricing-card <?= $pricing['is_featured'] ? 'featured' : ''; ?> <?= !$pricing['is_active'] ? 'inactive' : ''; ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h4 style="margin: 0;">
                                    <i class="fa <?= $pricing['icon']; ?>"></i>
                                    <?= htmlspecialchars($pricing['display_name']); ?>
                                </h4>
                                <small class="text-muted"><?= htmlspecialchars($pricing['profile_name']); ?></small>
                            </div>
                            <div>
                                <?php if ($pricing['is_featured']): ?>
                                <span class="badge badge-warning">Featured</span>
                                <?php endif; ?>
                                <?php if (!$pricing['is_active']): ?>
                                <span class="badge badge-secondary">Inactive</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <p style="margin: 10px 0; color: #666;">
                            <?= nl2br(htmlspecialchars($pricing['description'])); ?>
                        </p>
                        
                        <div style="margin: 15px 0;">
                            <?php if ($pricing['original_price']): ?>
                            <span style="text-decoration: line-through; color: #999;">
                                Rp <?= number_format($pricing['original_price'], 0, ',', '.'); ?>
                            </span>
                            <br>
                            <?php endif; ?>
                            <h2 style="margin: 5px 0; color: #28a745;">
                                Rp <?= number_format($pricing['price'], 0, ',', '.'); ?>
                            </h2>
                        </div>
                        
                        <div class="btn-group btn-group-sm" style="width: 100%;">
                            <button class="btn btn-info" onclick="editPricing(<?= $pricing['id']; ?>)">
                                <i class="fa fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-success" onclick="showProfileLink(<?= $pricing['id']; ?>, '<?= htmlspecialchars($pricing['display_name']); ?>')">
                                <i class="fa fa-link"></i> Link
                            </button>
                            <button class="btn btn-danger" onclick="deletePricing(<?= $pricing['id']; ?>)">
                                <i class="fa fa-trash"></i> Hapus
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php endif; ?>
            
        </div>
    </div>
    
</div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="pricingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Tambah Harga Voucher</h5>
                <button type="button" class="close" data-dismiss="modal" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="save_pricing">
                <input type="hidden" name="profile_id" id="profile_id" value="0">
                <input type="hidden" name="profile_name_locked" id="profile_name_locked" value="">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label>Profile MikroTik <span class="text-danger">*</span></label>
                        <select name="profile_name" id="profile_name" class="form-control" required>
                            <option value="">-- Pilih Profile --</option>
                            <?php foreach ($profiles as $prof): ?>
                            <option value="<?= htmlspecialchars($prof); ?>"><?= htmlspecialchars($prof); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Nama Tampilan <span class="text-danger">*</span></label>
                        <input type="text" name="display_name" id="display_name" class="form-control" 
                               placeholder="Contoh: Paket 1 Hari" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="description" id="description" class="form-control" rows="3"
                                  placeholder="Contoh: Speed 5 Mbps&#10;Kuota Unlimited&#10;Berlaku 24 Jam"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Harga Jual <span class="text-danger">*</span></label>
                                <input type="number" name="price" id="price" class="form-control" 
                                       placeholder="10000" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Harga Coret (Optional)</label>
                                <input type="number" name="original_price" id="original_price" class="form-control" 
                                       placeholder="15000">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Icon</label>
                                <select name="icon" id="icon" class="form-control">
                                    <option value="fa-wifi">WiFi</option>
                                    <option value="fa-rocket">Rocket</option>
                                    <option value="fa-bolt">Bolt</option>
                                    <option value="fa-star">Star</option>
                                    <option value="fa-fire">Fire</option>
                                    <option value="fa-clock-o">Clock</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Warna Card</label>
                                <select name="color" id="color" class="form-control">
                                    <option value="blue">Blue</option>
                                    <option value="green">Green</option>
                                    <option value="red">Red</option>
                                    <option value="yellow">Yellow</option>
                                    <option value="aqua">Aqua</option>
                                    <option value="orange">Orange</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" id="is_active" value="1" checked>
                            Aktif (tampilkan di halaman publik)
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_featured" id="is_featured" value="1">
                            Featured (highlight card)
                        </label>
                    </div>
                    
                    <hr>
                    <h6>Tipe User</h6>
                    
                    <div class="form-group">
                        <label style="font-weight: bold; font-size: 14px;">
                            <input type="checkbox" name="user_type" id="user_type" value="member">
                            Mode Member (Customer input username & password sendiri)
                        </label>
                        <div style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-left: 4px solid #3c8dbc; border-radius: 3px; font-size: 13px; line-height: 1.8;">
                            <div style="margin-bottom: 12px;">
                                <strong style="color: #28a745; font-size: 14px;">✓ Tidak dicentang (Default - Voucher):</strong><br>
                                <span style="color: #333;">• Username & password di-generate otomatis</span><br>
                                <span style="color: #333;">• Format mengikuti <a href="./?hotspot=voucher-settings&session=<?= $session; ?>" style="color: #3c8dbc; font-weight: 600;">Pengaturan Format Voucher</a></span><br>
                                <span style="color: #333;">• Cocok untuk paket harian/jam (sekali pakai)</span>
                            </div>
                            <div>
                                <strong style="color: #007bff; font-size: 14px;">✓ Dicentang (Member):</strong><br>
                                <span style="color: #333;">• Customer input username & password sendiri</span><br>
                                <span style="color: #333;">• Cocok untuk paket bulanan/unlimited (langganan)</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const pricings = <?= json_encode($pricings); ?>;

// Initialize modal properly
$(document).ready(function() {
    // Ensure modal is properly initialized
    $('#pricingModal').modal({
        show: false,
        backdrop: true,
        keyboard: true
    });
    
    // Handle close button clicks with debugging
    $('#pricingModal .close').on('click', function(e) {
        e.preventDefault();
        console.log('Close button (X) clicked');
        closeModal();
    });
    
    // Handle data-dismiss buttons
    $('#pricingModal [data-dismiss="modal"]').on('click', function(e) {
        e.preventDefault();
        console.log('Data-dismiss button clicked');
        closeModal();
    });
    
    // Handle backdrop click
    $('#pricingModal').on('click', function(e) {
        if (e.target === this) {
            console.log('Backdrop clicked');
            closeModal();
        }
    });
    
    // Handle ESC key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#pricingModal').hasClass('show')) {
            console.log('ESC key pressed');
            closeModal();
        }
    });
    
    // Alternative event handlers for pricing card buttons
    $(document).on('click', '.btn-info', function(e) {
        const onclick = $(this).attr('onclick');
        if (onclick && onclick.includes('editPricing')) {
            console.log('Edit button clicked via event handler');
            e.preventDefault();
            e.stopPropagation();
            
            // Extract ID from onclick attribute
            const match = onclick.match(/editPricing\((\d+)\)/);
            if (match) {
                const id = parseInt(match[1]);
                editPricing(id);
            }
        }
    });
    
    $(document).on('click', '.btn-success', function(e) {
        const onclick = $(this).attr('onclick');
        if (onclick && onclick.includes('showProfileLink')) {
            console.log('Link button clicked via event handler');
            e.preventDefault();
            e.stopPropagation();
            
            // Extract parameters from onclick attribute
            const match = onclick.match(/showProfileLink\((\d+),\s*'([^']+)'\)/);
            if (match) {
                const id = parseInt(match[1]);
                const name = match[2];
                showProfileLink(id, name);
            }
        }
    });
    
    $(document).on('click', '.btn-danger', function(e) {
        const onclick = $(this).attr('onclick');
        if (onclick && onclick.includes('deletePricing')) {
            console.log('Delete button clicked via event handler');
            e.preventDefault();
            e.stopPropagation();
            
            // Extract ID from onclick attribute
            const match = onclick.match(/deletePricing\((\d+)\)/);
            if (match) {
                const id = parseInt(match[1]);
                deletePricing(id);
            }
        }
    });
});

// Function to close modal
function closeModal() {
    console.log('closeModal function called');
    
    try {
        // Try Bootstrap modal hide method
        $('#pricingModal').modal('hide');
        console.log('Bootstrap modal hide called');
        
        // Wait a bit and check if it worked
        setTimeout(function() {
            if ($('#pricingModal').hasClass('show') || $('#pricingModal').is(':visible')) {
                console.log('Bootstrap modal hide failed, using fallback');
                manualCloseModal();
            } else {
                console.log('Modal closed successfully');
            }
        }, 300);
        
    } catch (e) {
        console.error('Error with Bootstrap modal hide:', e);
        manualCloseModal();
    }
}

// Manual close modal fallback
function manualCloseModal() {
    console.log('Manual close modal');
    $('#pricingModal').removeClass('show').removeClass('fade').css('display', 'none');
    $('body').removeClass('modal-open');
    $('.modal-backdrop').remove();
    
    // Reset modal classes
    setTimeout(function() {
        $('#pricingModal').addClass('fade');
    }, 100);
}

function showAddModal() {
    // Debug check
    console.log('showAddModal called');
    console.log('jQuery available:', typeof $ !== 'undefined');
    console.log('Modal element exists:', $('#pricingModal').length > 0);
    
    // Reset form
    $('#modalTitle').text('Tambah Harga Voucher');
    $('#profile_id').val('0');
    $('#profile_name').val('').prop('disabled', false);
    $('#profile_name_locked').val('');
    $('#display_name').val('');
    $('#description').val('');
    $('#price').val('');
    $('#original_price').val('');
    $('#icon').val('fa-wifi');
    $('#color').val('blue');
    $('#is_active').prop('checked', true);
    $('#is_featured').prop('checked', false);
    $('#user_type').prop('checked', false);
    
    // Try multiple methods to show modal
    try {
        $('#pricingModal').modal('show');
        console.log('Modal show method called');
    } catch (e) {
        console.error('Error showing modal:', e);
        // Fallback: show modal manually
        $('#pricingModal').addClass('show').css('display', 'block');
        $('body').addClass('modal-open');
        if ($('.modal-backdrop').length === 0) {
            $('body').append('<div class="modal-backdrop fade show"></div>');
        }
    }
}

function editPricing(id) {
    console.log('editPricing called with id:', id);
    console.log('Available pricings:', pricings);
    
    const pricing = pricings.find(p => p.id == id);
    if (!pricing) {
        console.error('Pricing not found for id:', id);
        alert('Data pricing tidak ditemukan!');
        return;
    }
    
    console.log('Found pricing:', pricing);
    
    $('#modalTitle').text('Edit Harga Voucher');
    $('#profile_id').val(pricing.id);
    $('#profile_name').val(pricing.profile_name).prop('disabled', true);
    $('#profile_name_locked').val(pricing.profile_name);
    $('#display_name').val(pricing.display_name);
    $('#description').val(pricing.description);
    $('#price').val(pricing.price);
    $('#original_price').val(pricing.original_price || '');
    $('#icon').val(pricing.icon);
    $('#color').val(pricing.color);
    $('#is_active').prop('checked', pricing.is_active == 1);
    $('#is_featured').prop('checked', pricing.is_featured == 1);
    $('#user_type').prop('checked', pricing.user_type == 'member');
    
    // Show modal with debugging
    try {
        $('#pricingModal').modal('show');
        console.log('Edit modal shown');
    } catch (e) {
        console.error('Error showing edit modal:', e);
        // Fallback
        $('#pricingModal').addClass('show').css('display', 'block');
        $('body').addClass('modal-open');
        if ($('.modal-backdrop').length === 0) {
            $('body').append('<div class="modal-backdrop fade show"></div>');
        }
    }
}

function deletePricing(id) {
    if (!confirm('Yakin ingin menghapus pricing ini?')) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="profile_id" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function showProfileLink(profileId, profileName) {
    console.log('showProfileLink called with:', profileId, profileName);
    
    // Get agent code from current agent selection or use default
    const agentCode = '<?= $agent_code ?? "AG001"; ?>';
    const baseUrl = window.location.protocol + '//' + window.location.host;
    
    console.log('Agent code:', agentCode);
    console.log('Base URL:', baseUrl);
    
    // URLs untuk halaman publik dengan payment gateway (sesuai server produksi)
    const publicOrderLink = baseUrl + '/public/order.php?agent=' + agentCode + '&profile=' + profileId;
    const directOrderLink = baseUrl + '/public/order.php?agent=' + agentCode + '&profile=' + profileId;
    
    // Short links
    const shortOrderLink = '/public/order.php?agent=' + agentCode + '&profile=' + profileId;
    
    // Alternative links untuk berbagai kebutuhan
    const hotspotLink = baseUrl + '/?agent=' + agentCode + '&profile=' + profileId;
    
    // QR Code untuk link utama
    const qrCode = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(publicOrderLink);
    
    // Create modal content
    const modalContent = `
        <div class="modal fade" id="profileLinkModal" tabindex="-1" style="z-index: 9999;">
            <div class="modal-dialog modal-lg" style="margin: 30px auto;">
                <div class="modal-content" style="box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fa fa-link"></i> Direct Link - ${profileName}
                        </h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <h6><i class="fa fa-info-circle"></i> Link Publik dengan Payment Gateway</h6>
                            <p>Customer bisa langsung order dan bayar menggunakan payment gateway yang sudah dikonfigurasi.</p>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fa fa-globe"></i> Public Order URL (Production Format)</label>
                            <div class="input-group">
                                <input type="text" class="form-control" value="${publicOrderLink}" readonly style="font-family: monospace; font-size: 12px;">
                                <div class="input-group-append">
                                    <button class="btn btn-info" onclick="copyToClipboard('${publicOrderLink}', this)">
                                        <i class="fa fa-copy"></i> Copy
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted">Format: /public/order.php?agent=AG001&profile=7 (sesuai server produksi)</small>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fa fa-link"></i> Short URL (Internal)</label>
                            <div class="input-group">
                                <input type="text" class="form-control" value="${shortOrderLink}" readonly style="font-family: monospace; font-size: 12px;">
                                <div class="input-group-append">
                                    <button class="btn btn-info" onclick="copyToClipboard('${shortOrderLink}', this)">
                                        <i class="fa fa-copy"></i> Copy
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted">URL pendek untuk penggunaan internal</small>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fa fa-wifi"></i> Hotspot Integration URL</label>
                            <div class="input-group">
                                <input type="text" class="form-control" value="${hotspotLink}" readonly style="font-family: monospace; font-size: 12px;">
                                <div class="input-group-append">
                                    <button class="btn btn-info" onclick="copyToClipboard('${hotspotLink}', this)">
                                        <i class="fa fa-copy"></i> Copy
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center" style="padding: 20px; background: #f8f9fa; border-radius: 4px; margin: 20px 0;">
                            <label><i class="fa fa-qrcode"></i> QR Code</label><br>
                            <img src="${qrCode}" alt="QR Code" style="max-width: 200px; border: 2px solid #ddd; border-radius: 4px;">
                            <br><small class="text-muted">Customer bisa scan untuk akses langsung</small>
                        </div>
                        
                        <style>
                        #profileLinkModal {
                            z-index: 9999 !important;
                            display: none;
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            overflow-x: hidden;
                            overflow-y: auto;
                            outline: 0;
                        }
                        
                        #profileLinkModal.show {
                            display: block !important;
                        }
                        
                        #profileLinkModal .modal-backdrop {
                            z-index: 9998 !important;
                        }
                        
                        #profileLinkModal .modal-dialog {
                            margin: 30px auto !important;
                            max-width: 90% !important;
                            position: relative;
                            width: auto;
                            pointer-events: none;
                        }
                        
                        #profileLinkModal .modal-content {
                            position: relative;
                            display: flex;
                            flex-direction: column;
                            width: 100%;
                            pointer-events: auto;
                            background-color: #fff;
                            background-clip: padding-box;
                            border: 1px solid rgba(0,0,0,.2);
                            border-radius: 0.3rem;
                            outline: 0;
                            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                        }
                        
                        @media (min-width: 576px) {
                            #profileLinkModal .modal-dialog {
                                max-width: 700px !important;
                            }
                        }
                        </style>
                        
                        <div class="alert alert-success">
                            <h6><i class="fa fa-info-circle"></i> Cara Penggunaan (Format Server Produksi):</h6>
                            <ul style="margin-bottom: 0;">
                                <li>Copy <strong>Public Order URL</strong> dengan format: <code>/public/order.php?agent=AG001&profile=7</code></li>
                                <li>Customer bisa pilih metode pembayaran: QRIS, Virtual Account, E-Wallet, dll</li>
                                <li><strong>QR Code</strong> bisa di-print dan ditempel di area hotspot</li>
                                <li>Setelah pembayaran berhasil, voucher otomatis dikirim ke customer</li>
                                <li>Contoh di login page: <code>&lt;a href="${publicOrderLink}" target="_blank"&gt;Beli ${profileName}&lt;/a&gt;</code></li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-warning">
                            <h6><i class="fa fa-exclamation-triangle"></i> Pastikan Payment Gateway Aktif:</h6>
                            <p style="margin-bottom: 0;">
                                Sebelum menggunakan link ini, pastikan minimal satu payment gateway (Tripay/Xendit/Midtrans) 
                                sudah dikonfigurasi dan aktif di <strong>Payment Gateway Config</strong>.
                            </p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <a href="${publicOrderLink}" target="_blank" class="btn btn-primary">
                            <i class="fa fa-external-link"></i> Test Order Page
                        </a>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('profileLinkModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to page
    document.body.insertAdjacentHTML('beforeend', modalContent);
    
    console.log('Modal content added to page');
    console.log('Modal element exists:', $('#profileLinkModal').length > 0);
    
    // Show modal with debugging
    try {
        // Check if Bootstrap modal is available
        if (typeof $.fn.modal !== 'undefined') {
            $('#profileLinkModal').modal('show');
            console.log('Profile link modal shown with Bootstrap');
        } else {
            console.log('Bootstrap modal not available, using manual method');
            manualShowProfileLinkModal();
        }
    } catch (e) {
        console.error('Error showing profile link modal:', e);
        console.log('Using fallback manual method');
        manualShowProfileLinkModal();
    }
    
    // Ensure modal and backdrop have high z-index
    setTimeout(function() {
        $('.modal-backdrop').css('z-index', '9998');
        $('#profileLinkModal').css('z-index', '9999');
        console.log('Z-index applied to profile link modal');
        
        // Add event handlers for close buttons (manual method)
        $('#profileLinkModal .close, #profileLinkModal [data-dismiss="modal"]').on('click', function() {
            console.log('Profile link modal close button clicked');
            closeProfileLinkModal();
        });
        
        // Handle backdrop click
        $('#profileLinkModal').on('click', function(e) {
            if (e.target === this) {
                console.log('Profile link modal backdrop clicked');
                closeProfileLinkModal();
            }
        });
        
        // Handle ESC key for profile link modal
        $(document).on('keydown.profileLinkModal', function(e) {
            if (e.key === 'Escape' && $('#profileLinkModal').hasClass('show')) {
                console.log('ESC key pressed for profile link modal');
                closeProfileLinkModal();
            }
        });
        
    }, 100);
}

// Manual show function for profile link modal
function manualShowProfileLinkModal() {
    console.log('Manual show profile link modal');
    
    // Show modal
    $('#profileLinkModal').addClass('show').css('display', 'block');
    $('body').addClass('modal-open');
    
    // Add backdrop if not exists
    if ($('.modal-backdrop').length === 0) {
        $('body').append('<div class="modal-backdrop fade show" style="z-index: 9998;"></div>');
    }
    
    console.log('Profile link modal shown manually');
}

// Manual close function for profile link modal
function closeProfileLinkModal() {
    console.log('closeProfileLinkModal called');
    
    try {
        // Try Bootstrap method first
        if (typeof $.fn.modal !== 'undefined') {
            $('#profileLinkModal').modal('hide');
            console.log('Bootstrap modal hide used');
            setTimeout(function() {
                $('#profileLinkModal').remove();
                $(document).off('keydown.profileLinkModal');
            }, 300);
        } else {
            // Manual method
            console.log('Using manual close method');
            manualCloseProfileLinkModal();
        }
    } catch (e) {
        console.error('Error in closeProfileLinkModal:', e);
        manualCloseProfileLinkModal();
    }
}

// Manual close method for profile link modal
function manualCloseProfileLinkModal() {
    console.log('Manual close profile link modal');
    
    // Hide modal with fade effect
    $('#profileLinkModal').removeClass('show').addClass('fade');
    
    // Remove after animation
    setTimeout(function() {
        $('#profileLinkModal').remove();
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
        $(document).off('keydown.profileLinkModal');
        console.log('Profile link modal removed from DOM');
    }, 300);
}

function copyToClipboard(text, button) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fa fa-check"></i> Copied!';
            button.className = button.className.replace('btn-info', 'btn-success');
            
            setTimeout(function() {
                button.innerHTML = originalText;
                button.className = button.className.replace('btn-success', 'btn-info');
            }, 2000);
        }).catch(function() {
            alert('Failed to copy to clipboard');
        });
    } else {
        // Fallback
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        
        try {
            document.execCommand('copy');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fa fa-check"></i> Copied!';
            button.className = button.className.replace('btn-info', 'btn-success');
            
            setTimeout(function() {
                button.innerHTML = originalText;
                button.className = button.className.replace('btn-success', 'btn-info');
            }, 2000);
        } catch (err) {
            alert('Failed to copy to clipboard');
        }
        
        document.body.removeChild(textArea);
    }
}
</script>

