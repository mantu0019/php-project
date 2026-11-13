<?php
// Core System Files Integration
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

// Security Sanitization Function (If not loaded globally)
if (!function_exists('clean')) {
    function clean($DarkXen_conn,$data) {
        return mysqli_real_escape_string($DarkXen_conn, trim($data));
    }
}

// Session se active user ka uniquely mapped identification check karna
$current_userid = clean($DarkXen_conn,$udata['phone'] ?? '');

// Pagination Framework Configuration
$limit = 20; // Ek page par max 20 records dikhenge
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) *$limit;

// User session records pull karne ke liye optimal SQL framework
$query_string = "SELECT `id`, `userid`, `pan_no`, `name`, `fathername`, `dob`, `gender`, `aadhaar_linked`, `aadhar_ref`, `created_at` 
                 FROM `pan_fetch_history` 
                 WHERE `userid` = '$current_userid' 
                 ORDER BY `id` DESC 
                 LIMIT $offset,$limit";
$result = mysqli_query($DarkXen_conn,$query_string);

// Total counts pagination functionality ke liye
$total_query = mysqli_query($DarkXen_conn, "SELECT COUNT(*) as total FROM `pan_fetch_history` WHERE `userid` = '$current_userid'");
$total_rows = mysqli_fetch_assoc($total_query)['total'] ?? 0;
$total_pages = ceil($total_rows / $limit);
?>

<!-- Header Action Panel Component -->
<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-success-subtle shadow-none me-3">
                        <span class="fas fa-history text-success fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-success fs-10 mb-0">PAN Database</h6>
                        <h4 class="text-primary fw-bold mb-0">Detailed PAN Fetch <span class="text-success fw-medium">History Logs</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="pan_full_details.php" class="btn btn-sm btn-success shadow-none fw-bold text-white">
                        <i class="fas fa-search me-1"></i> Fetch New Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- History Records Data Grid Section -->
