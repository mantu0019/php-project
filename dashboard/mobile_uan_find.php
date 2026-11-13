<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 

// ================= INIT & FEE SETTINGS =================
$fee = 10.00; // Default Fee Fallback
$fee_query = mysqli_query($DarkXen_conn, "SELECT `mobile_uan_fee` FROM `service_free` WHERE `id` = 1 LIMIT 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if(isset($fee_row['mobile_uan_fee']) && $fee_row['mobile_uan_fee'] != '') {
        $fee = (float)$fee_row['mobile_uan_fee'];
    }
}

$resdata = [];
$error_message = '';
$success_message = '';
$appliedby = $udata['phone'] ?? '';
$date_time = date('Y-m-d H:i:s');

// ================= ACTION =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['find_uan_btn'])) {
    
    // Live Wallet Fetch
    $chk_user = mysqli_query($DarkXen_conn, "SELECT `balance` FROM `users` WHERE `phone` = '$appliedby' LIMIT 1");
    $user_row = mysqli_fetch_assoc($chk_user);
    $wallet_amount = isset($user_row['balance']) ? (float)$user_row['balance'] : 0.00;

    $mobile_no = trim($_POST['mobile_no'] ?? '');
    
    if (!preg_match('/^[0-9]{10}$/', $mobile_no)) {
        $error_message = "कृपया सही 10-अंकीय मोबाइल नंबर दर्ज करें!";
    } elseif ($wallet_amount < $fee) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
              <script>document.addEventListener('DOMContentLoaded', function() {
                  Swal.fire('Low Balance!', 'Please recharge now. Required: ₹" . $fee . "', 'error').then(() => { window.location='wallet.php'; });
              });</script>";
        exit;
    } else {
        // ================= API CONFIG =================
        $api_key = urlencode($DarkXen_apikey ?? '');
        $api_url_host = $DarkXen_url;
        $url = "https://" . $api_url_host . "/api/v3/NEW/mobile-to-uan-num-instant.php?apiKey=$api_key&mobile=$mobile_no";

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 45,
        ));
        $response = curl_exec($curl);
        $curl_error = curl_error($curl);
        curl_close($curl);

        if ($curl_error) {
            $error_message = "API Connection Failed: Server Timeout.";
        } else {
            $resdata = json_decode($response, true) ?? [];

            // API Status Check
            if (isset($resdata['sampleCode']) && $resdata['sampleCode'] == "200" && isset($resdata['data'])) {
                
                $data = $resdata['data'];
                $app_no = $resdata['orderId'] ?? 'UAN_' . time();
                
                // Deep Mapping variables
                $name = $data['name'] ?? 'N/A';
                $fetched_uan = $data['matching_uan'] ?? 'N/A';
                $gender = $data['gender'] ?? 'N/A';
                $dob = $data['date_of_birth'] ?? 'N/A';
                
                // Employment mapping
                $is_emp = ($data['is_employed'] == true || $data['is_employed'] == "true") ? 'Yes' : 'No';
                $company = $data['establishment_name'] ?? 'N/A';
                $doj = $data['date_of_joining'] ?? 'N/A';
                $doe = $data['date_of_exit'] ?? 'N/A';

                mysqli_begin_transaction($DarkXen_conn);
                try {
                    // 1. Wallet Deduct
                    $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");
                    if (!$update_wallet) throw new Exception("Wallet Update Failed.");
                    
                    $new_balance = $wallet_amount - $fee;

                    // 2. Wallet History
                    $purpose = "Mobile to UAN: " . $mobile_no;
                    $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES (?, ?, ?, ?, '1', 'Debit')");
                    mysqli_stmt_bind_param($stmt_wallet, "sdds", $appliedby, $fee, $new_balance, $purpose);
                    if (!mysqli_stmt_execute($stmt_wallet)) throw new Exception("Wallet History Insert Failed.");

                    // 3. Database Save 
                    $insert_sql = "INSERT INTO `mobile_uan_history` (`application_no`, `username`, `searched_mobile`, `name`, `gender`, `dob`, `fetched_uan`, `company_name`, `date_of_joining`, `date_of_exit`, `is_employed`, `charge`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt_hist = mysqli_prepare($DarkXen_conn,$insert_sql);
                    
                    if ($stmt_hist) {
                        mysqli_stmt_bind_param($stmt_hist, "sssssssssssds", $app_no, $appliedby,$mobile_no, $name,$gender, $dob,$fetched_uan, $company,$doj, $doe,$is_emp, $fee,$date_time);
                        if (!mysqli_stmt_execute($stmt_hist)) throw new Exception("Failed to save record in Database.");
                    } else {
                        throw new Exception("History Prepare Failed: " . mysqli_error($DarkXen_conn));
                    }

                    mysqli_commit($DarkXen_conn);
                    $success_message =$resdata['message'] ?? "UAN Fetched Successfully!";
                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);$error_message = "DB Error: " . $e->getMessage();$resdata = []; // Clear data
                }
            } else {
                $error_message =$resdata['message'] ?? 'No UAN data found linked to this mobile number.';
            }
        }
    }
}
?>

