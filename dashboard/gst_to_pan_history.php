<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata");

// Session data and security
$user_phone = mysqli_real_escape_string($DarkXen_conn,$udata['phone'] ?? '');
$user_role =$udata['role'] ?? 'User'; 

// Fetch Query based on Role (Admin sees all, User sees their own)
$query = ($user_role === 'Admin') 
    ? "SELECT * FROM `gst_to_pan_history` ORDER BY id DESC" 
    : "SELECT * FROM `gst_to_pan_history` WHERE `username` = '$user_phone' ORDER BY id DESC";

$result = mysqli_query($DarkXen_conn,$query);
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-info-subtle shadow-none me-3">
                        <span class="fas fa-history text-info fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-info fs-10 mb-0">GST Database</h6>
                        <h4 class="text-primary fw-bold mb-0">GST To PAN <span class="text-info fw-medium">History</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="gst_to_pan.php" class="btn btn-sm btn-info shadow-none fw-bold text-white">
                        <i class="fas fa-plus me-1"></i> New Extraction
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3 shadow-none border">
    <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 border-bottom">
        <h5 class="mb-0 text-primary fs-10 fw-bold"><i class="fas fa-list me-2"></i>Extraction Records</h5>
        <span class="badge bg-primary-subtle text-primary fw-bold fs-11">
            Total Records: <?= mysqli_num_rows($result); ?>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped table-hover align-middle fs-11 mb-0" id="historyTable">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-3 text-center" style="width: 5%;">#</th>
                        <th class="py-3">App Reference</th>
                        <th class="py-3">GSTIN</th>
                        <th class="py-3">Extracted PAN</th>
                        <th class="py-3">Charge</th>
                        <th class="py-3">Status</th>
                        <th class="py-3 text-center">Date & Time</th>
                        <th class="py-3 text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if(mysqli_num_rows($result) > 0):$count = 1;
                        while($row = mysqli_fetch_assoc($result)): 
                    ?>
                    <tr>
                        <td class="py-3 text-center fw-bold text-secondary"><?= $count++; ?></td>
                        <td class="py-3 text-primary fw-semi-bold"><?= htmlspecialchars($row['application_no']); ?></td>
                        <td class="py-3 fw-bold font-family-monospace text-uppercase tracking-wide"><?= htmlspecialchars($row['gstin']); ?></td>
                        <td class="py-3 fw-bold font-family-monospace text-danger text-uppercase tracking-wide fs-10">
                            <?= htmlspecialchars($row['pan_number']); ?>
                        </td>
                        <td class="py-3 text-warning-emphasis fw-bold font-family-monospace">
                            ₹<?= number_format($row['amount'], 2); ?>
                        </td>
                        <td class="py-3">
                            <span class="badge badge-subtle-success border border-success-subtle px-2 py-1">
                                <i class="fas fa-check-circle me-1"></i> Success
                            </span>
                        </td>
                        <td class="py-3 text-center text-muted fw-medium">
                            <?= date('d M Y, h:i A', strtotime($row['date'])); ?>
                        </td>
                        <td class="py-3 text-center">
                            <!-- Action Print Button -->
                            <button class="btn btn-sm btn-outline-primary shadow-none px-2 py-1" onclick="printData('print_<?= $row['id']; ?>')" title="Print Statement">
                                <i class="fas fa-print"></i>
                            </button>

                            <!-- Hidden Print Layout -->
                            <div id="print_<?= $row['id']; ?>" class="d-none">
                                <div class="card border mb-4">
                                    <div class="card-header py-2" style="background-color: #0dcaf0 !important;">
                                        <h5 class="mb-0" style="color: #000000 !important; font-weight:bold;">GST to PAN Verification Statement</h5>
                                    </div>
                                    <div class="card-body p-4 bg-white">
                                        <table class="table table-bordered table-striped mb-0 align-middle">
                                            <tbody>
                                                <tr>
                                                    <th width="35%" class="text-secondary fw-bold" style="background-color: #f8f9fa;">App Reference</th>
                                                    <td class="text-dark fw-bold"><?= htmlspecialchars($row['application_no']); ?></td>
                                                </tr>
                                                <tr>
                                                    <th class="text-secondary fw-bold" style="background-color: #f8f9fa;">Queried GSTIN</th>
                                                    <td class="text-dark font-family-monospace fw-bold text-uppercase"><?= htmlspecialchars($row['gstin']); ?></td>
                                                </tr>
                                                <tr>
                                                    <th class="text-secondary fw-bold" style="background-color: #f8f9fa;">Extracted PAN Number</th>
                                                    <td class="text-danger font-family-monospace fw-bold tracking-wide fs-5"><?= htmlspecialchars($row['pan_number']); ?></td>
                                                </tr>
                                                <tr>
                                                    <th class="text-secondary fw-bold" style="background-color: #f8f9fa;">Network Message</th>
                                                    <td class="text-success fw-bold"><i><?= htmlspecialchars($row['status_msg']); ?></i></td>
                                                </tr>
                                                <tr>
                                                    <th class="text-secondary fw-bold" style="background-color: #f8f9fa;">Date Verified</th>
                                                    <td class="text-dark"><?= date('d M Y, h:i A', strtotime($row['date'])); ?></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <!-- End Hidden Print Layout -->
                        </td>
                    </tr>
                    <?php 
                        endwhile; 
                    else: 
                    ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 bg-white">
                            <div class="text-center p-3">
                                <i class="fas fa-inbox fa-3x mb-3 text-300"></i>
                                <p class="text-secondary fw-bold fs-10 mb-0">No GST to PAN extraction records found.</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Scripts for Print & DataTables -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function printData(divId) {
    var divToPrint = document.getElementById(divId);
    if (!divToPrint) return;

    var newWindow = window.open("", "_blank");
    newWindow.document.write('<html><head><title>Print Statement</title>');
    newWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">');
    newWindow.document.write('<style>');
    newWindow.document.write('body { padding: 30px; font-family: Arial, sans-serif; }');
    newWindow.document.write('.card-header { background-color: #0dcaf0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; color: #000 !important; }');
    newWindow.document.write('th { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }');
    newWindow.document.write('.text-danger { color: #dc3545 !important; } .text-success { color: #198754 !important; }');
    newWindow.document.write('</style>');
    newWindow.document.write('</head><body>');
    newWindow.document.write(divToPrint.innerHTML);
    newWindow.document.write('</body></html>');
    newWindow.document.close();

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