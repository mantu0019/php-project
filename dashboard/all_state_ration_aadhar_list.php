<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 
$appliedby =$udata['phone'];

// Fetch History Data
$query = "SELECT * FROM `all_state_ration_aadhar_history` WHERE `username` = '$appliedby' ORDER BY `id` DESC";
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
                        <h6 class="text-primary fs-10 mb-0">National Services</h6>
                        <h4 class="text-primary fw-bold mb-0">All State Search <span class="text-info fw-medium">History</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="all_state_ration_aadhar.php" class="btn btn-sm btn-primary shadow-none fw-bold">
                        <i class="fas fa-search me-1"></i> New Search
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
                <h5 class="mb-0 text-white fs-0"><i class="fas fa-list me-2"></i> All Extracted Records</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive scrollbar">
                    <table class="table table-sm table-striped table-bordered align-middle fs-10 mb-0">
                        <thead class="bg-200">
                            <tr>
                                <th class="text-center">Sl No.</th>
                                <th>Ration Number</th>
                                <th class="text-center">Total Members</th>
                                <th>Deducted Charge</th>
                                <th class="text-center">Date & Time</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($result && mysqli_num_rows($result) > 0) {$sl = 1;
                                while ($row = mysqli_fetch_assoc($result)) {
                                    // Properly escape JSON Data for button attribute
                                    $members_json = htmlspecialchars($row['members_data'], ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr>
                                <td class="text-center fw-bold text-dark"><?= $sl++; ?></td>
                                <td class="fw-bold text-dark font-monospace"><?= htmlspecialchars($row['ration_no']); ?></td>
                                <td class="text-center"><span class="badge bg-info px-2 py-1"><?= htmlspecialchars($row['total_members']); ?></span></td>
                                <td class="text-danger fw-bold">₹<?= htmlspecialchars($row['charge']); ?></td>
                                <td class="text-center text-muted fw-medium"><?= date('d M Y, h:i A', strtotime($row['date'])); ?></td>
                                <td class="text-center"> 
                                    <button type="button" class="btn btn-success btn-sm fw-bold shadow-none view-btn" data-json='<?= $members_json ?>' data-ration="<?= htmlspecialchars($row['ration_no']); ?>">
                                        <i class="fas fa-eye me-1"></i> View Data
                                    </button>
                                </td>
                            </tr>
                            <?php 
                                }
                            } else {
                            ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted fw-bold">
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

<!-- Bootstrap 5 Modal for Viewing Member Details -->
<div class="modal fade" id="membersModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-light border-bottom-0">
        <h5 class="modal-title fw-bold text-primary">Family List (Ration No: <span id="modal_ration_no" class="text-dark"></span>)</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped align-middle fs-10 mb-0">
                <thead class="bg-200">
                    <tr>
                        <th class="text-center" style="width:10%;">#</th>
                        <th>Member Name</th>
                        <th>Aadhar Number</th>
                    </tr>
                </thead>
                <tbody id="members_tbody">
                    <!-- Data binds dynamically here -->
                </tbody>
            </table>
        </div>
      </div>
      <div class="modal-footer bg-light py-2 border-top-0">
        <button type="button" class="btn btn-secondary btn-sm fw-bold shadow-none" data-bs-dismiss="modal">Close Window</button>
      </div>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(document).ready(function() {
    
    // Bind click event to View buttons
    $('.view-btn').on('click', function() {
        let ration_no = $(this).data('ration');
        let members_data = $(this).data('json');$('#modal_ration_no').text(ration_no);
        let tbody = '';
        
        if (members_data && members_data.length > 0) {
            $.each(members_data, function(index, item) {
                tbody += `<tr>
                    <td class="text-center fw-bold">${index + 1}</td>
                    <td class="fw-bold text-primary text-uppercase">${item.name || 'N/A'}</td>
                    <td class="fw-bold text-success font-monospace">${item.aadhaar || 'N/A'}</td>
                </tr>`;
            });
        } else {
            tbody = `<tr><td colspan="3" class="text-center text-muted py-3 fw-bold">No members data available.</td></tr>`;
        }
        
        $('#members_tbody').html(tbody);
        
        // Render Modal
        var myModal = new bootstrap.Modal(document.getElementById('membersModal'));
        myModal.show();
    });
    
});
</script>

<?php include('footer.php'); ?>