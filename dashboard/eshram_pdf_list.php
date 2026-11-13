<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 
$appliedby =$udata['phone'];
$user_role =$udata['role'] ?? 'User';

if ($user_role === 'Admin') {$query = "SELECT * FROM `eshram_pdf_history` ORDER BY id DESC";
} else {
    $query = "SELECT * FROM `eshram_pdf_history` WHERE `username` = '$appliedby' ORDER BY id DESC";
}

$result = mysqli_query($DarkXen_conn,$query);
?>

<style>
    @media print {
        body * { visibility: hidden; }
        #printCard, #printCard * { visibility: visible; }
        #printCard { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
        .card { border: 1px solid #ddd !important; box-shadow: none !important; }
    }
</style>

<div class="row mb-3 no-print">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3" style="width: 50px; height: 50px;">
                        <span class="fas fa-history text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">E-Shram Services</h6>
                        <h4 class="text-primary fw-bold mb-0">PDF Download <span class="text-info fw-medium">History</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="eshram_pdf.php" class="btn btn-sm btn-primary shadow-none fw-bold">
                        <i class="fas fa-file-download me-1"></i> New PDF Generate
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border border-top border-4 border-info" id="printCard">
            <div class="card-header bg-light py-3 border-bottom">
                <div class="row align-items-center flex-between-center">
                    <div class="col-auto">
                        <h5 class="mb-0 text-dark fw-bold"><i class="fas fa-list me-2 text-info"></i> All Generated E-Shram PDFs</h5>
                    </div>
                    <div class="col-auto no-print">
                        <button onclick="window.print()" class="btn btn-sm btn-dark shadow-none fw-bold">
                            <i class="fas fa-print me-1"></i> Print Report
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive scrollbar">
                    <table class="table table-sm table-striped table-bordered align-middle fs-10 mb-0">
                        <thead class="bg-200">
                            <tr>
                                <th class="text-center py-3">Sl No.</th>
                                <th class="py-3">App No</th>
                                <?php if($user_role === 'Admin'): ?><th class="py-3">User</th><?php endif; ?>
                                <th class="py-3">Name</th>
                                <th class="py-3">UAN Number</th>
                                <th class="py-3">DOB</th>
                                <th class="text-center py-3">Date & Time</th>
                                <th class="text-center py-3 no-print">Action</th>
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
                                <?php if($user_role === 'Admin'): ?>
                                    <td class="fw-medium text-dark"><?= htmlspecialchars($row['username']); ?></td>
                                <?php endif; ?>
                                <td class="fw-bold text-uppercase"><?= htmlspecialchars($row['name']); ?></td>
                                <td class="fw-bold text-success font-monospace"><?= htmlspecialchars($row['uan_no']); ?></td>
                                <td class="text-muted fw-bold"><?= htmlspecialchars($row['dob']); ?></td>
                                <td class="text-center text-muted fw-medium"><?= date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
                                <td class="text-center no-print"> 
                                    <a href="<?= htmlspecialchars($row['pdf_url']) ?>" download="EShram_<?= htmlspecialchars(str_replace(' ', '', $row['uan_no'])) ?>.pdf" class="btn btn-danger btn-sm fw-bold shadow-none">
                                        <i class="fas fa-download me-1"></i> Download PDF
                                    </a>
                                </td>
                            </tr>
                            <?php 
                                }
                            } else {
                                $colspan = ($user_role === 'Admin') ? 8 : 7;
                            ?>
                            <tr>
                                <td colspan="<?= $colspan ?>" class="text-center py-5 text-muted fw-bold">
                                    <i class="fas fa-folder-open fs-2 mb-2 d-block"></i> No PDF generation history found!
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light py-2 border-top no-print">
                <p class="mb-0 fs-11 text-600 text-center">Your Base64 PDF files are securely stored in the database.</p>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>