<!-- UI SECTION -->
<div class="container-fluid py-4">
    <!-- Title Area -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-100 shadow-none border">
                <div class="row gx-0 flex-between-center p-3">
                    <div class="col-sm-auto d-flex align-items-center">
                        <div class="icon-item bg-primary-subtle shadow-none me-3" style="width: 50px; height: 50px;">
                            <span class="fas fa-search-dollar text-primary fs-3"></span>
                        </div>
                        <div>
                            <h6 class="text-primary fs-10 mb-0">PF / UAN Services</h6>
                            <h4 class="text-primary fw-bold mb-0">Find UAN <span class="text-info fw-medium">By Mobile No</span></h4>
                            <p class="mb-0 fs-11 text-muted">Retrieve hidden UAN Number using linked Mobile Number. <strong class="text-danger">Cost: ₹<?= number_format($fee, 2); ?></strong></p>
                        </div>
                    </div>
                    <div class="col-md-auto mt-3 mt-md-0">
                        <a href="mobile_uan_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-history me-1"></i> View Extracted History</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <!-- Left Side: Form -->
        <div class="col-lg-5 col-12">
            <div class="card h-100 shadow-none border border-top border-4 border-primary">
                <div class="card-header bg-light border-bottom py-3">
                    <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-mobile-alt me-2"></i>Enter Registered Mobile</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" id="searchForm" autocomplete="off">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-800 small">10-Digit Mobile Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-200 fw-bold">+91</span>
                                <input type="text" class="form-control shadow-none border-200 fw-bold font-monospace" name="mobile_no" placeholder="e.g. 9876543210" maxlength="10" pattern="\d{10}" value="<?= htmlspecialchars($_POST['mobile_no'] ?? ''); ?>" required autofocus>
                            </div>
                        </div>

                        <button class="btn btn-primary w-100 py-2 fw-bold shadow-none" type="submit" name="find_uan_btn">
                            <i class="fas fa-search me-2"></i> Extract PF/UAN Details (-₹<?= number_format($fee, 2); ?>)
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Side: Server Response -->
        <div class="col-lg-7 col-12">
            <div class="card h-100 shadow-none border border-top border-4 border-success">
                <div class="card-header bg-light border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-success"><i class="fas fa-check-circle me-2"></i>Extracted Employee Data</h5>
                    <?php if (!empty($resdata) && isset($resdata['sampleCode']) &&$resdata['sampleCode'] == "200"): ?>
                        <span class="badge bg-success"><i class="fas fa-check me-1"></i> Data Fetched</span>
                    <?php endif; ?>
                </div>
                
                <div class="card-body p-0 d-flex flex-column justify-content-center bg-body-tertiary">
                    <?php if (!empty($resdata) && isset($resdata['sampleCode']) &&$resdata['sampleCode'] == "200" && isset($resdata['data'])):$data = $resdata['data'];$is_working = ($data['is_employed'] == true ||$data['is_employed'] == "true");
                    ?>
                        <div class="p-4">
                            <!-- Main Fetched Details -->
                            <div class="bg-white rounded-3 shadow-sm border overflow-hidden mb-3">
                                <table class="table table-bordered table-striped align-middle mb-0 fs-10">
                                    <tbody>
                                        <tr>
                                            <th width="35%" class="bg-light text-700 py-3">Order ID / Ref</th>
                                            <td class="fw-bold text-primary font-monospace py-3"><?= htmlspecialchars($resdata['orderId'] ?? 'N/A') ?></td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light text-700 py-3 align-middle">Mapped UAN Number</th>
                                            <td class="py-3">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-id-card me-2 text-success"></i>
                                                    <span class="text-success fw-bold font-monospace fs-11 text-decoration-underline"><?= htmlspecialchars($data['matching_uan'] ?? 'N/A') ?></span>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light text-700 py-3">Employee Name</th>
                                            <td class="fw-bold text-dark text-uppercase py-3"><?= htmlspecialchars($data['name'] ?? 'N/A') ?></td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light text-700 py-3">Gender / DOB</th>
                                            <td class="fw-bold text-dark py-3">
                                                <span class="me-3"><i class="fas <?= ($data['gender'] == 'MALE' ? 'fa-male text-primary' : 'fa-female text-danger') ?> me-1"></i> <?= htmlspecialchars($data['gender'] ?? 'N/A') ?></span>
                                                <span><i class="fas fa-calendar-alt text-info me-1"></i> <?= htmlspecialchars($data['date_of_birth'] ?? 'N/A') ?></span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Company Summary Details -->
                            <div class="bg-white rounded-3 shadow-sm border p-3">
                                <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="fas fa-building me-2"></i>Last/Current Employment Record</h6>
                                <div class="row g-2 fs-10">
                                    <div class="col-12">
                                        <span class="text-600 d-block small">Establishment Name</span>
                                        <strong class="text-dark d-block mb-2 text-uppercase"><?= htmlspecialchars($data['establishment_name'] ?? 'N/A') ?></strong>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="text-600 d-block small">Member ID</span>
                                        <strong class="text-muted font-monospace d-block mb-2"><?= htmlspecialchars($data['member_id'] ?? 'N/A') ?></strong>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="text-600 d-block small">Currently Employed?</span>
                                        <?php if($is_working): ?>
                                            <strong class="text-success d-block mb-2"><i class="fas fa-check-circle me-1"></i> Yes, Active</strong>
                                        <?php else: ?>
                                            <strong class="text-danger d-block mb-2"><i class="fas fa-times-circle me-1"></i> No, Resigned</strong>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="text-600 d-block small">Date of Joining</span>
                                        <strong class="text-dark d-block"><i class="fas fa-calendar-check text-success me-1"></i> <?= htmlspecialchars($data['date_of_joining'] ?? 'N/A') ?></strong>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="text-600 d-block small">Date of Exit</span>
                                        <strong class="text-dark d-block"><i class="fas fa-calendar-times text-danger me-1"></i> <?= !empty($data['date_of_exit']) ? htmlspecialchars($data['date_of_exit']) : 'Active/Not Marked' ?></strong>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                    <?php else: ?>
                        <!-- STANDBY STATE -->
                        <div class="text-center text-muted p-5">
                            <i class="fas fa-database fa-4x mb-3 text-300"></i>
                            <h6 class="fw-bold mb-1 text-700">Interface Standby</h6>
                            <p class="small text-muted mx-auto mb-0" style="max-width: 340px;">
                                Enter Mobile Number on the left panel to scan EPFO databases and extract the linked UAN and Company details.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById('searchForm').addEventListener('submit', function() {
    Swal.fire({
        title: 'Scanning Database...',
        html: 'Checking EPFO registries against the provided mobile number...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
});
<?php if(!empty($error_message)): ?>
    Swal.fire({ icon: 'error', title: 'Search Failed', text: '<?= addslashes($error_message); ?>', confirmButtonText: 'Try Again' });
<?php endif; ?>
<?php if(!empty($success_message)): ?>
    Swal.fire({ icon: 'success', title: 'Record Found!', text: '<?= addslashes($success_message); ?>', showConfirmButton: false, timer: 1500 });
<?php endif; ?>
</script>

<?php include('footer.php'); ?>