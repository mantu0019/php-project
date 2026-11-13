<?php
// ==========================================
//        DARKXEN API - CORE SYSTEM
// ==========================================
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 
$user =$udata['phone'];
?>

<!-- Header Section -->
<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-list text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Banking History</h6>
                        <h4 class="text-primary fw-bold mb-0">NPCI Status <span class="fw-medium">History List</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="npci_status.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold">
                        <i class="fas fa-plus me-1"></i> Check New Status
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- History Table Section -->
<div class="card shadow-sm border mb-4">
    <div class="card-header bg-light border-bottom py-3">
        <h5 class="mb-0 text-900 fw-bold"><i class="fas fa-history me-2"></i> All NPCI Request Logs</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped fs-10 mb-0 text-center table-hover align-middle">
                <thead class="bg-200 text-900 text-uppercase tracking-wide">
                    <tr>
                        <th class="py-2 text-center">#</th>
                        <th class="py-2 text-start">Application No</th>
                        <th class="py-2 text-start">Customer Details</th>
                        <th class="py-2 text-center">Aadhaar No</th>
                        <th class="py-2 text-start">Mapped Bank</th>
                        <th class="py-2 text-center">Date & Time</th>
                        <th class="py-2 text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "SELECT * FROM `npci_status_history` WHERE `username` = '$user' ORDER BY `id` DESC";
                    $result = mysqli_query($DarkXen_conn,$query);

                    if ($result && mysqli_num_rows($result) > 0) {$count = 1;
                        while ($row = mysqli_fetch_assoc($result)) {
                            // Extract Data
                            $app_no = htmlspecialchars($row['application_no']);
                            $v_id = htmlspecialchars($row['aadhar_no']);
                            $bank = htmlspecialchars($row['bank_name'] ?? 'N/A');
                            $status_msg = htmlspecialchars($row['status_msg'] ?? 'Active');
                            $date = date('d M Y, h:i A', strtotime($row['date']));
                            
                            // Handling New API Fields (Requires updating SQL table to include these columns)
                            // If these columns don't exist in your DB yet, it will fallback to "N/A"
                            $cust_name = htmlspecialchars($row['customer_name'] ?? 'N/A');
                            $cust_mobile = htmlspecialchars($row['mobile_no'] ?? 'N/A');

                            // Status Badge Logic
                            if (strtolower($status_msg) === 'active' || $status_msg === 'A') {$status_badge = '<span class="badge bg-success-subtle text-success fw-bold px-2 py-1"><i class="fas fa-check-circle me-1"></i> Active</span>';
                            } else {
                                $status_badge = '<span class="badge bg-danger-subtle text-danger fw-bold px-2 py-1"><i class="fas fa-times-circle me-1"></i> Inactive</span>';
                            }
                            ?>
                            <tr class="align-middle transition-base">
                                <td class="text-center text-muted fw-bold"><?= $count++; ?></td>
                                
                                <td class="text-start">
                                    <span class="fw-semi-bold text-primary font-monospace"><?= $app_no; ?></span>
                                </td>
                                
                                <td class="text-start">
                                    <div class="fw-bold text-dark mb-1"><?= $cust_name; ?></div>
                                    <div class="text-muted fs-11"><i class="fas fa-phone-alt me-1"></i><?= $cust_mobile; ?></div>
                                </td>
                                
                                <td class="text-center fw-bold text-secondary tracking-wide">
                                    <?= $v_id; ?>
                                </td>
                                
                                <td class="text-start">
                                    <span class="fw-bold text-primary fs-10"><i class="fas fa-university text-muted me-2"></i><?= $bank; ?></span>
                                </td>
                                
                                <td class="text-center">
                                    <div class="text-900 fw-semi-bold"><?= date('d M Y', strtotime($row['date'])); ?></div>
                                    <div class="text-muted fs-11"><?= date('h:i A', strtotime($row['date'])); ?></div>
                                </td>
                                
                                <td class="text-center">
                                    <?= $status_badge; ?>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo '<tr>
                                <td colspan="7" class="py-5 text-center">
                                    <i class="fas fa-box-open fs-2 text-300 mb-2"></i>
                                    <h6 class="fw-bold text-danger mb-0">No NPCI Check Log History Found!</h6>
                                    <p class="text-muted fs-11">You have not checked any NPCI status yet.</p>
                                </td>
                              </tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>