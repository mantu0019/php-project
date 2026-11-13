<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

$appliedby = $udata['phone'];

// Fetch logged-in user's history
$query = "SELECT * FROM `aadhaar_pan_link_records` WHERE `userid` = '$appliedby' ORDER BY `id` DESC";
$result = mysqli_query($DarkXen_conn, $query);
?>

<!-- DataTables CSS & Responsive Layout Framework -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">


                            <!-- Header Action Panel Component -->
<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-warning-subtle shadow-none me-3">
                        <span class="fas fa-list text-warning fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-warning fs-10 mb-0">PAN Management</h6>
                        <h4 class="text-primary fw-bold mb-0">Aadhaar to PAN Link <span class="text-warning fw-medium">History Logs</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="aadhaar_mask.php" class="btn btn-sm btn-warning shadow-none fw-bold text-dark">
                        <i class="fas fa-plus-circle me-1"></i> Find New PAN Link
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- History Records Data Grid Section -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-light border-bottom py-3">
        <h5 class="mb-0 text-900 fw-bold"><i class="fas fa-history text-secondary me-2"></i>Recent PAN Mask Transactions</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-striped table-hover align-middle mb-0 fs-11">
                <thead class="bg-200 text-900 font-weight-600">
                            <thead class="table-light">
                                
                                <tr>
                                    <th width="5%">S.No</th>
                                    <th width="20%">Aadhaar Reference</th>
                                    <th width="45%">API Registry Message</th>
                                    <th width="15%">Status</th>
                                    <th width="15%">Verification Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($result && mysqli_num_rows($result) > 0) {
                                    $sn = 1;
                                    while ($row = mysqli_fetch_assoc($result)) { 
                                        // Redact or mask Aadhaar for UI compliance
                                        $masked_aadhaar = "XXXXXXXX" . substr($row['aadhaar_number'], -4);
                                        
                                        // Badge styling based on response context
                                        $status_clean = strtolower($row['status']);
                                        if ($status_clean == 'success' || $status_clean == 'true' || $status_clean == '1') {
                                            $badge_class = 'bg-success-subtle text-success border border-success-subtle';
                                            $badge_text = 'Success';
                                        } else {
                                            $badge_class = 'bg-danger-subtle text-danger border border-danger-subtle';
                                            $badge_text = 'Failed';
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo $sn++; ?></td>
                                            <td class="font-weight-500 text-dark"><?php echo htmlspecialchars($masked_aadhaar); ?></td>
                                            <td>
                                                <span class="text-secondary font-weight-500" style="font-size: 13px;">
                                                    <?php echo htmlspecialchars($row['message']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge rounded-pill <?php echo $badge_class; ?> px-2 py-1" style="font-size: 12px;">
                                                    <?php echo $badge_text; ?>
                                                </span>
                                            </td>
                                            <td class="text-muted" style="font-size: 13px;">
                                                <?php echo date('d M Y, h:i A', strtotime($row['date_created'])); ?>
                                            </td>
                                        </tr>
                                    <?php } 
                                } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts for DataTables integration -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#linkStatusTable').DataTable({
        "order": [[ 0, "asc" ]],
        "pageLength": 10,
        "language": {
            "search": "_INPUT_",
            "searchPlaceholder": "Search logs..."
        }
    });
});
</script>

<?php include('footer.php'); ?>