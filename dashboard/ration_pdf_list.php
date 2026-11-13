<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 

$appliedby =$udata['phone'];

// इतिहास डेटा फेच करना
$query = "SELECT * FROM `ration_pdf_history` WHERE `username` = '$appliedby' ORDER BY `id` DESC";
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
                        <h6 class="text-primary fs-10 mb-0">PDF Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Ration PDF <span class="text-info fw-medium">Download History</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0 d-flex gap-2">
                    <!-- Yahan apne generation wale page ka sahi link daalein (e.g. ration_pdf.php) -->
                    <a href="ration_card.php" class="btn btn-sm btn-primary shadow-none fw-bold">
                        <i class="fas fa-file-pdf me-1"></i> New PDF Download
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border">
            <div class="card-header bg-success py-2 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-white fs-0"><i class="fas fa-list me-2"></i> All Downloaded PDF Records</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive scrollbar">
                    <table class="table table-sm table-striped table-bordered align-middle fs-10 mb-0">
                        <thead class="bg-200">
                            <tr>
                                <th class="text-center">Sl No.</th>
                                <th>Order ID</th>
                                <th>Document No.</th>
                                <th>Document Type</th>
                                <th>PDF Format</th>
                                <th>Mobile No.</th>
                                <th class="text-center">Action (PDF)</th>
                                <th class="text-center">Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($result && mysqli_num_rows($result) > 0) {$sl = 1;
                                while ($row = mysqli_fetch_assoc($result)) {
                            ?>
                            <tr>
                                <td class="text-center fw-bold text-dark"><?= $sl++; ?></td>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($row['application_no']); ?></td>
                                <td class="fw-bold text-dark font-monospace"><?= htmlspecialchars($row['rcid']); ?></td>
                                
                                <!-- fps_id column me humne doc type (Aadhar/Ration) save kiya tha -->
                                <td>
                                    <span class="badge bg-info-subtle text-info text-uppercase border border-info">
                                        <?= htmlspecialchars($row['fps_id'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                
                                <!-- head_family column me humne PDF type (NEW/OLD) save kiya tha -->
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary text-uppercase border border-secondary">
                                        <?= htmlspecialchars($row['head_family'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                
                                <td class="text-muted"><?= htmlspecialchars($row['mobile'] ?? 'N/A'); ?></td>
                                
                                <td class="text-center"> 
                                    <a href="<?= htmlspecialchars($row['pdf']) ?>" download="Ration_<?= htmlspecialchars($row['rcid']) ?>.pdf" class="btn btn-danger btn-sm fw-bold shadow-none">
                                        <i class="fas fa-download me-1"></i> Download
                                    </a>
                                </td>
                                
                                <td class="text-center text-muted fw-medium"><?= date('d M Y, h:i A', strtotime($row['date'])); ?></td>
                            </tr>
                            <?php 
                                }
                            } else {
                            ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted fw-bold">
                                    <i class="fas fa-folder-open fs-3 mb-2"></i><br>
                                    No history found!
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