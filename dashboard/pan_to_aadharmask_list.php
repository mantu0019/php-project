<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set('Asia/Kolkata');

$user_phone = mysqli_real_escape_string($DarkXen_conn,$udata['phone'] ?? '');

// Fetch records for the logged-in user
$query = "SELECT * FROM `pan_aadhar_mask_status` WHERE `user_phone` = '$user_phone' ORDER BY `id` DESC";
$result = mysqli_query($DarkXen_conn,$query);
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-warning-subtle shadow-none me-3">
                        <span class="fas fa-list-alt text-warning fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-warning fs-10 mb-0">PAN Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Mask Aadhaar <span class="text-warning fw-medium">History</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-2 mt-md-0">
                    <!-- Link back to the search page -->
                    <a href="pan_to_aadharmask.php" class="btn btn-sm btn-primary shadow-none fw-bold">
                        <i class="fas fa-search-plus me-1"></i> New Search
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border shadow-none mb-4">
    <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center py-2">
        <h5 class="mb-0 text-primary fs-10 fw-bold"><i class="fas fa-history me-2"></i>Search History Records</h5>
        <span class="badge bg-primary-subtle text-primary fw-bold fs-11">
            Total Records: <?php echo mysqli_num_rows($result); ?>
        </span>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover mb-0 fs-11 align-middle text-center" id="historyTable">
                <thead class="table-dark">
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 12%;">PAN No.</th>
                        <th style="width: 15%;">Masked Aadhaar</th>
                        <th style="width: 18%;">Full Name</th>
                        <th style="width: 15%;">DOB / Gender</th>
                        <th style="width: 10%;">Charge</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 10%;">Date & Time</th>
                        <th style="width: 5%;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result && mysqli_num_rows($result) > 0) {$count = 1;
                        while ($row = mysqli_fetch_assoc($result)) { 
                    ?>
                        <tr>
                            <td class="fw-bold"><?php echo $count++; ?></td>
                            <td class="font-family-monospace fw-bold text-dark text-uppercase tracking-wide">
                                <?php echo htmlspecialchars($row['pan_no']); ?>
                            </td>
                            <td class="font-family-monospace fw-bold text-danger tracking-wide">
                                <?php echo htmlspecialchars($row['aadhar_no']); ?>
                            </td>
                            <td class="text-start fw-bold text-primary text-uppercase">
                                <?php echo htmlspecialchars($row['fullname'] ?? 'N/A'); ?>
                            </td>
                            <td>
                                <span class="d-block text-dark fw-bold"><?php echo htmlspecialchars($row['dob'] ?? '-'); ?></span>
                                <span class="badge bg-secondary-subtle text-dark border border-secondary-subtle"><?php echo htmlspecialchars($row['gender'] ?? '-'); ?></span>
                            </td>
                            <td class="text-warning-emphasis fw-bold font-family-monospace">
                                ₹<?php echo number_format($row['charge_debited'], 2); ?>
                            </td>
                            <td>
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                    <i class="fas fa-check-circle me-1"></i> Success
                                </span>
                            </td>
                            <td class="text-muted fs-11">
                                <?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?>
                            </td>
                            <td>
                                <!-- Download / Print Button -->
                                <button class="btn btn-sm btn-info text-white shadow-none px-2 py-1" onclick="printData('printData_<?php echo $row['id']; ?>')" title="Print / Save PDF">
                                    <i class="fas fa-print"></i>
                                </button>

                                <!-- Hidden Div for Print Layout -->
                                <div id="printData_<?php echo $row['id']; ?>" class="d-none">
                                    <div class="card border mb-4">
                                        <div class="card-header py-2" style="background-color: #198754 !important;">
                                            <h5 class="mb-0" style="color: #ffffff !important;">Identity Ledger Statement</h5>
                                        </div>
                                        <div class="card-body p-4 bg-white">
                                            <table class="table table-bordered table-striped mb-0 align-middle">
                                                <tbody>
                                                    <tr>
                                                        <th width="35%" class="text-secondary fw-bold" style="background-color: #f8f9fa;">PAN Reference</th>
                                                        <td class="text-dark font-family-monospace fw-bold text-uppercase"><?php echo htmlspecialchars($row['pan_no']); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th class="text-secondary fw-bold" style="background-color: #f8f9fa;">Masked Aadhaar</th>
                                                        <td class="text-danger font-family-monospace fw-bold tracking-wide"><?php echo htmlspecialchars($row['aadhar_no']); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th class="text-secondary fw-bold" style="background-color: #f8f9fa;">Full Name</th>
                                                        <td class="text-dark fw-bold text-uppercase"><?php echo htmlspecialchars($row['fullname'] ?? 'N/A'); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th class="text-secondary fw-bold" style="background-color: #f8f9fa;">Date of Birth</th>
                                                        <td class="text-dark fw-bold"><?php echo htmlspecialchars($row['dob'] ?? 'N/A'); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th class="text-secondary fw-bold" style="background-color: #f8f9fa;">Gender</th>
                                                        <td class="text-dark fw-bold text-uppercase"><?php echo htmlspecialchars($row['gender'] ?? 'N/A'); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th class="text-secondary fw-bold" style="background-color: #f8f9fa;">Network Message</th>
                                                        <td class="text-success fw-bold"><i><?php echo htmlspecialchars($row['status_msg'] ?? 'Successfully Processed'); ?></i></td>
                                                    </tr>
                                                    <tr>
                                                        <th class="text-secondary fw-bold" style="background-color: #f8f9fa;">Date Verified</th>
                                                        <td class="text-dark"><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
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
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-3x mb-2 text-300"></i>
                                <p class="mb-0">No record history found.</p>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Print Script & DataTables Initialization -->
<script>
function printData(divId) {
    var divToPrint = document.getElementById(divId);
    if (!divToPrint) return;

    var newWindow = window.open("", "_blank");
    newWindow.document.write('<html><head><title>Print / Download Statement</title>');
    newWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">');
    newWindow.document.write('<style>');
    newWindow.document.write('body { padding: 30px; font-family: Arial, sans-serif; }');
    newWindow.document.write('.card-header { background-color: #198754 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; color: #fff !important; }');
    newWindow.document.write('th { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }');
    newWindow.document.write('.text-danger { color: #dc3545 !important; } .text-success { color: #198754 !important; }');
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

document.addEventListener("DOMContentLoaded", function() {
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#historyTable').DataTable({
            "order": [[ 0, "asc" ]],
            "pageLength": 10,
            "language": {
                "search": "_INPUT_",
                "searchPlaceholder": "Search records..."
            }
        });
    }
});
</script>

<?php include('footer.php'); ?>