<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set('Asia/Kolkata');

// ================= INIT & FEE CONFIG =================

$fee_query = mysqli_query($DarkXen_conn, "SELECT `ennrollment_status_check` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if ($fee_row['ennrollment_status_check'] !== '' &&$fee_row['ennrollment_status_check'] !== null) {
        $fee = floatval($fee_row['ennrollment_status_check']);
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
$status_flag = '';$eid = '';

// ================= POST FORM PROCESSING =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_btn'])) {$eid = clean($DarkXen_conn,$_POST['eid'] ?? '');
    $captcha = clean($DarkXen_conn, $_POST['captcha'] ?? '');$captcha_txn_id = clean($DarkXen_conn,$_POST['captcha_txn_id'] ?? '');

    if (!empty($eid) && !empty($captcha) && !empty($captcha_txn_id)) {
        if (($udata['balance'] ?? 0) >=$fee) {
            
            // Validation API Call
            $url = "https://" . $api_domain . "/api/v3/AADHAR/enrollment_status_verification.php?" . http_build_query([
                'apiKey' => $DarkXen_apikey,
                'eid' => $eid,
                'captcha' => $captcha,
                'captcha_txn' => $captcha_txn_id
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

            // Checking API Response Success condition
            if (isset($resdata['status']) && $resdata['status'] === true && !empty($resdata['statusData'])) {
                
                mysqli_begin_transaction($DarkXen_conn);$debit_fee = $udata['balance'] -$fee;
                $date_time = date('Y-m-d H:i:s');$remark = "Aadhaar EID Enrollment Status";
                
                $status_data = $resdata['statusData'][0];$success_message = clean($DarkXen_conn,$status_data['resMessage'] ?? 'Enrollment Status Fetched Successfully.');

                // 1. Deduct user wallet balance
                $update_user = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby' AND balance >=$fee");

                // 2. Insert ledger logs into wallethistory
                $insert_wallet = mysqli_query($DarkXen_conn, "INSERT INTO `wallethistory`
                    (`userid`, `amount`, `balance`, `purpose`, `status`, `type`)
                    VALUES ('$appliedby', '$fee', '$debit_fee', '$remark', '1', 'Debit')");

                // 3. Insert record into history table
                $insert_status = mysqli_query($DarkXen_conn, "INSERT INTO `eid_status_history` 
                    (`userid`, `eid`, `status_message`) 
                    VALUES ('$appliedby', '$eid', '$success_message')");

                if ($update_user && $insert_wallet &&$insert_status) {
                    mysqli_commit($DarkXen_conn);$status_flag = "success";
                } else {
                    mysqli_rollback($DarkXen_conn);$error_message = "Database synchronization error. Process Aborted.";
                    $status_flag = "error";
                }
            } else {
                $error_message = $resdata['message'] ?? 'Remote validation gateway responded with an error or invalid captcha.';$status_flag = "error";
            }
        } else {
            $status_flag = "low_balance";
        }
    } else {
        $error_message = "Invalid Parameters! Enter EID and Captcha correctly.";
        $status_flag = "error";
    }
}

// ================= GET NEW CAPTCHA ON EVERY PAGE LOAD =================
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
                    <div class="icon-item bg-warning-subtle shadow-none me-3">
                        <span class="fas fa-id-badge text-warning fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-warning fs-10 mb-0">EID Services</h6>
                        <h4 class="text-primary fw-bold mb-0">EID Enrollment <span class="text-warning fw-medium">Status</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-2 mt-md-0">
                    <a href="aadhaar_eid_status_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- EID Finder Form -->
    <div class="col-lg-5">
        <div class="card h-100 mb-4 shadow-sm border-0 border-top border-4 border-warning">
            <div class="card-header bg-light border-bottom py-3">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-search me-2"></i>Status Finder</h5>
            </div>
            <div class="card-body bg-white p-4">
                <div class="alert alert-warning border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-warning"></i>
                    <p class="mb-0 fs-11">Current service charge: <strong>₹<?php echo number_format($fee, 2); ?></strong> will be deducted upon processing.</p>
                </div>

                <form method="POST" autocomplete="off" id="verificationForm">
                    <div class="mb-3">
                        <label class="form-label text-900 fw-bold" for="eid">Enrollment ID (EID) <span class="text-danger">*</span></label>
                        <input class="form-control shadow-none font-family-monospace text-center fs-9 fw-bold" id="eid" name="eid" type="text" maxlength="28" placeholder="Enter 14-Digit EID" required value="<?php echo htmlspecialchars($eid); ?>" autofocus>
                        <div class="form-text text-muted fs-12 mt-1 text-center">Format: 14/28-Digit EID + 14-Digit Date/Time</div>
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

                    <div class="mt-4 pt-2">
                        <button class="btn btn-primary w-100 shadow-none text-white fw-bold py-2" type="submit" name="verify_btn" onclick="triggerLoadingState()">
                            <i class="fas fa-shield-alt me-2"></i> Verify Account Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- API Response UI View Box (Beautiful Grid Format) -->
    <?php if (isset($resdata['status']) && $resdata['status'] === true && !empty($resdata['statusData'])) { 
        $data =$resdata['statusData'][0];
    ?>
    <div class="col-lg-7">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-success" id="printSection">
            <div class="card-header bg-success-subtle py-3 border-bottom">
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="text-success font-weight-bold mb-0">
                        <i class="fas fa-check-circle me-2"></i> EID Verification Result
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-success shadow-none px-3" onclick="window.print()">
                        <i class="fas fa-print me-1"></i> Print
                    </button>
                </div>
            </div>
            
            <div class="card-body bg-body-tertiary p-3">
                
                <!-- Primary Status Box -->
                <div class="alert bg-white border shadow-sm mb-3">
                    <h6 class="fw-bold text-primary mb-2 border-bottom pb-2"><i class="fas fa-info-circle me-1"></i> Main Status</h6>
                    <p class="mb-0 text-dark fw-medium lh-base"><?php echo htmlspecialchars($data['resMessage'] ?? 'N/A'); ?></p>
                </div>

                <!-- Secondary Info Row -->
                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <div class="bg-white p-3 border rounded shadow-sm h-100">
                            <span class="d-block text-muted fs-11 fw-bold text-uppercase mb-1">Request Type</span>
                            <span class="text-dark fw-bold"><?php echo htmlspecialchars($data['typeOfRequest'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="bg-white p-3 border rounded shadow-sm h-100">
                            <span class="d-block text-muted fs-11 fw-bold text-uppercase mb-1">Completion Date</span>
                            <span class="text-primary fw-bold font-family-monospace"><?php echo htmlspecialchars($data['uidCompletedOrRejectedDate'] ?? 'Pending'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Stages Process Grid -->
                <?php if (!empty($data['stages']) && is_array($data['stages'])) { ?>
                <h6 class="fw-bold text-dark mt-4 mb-3"><i class="fas fa-tasks me-2"></i> Processing Stages</h6>
                <div class="row g-2">
                    <?php foreach ($data['stages'] as$stage) { ?>
                        <div class="col-md-6">
                            <div class="card border shadow-none h-100">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0 fw-bold text-900 fs-11"><?php echo htmlspecialchars($stage['stageName']); ?></h6>
                                        <?php if ($stage['state'] === 'COMPLETE') { ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fas fa-check"></i> Complete</span>
                                        <?php } else { ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="fas fa-spinner fa-spin"></i> In Progress</span>
                                        <?php } ?>
                                    </div>
                                    <p class="text-muted fs-11 mb-0 lh-sm"><?php echo htmlspecialchars($stage['messageLocal'] ?? $stage['message']); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <?php } ?>

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
            title: 'Checking Status...',
            html: 'Communicating with central servers and matching Captcha, please hold...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
    }
}
</script>

<?php
// SweetAlert Handlers 
if (!empty($status_flag)) {
    if ($status_flag === "success") {
        echo "<script>
            setTimeout(function() {
                Swal.fire({
                    title: 'Verification Successful',
                    text: 'Status parameters fetched and decoded.',
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