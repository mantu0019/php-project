<?php
// Core System Files Integration
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

// Security Sanitization Function (If not loaded globally)
if (!function_exists('clean')) {
    function clean($DarkXen_conn, $data) {
        return mysqli_real_escape_string($DarkXen_conn, trim($data));
    }
}

// Session se active user ka uniquely mapped configuration identification
$current_user = clean($DarkXen_conn, $udata['username'] ?? $udata['phone'] ?? '');

// Pagination Framework Configuration
$limit = 20; // Ek page par max 20 records dikhenge
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// User session records pull karne ke liye query framework
$query_string = "SELECT `id`, `application_no`, `username`, `aadhaar_no`, `pan_no`, `date`, `order_id`, `full_pan` 
                 FROM `pan_find` 
                 WHERE `username` = '$current_user' 
                 ORDER BY `id` DESC 
                 LIMIT $offset, $limit";
$result = mysqli_query($DarkXen_conn, $query_string);

// Total counts pagination dots generate karne ke liye
$total_query = mysqli_query($DarkXen_conn, "SELECT COUNT(*) as total FROM `pan_find` WHERE `username` = '$current_user'");
$total_rows = mysqli_fetch_assoc($total_query)['total'] ?? 0;
$total_pages = ceil($total_rows / $limit);
?>

<!-- Header Action Panel Component -->
<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-file-invoice text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">PAN Management</h6>
                        <h4 class="text-primary fw-bold mb-0">PAN Allocation <span class="text-warning fw-medium">Ledger Records</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="mobile_otp_pan.php" class="btn btn-sm btn-primary shadow-none fw-bold text-white">
                        <i class="fas fa-plus-circle me-1"></i> New Request
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- History Records Data Grid Section -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-light border-bottom py-3">
        <h5 class="mb-0 text-900 fw-bold"><i class="fas fa-history text-secondary me-2"></i>Recent PAN Find Transactions</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-striped table-hover align-middle mb-0 fs-11">
                <thead class="bg-200 text-900 font-weight-600">
                    <tr>
                        <th class="py-3 text-center" style="width: 60px;">S.No.</th>
                        <th>Order / App ID</th>
                        <th>Aadhaar Reference</th>
                        <th>Retrieved PAN</th>
                        <th>Full PAN Details</th>
                        <th class="text-center">Submission Date</th>
                        <th class="text-end px-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result && mysqli_num_rows($result) > 0) {
                        $sr = $offset + 1;
                        while($row = mysqli_fetch_assoc($result)) {
                    ?>
                    <tr>
                        <td class="text-center fw-medium text-secondary"><?php echo $sr++; ?></td>
                        <td>
                            <span class="d-block fw-bold text-dark fs-11"><?php echo htmlspecialchars($row['application_no'] ?: 'N/A'); ?></span>
                            <small class="text-muted text-uppercase fs-12">ID: <?php echo htmlspecialchars($row['order_id'] ?: 'N/A'); ?></small>
                        </td>
                        <td class="font-family-monospace text-secondary fw-medium"><?php echo htmlspecialchars($row['aadhaar_no'] ?: 'N/A'); ?></td>
                        <td class="fw-bold text-dark font-family-monospace text-uppercase"><?php echo htmlspecialchars($row['pan_no'] ?: 'N/A'); ?></td>
                        <td class="fw-bold text-success font-family-monospace text-uppercase"><?php echo htmlspecialchars($row['full_pan'] ?: 'N/A'); ?></td>
                        <td class="text-center text-secondary"><?php echo date('d M Y, h:i A', strtotime($row['date'])); ?></td>
                        <td class="text-end px-3">
                            <span class="badge bg-success-subtle text-success border border-success-200 py-1 px-2">
                                <i class="fas fa-check-circle me-1"></i> Allocated
                            </span>
                        </td>
                    </tr>
                    <?php 
                        }
                    } else { 
                    ?>
                    <!-- Empty Database State Handling -->
                    <tr>
                        <td colspan="7" class="text-center py-5 bg-white">
                            <div class="text-center p-3">
                                <img src="../assets/img/icons/spot-illustrations/corndog.png" alt="No Data" style="width: 70px;" class="mb-2 opacity-50">
                                <p class="text-secondary fw-bold fs-10 mb-0">No active operational log files found mapped to your directory profiles.</p>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Dynamic Pagination Footer Block -->
    <?php if ($total_pages > 1) { ?>
    <div class="card-footer bg-light d-flex align-items-center justify-content-center border-top py-2">
        <ul class="pagination pagination-sm mb-0 shadow-none">
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
            </li>
            <?php for($i = 1; $i <= $total_pages; $i++) { ?>
                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
            <?php } ?>
            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
            </li>
        </ul>
    </div>
    <?php } ?>
</div>

<?php include('footer.php'); ?>