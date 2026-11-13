<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

// टाइमज़ोन सेटिंग्स
date_default_timezone_set("Asia/Kolkata"); 

$user = $udata['phone'];
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-list text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">RTO History</h6>
                        <h4 class="text-primary fw-bold mb-0">Vehicle Challan <span class="fw-medium">History List</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="challan_check.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold">
                        <i class="fas fa-plus me-1"></i> New Challan Check
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border mb-4">
    <div class="card-header bg-light border-bottom py-3">
        <h5 class="mb-0 text-900 fw-bold"><i class="fas fa-history me-2"></i> Your Recent Requests</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped fs-10 mb-0 text-center table-hover">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">#</th>
                        <th class="py-2">Application No</th>
                        <th class="py-2">Vehicle Number</th>
                        <th class="py-2">Owner Name</th>
                        <th class="py-2">Total Challans</th>
                        <th class="py-2">Date & Time</th>
                        <th class="py-2">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // डेटाबेस से इस यूजर का इतिहास निकालना
                    $query = "SELECT * FROM `vehicle_challans` WHERE `username` = '$user' ORDER BY `id` DESC";
                    $result = mysqli_query($DarkXen_conn, $query);

                    if ($result && mysqli_num_rows($result) > 0) {
                        $count = 1;
                        while ($row = mysqli_fetch_assoc($result)) {
                            $app_no = htmlspecialchars($row['application_no']);
                            $v_no = htmlspecialchars($row['vehicle_no']);
                            $owner = htmlspecialchars($row['owner_name'] ?? 'N/A');
                            $total_c = (int)($row['total_challans'] ?? 0);
                            $date = date('d M Y, h:i A', strtotime($row['date']));
                            
                            // चालान की स्थिति के अनुसार बैज कलर बदलना
                            if ($total_c > 0) {
                                $status_badge = '<span class="badge bg-danger-subtle text-danger fw-bold"><i class="fas fa-exclamation-circle me-1"></i> Challan Found</span>';
                            } else {
                                $status_badge = '<span class="badge bg-success-subtle text-success fw-bold"><i class="fas fa-check-circle me-1"></i> No Challan</span>';
                            }
                            ?>
                            <tr class="align-middle">
                                <td><?= $count++; ?></td>
                                <td class="fw-semi-bold text-primary"><?= $app_no; ?></td>
                                <td class="fw-bold text-uppercase text-900"><?= $v_no; ?></td>
                                <td class="text-start ps-3"><?= $owner; ?></td>
                                <td>
                                    <span class="badge <?= $total_c > 0 ? 'bg-danger' : 'bg-secondary'; ?> rounded-pill px-2">
                                        <?= $total_c; ?>
                                    </span>
                                </td>
                                <td class="text-muted"><?= $date; ?></td>
                                <td><?= $status_badge; ?></td>
                            </tr>
                            <?php
                        }
                    } else {
                        // इतिहास खाली होने पर संदेश
                        echo '<tr><td colspan="7" class="py-4 text-center fw-bold text-danger fs-9">No Challan Check History Found!</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>