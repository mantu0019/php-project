<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set('Asia/Kolkata');

if (!function_exists('clean')) {
    function clean($conn, $data) {
        return mysqli_real_escape_string($conn, trim($data));
    }
}

$user = clean($DarkXen_conn, $udata['phone']);

// डेटाबेस से यूजर के रिकॉर्ड्स निकालना
$query_string = "SELECT * FROM `voter_pdf` WHERE `username` = '$user' ORDER BY `id` DESC";
$result_set = mysqli_query($DarkXen_conn, $query_string);
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-info-subtle shadow-none me-3">
                        <span class="fas fa-history text-info fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-info fs-10 mb-0">Logs & Reports</h6>
                        <h4 class="text-primary fw-bold mb-0">Voter PDF <span class="fw-medium">Download History</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="voter_pdf.php" class="btn btn-sm btn-info text-white shadow-none fw-bold">
                        <i class="fas fa-arrow-left me-1"></i> Download New Card
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-light py-3 border-bottom">
        <h5 class="mb-0 text-1000 fw-bold"><i class="fas fa-table me-2 text-info"></i>Your Downloaded Records</h5>
    </div>
    <div class="card-body p-3">
        <div class="table-responsive scrollbar">
            <table class="table table-striped table-bordered fs-11 mb-0" id="historyTable" style="width:100%">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="text-center" style="width: 5%;">S.No</th>
                        <th>Voter / Applicant Name</th>
                        <th class="text-center">EPIC Number</th>
                        <th class="text-center">Generated Date & Time</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width: 15%;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (mysqli_num_rows($result_set) > 0) {
                        $count = 1;
                        while($row = mysqli_fetch_assoc($result_set)) {
                            ?>
                            <tr>
                                <td class="text-center align-middle fw-semi-bold"><?php echo $count++; ?></td>
                                <td class="align-middle fw-bold text-primary text-uppercase"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td class="text-center align-middle fw-bold text-dark"><?php echo htmlspecialchars($row['epicno']); ?></td>
                                <td class="text-center align-middle"><?php echo date('d-M-Y h:i A', strtotime($row['created_at'])); ?></td>
                                <td class="text-center align-middle">
                                    <span class="badge rounded-pill bg-success-subtle text-success fs-11">
                                        <i class="fas fa-check-circle me-1"></i><?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                                <td class="text-center align-middle">
                                    <?php if(file_exists($row['pdfpath'])): ?>
                                        <a href="<?php echo htmlspecialchars($row['pdfpath']); ?>" target="_blank" class="btn btn-sm btn-falcon-primary py-1 px-3 fs-11">
                                            <i class="fas fa-file-pdf me-1"></i> View / Open PDF
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary py-1 px-3 fs-11" disabled title="File deleted or moved">
                                            <i class="fas fa-exclamation-triangle me-1"></i> Missing File
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-secondary">
                                <i class="fas fa-folder-open display-4 d-block mb-2 text-400"></i>
                                No voter PDF downloads recorded yet.
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Live Client-Side Searching, Sorting aur Pagination active karne ke liye
    if ($('#historyTable tbody tr').length > 1 || $('#historyTable tbody tr td').length > 1) {
        $('#historyTable').DataTable({
            "order": [[ 0, "asc" ]],
            "pageLength": 10,
            "language": {
                "search": "Filter Records:"
            }
        });
    }
});
</script>

<?php include('footer.php'); ?>