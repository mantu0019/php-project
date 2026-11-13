<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 
$user = $udata['phone'];
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-warning-subtle shadow-none me-3">
                        <span class="fas fa-list text-warning fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-warning fs-10 mb-0">RTO Records</h6>
                        <h4 class="text-primary fw-bold mb-0">RC PRO PDF <span class="fw-medium">History List</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="rc_pro.php" class="btn btn-sm btn-outline-warning text-dark shadow-none fw-bold">
                        <i class="fas fa-plus me-1"></i> New Request
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border mb-4">
    <div class="card-header bg-light border-bottom py-3">
        <h5 class="mb-0 text-900 fw-bold"><i class="fas fa-history me-2 text-warning"></i> Your Generated Documents</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped fs-10 mb-0 text-center table-hover">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">#</th>
                        <th class="py-2">Application No</th>
                        <th class="py-2">Vehicle Number</th>
                        <th class="py-2">Generated Date</th>
                        <th class="py-2">Status</th>
                        <th class="py-2">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "SELECT * FROM `rc_pdf_history` WHERE `username` = '$user' ORDER BY `id` DESC";
                    $result = mysqli_query($DarkXen_conn, $query);

                    if ($result && mysqli_num_rows($result) > 0) {
                        $count = 1;
                        while ($row = mysqli_fetch_assoc($result)) {
                            $app_no = htmlspecialchars($row['application_no']);
                            $v_no = htmlspecialchars($row['vehicle_no']);
                            $path = htmlspecialchars($row['pdf_path']);
                            $date = date('d M Y, h:i A', strtotime($row['date']));
                            ?>
                            <tr class="align-middle">
                                <td><?= $count++; ?></td>
                                <td class="fw-semi-bold text-primary"><?= $app_no; ?></td>
                                <td class="fw-bold text-uppercase text-900"><?= $v_no; ?></td>
                                <td class="text-muted"><?= $date; ?></td>
                                <td>
                                    <span class="badge bg-success-subtle text-success fw-bold">
                                        <i class="fas fa-check me-1"></i> Success
                                    </span>
                                </td>
                                <td>
                                    <?php if (file_exists($path)): ?>
                                        <a href="<?= $path; ?>" target="_blank" class="btn btn-xs btn-primary px-2 fs-11 shadow-none">
                                            <i class="fas fa-download me-1"></i> Download
                                        </a>
                                    <?php else: ?>
                                        <span class="text-danger fw-semi-bold fs-11"><i class="fas fa-times-circle me-1"></i> File Removed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo '<tr><td colspan="6" class="py-4 text-center fw-bold text-danger fs-9">No RC Print Logs Found in History!</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>