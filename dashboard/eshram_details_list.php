<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 
$appliedby =$udata['phone'];
$user_role =$udata['role'] ?? 'User';

if ($user_role === 'Admin') {$query = "SELECT * FROM `eshram_details_history` ORDER BY id DESC";
} else {
    $query = "SELECT * FROM `eshram_details_history` WHERE `username` = '$appliedby' ORDER BY id DESC";
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
    
    /* Modal Image Fix for Print */
    @media print {
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
                        <h6 class="text-primary fs-10 mb-0">E-Shram Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Details Extractor <span class="text-info fw-medium">History</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="eshram_details.php" class="btn btn-sm btn-primary shadow-none fw-bold">
                        <i class="fas fa-search me-1"></i> Extract New Details
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
                        <h5 class="mb-0 text-dark fw-bold"><i class="fas fa-list me-2 text-info"></i> All Processed Profile Requests</h5>
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
                                <th class="py-3">App Reference</th>
                                <?php if($user_role === 'Admin'): ?><th class="py-3">User</th><?php endif; ?>
                                <th class="py-3">Profile Name</th>
                                <th class="py-3">DOB</th>
                                <th class="py-3">Gender</th>
                                <th class="py-3">State</th>
                                <th class="text-center py-3">Date & Time</th>
                                <th class="text-center py-3 no-print">Action</th>
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
                                <td class="fw-bold text-primary font-monospace fs-11"><?= htmlspecialchars($row['application_no']); ?></td>
                                <?php if($user_role === 'Admin'): ?>
                                    <td class="fw-medium text-dark"><?= htmlspecialchars($row['username']); ?></td>
                                <?php endif; ?>
                                <td class="fw-bold text-uppercase"><?= htmlspecialchars($row['name']); ?></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($row['dob']); ?></td>
                                <td class="text-muted fw-bold"><?= htmlspecialchars($row['gender']); ?></td>
                                <td class="text-success fw-bold text-uppercase"><?= htmlspecialchars($row['ekycstate']); ?></td>
                                <td class="text-center text-muted fw-medium"><?= date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
                                <td class="text-center no-print"> 
                                    <button class="btn btn-info btn-sm fw-bold shadow-none view-btn" data-info='<?= $row_json ?>'>
                                        <i class="fas fa-eye me-1"></i> View Profile
                                    </button>
                                </td>
                            </tr>
                            <?php 
                                }
                            } else {
                                $colspan = ($user_role === 'Admin') ? 9 : 8;
                            ?>
                            <tr>
                                <td colspan="<?= $colspan ?>" class="text-center py-5 text-muted fw-bold">
                                    <i class="fas fa-folder-open fs-2 mb-2 d-block"></i> No extraction history found!
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

<!-- Modal for Viewing Profile Details -->
<div class="modal fade" id="infoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-light border-bottom-0 no-print">
        <h5 class="modal-title fw-bold text-primary"><i class="fas fa-user-circle me-2"></i> E-Shram Profile Card</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <!-- ID Card Style Layout -->
      <div class="modal-body p-0" id="printModalArea">
        <div class="card shadow-none border-0 rounded-0">
            <div class="card-body p-4">
                
                <div class="d-flex flex-row align-items-center mb-4 pb-3 border-bottom">
                    <div class="avatar border border-3 border-success rounded p-1 shadow-sm flex-shrink-0" style="width: 100px; height: 100px;">
                        <img id="modal_photo" class="rounded w-100 h-100 object-fit-cover" src="" alt="Photo" />
                    </div>
                    <div class="ms-4">
                        <h4 class="mb-1 fw-bold text-primary text-uppercase" id="modal_name"></h4>
                        <h6 class="mb-0 text-muted fw-bold" id="modal_localname"></h6>
                        <span class="badge bg-success-subtle text-success mt-2 border px-2 py-1"><i class="fas fa-map-marker-alt me-1"></i> <span id="modal_state"></span></span>
                    </div>
                </div>
                
                <table class="table table-sm table-borderless mb-0 fs-10">
                    <tr><th class="ps-0 text-700" width="35%">Reference No:</th><td class="text-dark fw-bold font-monospace" id="modal_appno"></td></tr>
                    <tr><th class="ps-0 text-700">Date of Birth:</th><td class="text-dark fw-bold"><i class="fas fa-calendar-day me-1 text-info"></i> <span id="modal_dob"></span></td></tr>
                    <tr><th class="ps-0 text-700">Gender:</th><td class="text-dark fw-bold" id="modal_gender"></td></tr>
                    <tr><th class="ps-0 text-700">Relative Name:</th><td class="text-900 fw-bold text-uppercase" id="modal_relative"></td></tr>
                    <tr><th class="ps-0 text-700 align-top">Full Address:</th><td class="text-800 fw-bold text-uppercase" style="line-height: 1.5;" id="modal_address"></td></tr>
                </table>
                
            </div>
        </div>
      </div>
      
      <div class="modal-footer bg-light py-2 border-top-0 d-flex justify-content-between no-print">
        <button type="button" class="btn btn-dark btn-sm fw-bold shadow-none" onclick="printModal()"><i class="fas fa-print me-1"></i> Print Details</button>
        <button type="button" class="btn btn-secondary btn-sm fw-bold shadow-none" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {$('.view-btn').on('click', function() {
        let data = $(this).data('info');
        
        // Fill modal fields dynamically from DB
        $('#modal_appno').text(data.application_no);
        $('#modal_state').text(data.ekycstate);
        $('#modal_name').text(data.name);
        $('#modal_localname').text(data.localname);
        $('#modal_dob').text(data.dob);
        $('#modal_gender').text(data.gender);
        $('#modal_relative').text(data.relative_name);
        $('#modal_address').text(data.full_address);
        
        // Handle Base64 Image
        let photo = data.photo_base64;
        if(photo && photo !== '') {
            $('#modal_photo').attr('src', photo);
        } else {
            $('#modal_photo').attr('src', '../assets/img/avatar.png');
        }
        
        var myModal = new bootstrap.Modal(document.getElementById('infoModal'));
        myModal.show();
    });
});

// Specific function to print only the modal content
function printModal() {
    let printContents = document.getElementById('printModalArea').innerHTML;
    let originalContents = document.body.innerHTML;

    document.body.innerHTML = "<html><head><title>Print Report</title></head><body>" + printContents + "</body></html>";
    window.print();
    document.body.innerHTML = originalContents;
    window.location.reload(); // Reload to restore event listeners
}
</script>

<?php include('footer.php'); ?>