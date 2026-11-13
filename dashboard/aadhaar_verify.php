<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set('Asia/Kolkata');

// ================= INIT & FEE CONFIG =================

$fee_query = mysqli_query($DarkXen_conn, "SELECT `aadhar_validation_check` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if ($fee_row['aadhar_validation_check'] !== '' &&$fee_row['aadhar_validation_check'] !== null) {
        $fee = floatval($fee_row['aadhar_validation_check']);
    }
}

if (!function_exists('clean')) {
    function clean($conn,$data) {
        return mysqli_real_escape_string($conn, trim($data));
    }
}

$appliedby = clean($DarkXen_conn,$udata['phone']);
$DarkXen_apikey =$webdata['api_key'] ?? ''; 
$api_domain =$DarkXen_url;

$resdata = [];$error_message = '';
$status_flag = '';$aadhar = '';

// ================= POST FORM PROCESSING (VALIDATE API) =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_btn'])) {$aadhar = clean($DarkXen_conn,$_POST['aadhar_number'] ?? '');
    $captcha = clean($DarkXen_conn, $_POST['captcha'] ?? '');$captcha_txn_id = clean($DarkXen_conn,$_POST['captcha_txn_id'] ?? '');

    if (!empty($aadhar) && strlen($aadhar) === 12 && !empty($captcha) && !empty($captcha_txn_id)) {
        if (($udata['balance'] ?? 0) >=$fee) {
            
            // Step 2: Validate API Call
            $url = "https://" . $api_domain . "/api/v3/AADHAR/aadhaar_valid.php?" . http_build_query([
                'apiKey' => $DarkXen_apikey,
                'uid' => $aadhar,
                'captcha' => $captcha,
                'captchaTxnId' => $captcha_txn_id
            ]);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            $resdata = json_decode($response, true);

            // API Payload Check (New API returns "status": "Success")
            if (isset($resdata['status']) && strtolower($resdata['status']) === 'success') {
                
                mysqli_begin_transaction($DarkXen_conn);$debit_fee = $udata['balance'] -$fee;
                $date_time = date('Y-m-d H:i:s');$remark = "Aadhaar System Verification Check";
                
                // Extracting new API keys
                $state_address = clean($DarkXen_conn,$resdata['address'] ?? '');
                $age_band      = clean($DarkXen_conn, $resdata['ageBand'] ?? '');$gender        = clean($DarkXen_conn,$resdata['gender'] ?? '');
                $mobile        = clean($DarkXen_conn, $resdata['maskedMobileNumber'] ?? '');$status_msg    = clean($DarkXen_conn,$resdata['statusMessage'] ?? 'Verified');

                // 1. Deduct users wallet balance metrics safely
                $update_user = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby' AND balance >=$fee");

                // 2. Insert ledger logs into wallethistory
                $insert_wallet = mysqli_query($DarkXen_conn, "INSERT INTO `wallethistory`
                    (`userid`, `amount`, `balance`, `purpose`, `status`, `type`)
                    VALUES ('$appliedby', '$fee', '$debit_fee', '$remark', '1', 'Debit')");

                // 3. Insert record into aadhar_status table
                $insert_status = mysqli_query($DarkXen_conn, "INSERT INTO `aadhar_status` 
                    (`user_phone`, `aadhar_no`, `state_address`, `age_band`, `gender`, `masked_mobile`, `status_msg`, `charge_debited`, `created_at`) 
                    VALUES 
                    ('$appliedby', '$aadhar', '$state_address', '$age_band', '$gender', '$mobile', '$status_msg', '$fee', '$date_time')");

                if ($update_user && $insert_wallet &&$insert_status) {
                    mysqli_commit($DarkXen_conn);$status_flag = "success";
                } else {
                    mysqli_rollback($DarkXen_conn);$error_message = "Database synchronization error. Process Aborted.";
                    $status_flag = "error";
                }
            } else {
                $error_message =$resdata['message'] ?? $resdata['statusMessage'] ?? 'Remote validation gateway responded with an error or invalid captcha.';$status_flag = "error";
            }
        } else {
            $status_flag = "low_balance";
        }
    } else {
        $error_message = "Invalid Parameters! Enter 12-digit UID and Captcha.";
        $status_flag = "error";
    }
}

// ================= GET NEW CAPTCHA ON EVERY PAGE LOAD / FAILED SUBMIT =================
$captcha_base64 = '';$captcha_txn_id = '';

$captcha_url = "https://" . $api_domain . "/api/v3/AADHAR/aadhar_captcha_verification.php?" . http_build_query([
    'apiKey' => $DarkXen_apikey
]);

$ch_cap = curl_init();
curl_setopt_array($ch_cap, [
    CURLOPT_URL => $captcha_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$captcha_response = curl_exec($ch_cap);
curl_close($ch_cap);

$captcha_data = json_decode($captcha_response, true);
if (isset($captcha_data['status']) &&$captcha_data['status'] == true) {
    // Cleaning up base64 string if it contains prefix
    $captcha_raw =$captcha_data['captchaImageBase64'];
    $captcha_base64 = str_replace('data:image/jpeg;base64,', '',$captcha_raw);
    $captcha_txn_id =$captcha_data['transactionId'];
}
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-info-subtle shadow-none me-3">
                        <span class="fas fa-check-circle text-info fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-info fs-10 mb-0">Identity Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Aadhaar <span class="text-info fw-medium">Verify</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-control form-control-sm bg-white border-200 shadow-sm px-3">
                            <span class="fas fa-wallet text-success me-2"></span>
                            <span class="fw-bold text-success">Balance: ₹<?php echo number_format((float)($udata['balance'] ?? 0), 2); ?></span>
                        </div>
                        <a href="aadhaar_verify_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History List</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center g-3">
    <div class="col-lg-5">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-primary">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-fingerprint me-2 text-primary"></i>Verify Details</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                
                <div class="alert alert-info border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-info"></i>
                    <p class="mb-0 fs-11">Verification charge: <strong>₹<?php echo number_format($fee, 2); ?></strong> will be automatically adjusted upon active query confirmation.</p>
                </div>
                
                <form method="POST" autocomplete="off" id="verificationForm">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-900">Identification Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-id-card text-secondary"></i></span>
                            <input type="text" class="form-control shadow-none fw-bold text-dark text-center fs-9 tracking-wide" name="aadhar_number" value="<?php echo htmlspecialchars($aadhar); ?>" placeholder="Enter 12-digit number" maxlength="12" pattern="\d{12}" required autofocus>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Security Captcha <span class="text-danger">*</span></label>
                        <div class="d-flex align-items-center gap-2">
                            <div class="border rounded bg-white d-flex align-items-center justify-content-center overflow-hidden shadow-sm" style="height: 42px; width: 140px; min-width: 140px;">
                                <?php if (!empty($captcha_base64)): ?>
                                    <img src="data:image/jpeg;base64,<?php echo $captcha_base64; ?>" alt="Captcha" style="height: 100%; width: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <span class="text-danger fs-11 fw-bold">Captcha Error</span>
                                <?php endif; ?>
                            </div>
                            
                            <input type="hidden" name="captcha_txn_id" value="<?php echo htmlspecialchars($captcha_txn_id); ?>">
                            
                            <input type="text" class="form-control shadow-none fw-bold text-center fs-9" name="captcha" placeholder="Enter Captcha" required style="height: 42px;">
                        </div>
                    </div>
                    
                    <button class="btn btn-primary w-100 fw-bold shadow-none py-2" type="submit" name="verify_btn" onclick="triggerLoadingState()">
                        <i class="fas fa-search me-2"></i> Verify & Check Details
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- API Response UI View Box -->
    <?php if (isset($resdata['status']) && strtolower($resdata['status']) === 'success') { ?>
    <div class="col-lg-7">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-success">
            <div class="card-header bg-success-subtle py-3 border-bottom">
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="text-success font-weight-bold mb-0">
                        <i class="fas fa-user-check me-2"></i> Aadhaar Details Status
                    </h5>
                    <span class="badge bg-success px-2 py-1" style="border-radius:4px;">Verified</span>
                </div>
            </div>
            <div class="card-body bg-white p-0" id="printSection">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle mb-0 fs-11">
                        <tbody>
                            <tr>
                                <th width="35%" class="text-secondary font-weight-600 bg-light">Aadhaar Target</th>
                                <td class="font-weight-bold text-dark font-family-monospace"><?php echo htmlspecialchars($aadhar ?? ''); ?></td>
                            </tr>
                            <tr>
                                <th class="text-secondary font-weight-600 bg-light">State / Address</th>
                                <td class="fw-bold text-primary"><?php echo htmlspecialchars($resdata['address'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th class="text-secondary font-weight-600 bg-light">Age Band Group</th>
                                <td><span class="badge bg-light-primary text-primary px-2"><?php echo htmlspecialchars($resdata['ageBand'] ?? 'N/A'); ?> Years</span></td>
                            </tr>
                            <tr>
                                <th class="text-secondary font-weight-600 bg-light">Gender</th>
                                <td class="fw-bold text-dark text-uppercase"><?php echo htmlspecialchars($resdata['gender'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th class="text-secondary font-weight-600 bg-light">Linked Mobile</th>
                                <td class="font-family-monospace text-danger tracking-wide fw-bold"><?php echo htmlspecialchars($resdata['maskedMobileNumber'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th class="text-secondary font-weight-600 bg-light">Status Response</th>
                                <td class="text-success font-weight-500 italic fw-bold"><?php echo htmlspecialchars($resdata['statusMessage'] ?? 'N/A'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light text-end py-2 no-print-element">
                <button type="button" class="btn btn-sm btn-secondary shadow-none" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Print Status
                </button>
            </div>
        </div>
    </div>
    <?php } ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function triggerLoadingState() {
    if(document.getElementById('verificationForm').checkValidity()) {
        Swal.fire({
            title: 'Verifying Records...',
            html: 'Connecting to servers and matching Captcha, please hold...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
    }
}
</script>

<?php
// SweetAlert Popup Handlers 
if (!empty($status_flag)) {
    if ($status_flag === "success") {
        echo "<script>
            setTimeout(function() {
                Swal.fire({
                    title: 'Record Fetched!',
                    text: 'Data parameter trees loaded successfully.',
                    icon: 'success',
                    confirmButtonText: 'Okay'
                });
            }, 150);
        </script>";
    } 
    elseif ($status_flag === "error") {
        echo "<script>
            setTimeout(function() {
                Swal.fire({
                    title: 'Verification Failed',
                    text: '" . addslashes($error_message) . "',
                    icon: 'error',
                    confirmButtonText: 'Okay'
                });
            }, 150);
        </script>";
    }
    elseif ($status_flag === "low_balance") {
        echo "<script>
            setTimeout(function() {
                Swal.fire({
                    title: 'Wallet Balance Low!',
                    text: 'Please Recharge Wallet to run this execution query.',
                    icon: 'error',
                    timer: 2000,
                    showConfirmButton: false
                });
            }, 150);
            setTimeout(function() {
                window.location = 'wallet.php';
            }, 2100);
        </script>";
    }
}
include('footer.php'); 
?>