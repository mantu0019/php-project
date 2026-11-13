<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 

// ================= INIT & FEE SETTINGS =================
$fee = 10.00; // Default Fee Fallback
$fee_query = mysqli_query($DarkXen_conn, "SELECT `eshram_details_fee` FROM `service_free` WHERE `id` = 1 LIMIT 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if(isset($fee_row['eshram_details_fee']) && $fee_row['eshram_details_fee'] != '') {
        $fee = (float)$fee_row['eshram_details_fee'];
    }
}

$resdata = [];
$error_message = '';
$success_message = '';
$appliedby = $udata['phone'] ?? '';
$date_time = date('Y-m-d H:i:s');

// ================= ACTION =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['find_btn'])) {
    
    // Live Wallet Fetch
    $chk_user = mysqli_query($DarkXen_conn, "SELECT `balance` FROM `users` WHERE `phone` = '$appliedby' LIMIT 1");
    $user_row = mysqli_fetch_assoc($chk_user);
    $wallet_amount = isset($user_row['balance']) ? (float)$user_row['balance'] : 0.00;

    $aadhaar_no = trim($_POST['aadhaar_no'] ?? '');
    
    if (!preg_match('/^[0-9]{12}$/', $aadhaar_no)) {
        $error_message = "कृपया सही 12-अंकीय आधार नंबर दर्ज करें!";
    } elseif ($wallet_amount < $fee) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
              <script>document.addEventListener('DOMContentLoaded', function() {
                  Swal.fire('Low Balance!', 'Please recharge now. Required: ₹" . $fee . "', 'error').then(() => { window.location='wallet.php'; });
              });</script>";
        exit;
    } else {
        // ================= API CONFIG =================
        $api_key = urlencode($DarkXen_apikey ?? '');
        $api_url_host = $DarkXen_url ?? 'darkxen.in';
        $url = "https://" . $api_url_host . "/api/v3/AADHAR/eshram_details_find.php?apiKey=$api_key&uid=$aadhaar_no";

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

            // API Status Check (StatusCode: 100)
            if (isset($resdata['StatusCode']) && $resdata['StatusCode'] == 100 && isset($resdata['data'])) {
                
                $data = $resdata['data'];
                $app_no = $resdata['application_no'] ?? 'FIND_' . time();
                $ekycstate = $resdata['ekycstate'] ?? 'N/A';
                
                $name = $data['name'] ?? 'N/A';
                $localname = $data['localname'] ?? '';
                $dob = $data['dob'] ?? 'N/A';
                $gender = $data['gender'] ?? 'N/A';
                $relative = $data['realtive'] ?? 'N/A'; // Spelling as per API response
                $address = trim(preg_replace('/^[\s,]+|[\s,]+$/', '', $data['full_address'] ?? 'N/A')); // Clean starting commas
                
                $photo_raw = $data['ekycPhotoBaseStr'] ?? '';
                $photo_base64 = '';
                if (!empty($photo_raw)) {
                    $photo_base64 = (strpos($photo_raw, 'data:image') === false) ? 'data:image/jpeg;base64,' . $photo_raw : $photo_raw;
                }

                $safe_uid = "[Aadhaar Redacted]"; // Absolute masking

                mysqli_begin_transaction($DarkXen_conn);
                try {
                    // 1. Wallet Deduct
                    $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");
                    if (!$update_wallet) throw new Exception("Wallet Update Failed.");
                    
                    $new_balance = $wallet_amount - $fee;

                    // 2. Wallet History
                    $purpose = "E-Shram Details Find: " . $name;
                    $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES (?, ?, ?, ?, '1', 'Debit')");
                    mysqli_stmt_bind_param($stmt_wallet, "sdds", $appliedby, $fee, $new_balance, $purpose);
                    if (!mysqli_stmt_execute($stmt_wallet)) throw new Exception("Wallet History Insert Failed.");

                    // 3. Database Save 
                    $insert_sql = "INSERT INTO `eshram_details_history` (`application_no`, `username`, `uid_no`, `ekycstate`, `name`, `localname`, `dob`, `gender`, `relative_name`, `full_address`, `photo_base64`, `charge`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt_hist = mysqli_prepare($DarkXen_conn, $insert_sql);
                    
                    if ($stmt_hist) {
                        mysqli_stmt_bind_param($stmt_hist, "sssssssssssds", $app_no, $appliedby, $safe_uid, $ekycstate, $name, $localname, $dob, $gender, $relative, $address, $photo_base64, $fee, $date_time);
                        if (!mysqli_stmt_execute($stmt_hist)) throw new Exception("Failed to save record in Database.");
                    } else {
                        throw new Exception("History Prepare Failed: " . mysqli_error($DarkXen_conn));
                    }

                    mysqli_commit($DarkXen_conn);
                    $success_message = $resdata['message'] ?? "Data Found Successfully!";
                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);
                    $error_message = "DB Error: " . $e->getMessage();
                    $resdata = []; // Clear data on failure
                }
            } else {
                $error_message = $resdata['message'] ?? 'Data not found or Update Failed.';
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
                            <span class="fas fa-search text-primary fs-3"></span>
                        </div>
                        <div>
                            <h6 class="text-primary fs-10 mb-0">E-Shram Services</h6>
                            <h4 class="text-primary fw-bold mb-0">E-Shram Details <span class="text-info fw-medium">Find</span></h4>
                            <p class="mb-0 fs-11 text-muted">Retrieve E-Shram KYC profile details including Photo. <strong class="text-danger">Cost: ₹<?= number_format($fee, 2); ?></strong></p>
                        </div>
                    </div>
                    <div class="col-md-auto mt-3 mt-md-0">
                        <a href="eshram_details_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-history me-1"></i> View History</a>
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
                    <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-keyboard me-2"></i>Enter Identifier</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" id="searchForm" autocomplete="off">
                        <div class="mb-4">
                            <label class="form-label fw-bold text-800 small">Aadhaar Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-200"><i class="fas fa-id-card text-secondary"></i></span>
                                <input type="text" class="form-control shadow-none border-200 fw-bold font-monospace" name="aadhaar_no" placeholder="Enter 12-digit Aadhaar Number" maxlength="12" pattern="\d{12}" value="<?= htmlspecialchars($_POST['aadhaar_no'] ?? ''); ?>" required autofocus>
                            </div>
                        </div>
                        <button class="btn btn-primary w-100 py-2 fw-bold shadow-none" type="submit" name="find_btn">
                            <i class="fas fa-search me-2"></i>Fetch Details (-₹<?= number_format($fee, 2); ?>)
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Side: Server Response & Profile View -->
        <div class="col-lg-7 col-12">
            <div class="card h-100 shadow-none border border-top border-4 border-success">
                <div class="card-header bg-light border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-success"><i class="fas fa-user-circle me-2"></i>Extracted Profile</h5>
                    <?php if (!empty($resdata) && isset($resdata['StatusCode']) &&$resdata['StatusCode'] == 100): ?>
                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Found</span>
                    <?php endif; ?>
                </div>
                
                <div class="card-body p-0 d-flex flex-column justify-content-center bg-body-tertiary">
                    <?php if (!empty($resdata) && isset($resdata['StatusCode']) && $resdata['StatusCode'] == 100 && isset($resdata['data'])): 
                        $data =$resdata['data'];
                        $photo_src = (!empty($data['ekycPhotoBaseStr'])) ? ((strpos($data['ekycPhotoBaseStr'], 'data:image') === false) ? 'data:image/jpeg;base64,' .$data['ekycPhotoBaseStr'] : $data['ekycPhotoBaseStr']) : '../assets/img/avatar.png';$clean_address = trim(preg_replace('/^[\s,]+|[\s,]+$/', '',$data['full_address'] ?? 'N/A'));
                    ?>
                        <div class="p-4">
                            <!-- Premium Profile Layout -->
                            <div class="d-flex flex-column flex-md-row align-items-center bg-white p-3 rounded-3 shadow-sm border mb-3">
                                <div class="avatar avatar-4xl me-md-4 mb-3 mb-md-0 border border-3 border-success rounded p-1" style="width: 120px; height: 120px;">
                                    <img class="rounded w-100 h-100 object-fit-cover" src="<?= htmlspecialchars($photo_src) ?>" alt="Profile Photo" />
                                </div>
                                <div class="text-center text-md-start w-100">
                                    <h4 class="mb-1 fw-bold text-primary text-uppercase"><?= htmlspecialchars($data['name'] ?? 'N/A') ?></h4>
                                    <h6 class="mb-2 text-muted fw-bold"><?= htmlspecialchars($data['localname'] ?? '') ?></h6>
                                    
                                    <div class="row g-2 fs-10 mt-2">
                                        <div class="col-6">
                                            <div class="bg-light p-2 rounded border">
                                                <span class="text-600 d-block small">Gender</span>
                                                <strong class="text-dark"><i class="fas <?= ($data['gender'] == 'Male' ? 'fa-male text-primary' : 'fa-female text-danger') ?> me-1"></i> <?= htmlspecialchars($data['gender'] ?? 'N/A') ?></strong>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="bg-light p-2 rounded border">
                                                <span class="text-600 d-block small">DOB</span>
                                                <strong class="text-dark"><i class="fas fa-calendar-day me-1 text-info"></i> <?= htmlspecialchars($data['dob'] ?? 'N/A') ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Detailed Information Table -->
                            <div class="bg-white rounded-3 shadow-sm border overflow-hidden">
                                <table class="table table-bordered table-striped align-middle mb-0 fs-10">
                                    <tbody>
                                        <tr>
                                            <th width="35%" class="bg-light text-700 py-3">Reference ID</th>
                                            <td class="fw-bold text-primary font-monospace py-3"><?= htmlspecialchars($resdata['application_no'] ?? 'N/A') ?></td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light text-700 py-3">E-KYC State</th>
                                            <td class="fw-bold text-success text-uppercase py-3"><?= htmlspecialchars($resdata['ekycstate'] ?? 'N/A') ?></td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light text-700 py-3">Relative Name</th>
                                            <td class="fw-bold text-dark text-uppercase py-3"><?= htmlspecialchars($data['realtive'] ?? 'N/A') ?></td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light text-700 py-3 align-top">Full Address</th>
                                            <td class="text-800 fw-bold py-3 text-uppercase" style="line-height: 1.5;"><?= htmlspecialchars($clean_address) ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- STANDBY STATE -->
                        <div class="text-center text-muted p-5">
                            <i class="fas fa-id-badge fa-4x mb-3 text-300"></i>
                            <h6 class="fw-bold mb-1 text-700">Waiting for Request</h6>
                            <p class="small text-muted mx-auto mb-0" style="max-width: 340px;">
                                Submit the Aadhaar details. Verified profile photo and information will be displayed here securely.
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
        title: 'Extracting Details...',
        text: 'Fetching E-KYC profile from central database...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
});
<?php if(!empty($error_message)): ?>
    Swal.fire({ icon: 'error', title: 'Extraction Failed', text: '<?= addslashes($error_message); ?>', confirmButtonText: 'Try Again' });
<?php endif; ?>
<?php if(!empty($success_message)): ?>
    Swal.fire({ icon: 'success', title: 'Task Completed!', text: '<?= addslashes($success_message); ?>', showConfirmButton: false, timer: 1500 });
<?php endif; ?>
</script>

<?php include('footer.php'); ?>