<?php
// Cek akses
if (!isset($_SESSION["mikhmon"])) {
    header("Location:../admin.php?id=login");
} else {
    
    $getdeposits = $API->comm("/system/script/print", array(
        "?comment" => "deposit",
    ));
    
?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fa fa-money"></i> Manajemen Deposit</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Username</th>
                                            <th>WhatsApp</th>
                                            <th>Saldo</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Data deposit -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
}
?> 