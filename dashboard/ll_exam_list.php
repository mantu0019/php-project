<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 
$appliedby =$udata['phone'];

$query = "SELECT * FROM `ll_exam_history` WHERE `username` = '$appliedby' ORDER BY `id` DESC";
$result = mysqli_query($DarkXen_conn,$query);
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3" style="width: 50px; height: 50px;">
                        <span class="fas fa-list-alt text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Transport Services</h6>
                        <h4 class="text-primary fw-bold mb-0">LL Exam <span class="text-info fw-medium">Live Status</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="ll_exam.php" class="btn btn-sm btn-primary shadow-none fw-bold">
                        <i class="fas fa-plus me-1"></i> New Request
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
                <h5 class="mb-0 text-white fs-0"><i class="fas fa-server me-2"></i> All Exam Requests</h5>
                <span class="badge bg-white text-success shadow-sm" id="syncStatus">
                    <i class="fas fa-sync fa-spin me-1"></i> Auto-Sync Active
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive scrollbar">
                    <table class="table table-sm table-striped table-bordered align-middle fs-10 mb-0">
                        <thead class="bg-200">
                            <tr>
                                <th class="text-center">Sl No.</th>
                                <th>Name</th>
                                <th>Application No</th>
                                <th>Password</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Submission Time</th>
                                <th class="text-center">Result Image</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($result && mysqli_num_rows($result) > 0) {$sl = 1;
                                while ($row = mysqli_fetch_assoc($result)) {
                            ?>
                            <tr>
                                <td class="text-center fw-bold text-dark"><?= $sl++; ?></td>
                                <td class="fw-bold text-primary text-uppercase"><?= htmlspecialchars($row['name']); ?></td>
                                <td class="fw-bold font-monospace"><?= htmlspecialchars($row['application_no']); ?></td>
                                <td class="text-danger fw-bold"><?= htmlspecialchars($row['password']); ?></td>
                                
                                <td class="text-center">
                                    <?php if($row['status'] === 'pending'): ?>
                                        <span class="badge bg-warning text-dark"><i class="fas fa-spinner fa-spin me-1"></i> Processing</span>
                                    <?php elseif($row['status'] === 'success'): ?>
                                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Cleared</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Failed</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="text-center text-muted fw-medium"><?= date('d M Y, h:i A', strtotime($row['date'])); ?></td>
                                
                                <td class="text-center"> 
                                    <?php if($row['status'] === 'success' && !empty($row['image_base64'])): ?>
                                        <button type="button" class="btn btn-success btn-sm fw-bold shadow-none view-img-btn" data-img="<?= htmlspecialchars($row['image_base64']); ?>">
                                            <i class="fas fa-image me-1"></i> View Result
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-secondary btn-sm fw-bold shadow-none" disabled>
                                            <i class="fas fa-hourglass-half"></i> Wait...
                                        </button>
                                    <?php endif; ?>
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

<!-- Modal for Viewing Result Image -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-light border-bottom-0">
        <h5 class="modal-title fw-bold text-primary">Exam Result Preview</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-3">
         <img id="result_image" src="" alt="Result" class="img-fluid rounded border shadow-sm" style="max-height: 70vh;">
      </div>
      <div class="modal-footer bg-light py-2 border-top-0 d-flex justify-content-between">
        <a id="download_btn" href="#" download="LL_Result.png" class="btn btn-danger btn-sm fw-bold shadow-none"><i class="fas fa-download me-1"></i> Download File</a>
        <button type="button" class="btn btn-secondary btn-sm fw-bold shadow-none" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(document).ready(function() {
    
    // View Image Button Logic
    $('.view-img-btn').on('click', function() {
        let imgSrc = $(this).data('img');$('#result_image').attr('src', imgSrc);
        $('#download_btn').attr('href', imgSrc);
        var myModal = new bootstrap.Modal(document.getElementById('imageModal'));
        myModal.show();
    });
    
    // ==========================================
    // AUTOMATIC BACKGROUND POLLING (Every 10 Seconds)
    // ==========================================
    setInterval(function(){
        $.ajax({
            url: 'll_ajax_update.php',
            type: 'GET',
            success: function(response) {
                try {
                    let res = JSON.parse(response);
                    // Agar ek bhi record update (Pending se success) hua, toh page reload kar do
                    if(res.updated > 0) {
                        location.reload(); 
                    }
                } catch(e) {
                    console.log("Parsing error in AJAX response.");
                }
            }
        });
    }, 10000); // 10000 ms = 10 Seconds
    
});
</script>

<?php include('footer.php'); ?>