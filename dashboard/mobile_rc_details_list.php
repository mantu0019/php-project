<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

$user =$udata['phone'];
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
                        <h6 class="text-primary fs-10 mb-0">History</h6>
                        <h4 class="text-primary fw-bold mb-0">Mobile to RC Details <span class="text-info fw-medium">History</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="mobile_to_rc_details.php" class="btn btn-sm btn-primary shadow-none fw-bold"><i class="fas fa-search me-1"></i> New Search</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 text-center fs-10 align-middle">
                        <thead class="bg-light text-900">
                            <tr>
                                <th class="py-3">#</th>
                                <th class="py-3">Mobile Number</th>
                                <th class="py-3">Details Extracted For</th>
                                <th class="py-3 text-start">Vehicle Numbers</th>
                                <th class="py-3">Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $query = mysqli_query($DarkXen_conn, "SELECT * FROM `mobile_rc_details_history` WHERE `username`='$user' ORDER BY `id` DESC");
                            if (mysqli_num_rows($query) > 0) {$count = 1;
                                while ($row = mysqli_fetch_assoc($query)) {$date = date("d M Y, h:i A", strtotime($row['date']));
                                    
                                    // Split and format vehicle numbers beautifully
                                    $vehicles = explode(", ", $row['vehicle_list']);$badges = '';
                                    foreach($vehicles as$v) {
                                        if(!empty(trim($v))) {$badges .= '<span class="badge bg-dark me-1 mb-1 fs-10">'.htmlspecialchars(trim($v)).'</span> ';
                                        }
                                    }

                                    ?>
                                    <tr>
                                        <td class="fw-medium"><?= $count++; ?></td>
                                        <td class="fw-bold text-primary"><i class="fas fa-phone-alt me-1 text-muted"></i> <?= htmlspecialchars($row['mobile_no']); ?></td>
                                        <td><span class="badge bg-success fs-10"><?= htmlspecialchars($row['vehicle_count']); ?> Vehicles</span></td>
                                        <td class="text-start"><?= $badges; ?></td>
                                        <td class="text-muted"><?= $date; ?></td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo '<tr><td colspan="5" class="text-center py-4 text-muted"><i class="fas fa-folder-open fs-3 mb-2 d-block text-300"></i> No history found</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>