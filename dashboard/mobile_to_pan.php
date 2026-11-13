<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('../includes/session.php');
include('../includes/config.php');
include('header.php'); // Dashboard layout ke liye header file

// Set timezone once
date_default_timezone_set("Asia/Kolkata");

$user_id  =$udata['id'] ?? '';
$username =$udata['phone'] ?? '';

// 1. Dynamic Fee Loader Structure
$fee = 50; 
$fee_query = mysqli_query($DarkXen_conn, "SELECT `pan_otp_find` FROM `service_free` WHERE `id` = 1 LIMIT 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if ($fee_row['pan_otp_find'] !== '' &&$fee_row['pan_otp_find'] !== null) {
        $fee = (float)$fee_row['pan_otp_find'];
    }
}

$error_message = '';
$success_message = '';$api_result = null;

// Form submit hone par API call aur Wallet deduction logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['find_pan_btn'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $mobile_no = trim($_POST['mobile_no'] ?? '');

    // Database se user ka current balance fetch karein
    $chk_user = mysqli_query($DarkXen_conn, "SELECT `balance` FROM `users` WHERE `phone` = '$username' LIMIT 1");
    $user_row = mysqli_fetch_assoc($chk_user);$current_wallet = isset($user_row['balance']) ? (float)$user_row['balance'] : 0.00;
    
    if (empty($full_name) || empty($mobile_no)) {$error_message = "Please fill in all the required fields.";
    } 
    // Wallet Balance Check Condition
    elseif ($current_wallet < $fee) {$error_message = "Insufficient Wallet Balance! Required: {$fee} Coins. Available: {$current_wallet} Coins.";
    } 
    else {
        // API Configuration
        $api_token =$webdata['api_key'] ?? '';
        $api_domain =$DarkXen_url ?? 'darkxen.in';
        
        // Dynamic API URL generation
        $api_url = "https://" . $api_domain . "/api/v3/PAN/mobilepan.php?" . http_build_query([
            'apiKey' => $api_token,
            'mobile' => $mobile_no,
            'name' => $full_name
        ]);

        // cURL Request (Fast & Reliable)
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if (!$err &&$response !== false) {
            $api_result = json_decode($response, true);

            // Check new API success status (100)
            if (isset($api_result['status']) &&$api_result['status'] == 100) {
                
                mysqli_begin_transaction($DarkXen_conn);
                try {
                    // Extract data from API response safely
                    $pan = mysqli_real_escape_string($DarkXen_conn,$api_result['pan'] ?? '');
                    $fetched_name = mysqli_real_escape_string($DarkXen_conn, $api_result['name'] ?? '');$txn_id = "MOB2PAN_" . strtoupper(uniqid());
                    $date = date('Y-m-d H:i:s');
                    
                    // 1. INSERT LOG DATABASE 
                    $insert_log = mysqli_query($DarkXen_conn, "INSERT INTO `pan_find` (application_no, username, aadhaar_no, full_pan, date) VALUES ('$txn_id','$username','$mobile_no','$pan','$date')");
                    if(!$insert_log) throw new Exception("Failed to log transaction.");

                    // 2. WALLET DEDUCTION LOGIC (More Secure)
                    $update_wallet = mysqli_query($DarkXen_conn, "UPDATE `users` SET `balance` = balance - $fee WHERE `phone` = '$username' AND balance >=$fee");
                    if(mysqli_affected_rows($DarkXen_conn) == 0) throw new Exception("Failed to deduct balance due to low balance.");
                    
                    // 3. WALLET HISTORY LOGIC (Fixed Table Name & Columns)
                    $new_wallet_balance = $current_wallet -$fee;
                    $remarks = "Mobile to PAN Find: " . $fetched_name;
                    $history_query = "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) 
                                      VALUES ('$username', '$fee', '$new_wallet_balance', '$remarks', '1', 'Debit')";
                    
                    $insert_history = mysqli_query($DarkXen_conn,$history_query);
                    if(!$insert_history) throw new Exception("Failed to update wallet history.");

                    mysqli_commit($DarkXen_conn);$success_message = "PAN Details Fetched & Wallet Debited Successfully!";
                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);$error_message = "Transaction Error: " . $e->getMessage();$api_result = null; // Clear result on DB failure
                }

            } else {
                $error_message =$api_result['message'] ?? "API responded with a failure status. Data not found.";
            }
        } else {
            $error_message = "Unable to connect to the verification server. Error: $err";
        }
    }
}
?>

