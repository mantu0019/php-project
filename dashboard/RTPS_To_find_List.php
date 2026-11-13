<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

$curr_user = $udata['phone'];
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-history text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Logs Center</h6>
                        <h4 class="text-primary fw-bold mb-0">RTPS Documents <span class="text-success fw-medium">History List</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="RTPS_To_find.php" class="btn btn-sm btn-primary shadow-none fw-bold"><i class="fas fa-plus-circle me-1"></i> New Request</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Logs Table Card -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light border-bottom py-3">
                <h5 class="mb-0 text-dark font-weight-600"><i class="fas fa-th-list me-2 text-primary"></i>Generated History Records</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light text-dark font-weight-600">
                            <tr>
                                <th>#Transaction ID</th>
                                <th>Application Ref Number</th>
                                <th>Applicant Name</th>
                                <th>Debited Amount</th>
                                <th>Processed At</th>
                                <th class="text-center">Action Handlers</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Fetch logged in user rows
                            $list_query = mysqli_query($DarkXen_conn, "SELECT * FROM `rtps_pdf_history` WHERE `userid` = '$curr_user' ORDER BY `id` DESC");
                            
                            if (mysqli_num_rows($list_query) > 0) {
                                while ($row = mysqli_fetch_assoc($list_query)) {
                            ?>
                                <tr>
                                    <td><strong>#<?= $row['id'] ?></strong></td>
                                    <td><code class="text-dark font-weight-600"><?= htmlspecialchars($row['application_number']) ?></code></td>
                                    <td class="font-weight-600 text-secondary text-uppercase"><?= htmlspecialchars($row['applicant_name']) ?></td>
                                    <td><span class="badge bg-danger-subtle text-danger font-weight-600 px-2 py-1">₹<?= number_format($row['fee_debited'], 2) ?></span></td>
                                    <td class="text-muted"><?= date("d-m-Y h:i A", strtotime($row['created_at'])) ?></td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <!-- Download Anchor natively via Base64 source string -->
                                            <a href="<?= $row['pdf_base64'] ?>" download="RTPS_<?= htmlspecialchars($row['application_number']) ?>.pdf" class="btn btn-danger fw-medium">
                                                <i class="fas fa-cloud-download-alt me-1"></i> Get PDF
                                            </a>
                                           
                                           
                                        </div>
                                    </td>
                                </tr>
                            <?php 
                                }
                            } else {
                            ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fas fa-folder-minus display-4 mb-3 text-secondary"></i>
                                        <p class="mb-0 fs-5">No RTPS statement downloads found in database.</p>
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