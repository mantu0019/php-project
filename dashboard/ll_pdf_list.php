<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 
$appliedby =$udata['phone'];

// Fetch History Data
$query = "SELECT * FROM `ll_pdf_history` WHERE `username` = '$appliedby' ORDER BY `id` DESC";
$result = mysqli_query($DarkXen_conn,$query);
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3" style="width: 50px; height: 50px;">
                        <span class="fas fa-history text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Transport Services</h6>
                        <h4 class="text-primary fw-bold mb-0">LL PDF Download <span class="text-info fw-medium">History</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="ll_pdf.php" class="btn btn-sm btn-primary shadow-none fw-bold">
                        <i class="fas fa-file-download me-1"></i> New PDF Generate
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border">
            <div class="card-header bg-success py-2">
                <h5 class="mb-0 text-white fs-0"><i class="fas fa-list me-2"></i> All Generated PDF Records</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive scrollbar">
                    <table class="table table-sm table-striped table-bordered align-middle fs-10 mb-0">
                        <thead class="bg-200">
                            <tr>
                                <th class="text-center">Sl No.</th>
                                <th>Application Number</th>
                                <th>Deducted Charge</th>
                                <th class="text-center">Date & Time</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($result && mysqli_num_rows($result) > 0) {$sl = 1;
                                while ($row = mysqli_fetch_assoc($result)) {
                            ?>
                            <tr>
                                <td class="text-center fw-bold text-dark"><?= $sl++; ?></td>
                                <td class="fw-bold text-primary font-monospace fs-11"><?= htmlspecialchars($row['application_no']); ?></td>
                                <td class="text-danger fw-bold">₹<?= htmlspecialchars($row['charge']); ?></td>
                                <td class="text-center text-muted fw-medium"><?= date('d M Y, h:i A', strtotime($row['date'])); ?></td>
                                <td class="text-center"> 
                                    <!-- Direct download link via base64 encoded string saved in DB -->
                                    <a href="<?= htmlspecialchars($row['pdf']) ?>" download="Learning_License_<?= htmlspecialchars($row['application_no']) ?>.pdf" class="btn btn-danger btn-sm fw-bold shadow-none px-3">
                                        <i class="fas fa-download me-1"></i> Download PDF
                                    </a>
                                </td>
                            </tr>
                            <?php 
                                }
                            } else {
                            ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted fw-bold">
                                    <i class="fas fa-folder-open fs-3 mb-2"></i><br>No history found!
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

<?php include('footer.php'); ?>