<div class="container-fluid py-4">
    
    <!-- Top Header Title Banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-100 shadow-none border">
                <div class="row gx-0 flex-between-center p-3">
                    <div class="col-sm-auto d-flex align-items-center">
                        <div class="avatar avatar-xl me-3 bg-200 rounded-circle text-center d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                            <span class="fas fa-search-plus text-primary fs-2"></span>
                        </div>
                        <div>
                            <h4 class="text-primary fw-bold mb-0">Mobile To PAN Finder</h4>
                            <p class="mb-0 fs-11 text-muted">Find linked PAN card details securely. <strong class="text-danger">Service Cost: <?= number_format($fee, 2); ?> Coins</strong></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 justify-content-center mb-4">
        <!-- Input Form Section -->
        <div class="col-lg-5">
            <div class="card h-100 shadow-sm border-0 border-top border-4 border-primary">
                <div class="card-header bg-light border-bottom py-3">
                    <h5 class="mb-0 fw-bold text-primary text-center"><i class="fas fa-mobile-alt me-2"></i>Search PAN Information</h5>
                </div>
                <div class="card-body bg-white p-4">
                    <form method="POST" autocomplete="off" id="searchForm">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold text-800 small">Full Name (As per Document) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control shadow-none border-200 text-uppercase fw-semibold" name="full_name" placeholder="e.g. AMAN KUMAR" value="<?= htmlspecialchars($_POST['full_name'] ?? ''); ?>" required autofocus>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-800 small">Registered Mobile Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control shadow-none border-200 fw-bold tracking-wide" name="mobile_no" maxlength="10" pattern="[0-9]{10}" placeholder="e.g. 9876543210" value="<?= htmlspecialchars($_POST['mobile_no'] ?? ''); ?>" required>
                        </div>

                        <div class="mt-4">
                            <button class="btn btn-primary w-100 py-2 fw-bold shadow-none" name="find_pan_btn" type="submit" onclick="showLoader()">
                                <span class="fas fa-search me-2"></span>Find PAN Card - ₹<?= number_format($fee, 2); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Result Display Section -->
        <div class="col-lg-7">
            <div class="card h-100 shadow-sm border-0 border-top border-4 border-success">
                <div class="card-header bg-light border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-success"><i class="fas fa-id-card me-2"></i>Response Data / Results</h5>
                    <?php if ($api_result && isset($api_result['status']) &&$api_result['status'] == 100): ?>
                        <span class="badge bg-success">Found</span>
                    <?php endif; ?>
                </div>
                <div class="card-body bg-white p-4 d-flex flex-column justify-content-center">
                    
                    <?php if ($api_result && isset($api_result['status']) &&$api_result['status'] == 100): ?>
                        <!-- Success View Structure -->
                        <div class="alert bg-success-subtle border-success-subtle text-success fw-bold text-center mb-3 py-2">
                            <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($api_result['message'] ?? 'Successfully Processed'); ?>
                        </div>

                        <div class="table-responsive border rounded">
                            <table class="table table-bordered table-striped align-middle mb-0 text-dark">
                                <tbody>
                                    <tr>
                                        <td class="bg-light fw-bold text-secondary" style="width: 35%;">PAN Number</td>
                                        <td class="fw-bold text-danger text-monospace fs-5 text-uppercase tracking-wide">
                                            <?= htmlspecialchars($api_result['pan'] ?? 'N/A'); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="bg-light fw-bold text-secondary">Applicant Name</td>
                                        <td class="text-uppercase fw-bold text-primary"><?= htmlspecialchars($api_result['name'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="bg-light fw-bold text-secondary">Father's Name</td>
                                        <td class="text-uppercase fw-bold text-dark"><?= htmlspecialchars($api_result['fathername'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="bg-light fw-bold text-secondary">Date of Birth</td>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($api_result['dob'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="bg-light fw-bold text-secondary">Gender</td>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($api_result['gender'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="bg-light fw-bold text-secondary">Masked Aadhaar</td>
                                        <td class="fw-bold text-muted font-family-monospace"><?= htmlspecialchars($api_result['aadhar'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="bg-light fw-bold text-secondary">Queried Mobile No.</td>
                                        <td class="fw-medium text-monospace"><?= htmlspecialchars($api_result['mobile'] ?? $_POST['mobile_no']); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <!-- Default Empty View Structure -->
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-database fa-3x mb-3 text-200"></i>
                            <h6 class="fw-bold mb-1 text-700">No Active Query Data</h6>
                            <p class="small text-muted mx-auto mb-0" style="max-width: 320px;">
                                Name aur Mobile Number enter karke submit karein. Search response real-time yahan load hoga.
                            </p>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 Alerts Implementation -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function showLoader() {
    var form = document.getElementById('searchForm');
    if(form.checkValidity()) {
        Swal.fire({
            title: 'Fetching PAN Details...',
            html: 'Connecting to centralized database, please wait...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
    }
}

<?php if(!empty($error_message)): ?>
    Swal.fire({
        icon: 'error',
        title: 'Query Failed',
        text: '<?= addslashes($error_message); ?>',
        confirmButtonText: 'OK'
    });
<?php endif; ?>

<?php if(!empty($success_message)): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '<?= addslashes($success_message); ?>',
        showConfirmButton: false,
        timer: 2000
    });
<?php endif; ?>
</script>

<style>
    .form-control { padding: 0.65rem 1rem; border-radius: 0.375rem; }
    .card { border-radius: 0.75rem; overflow: hidden; }
    .text-monospace { font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
    .tracking-wide { letter-spacing: 2px; }
</style>

<?php include('footer.php'); ?>