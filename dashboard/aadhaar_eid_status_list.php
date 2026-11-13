<?php
// Core System Files Integration
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

// Security Sanitization Function (If not loaded globally)
if (!function_exists('clean')) {
    function clean($conn, $data) {
        return mysqli_real_escape_string($conn, trim($data));
    }
}

// Session se active user ka uniquely mapped identification track karna
$appliedby = clean($DarkXen_conn, $udata['phone'] ?? $udata['username'] ?? '');

// Pagination Framework Configuration
$limit = 20; // Ek page par max 20 records dikhenge
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// User session records pull karne ke liye optimal SQL framework
$query_string = "SELECT `id`, `userid`, `eid`, `status_message`, `date_time` 
                 FROM `eid_status_history` 
                 WHERE `userid` = '$appliedby' 
                 ORDER BY `id` DESC 
                 LIMIT $offset, $limit";
$result = mysqli_query($DarkXen_conn, $query_string);

// Total counts pagination functionality ke liye
$total_query = mysqli_query($DarkXen_conn, "SELECT COUNT(*) as total FROM `eid_status_history` WHERE `userid` = '$appliedby'");
$total_rows = mysqli_fetch_assoc($total_query)['total'] ?? 0;
$total_pages = ceil($total_rows / $limit);
?>

<!-- Header Action Panel Component -->
<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-warning-subtle shadow-none me-3">
                        <span class="fas fa-history text-warning fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-warning fs-10 mb-0">EID Ledger Profiles</h6>
                        <h4 class="text-primary fw-bold mb-0">EID Search <span class="text-warning fw-medium">History Logs</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="aadhaar_eid_status.php" class="btn btn-sm btn-warning shadow-none fw-bold text-white">
                        <i class="fas fa-search me-1"></i> Check New Status
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- History Records Data Grid Section -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-light border-bottom py-3">
        <h5 class="mb-0 text-900 fw-bold"><i class="fas fa-database text-secondary me-2"></i>Retrieved EID Profile Logs</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-striped table-hover align-middle mb-0 fs-11">
                <thead class="bg-200 text-900 font-weight-600">
                    <tr>
                        <th class="py-3 text-center" style="width: 60px;">S.No.</th>
                        <th style="width: 250px;">Enrollment Identity (EID)</th>
                        <th> Status Response </th>
                        <th class="text-center" style="width: 200px;">Execution Date</th>
                        <th class="text-end px-3" style="width: 120px;">Verification Status</th>
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
                            <span class="d-block fw-bold text-danger font-family-monospace text-uppercase fs-10"><?php echo htmlspecialchars($row['eid']); ?></span>
                            
                        </td>
                        <td class="text-dark fw-medium lh-base py-3">
                            <?php echo htmlspecialchars($row['status_message'] ?: 'No response payload returned.'); ?>
                        </td>
                        <td class="text-center text-secondary font-family-monospace">
                            <?php echo date('d M Y, h:i A', strtotime($row['date_time'])); ?>
                        </td>
                        <td class="text-end px-3">
                            <span class="badge bg-success-subtle text-success border border-success-200 py-1 px-2">
                                <i class="fas fa-check-circle me-1"></i> Success
                            </span>
                        </td>
                    </tr>
                    <?php 
                        }
                    } else { 
                    ?>
                    <!-- Empty Database State Handling -->
                    <tr>
                        <td colspan="5" class="text-center py-5 bg-white">
                            <div class="text-center p-3">
                                <img src="../assets/img/icons/spot-illustrations/corndog.png" alt="No Records Data" style="width: 70px;" class="mb-2 opacity-50">
                                <p class="text-secondary fw-bold fs-10 mb-0">No profile transactional history found linked to your reference logs.</p>
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