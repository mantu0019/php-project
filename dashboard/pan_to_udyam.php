<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 

// ================= INIT & FEE SETTINGS =================

$fee_query = mysqli_query($DarkXen_conn, "SELECT `pan_udyam_fee` FROM `service_free` WHERE `id` = 1 LIMIT 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if(isset($fee_row['pan_udyam_fee']) && $fee_row['pan_udyam_fee'] != '') {
        $fee = (float)$fee_row['pan_udyam_fee'];
    }
}

$resdata = [];
$error_message = '';
$success_message = '';
$appliedby = $udata['phone'] ?? '';
$date_time = date('Y-m-d H:i:s');

// ================= ACTION =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['find_udyam_btn'])) {
    
    // Live Wallet Fetch
    $chk_user = mysqli_query($DarkXen_conn, "SELECT `balance` FROM `users` WHERE `phone` = '$appliedby' LIMIT 1");
    $user_row = mysqli_fetch_assoc($chk_user);
    $wallet_amount = isset($user_row['balance']) ? (float)$user_row['balance'] : 0.00;

    $pan_no = strtoupper(trim($_POST['pan_no'] ?? ''));
    
    // PAN Card Exact Format Validation
    if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan_no)) {
        $error_message = "कृपया सही 10-अंकीय PAN नंबर दर्ज करें (e.g. ABCDE1234F)!";
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
        $url = "https://" . $api_url_host . "/api/v3/MSME/pan-to-udyam-find.php?apiKey=$api_key&pan=$pan_no";

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

            // API Status Check (Looking for status: true and code: 200)
            if (isset($resdata['status']) && $resdata['status'] == true && isset($resdata['code']) && $resdata['code'] == 200) {
                
                $fetched_udyam = $resdata['udyam'] ?? 'N/A';
                $verification_id = $resdata['verification_id'] ?? 'PANU_' . time();
                $ref_id = $resdata['reference_id'] ?? 'N/A';

                mysqli_begin_transaction($DarkXen_conn);
                try {
                    // 1. Wallet Deduct
                    $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");
                    if (!$update_wallet) throw new Exception("Wallet Update Failed.");
                    
                    $new_balance = $wallet_amount - $fee;

                    // 2. Wallet History
                    $purpose = "PAN to Udyam Find: " . $pan_no;
                    $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES (?, ?, ?, ?, '1', 'Debit')");
                    mysqli_stmt_bind_param($stmt_wallet, "sdds", $appliedby, $fee, $new_balance, $purpose);
                    if (!mysqli_stmt_execute($stmt_wallet)) throw new Exception("Wallet History Insert Failed.");

                    // 3. Database Save 
                    $insert_sql = "INSERT INTO `pan_udyam_history` (`verification_id`, `username`, `pan_no`, `udyam_no`, `reference_id`, `charge`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt_hist = mysqli_prepare($DarkXen_conn, $insert_sql);
                    
                    if ($stmt_hist) {
                        mysqli_stmt_bind_param($stmt_hist, "sssssds", $verification_id, $appliedby, $pan_no, $fetched_udyam, $ref_id, $fee, $date_time);
                        if (!mysqli_stmt_execute($stmt_hist)) throw new Exception("Failed to save record in Database.");
                    } else {
                        throw new Exception("History Prepare Failed: " . mysqli_error($DarkXen_conn));
                    }

                    mysqli_commit($DarkXen_conn);
                    $success_message = $resdata['message'] ?? "Udyam Number Fetched Successfully!";
                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);
                    $error_message = "DB Error: " . $e->getMessage();
                    $resdata = []; // Clear data on failure
                }
            } else {
                $error_message = $resdata['message'] ?? 'Data not found or Udyam Search Failed.';
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
                            <span class="fas fa-building text-primary fs-3"></span>
                        </div>
                        <div>
                            <h6 class="text-primary fs-10 mb-0">MSME Services</h6>
                            <h4 class="text-primary fw-bold mb-0">PAN to Udyam <span class="text-info fw-medium">Find</span></h4>
                            <p class="mb-0 fs-11 text-muted">Retrieve MSME Udyam Registration Number using PAN. <strong class="text-danger">Cost: ₹<?= number_format($fee, 2); ?></strong></p>
                        </div>
                    </div>
                    <div class="col-md-auto mt-3 mt-md-0">
                        <a href="pan_to_udyam_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-history me-1"></i> View Find History</a>
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
                    <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-keyboard me-2"></i>Enter PAN Details</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" id="searchForm" autocomplete="off">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-800 small">10-Digit PAN Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-200"><i class="fas fa-id-card text-secondary"></i></span>
                                <input type="text" class="form-control shadow-none border-200 fw-bold font-monospace text-uppercase" name="pan_no" placeholder="e.g. ABCDE1234F" maxlength="10" pattern="[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}" value="<?= htmlspecialchars($_POST['pan_no'] ?? ''); ?>" required autofocus>
                            </div>
                        </div>

                        <button class="btn btn-primary w-100 py-2 fw-bold shadow-none" type="submit" name="find_udyam_btn">
                            <i class="fas fa-search me-2"></i> Find Udyam Number (-₹<?= number_format($fee, 2); ?>)
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Side: Server Response & Status -->
        <div class="col-lg-7 col-12">
            <div class="card h-100 shadow-none border border-top border-4 border-success">
                <div class="card-header bg-light border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-success"><i class="fas fa-check-circle me-2"></i>Retrieval Result</h5>
                    <?php if (!empty($resdata) && isset($resdata['status']) && $resdata['status'] == true): ?>
                        <span class="badge bg-success"><i class="fas fa-check me-1"></i> Found</span>
                    <?php endif; ?>
                </div>
                
                <div class="card-body p-0 d-flex flex-column justify-content-center bg-body-tertiary">
                    <?php if (!empty($resdata) && isset($resdata['status']) && $resdata['status'] == true): ?>
                        <div class="p-4">
                            <div class="alert alert-success border-0 d-flex align-items-center mb-4">
                                <i class="fas fa-industry fs-3 me-3 text-success"></i>
                                <div>
                                    <h6 class="mb-0 fw-bold text-success-emphasis">Udyam Number Fetched Successfully!</h6>
                                    <p class="mb-0 fs-11 text-success">We have retrieved the MSME Udyam Registration Number from the central registry.</p>
                                </div>
                            </div>
                            
                            <table class="table table-bordered table-striped align-middle mb-0 fs-10 bg-white shadow-sm rounded-3">
                                <tbody>
                                    <tr>
                                        <th width="35%" class="bg-light text-700 py-3">Verification ID</th>
                                        <td class="fw-bold text-primary font-monospace py-3"><?= htmlspecialchars($resdata['verification_id'] ?? 'N/A') ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light text-700 py-3">Reference ID</th>
                                        <td class="fw-bold text-secondary font-monospace py-3"><?= htmlspecialchars($resdata['reference_id'] ?? 'N/A') ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light text-700 py-3">Searched PAN Number</th>
                                        <td class="fw-bold text-dark font-monospace py-3 text-uppercase"><?= htmlspecialchars($resdata['pan'] ?? 'N/A') ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light text-700 py-3 align-middle">Retrieved Udyam No</th>
                                        <td class="py-3 bg-success-subtle">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-certificate me-2 text-success"></i>
                                                <span class="text-success fw-bold font-monospace fs-11"><?= htmlspecialchars($resdata['udyam'] ?? 'N/A') ?></span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            
                            <div class="mt-3 text-center">
                                <span class="badge bg-secondary-subtle text-secondary px-3 py-1"><i class="fas fa-bolt me-1 text-warning"></i> <?= htmlspecialchars($resdata['powered_by'] ?? 'DARKXEN API') ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- STANDBY STATE -->
                        <div class="text-center text-muted p-5">
                            <i class="fas fa-file-signature fa-4x mb-3 text-300"></i>
                            <h6 class="fw-bold mb-1 text-700">Interface Standby</h6>
                            <p class="small text-muted mx-auto mb-0" style="max-width: 340px;">
                                Submit the PAN Number on the left. The associated MSME Udyam Registration Number will be displayed here.
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
        title: 'Searching Database...',
        text: 'Extracting Udyam details based on PAN...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
});
<?php if(!empty($error_message)): ?>
    Swal.fire({ icon: 'error', title: 'Search Failed', text: '<?= addslashes($error_message); ?>', confirmButtonText: 'Try Again' });
<?php endif; ?>
<?php if(!empty($success_message)): ?>
    Swal.fire({ icon: 'success', title: 'Udyam Found!', text: '<?= addslashes($success_message); ?>', showConfirmButton: false, timer: 1500 });
<?php endif; ?>
</script>

<?php include('footer.php'); ?>