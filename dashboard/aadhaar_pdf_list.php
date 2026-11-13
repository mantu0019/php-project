<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set('Asia/Kolkata');

if (!function_exists('clean')) {
    function clean($conn,$data) {
        return mysqli_real_escape_string($conn, trim($data));
    }
}

$user_phone = clean($DarkXen_conn,$udata['phone']);

// History records fetch query
$query = mysqli_query($DarkXen_conn, "SELECT * FROM `aadhar_pdf_history` WHERE `user_phone` = '$user_phone' ORDER BY `id` DESC");
?>

<!-- DataTables CSS for clean searching and pagination -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

<!-- Header Section -->
<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-danger-subtle shadow-none me-3">
                        <span class="fas fa-history text-danger fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-danger fs-10 mb-0">UIDAI Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Aadhaar PDF <span class="text-danger fw-medium">History</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-control form-control-sm bg-white border-200 shadow-sm px-3">
                            <span class="fas fa-wallet text-success me-2"></span>
                            <span class="fw-bold text-success">Balance: ₹<?php echo number_format((float)($udata['balance'] ?? 0), 2); ?></span>
                        </div>
                        <a href="aadhaar_pdf.php" class="btn btn-sm btn-danger shadow-none fw-bold">
                            <i class="fas fa-plus-circle me-1"></i> Generate New PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- History Table Card -->
<div class="card border-0 shadow-sm border-top border-4 border-danger">
    <div class="card-header bg-light py-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-dark fw-bold">
            <i class="fas fa-list-alt me-2 text-danger"></i> PDF Generation Logs
        </h5>
        <span class="badge bg-secondary-subtle text-secondary px-3 py-2 rounded-pill font-sans-serif fs-11">
            Total Records: <?php echo mysqli_num_rows($query); ?>
        </span>
    </div>
    <div class="card-body bg-white p-3">
        <div class="table-responsive">
            <table id="pdfHistoryTable" class="table table-hover table-striped table-bordered align-middle fs-10 w-100">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="text-center" width="5%">#</th>
                        <th class="text-center">Aadhaar Number</th>
                        <th class="text-center">Aadhaar PDF</th>
                        <th class="text-center">PDF Password Format</th>
                        <th class="text-center">Debited Charge</th>
                        <th class="text-center">Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($query && mysqli_num_rows($query) > 0) {$sno = 1;
                        while ($row = mysqli_fetch_assoc($query)) {
                    ?>
                        <tr>
                            <td class="text-center fw-bold"><?php echo $sno++; ?></td>
                            
                            <!-- Masked Aadhaar -->
                            <td class="text-center fw-bold text-dark font-family-monospace">
                                <span class="badge bg-light text-dark border px-2 py-1">
                                    <i class="fas fa-fingerprint text-danger me-1"></i>
                                    <?php echo htmlspecialchars($row['aadhar_no']); ?>
                                </span>
                            </td>
                            
                            <!-- Download Button -->
                            <td class="text-center">
                                <a href="data:application/pdf;base64,<?php echo $row['pdf']; ?>" download="Aadhaar_<?php echo htmlspecialchars($row['aadhar_no']); ?>.pdf" class="btn btn-primary btn-sm fw-bold shadow-sm">
                                    <i class="fas fa-download me-1"></i> Download
                                </a>
                            </td>

                            <!-- Password Instructions (Replaced EID & VID) -->
                            <td class="text-center fs-11">
                                <div class="text-dark fw-bold mb-1">First 4 letters of Name (CAPITAL) + Year of Birth</div>
                                <div class="text-muted" style="font-size: 0.9em;">
                                    Example: Name = <i>Shivam Kumar</i>, DOB = <i>01-01-2006</i> ➔ <b class="text-danger">SHIV2006</b>
                                </div>
                            </td>

                            <!-- Debited Fee -->
                            <td class="text-center fw-bold text-danger">
                                ₹<?php echo number_format((float)$row['charge_debited'], 2); ?>
                            </td>

                            <!-- Date/Time -->
                            <td class="text-center text-secondary">
                                <i class="far fa-clock me-1"></i>
                                <?php echo date('d M, Y - h:i A', strtotime($row['created_at'])); ?>
                            </td>
                        </tr>
                    <?php 
                        }
                    } 
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {$('#pdfHistoryTable').DataTable({
        responsive: true,
        order: [[0, 'asc']],
        pageLength: 10,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search Aadhaar...",
            emptyTable: "No download history found"
        }
    });
});
</script>

<?php include('footer.php'); ?>