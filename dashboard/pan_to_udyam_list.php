<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 
$appliedby =$udata['phone'];
$user_role =$udata['role'] ?? 'User';

// Role based query
if ($user_role === 'Admin') {$query = "SELECT * FROM `pan_udyam_history` ORDER BY id DESC";
} else {
    $query = "SELECT * FROM `pan_udyam_history` WHERE `username` = '$appliedby' ORDER BY id DESC";
}

$result = mysqli_query($DarkXen_conn,$query);
?>

<style>
    /* CSS FOR SINGLE DATA PRINTING */
    @media print {
        body * { visibility: hidden; }
        #printModalArea, #printModalArea * { visibility: visible; }
        #printModalArea { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
        .modal { position: absolute; left: 0; top: 0; margin: 0; padding: 0; overflow: visible!important; }
        .modal-dialog { max-width: 100%; margin: 0; }
        .modal-content { border: none; box-shadow: none; }
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
                        <h6 class="text-primary fs-10 mb-0">MSME Services</h6>
                        <h4 class="text-primary fw-bold mb-0">PAN to Udyam <span class="text-info fw-medium">History</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="pan_to_udyam.php" class="btn btn-sm btn-primary shadow-none fw-bold">
                        <i class="fas fa-search-plus me-1"></i> New Find Search
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4 no-print">
    <div class="col-12">
        <div class="card shadow-sm border border-top border-4 border-info">
            <div class="card-header bg-light py-3 border-bottom">
                <h5 class="mb-0 text-dark fw-bold"><i class="fas fa-list me-2 text-info"></i> All Processed Find Requests</h5>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive scrollbar">
                    <table class="table table-sm table-striped table-bordered align-middle fs-10 mb-0">
                        <thead class="bg-200">
                            <tr>
                                <th class="text-center py-3">Sl No.</th>
                                <th class="py-3">Verification ID</th>
                                <?php if($user_role === 'Admin'): ?><th class="py-3">User</th><?php endif; ?>
                                <th class="py-3">Searched PAN</th>
                                <th class="py-3">Fetched Udyam No</th>
                                <th class="text-center py-3">Status</th>
                                <th class="text-center py-3">Date & Time</th>
                                <th class="text-center py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($result && mysqli_num_rows($result) > 0) {$sl = 1;
                                while ($row = mysqli_fetch_assoc($result)) {
                                    $row_json = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr>
                                <td class="text-center fw-bold text-dark"><?= $sl++; ?></td>
                                <td class="fw-bold text-primary font-monospace fs-11"><?= htmlspecialchars($row['verification_id']); ?></td>
                                <?php if($user_role === 'Admin'): ?>
                                    <td class="fw-medium text-dark"><?= htmlspecialchars($row['username']); ?></td>
                                <?php endif; ?>
                                <td class="fw-bold text-dark font-monospace text-uppercase"><?= htmlspecialchars($row['pan_no']); ?></td>
                                <td class="fw-bold text-success font-monospace">
                                    <i class="fas fa-certificate me-1 text-muted"></i> <?= htmlspecialchars($row['udyam_no']); ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-subtle-success fs-11 px-2 py-1 border border-success">
                                        <?= htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                                <td class="text-center text-muted fw-medium"><?= date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-info btn-sm fw-bold shadow-none view-btn" data-info='<?= $row_json ?>'>
                                        <i class="fas fa-print me-1"></i> Print Data
                                    </button>
                                </td>
                            </tr>
                            <?php 
                                }
                            } else {
                                $colspan = ($user_role === 'Admin') ? 8 : 7;
                            ?>
                            <tr>
                                <td colspan="<?= $colspan ?>" class="text-center py-5 text-muted fw-bold">
                                    <i class="fas fa-folder-open fs-2 mb-2 d-block"></i> No search history found!
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light py-2 border-top">
                <p class="mb-0 fs-11 text-600 text-center">Click on 'Print Data' to view and print individual Udyam record.</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Viewing & Printing Individual Profile Details -->
<div class="modal fade" id="infoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-light border-bottom-0 no-print">
        <h5 class="modal-title fw-bold text-primary"><i class="fas fa-file-invoice me-2"></i> Extracted Udyam Record</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <!-- Yahi Area Print Hoga -->
      <div class="modal-body p-0" id="printModalArea">
        <div class="card shadow-none border-0 rounded-0">
            <div class="card-body p-4">
                
                <div class="text-center mb-4 pb-3 border-bottom">
                    <h4 class="fw-bold text-dark text-uppercase mb-1">MSME Udyam Report</h4>
                    <p class="mb-0 text-muted fs-11">Generated on: <span id="modal_date" class="fw-bold"></span></p>
                </div>
                
                <table class="table table-sm table-bordered mb-0 fs-10 align-middle">
                    <tr>
                        <th class="bg-light text-700 w-50 py-2">Verification ID:</th>
                        <td class="text-dark fw-bold font-monospace py-2" id="modal_appno"></td>
                    </tr>
                    <tr>
                        <th class="bg-light text-700 py-2">Reference ID:</th>
                        <td class="text-dark fw-bold font-monospace py-2" id="modal_ref"></td>
                    </tr>
                    <tr>
                        <th class="bg-light text-700 py-2">Searched PAN Number:</th>
                        <td class="text-primary fw-bold font-monospace text-uppercase py-2" id="modal_pan"></td>
                    </tr>
                    <tr>
                        <th class="bg-light text-700 py-2">Retrieved Udyam Number:</th>
                        <td class="text-success fw-bold font-monospace py-2 fs-11 bg-success-subtle" id="modal_udyam"></td>
                    </tr>
                    <tr>
                        <th class="bg-light text-700 py-2">Transaction Status:</th>
                        <td class="text-dark fw-bold py-2" id="modal_status"></td>
                    </tr>
                </table>
                
                <div class="mt-4 pt-3 border-top text-center">
                    <p class="mb-0 fs-11 text-600">This is a system generated MSME registry slip. No signature required.</p>
                </div>
            </div>
        </div>
      </div>
      
      <div class="modal-footer bg-light py-2 border-top-0 d-flex justify-content-between no-print">
        <button type="button" class="btn btn-success btn-sm fw-bold shadow-none" onclick="printSingleData()"><i class="fas fa-print me-1"></i> Print This Slip</button>
        <button type="button" class="btn btn-secondary btn-sm fw-bold shadow-none" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {$('.view-btn').on('click', function() {
        let data = $(this).data('info');
        
        // Modal ke andar data bharna
        $('#modal_appno').text(data.verification_id);
        $('#modal_ref').text(data.reference_id);
        $('#modal_pan').text(data.pan_no);
        $('#modal_udyam').text(data.udyam_no);
        $('#modal_status').text(data.status);
        $('#modal_date').text(data.created_at);
        
        // Modal Show karna
        var myModal = new bootstrap.Modal(document.getElementById('infoModal'));
        myModal.show();
    });
});

function printSingleData() {
    window.print();
}
</script>

<?php include('footer.php'); ?>