<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

$curr_user = $udata['phone'];
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-success-subtle shadow-none me-3">
                        <span class="fas fa-history text-success fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-success fs-10 mb-0">Database History</h6>
                        <h4 class="text-primary fw-bold mb-0">Electricity Bill <span class="text-success fw-medium">History Logs</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="Bill_To_find.php" class="btn btn-sm btn-primary shadow-none fw-bold"><i class="fas fa-plus me-1"></i> Search New Bill</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- History Table Card -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light border-bottom py-3">
                <h5 class="mb-0 text-dark font-weight-600"><i class="fas fa-list me-2 text-success"></i>All Downloaded Bills</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light text-dark font-weight-600">
                            <tr>
                                <th>#ID</th>
                                <th>Consumer / CA Number</th>
                                <th>Electricity Provider</th>
                                <th>Charge Deducted</th>
                                <th>Created Date</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Fetch all bill generation history for current user
                            $list_query = mysqli_query($DarkXen_conn, "SELECT * FROM `electric_bill_history` WHERE `userid` = '$curr_user' ORDER BY `id` DESC");
                            
                            if (mysqli_num_rows($list_query) > 0) {
                                while ($row = mysqli_fetch_assoc($list_query)) {
                            ?>
                                <tr>
                                    <td><strong>#<?= $row['id'] ?></strong></td>
                                    <td><span class="badge bg-light text-dark border font-weight-600 fs-11"><?= htmlspecialchars($row['ca_number']) ?></span></td>
                                    <td class="font-weight-500 text-dark text-uppercase"><?= htmlspecialchars($row['provider'] ?? 'N/A') ?></td>
                                    <td><span class="text-danger font-weight-600">₹<?= number_format($row['fee_debited'], 2) ?></span></td>
                                    <td class="text-muted"><?= date("d-m-Y h:i A", strtotime($row['created_at'])) ?></td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <!-- Download Link directly using Base64 -->
                                            <a href="data:application/pdf;base64,<?= $row['pdf_base64'] ?>" download="Electric_Bill_<?= htmlspecialchars($row['ca_number']) ?>.pdf" class="btn btn-danger" title="Download PDF">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                            <!-- Print Button -->
                                            <button onclick="printPdf('<?= $row['pdf_base64'] ?>')" class="btn btn-outline-secondary" title="Print">
                                                <i class="fas fa-print"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php 
                                }
                            } else {
                            ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="far fa-folder-open display-4 mb-3"></i>
                                        <p class="mb-0 fs-5">No electricity bill downloads logged yet.</p>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Print utility
function printPdf(base64Str) {
    const blob = base64ToBlob(base64Str, 'application/pdf');
    const blobUrl = URL.createObjectURL(blob);
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = blobUrl;
    document.body.appendChild(iframe);
    iframe.contentWindow.focus();
    iframe.contentWindow.print();
}

function base64ToBlob(base64, type) {
    const binStr = atob(base64);
    const len = binStr.length;
    const arr = new Uint8Array(len);
    for (let i = 0; i < len; i++) {
        arr[i] = binStr.charCodeAt(i);
    }
    return new Blob([arr], { type: type });
}
</script>

<?php include('footer.php'); ?>