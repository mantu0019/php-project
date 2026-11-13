<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 
$appliedby =$udata['phone'];

// Fetch History
$query = "SELECT * FROM `up_ration_aadhar_history` WHERE `username` = '$appliedby' ORDER BY `id` DESC";
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
                        <h6 class="text-primary fs-10 mb-0">UP Govt Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Ration to Aadhar <span class="text-info fw-medium">History</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="up_ration_aadhar.php" class="btn btn-sm btn-primary shadow-none fw-bold">
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
                <h5 class="mb-0 text-white fs-0"><i class="fas fa-list me-2"></i> All Search Records</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive scrollbar">
                    <table class="table table-sm table-striped table-bordered align-middle fs-10 mb-0">
                        <thead class="bg-200">
                            <tr>
                                <th class="text-center">Sl No.</th>
                                <th>Order ID</th>
                                <th>Ration No.</th>
                                <th class="text-center">Total Members</th>
                                <th>Charge</th>
                                <th class="text-center">Date & Time</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($result && mysqli_num_rows($result) > 0) {$sl = 1;
                                while ($row = mysqli_fetch_assoc($result)) {
                                    // Parse JSON safely for button attributes
                                    $family_json = htmlspecialchars($row['family_data'], ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr>
                                <td class="text-center fw-bold text-dark"><?= $sl++; ?></td>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($row['order_id']); ?></td>
                                <td class="fw-bold text-dark font-monospace"><?= htmlspecialchars($row['ration_no']); ?></td>
                                <td class="text-center"><span class="badge bg-info px-2 py-1"><?= htmlspecialchars($row['total_members']); ?></span></td>
                                <td class="text-danger fw-bold">₹<?= htmlspecialchars($row['charge']); ?></td>
                                <td class="text-center text-muted fw-medium"><?= date('d M Y, h:i A', strtotime($row['date'])); ?></td>
                                <td class="text-center"> 
                                    <button type="button" class="btn btn-success btn-sm fw-bold shadow-none view-btn" data-json='<?= $family_json ?>' data-ration="<?= htmlspecialchars($row['ration_no']); ?>">
                                        <i class="fas fa-eye me-1"></i> View
                                    </button>
                                </td>
                            </tr>
                            <?php 
                                }
                            } else {
                            ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted fw-bold">
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

<!-- Modal for Viewing Family Data -->
<div class="modal fade" id="familyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-light border-bottom-0">
        <h5 class="modal-title fw-bold text-primary">Family Details (Ration No: <span id="modal_ration_no" class="text-dark"></span>)</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped align-middle fs-10 mb-0">
                <thead class="bg-200">
                    <tr>
                        <th class="text-center">#</th>
                        <th>Name</th>
                        <th>Relation</th>
                        <th>Father Name</th>
                        <th>DOB</th>
                        <th>Gender</th>
                        <th>UID (Aadhar)</th>
                    </tr>
                </thead>
                <tbody id="family_tbody">
                    <!-- Data will be appended here via JS -->
                </tbody>
            </table>
        </div>
      </div>
      <div class="modal-footer bg-light py-2 border-top-0">
        <button type="button" class="btn btn-secondary btn-sm fw-bold shadow-none" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- jQuery (Required for the script to work) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
// FIX: Corrected syntax from $(document.ready to$(document).ready
$(document).ready(function() {$('.view-btn').on('click', function() {
        let ration_no = $(this).data('ration');
        let family_data = $(this).data('json');$('#modal_ration_no').text(ration_no);
        let tbody = '';
        
        if (family_data && family_data.length > 0) {
            $.each(family_data, function(index, item) {
                tbody += `<tr>
                    <td class="text-center fw-bold">${index + 1}</td>
                    <td class="fw-bold text-primary">${item.name || 'N/A'}</td>
                    <td><span class="badge bg-secondary-subtle text-secondary border border-secondary">${item.relation || 'N/A'}</span></td>
                    <td class="text-muted">${item.father_name || 'N/A'}</td>
                    <td class="text-muted fw-medium">${item.dob || 'N/A'}</td>
                    <td>${item.gender || 'N/A'}</td>
                    <td class="fw-bold text-success font-monospace">${item.uid || 'N/A'}</td>
                </tr>`;
            });
        } else {
            tbody = `<tr><td colspan="7" class="text-center text-muted py-3 fw-bold">No members data available.</td></tr>`;
        }
        
        $('#family_tbody').html(tbody);
        
        // Show the modal using Bootstrap 5 method
        var myModal = new bootstrap.Modal(document.getElementById('familyModal'));
        myModal.show();
    });
    
});
</script>

<?php include('footer.php'); ?>