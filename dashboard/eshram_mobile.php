<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 

// ================= INIT & FEE SETTINGS =================
$fee = 10.00; // Default Fee Fallback
$fee_query = mysqli_query($DarkXen_conn, "SELECT `eshram_update_fee` FROM `service_free` WHERE `id` = 1 LIMIT 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if(isset($fee_row['eshram_update_fee']) &&$fee_row['eshram_update_fee'] != '') {
        $fee = (float)$fee_row['eshram_update_fee'];
    }
}

$resdata = [];$error_message = '';
$success_message = '';$appliedby = $udata['phone'] ?? '';$date_time = date('Y-m-d H:i:s');

// ================= ACTION =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_btn'])) {
    
    // Live Wallet Fetch
    $chk_user = mysqli_query($DarkXen_conn, "SELECT `balance` FROM `users` WHERE `phone` = '$appliedby' LIMIT 1");
    $user_row = mysqli_fetch_assoc($chk_user);$wallet_amount = isset($user_row['balance']) ? (float)$user_row['balance'] : 0.00;

    $aadhaar_no = trim($_POST['aadhaar_no'] ?? '');
    $raw_dob = trim($_POST['dob'] ?? ''); 
    $new_mobile = trim($_POST['mobile_no'] ?? ''); 
    
    // FIX: DOB Format Conversion (Strictly DD/MM/YYYY with slashes as required by API)
    $api_dob = date('d/m/Y', strtotime($raw_dob));

    if (empty($aadhaar_no) && empty($raw_dob) || empty($new_mobile)) {$error_message = "कृपया सभी आवश्यक विवरण दर्ज करें!";
    } elseif (!preg_match('/^[0-9]{10}$/', $new_mobile)) {$error_message = "कृपया सही 10-अंकीय मोबाइल नंबर दर्ज करें!";
    } elseif ($wallet_amount <$fee) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
              <script>document.addEventListener('DOMContentLoaded', function() {
                  Swal.fire('Low Balance!', 'Please recharge now. Required: ₹" . $fee . "', 'error').then(() => { window.location='wallet.php'; });
              });</script>";
        exit;
    } else {
        // ================= API CONFIG =================
        $api_key = urlencode($DarkXen_apikey ?? '');
        $api_url_host =$DarkXen_url ?? 'darkxen.in';
        
        // Passing date with urlencode to safely send the slashes
        $url = "https://" . $api_url_host . "/api/v3/AADHAR/eshram_mobile_update.php?apiKey=$api_key&uid=$aadhaar_no&dob=" . urlencode($api_dob) . "&mobile=$new_mobile";

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

        if ($curl_error) {$error_message = "API Connection Failed: Server Timeout.";
        } else {
            $resdata = json_decode($response, true) ?? [];

            // API Status Check (Looking for StatusCode: 100)
            if (isset($resdata['StatusCode']) && $resdata['StatusCode'] == 100 && isset($resdata['data'])) {
                
                $data =$resdata['data'];
                $app_no =$resdata['application_no'] ?? 'UPDT_' . time();
                $name = $data['name'] ?? 'N/A';$uan = $data['uan_no'] ?? 'N/A';$old_mob = $data['old_mobile'] ?? 'N/A';$new_mob = $data['new_mobile'] ?? $new_mobile;
                $safe_uid = "[Aadhaar Redacted]";

                mysqli_begin_transaction($DarkXen_conn);
                try {
                    // 1. Wallet Deduct
                    $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");
                    if (!$update_wallet) throw new Exception("Wallet Update Failed.");
                    
                    $new_balance = $wallet_amount -$fee;

                    // 2. Wallet History
                    $purpose = "E-Shram Mobile Update: " . $uan;
                    $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES (?, ?, ?, ?, '1', 'Debit')");
                    mysqli_stmt_bind_param($stmt_wallet, "sdds", $appliedby,$fee, $new_balance,$purpose);
                    if (!mysqli_stmt_execute($stmt_wallet)) throw new Exception("Wallet History Insert Failed.");

                    // 3. Database Save 
                    $insert_sql = "INSERT INTO `eshram_mobile_history` (`application_no`, `username`, `uid_no`, `uan_no`, `name`, `old_mobile`, `new_mobile`, `charge`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt_hist = mysqli_prepare($DarkXen_conn,$insert_sql);
                    
                    if ($stmt_hist) {
                        mysqli_stmt_bind_param($stmt_hist, "sssssssds", $app_no, $appliedby,$safe_uid, $uan,$name, $old_mob,$new_mob, $fee,$date_time);
                        if (!mysqli_stmt_execute($stmt_hist)) throw new Exception("Failed to save record in Database.");
                    } else {
                        throw new Exception("History Prepare Failed: " . mysqli_error($DarkXen_conn));
                    }

                    mysqli_commit($DarkXen_conn);
                    $success_message =$resdata['message'] ?? "Mobile Number Updated Successfully!";
                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);$error_message = "DB Error: " . $e->getMessage();$resdata = []; // Clear data on failure
                }
            } else {
                $error_message =$resdata['message'] ?? 'Data not found or Update Failed.';
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
                            <span class="fas fa-mobile-alt text-primary fs-3"></span>
                        </div>
                        <div>
                            <h6 class="text-primary fs-10 mb-0">E-Shram Services</h6>
                            <h4 class="text-primary fw-bold mb-0">Mobile Number <span class="text-info fw-medium">Update</span></h4>
                            <p class="mb-0 fs-11 text-muted">Link or Update new mobile number in E-Shram Card. <strong class="text-danger">Cost: ₹<?= number_format($fee, 2); ?></strong></p>
                        </div>
                    </div>
                    <div class="col-md-auto mt-3 mt-md-0">
                        <a href="eshram_mobile_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-history me-1"></i> View Update History</a>
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
                    <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-edit me-2"></i>Enter Verification Details</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" id="updateForm" autocomplete="off">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold text-800 small">Aadhaar Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-200"><i class="fas fa-id-card text-secondary"></i></span>
                                <input type="text" class="form-control shadow-none border-200 fw-bold font-monospace" name="aadhaar_no" placeholder="12-digit Aadhaar Number" maxlength="12" pattern="\d{12}" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-800 small">Date of Birth <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-200"><i class="fas fa-calendar-alt text-secondary"></i></span>
                                <input type="date" class="form-control shadow-none border-200 fw-bold" name="dob" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-800 small">New Mobile Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-200 fw-bold">+91</span>
                                <input type="text" class="form-control shadow-none border-200 fw-bold font-monospace text-success" name="mobile_no" placeholder="Enter new 10-digit mobile" maxlength="10" pattern="\d{10}" required>
                            </div>
                            <div class="form-text text-muted fs-11 mt-1">This number will be linked to the respective E-Shram Card.</div>
                        </div>

                        <button class="btn btn-primary w-100 py-2 fw-bold shadow-none" type="submit" name="update_btn">
                            <span class="fas fa-sync-alt me-2"></span>Update Mobile Number (-₹<?= number_format($fee, 2); ?>)
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Side: Server Response & Status -->
        <div class="col-lg-7 col-12">
            <div class="card h-100 shadow-none border border-top border-4 border-success">
                <div class="card-header bg-light border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-success"><i class="fas fa-satellite-dish me-2"></i>Live Update Status</h5>
                    <?php if (!empty($resdata) && isset($resdata['StatusCode']) &&$resdata['StatusCode'] == 100): ?>
                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Updated</span>
                    <?php endif; ?>
                </div>
                
                <div class="card-body p-0 d-flex flex-column justify-content-center">
                    <?php if (!empty($resdata) && isset($resdata['StatusCode']) && $resdata['StatusCode'] == 100 && isset($resdata['data'])): 
                        $data =$resdata['data'];
                    ?>
                        <div class="p-4">
                            <div class="alert alert-success border-0 d-flex align-items-center mb-4">
                                <i class="fas fa-check-double fs-3 me-3 text-success"></i>
                                <div>
                                    <h6 class="mb-0 fw-bold text-success-emphasis">Database Synced Successfully!</h6>
                                    <p class="mb-0 fs-11 text-success">The mobile number has been updated in the E-Shram portal.</p>
                                </div>
                            </div>
                            
                            <table class="table table-bordered table-striped align-middle mb-0 fs-10">
                                <tbody>
                                    <tr>
                                        <th width="35%" class="bg-light text-700 py-3">Reference ID</th>
                                        <td class="fw-bold text-primary font-monospace py-3"><?= htmlspecialchars($resdata['application_no'] ?? 'N/A') ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light text-700 py-3">Beneficiary Name</th>
                                        <td class="fw-bold text-dark text-uppercase py-3"><?= htmlspecialchars($data['name'] ?? 'N/A') ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light text-700 py-3">UAN Number</th>
                                        <td class="fw-bold text-info font-monospace py-3"><?= htmlspecialchars($data['uan_no'] ?? 'N/A') ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light text-700 py-3">Old Mobile Number</th>
                                        <td class="text-danger fw-bold font-monospace py-3 text-decoration-line-through"><?= htmlspecialchars($data['old_mobile'] ?? 'N/A') ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light text-700 py-3">New Updated Mobile</th>
                                        <td class="text-success fw-bold font-monospace fs-11 py-3"><i class="fas fa-mobile-alt me-1"></i> <?= htmlspecialchars($data['new_mobile'] ?? 'N/A') ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <!-- STANDBY STATE -->
                        <div class="text-center text-muted p-5">
                            <i class="fas fa-server fa-4x mb-3 text-300"></i>
                            <h6 class="fw-bold mb-1 text-700">Interface Standby</h6>
                            <p class="small text-muted mx-auto mb-0" style="max-width: 340px;">
                                Submit the Aadhaar and New Mobile details. Verified response from Government registry will appear here.
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
document.getElementById('updateForm').addEventListener('submit', function() {
    Swal.fire({
        title: 'Updating Records...',
        text: 'Syncing details with central E-Shram database...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
});
<?php if(!empty($error_message)): ?>
    Swal.fire({ icon: 'error', title: 'Update Failed', text: '<?= addslashes($error_message); ?>', confirmButtonText: 'Try Again' });
<?php endif; ?>
<?php if(!empty($success_message)): ?>
    Swal.fire({ icon: 'success', title: 'Task Completed!', text: '<?= addslashes($success_message); ?>', showConfirmButton: false, timer: 1500 });
<?php endif; ?>
</script>

<?php include('footer.php'); ?>