<div class="card border-0 shadow-sm mb-4 border-top border-4 border-success">
    <div class="card-header bg-light border-bottom py-3">
        <h5 class="mb-0 text-900 fw-bold"><i class="fas fa-database text-secondary me-2"></i>Retrieved Profile Statements</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-striped table-hover align-middle mb-0 fs-11" id="historyTable">
                <thead class="bg-200 text-900 font-weight-600">
                    <tr>
                        <th class="py-3 text-center" style="width: 5%;">S.No.</th>
                        <th style="width: 15%;">PAN Card Info</th>
                        <th style="width: 20%;">Personal Details</th>
                        <th style="width: 15%;">Guardian Reference</th>
                        <th class="text-center" style="width: 10%;">Biometric Link</th>
                        <th class="text-center" style="width: 15%;">Execution Date</th>
                        <th class="text-end px-3" style="width: 10%;">Status</th>
                        <th class="text-center" style="width: 10%;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result && mysqli_num_rows($result) > 0) {
                        $sr =$offset + 1;
                        while($row = mysqli_fetch_assoc($result)) {
                    ?>
                    <tr>
                        <td class="text-center fw-medium text-secondary"><?php echo $sr++; ?></td>
                        <td>
                            <span class="d-block fw-bold text-danger font-family-monospace text-uppercase fs-10"><?php echo htmlspecialchars($row['pan_no'] ?: 'N/A'); ?></span>
                            <small class="text-muted font-family-monospace">[Aadhaar Redacted]</small>
                        </td>
                        <td>
                            <span class="d-block fw-bold text-dark text-capitalize"><?php echo htmlspecialchars($row['name'] ?: 'N/A'); ?></span>
                            <small class="text-secondary fw-medium">DOB: <?php echo !empty($row['dob']) ? htmlspecialchars($row['dob']) : 'N/A'; ?> \vert{} <?php echo htmlspecialchars($row['gender'] ?: 'N/A'); ?></small>
                        </td>
                        <td class="text-dark text-capitalize fw-medium"><?php echo htmlspecialchars($row['fathername'] ?: 'N/A'); ?></td>
                        <td class="text-center">
                            <?php if(strpos(strtolower($row['aadhaar_linked']), 'yes') !== false || $row['aadhaar_linked'] == '1') { ?>
                                <span class="badge bg-success-subtle text-success border border-success-200 py-1 px-2 fs-12">Linked</span>
                            <?php } else { ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-200 py-1 px-2 fs-12">Not Linked</span>
                            <?php } ?>
                        </td>
                        <td class="text-center text-secondary fw-medium"><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
                        <td class="text-end px-3">
                            <span class="badge bg-primary-subtle text-primary border border-primary-200 py-1 px-2">
                                <i class="fas fa-check-double me-1"></i> Success
                            </span>
                        </td>
                        <td class="text-center">
                            <!-- Download / Print Button -->
                            <button class="btn btn-sm btn-info text-white shadow-none px-2 py-1" onclick="printData('printData_<?php echo $row['id']; ?>')" title="Print Statement">
                                <i class="fas fa-print"></i>
                            </button>

                            <!-- Hidden Div for Print Layout -->
                            <div id="printData_<?php echo $row['id']; ?>" class="d-none">
                                <div class="card border mb-4">
                                    <div class="card-header py-2" style="background-color: #0d6efd !important;">
                                        <h5 class="mb-0" style="color: #ffffff !important;">PAN Details Statement</h5>
                                    </div>
                                    <div class="card-body p-4 bg-white">
                                        <table class="table table-bordered table-striped mb-0 align-middle">
                                            <tbody>
                                                <tr>
                                                    <th width="35%" class="text-secondary fw-bold" style="background-color: #f8f9fa;">PAN Number</th>
                                                    <td class="text-dark font-family-monospace fw-bold text-uppercase fs-5"><?php echo htmlspecialchars($row['pan_no'] ?: 'N/A'); ?></td>
                                                </tr>
                                                <tr>
                                                    <th class="text-secondary fw-bold" style="background-color: #f8f9fa;">Full Name</th>
                                                    <td class="text-dark fw-bold text-uppercase"><?php echo htmlspecialchars($row['name'] ?: 'N/A'); ?></td>
                                                </tr>
                                                <tr>
                                                    <th class="text-secondary fw-bold" style="background-color: #f8f9fa;">Father Name</th>
                                                    <td class="text-dark text-uppercase"><?php echo htmlspecialchars($row['fathername'] ?: 'N/A'); ?></td>
                                                </tr>
                                                <tr>
                                                    <th class="text-secondary fw-bold" style="background-color: #f8f9fa;">Date of Birth</th>
                                                    <td class="text-dark fw-bold"><?php echo !empty($row['dob']) ? htmlspecialchars($row['dob']) : 'N/A'; ?></td>
                                                </tr>
                                                <tr>
                                                    <th class="text-secondary fw-bold" style="background-color: #f8f9fa;">Gender</th>
                                                    <td class="text-dark fw-bold text-uppercase"><?php echo htmlspecialchars($row['gender'] ?: 'N/A'); ?></td>
                                                </tr>
                                                <tr>
                                                    <th class="text-secondary fw-bold" style="background-color: #f8f9fa;">Aadhaar Linked Status</th>
                                                    <td class="text-dark fw-bold"><?php echo $is_linked ? 'Yes' : 'No'; ?></td>
                                                </tr>
                                                <tr>
                                                    <th class="text-secondary fw-bold" style="background-color: #f8f9fa;">Date Verified</th>
                                                    <td class="text-muted"><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <!-- End Hidden Div -->
                        </td>
                    </tr>
                    <?php 
                        }
                    } else { 
                    ?>
                    <!-- Empty Database State Handling -->
                    <tr>
                        <td colspan="8" class="text-center py-5 bg-white">
                            <div class="text-center p-3">
                                <i class="fas fa-inbox fa-3x mb-3 text-300"></i>
                                <p class="text-secondary fw-bold fs-10 mb-0">No profile fetching ledger entries found mapped to your session ID.</p>
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
                <li class="page-item <?php echo ($page ==$i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
            <?php } ?>
            <li class="page-item <?php echo ($page >=$total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
            </li>
        </ul>
    </div>
    <?php } ?>
</div>

<!-- Print Script -->
<script>
function printData(divId) {
    var divToPrint = document.getElementById(divId);
    if (!divToPrint) return;

    var newWindow = window.open("", "_blank");
    newWindow.document.write('<html><head><title>Print Statement</title>');
    newWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">');
    newWindow.document.write('<style>');
    newWindow.document.write('body { padding: 30px; font-family: Arial, sans-serif; }');
    newWindow.document.write('.card-header { background-color: #0d6efd !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; color: #fff !important; }');
    newWindow.document.write('th { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }');
    newWindow.document.write('</style>');
    newWindow.document.write('</head><body>');
    newWindow.document.write(divToPrint.innerHTML);
    newWindow.document.write('</body></html>');
    newWindow.document.close();

    // Trigger Print after loading layout
    newWindow.addEventListener('load', function() {
        newWindow.print();
    });
}
</script>

<?php include('footer.php'); ?>