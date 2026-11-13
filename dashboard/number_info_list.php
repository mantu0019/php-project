<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata");
$appliedby =$udata['phone'];

// Fetch History based on new schema
$query = "SELECT * FROM `number_info_history` WHERE `username` = '$appliedby' ORDER BY `id` DESC";
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
                        <h6 class="text-primary fs-10 mb-0">Search Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Mobile Search <span class="text-info fw-medium">History</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="number_info.php" class="btn btn-sm btn-primary shadow-none fw-bold">
                        <i class="fas fa-search me-1"></i> New Search
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border border-top border-4 border-info">
            <div class="card-header bg-light border-bottom py-3">
                <h5 class="mb-0 text-dark fw-bold"><i class="fas fa-list me-2 text-info"></i> All Extracted Records</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive scrollbar">
                    <table class="table table-sm table-striped table-bordered align-middle fs-10 mb-0">
                        <thead class="bg-200">
                            <tr>
                                <th class="text-center">Sl No.</th>
                                <th>App ID</th>
                                <th>Searched Mobile</th>
                                <th>Found Name</th>
                                <th>Circle</th>
                                <th>Charge</th>
                                <th class="text-center">Date & Time</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($result && mysqli_num_rows($result) > 0) {$sl = 1;
                                while ($row = mysqli_fetch_assoc($result)) {
                                    // Converting row to JSON string safely to pass to Modal
                                    $row_json = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr>
                                <td class="text-center fw-bold text-dark"><?= $sl++; ?></td>
                                <td class="fw-bold text-primary font-monospace"><?= htmlspecialchars($row['application_no']); ?></td>
                                <td class="fw-bold text-success font-monospace"><?= htmlspecialchars($row['searched_mobile']); ?></td>
                                <td class="fw-bold text-dark text-uppercase"><?= htmlspecialchars($row['found_name']); ?></td>
                                <td class="text-muted fw-bold"><?= htmlspecialchars($row['found_circle']); ?></td>
                                <td class="text-danger fw-bold">₹<?= htmlspecialchars($row['charge']); ?></td>
                                <td class="text-center text-muted fw-medium"><?= date('d M Y, h:i A', strtotime($row['date'])); ?></td>
                                <td class="text-center"> 
                                    <button type="button" class="btn btn-info btn-sm fw-bold shadow-none view-btn" data-info='<?= $row_json ?>'>
                                        <i class="fas fa-eye me-1"></i> View
                                    </button>
                                </td>
                            </tr>
                            <?php 
                                }
                            } else {
                            ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted fw-bold">
                                    <i class="fas fa-folder-open fs-2 mb-2 d-block"></i> No search history found!
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

<!-- Modal for Viewing Full Details -->
<div class="modal fade" id="infoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-light border-bottom-0">
        <h5 class="modal-title fw-bold text-primary">Detail Report (Mobile: <span id="modal_mobile" class="text-dark"></span>)</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 bg-body-tertiary">
        <div class="card shadow-none border">
            <div class="card-body fs-10 p-3">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th class="ps-0 text-700" width="35%">Owner Name:</th><td class="text-primary fw-bold text-uppercase" id="modal_name"></td></tr>
                    <tr><th class="ps-0 text-700">Father's Name:</th><td class="text-900 fw-bold text-uppercase" id="modal_fname"></td></tr>
                    <tr><th class="ps-0 text-700">Telecom Circle:</th><td class="text-success fw-bold" id="modal_circle"></td></tr>
                    <tr><th class="ps-0 text-700">Alt Mobile:</th><td class="text-900 font-monospace" id="modal_alt"></td></tr>
                    <tr><th class="ps-0 text-700">Email ID:</th><td class="text-900" id="modal_email"></td></tr>
                    <tr><th class="ps-0 text-700">ID Proof:</th><td><span class="badge bg-secondary-subtle text-secondary border" id="modal_id_proof"></span></td></tr>
                    <tr><th class="ps-0 text-700 align-top">Full Address:</th><td class="text-800 small text-uppercase" id="modal_address"></td></tr>
                </table>
            </div>
        </div>
      </div>
      <div class="modal-footer bg-light py-2 border-top-0">
        <button type="button" class="btn btn-secondary btn-sm fw-bold shadow-none" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(document).ready(function() {$('.view-btn').on('click', function() {
        // Parse the JSON data bound to the button
        let data = $(this).data('info');
        
        // Fill modal fields
        $('#modal_mobile').text(data.searched_mobile);
        $('#modal_name').text(data.found_name);
        $('#modal_fname').text(data.found_father_name);
        $('#modal_circle').text(data.found_circle);
        $('#modal_alt').text(data.found_alt_mobile);
        $('#modal_email').text(data.found_email);
        $('#modal_id_proof').text(data.found_id_number);
        $('#modal_address').text(data.found_address);
        
        // Show Modal
        var myModal = new bootstrap.Modal(document.getElementById('infoModal'));
        myModal.show();
    });
});
</script>

<?php include('footer.php'); ?>