<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

if (!function_exists('clean')) {
    function clean($conn, $data) {
        return mysqli_real_escape_string($conn, trim($data));
    }
}

$user_phone = clean($DarkXen_conn, $udata['phone']);

// Pagination Setup (Optional but recommended for optimization)
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch verified logs safely mapped to active sessions
$query_string = "SELECT * FROM `aadhar_status` WHERE `user_phone` = '$user_phone' ORDER BY `id` DESC LIMIT $offset, $limit";
$result = mysqli_query($DarkXen_conn, $query_string);

// Total counts for pagination metrics fallback
$total_query = mysqli_query($DarkXen_conn, "SELECT COUNT(*) as total FROM `aadhar_status` WHERE `user_phone` = '$user_phone'");
$total_rows = mysqli_fetch_assoc($total_query)['total'] ?? 0;
$total_pages = ceil($total_rows / $limit);
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-list text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Verification Management</h6>
                        <h4 class="text-primary fw-bold mb-0">Aadhaar Status <span class="text-info fw-medium">History Logs</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="aadhaar_verify.php" class="btn btn-sm btn-primary shadow-none fw-bold">
                        <i class="fas fa-plus-circle me-1"></i> Verify New Number
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-light border-bottom py-3">
        <h5 class="mb-0 text-900 fw-bold"><i class="fas fa-history text-secondary me-2"></i>Recent Verification Transactions</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-striped table-hover align-middle mb-0 fs-11">
                <thead class="bg-200 text-900 font-weight-600">
                    <tr>
                        <th class="py-3 text-center" style="width: 60px;">S.No.</th>
                        <th>Target ID Number</th>
                        <th>State Location</th>
                        <th class="text-center">Age Group</th>
                        <th class="text-center">Gender</th>
                        <th class="text-center">Linked Mobile</th>
                        <th class="text-center">Deducted Fee</th>
                        <th class="text-center">Date & Time</th>
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
                        <td class="fw-bold text-dark font-family-monospace"><?php echo htmlspecialchars($row['aadhar_no']); ?></td>
                        <td class="fw-bold text-primary"><?php echo htmlspecialchars($row['state_address'] ?: 'N/A'); ?></td>
                        <td class="text-center"><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['age_band'] ?: 'N/A'); ?></span></td>
                        <td class="text-center text-uppercase fw-medium"><?php echo htmlspecialchars($row['gender'] ?: 'N/A'); ?></td>
                        <td class="text-center font-family-monospace text-danger"><?php echo htmlspecialchars($row['masked_mobile'] ?: 'N/A'); ?></td>
                        <td class="text-center fw-bold text-success">₹<?php echo number_format((float)$row['charge_debited'], 2); ?></td>
                        <td class="text-center text-secondary"><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
                        <td class="text-end px-3">
                            <span class="badge bg-success-subtle text-success border border-success-200 py-1 px-2">
                                <i class="fas fa-check me-1"></i> Success
                            </span>
                        </td>
                    </tr>
                    <?php 
                        }
                    } else { 
                    ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 bg-white">
                            <div class="text-center p-3">
                                <img src="../assets/img/icons/spot-illustrations/corndog.png" alt="No Data" style="width: 70px;" class="mb-2 opacity-50">
                                <p class="text-secondary fw-bold fs-10 mb-0">No verification logs found in your account directory.</p>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
    
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