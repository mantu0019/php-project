<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

$appliedby = $udata['phone'];

// Delete Record Handler (Optional)
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $del_query = mysqli_query($DarkXen_conn, "DELETE FROM `dl_verify_records` WHERE `id`='$del_id' AND `userid`='$appliedby'");
    if ($del_query) {
        echo "<script>
            Swal.fire('Deleted!', 'Record has been deleted successfully.', 'success').then(() => {
                window.location.href = 'dl_advance_list.php';
            });
        </script>";
    } else {
        echo "<script>Swal.fire('Error', 'Failed to delete record.', 'error');</script>";
    }
}

// Fetch all saved DL records for logged-in user
$query = "SELECT * FROM `dl_verify_records` WHERE `userid`='$appliedby' ORDER BY `id` DESC";
$result = mysqli_query($DarkXen_conn, $query);
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-danger-subtle shadow-none me-3">
                        <span class="fas fa-id-card text-danger fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-danger fs-10 mb-0">Transport Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Driving License Print History</h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="dl_advance.php" class="btn btn-sm btn-danger shadow-none fw-bold">
                        <i class="fas fa-plus me-1"></i> New DL Designer
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm border-top border-4 border-danger">
    <div class="card-header bg-white py-3">
        <div class="row align-items-center">
            <div class="col">
                <h5 class="mb-0 text-dark fw-bold"><i class="fas fa-list-alt me-2 text-danger"></i> Saved DL Cards</h5>
            </div>
            <div class="col-auto">
                <span class="badge bg-danger-subtle text-danger fs-11 px-3 py-2 fw-bold">
                    Total Records: <?php echo mysqli_num_rows($result); ?>
                </span>
            </div>
        </div>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0" id="dlHistoryTable">
                <thead class="table-light text-uppercase fs-11 fw-bold">
                    <tr>
                        <th class="ps-3">#</th>
                        <th>Photo</th>
                        <th>DL Number</th>
                        <th>Name & Father Name</th>
                        <th>DOB / Issue Date</th>
                        <th>Vehicles (COV)</th>
                        <th>Created Date</th>
                        <th class="text-center pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="fs-10">
                    <?php 
                    if (mysqli_num_rows($result) > 0) {
                        $sn = 1;
                        while ($row = mysqli_fetch_assoc($result)) {
                            $encoded_id = rtrim(base64_encode($row['id']), '=');
                            
                            // Decode COV (JSON / Comma Array)
                            $cov_html = '';
                            $cov_data = json_decode($row['vehicle_categories'], true);
                            if (is_array($cov_data)) {
                                foreach ($cov_data as $cov_item) {
                                    $cov_item = trim($cov_item);
                                    if (!empty($cov_item)) {
                                        $cov_html .= '<span class="badge bg-secondary me-1 mb-1">' . htmlspecialchars($cov_item) . '</span>';
                                    }
                                }
                            } else {
                                $cov_html = '<span class="badge bg-secondary">' . htmlspecialchars($row['vehicle_categories']) . '</span>';
                            }

                            // Photo preview
                            $photo_src = !empty($row['photo']) ? $row['photo'] : 'assets/img/team/avatar.png';
                    ?>
                    <tr>
                        <td class="ps-3 fw-bold text-muted"><?php echo $sn++; ?></td>
                        <td>
                            <img src="<?php echo $photo_src; ?>" alt="DL Photo" class="rounded border shadow-sm" style="width: 45px; height: 50px; object-fit: cover;">
                        </td>
                        <td>
                            <span class="fw-bold text-primary fs-9"><?php echo htmlspecialchars($row['dl_number']); ?></span><br>
                            <span class="badge bg-success-subtle text-success fs-11"><?php echo htmlspecialchars($row['dl_status'] ?? 'ACTIVE'); ?></span>
                        </td>
                        <td>
                            <strong class="text-dark"><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                            <small class="text-muted"><i class="fas fa-user-shield me-1"></i>S/O, W/O: <?php echo htmlspecialchars($row['father_name']); ?></small>
                        </td>
                        <td>
                            <i class="far fa-calendar-alt text-danger me-1"></i> DOB: <strong><?php echo htmlspecialchars($row['dob']); ?></strong><br>
                            <small class="text-muted"><i class="fas fa-certificate text-warning me-1"></i> Issue: <?php echo htmlspecialchars($row['issued_date']); ?></small>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap" style="max-width: 180px;">
                                <?php echo $cov_html; ?>
                            </div>
                        </td>
                        <td>
                            <small class="text-muted fw-bold">
                                <?php echo date('d M Y, h:i A', strtotime($row['date_created'])); ?>
                            </small>
                        </td>
                        <td class="text-center pe-3">
                            <div class="btn-group shadow-none" role="group">
                                <!-- Print Button -->
                                <a href="dacoment/print_canvas.php?searchid=<?php echo $encoded_id; ?>" target="_blank" class="btn btn-sm btn-danger shadow-none" title="Print DL Card">
                                    <i class="fas fa-print me-1"></i> Print
                                </a>
                                <!-- Delete Button -->
                                <button type="button" class="btn btn-sm btn-outline-danger shadow-none" onclick="confirmDelete(<?php echo $row['id']; ?>)" title="Delete Record">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php 
                        }
                    } else {
                    ?>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="text-muted">
                                <i class="fas fa-folder-open fs-1 text-danger-subtle mb-3"></i>
                                <h6 class="fw-bold">No Driving License Records Found!</h6>
                                <p class="fs-10 mb-2">You have not generated any DL Cards yet.</p>
                                <a href="dl_advance.php" class="btn btn-sm btn-danger">Generate First DL</a>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function confirmDelete(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This record will be permanently deleted!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'dl_advance_list.php?delete_id=' + id;
        }
    });
}
</script>

<?php include('footer.php'); ?>