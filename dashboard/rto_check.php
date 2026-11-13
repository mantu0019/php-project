<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php'); // Dashboard layout header file

// ================= INIT =================
$charge = 10; // Default fallback price if DB config missing
$fee_query = mysqli_query($DarkXen_conn, "SELECT `rto_cheak` FROM `service_free` WHERE `id` = 1 LIMIT 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if ($fee_row['rto_cheak'] !== '' &&$fee_row['rto_cheak'] !== null) {
        $charge = (float)$fee_row['rto_cheak'];
    }
}

$rcData = [];
$error_message = '';$success_message = '';

$user_id = $udata['id'] ?? '';$user = $udata['phone'] ?? '';$date_time = date('Y-m-d H:i:s');

// ================= RTO SEARCH ACTION =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['rcno'])) {

    // Fetch live user balance from DB to prevent race conditions
    $chk_user = mysqli_query($DarkXen_conn, "SELECT `balance` FROM `users` WHERE `id` = '$user_id' LIMIT 1");
    $user_row = mysqli_fetch_assoc($chk_user);$current_wallet = isset($user_row['balance']) ? (float)$user_row['balance'] : 0.00;

    // ================= BALANCE CHECK =================
    if ($current_wallet <$charge) {
        echo '
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            Swal.fire(
                "Low Balance",
                "Recharge Required. Required Coins: ' . $charge . '",
                "error"
            ).then(()=>{
                window.location="wallet.php";
            });
        });
        </script>';
        exit;
    }

    // ================= GET RTO CODE =================
    $rcno = strtoupper(trim($_POST['rcno'])); // Kept input name 'rcno' for form compatibility

    // ================= API URL CONFIG =================
    $apiKey = urlencode($DarkXen_apikey ?? '');
    $api_url_host =$DarkXen_url ?? 'darkxen.in';
    // Updated with New API Endpoint and Parameters
    $url = "https://" . $api_url_host . "/api/v3/VEHICLE/rto_verification.php?apiKey=$apiKey&rtoCode=$rcno";

    // ================= CURL REQUEST =================
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => [
            "Accept: application/json"
        ]
    ]);

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // ================= PROCESS RESPONSE =================
    if ($curl_error) {$error_message = "Server Timeout. Please try again.";
    } else {
        $resdata = json_decode($response, true);
        $status =$resdata['success'] ?? false; // Based on new API "success": true

        // Check status and if data exists
        if (!$status && empty($resdata['data'])) {
            $error_message =$resdata['message'] ?? "RTO Data Not Found";
        } else {
            $rto_info =$resdata['data'];

            // Updated Data Array mapping new API response
            $rcData = [
                'rto_code'    => $rto_info['rto_code'] ?? '',
                'office_name' => $rto_info['office_name'] ?? '',
                'state'       => $rto_info['state'] ?? '',
                'district'    => $rto_info['district'] ?? '',
                'address'     => $rto_info['address'] ?? '',
                'source_type' => $rto_info['source_type'] ?? ''
            ];

            // ================= DB UPDATE WALLET & HISTORY =================
            $newbalance = $current_wallet -$charge;
            mysqli_query($DarkXen_conn, "UPDATE `users` SET `balance` = '$newbalance' WHERE `id` = '$user_id'");

            $remark = "RTO Check : " . $rcno;
            
            // Log in wallethistory
            mysqli_query($DarkXen_conn, "INSERT INTO `wallethistory` 
                (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) 
                VALUES 
                ('$user', '$charge', '$newbalance', '$remark', '1', 'Debit')"
            );

            // --- SAVING IN `rto_cheak` TABLE SAFELY ---
            // Existing DB structure kept intact, dynamically mapped new response data
            $u_username = mysqli_real_escape_string($DarkXen_conn,$user);
            $db_rc_number = mysqli_real_escape_string($DarkXen_conn, $rcData['rto_code']);$db_make = mysqli_real_escape_string($DarkXen_conn,$rcData['office_name']);
            $db_model = mysqli_real_escape_string($DarkXen_conn, $rcData['district']);$db_variant = mysqli_real_escape_string($DarkXen_conn,$rcData['state']);
            $db_fuel = mysqli_real_escape_string($DarkXen_conn, $rcData['source_type']);$db_rto = mysqli_real_escape_string($DarkXen_conn,$rcData['address']);
            $db_reg_date = mysqli_real_escape_string($DarkXen_conn, date('Y-m-d'));

            mysqli_query($DarkXen_conn, "INSERT INTO `rto_cheak` 
                (username, rc_number, make, model, variant, fuel_type, rto, registration_date, charge, status, date) 
                VALUES 
                ('$u_username', '$db_rc_number', '$db_make', '$db_model', '$db_variant', '$db_fuel', '$db_rto', '$db_reg_date', '$charge', '1', '$date_time')"
            );

            $success_message = "RTO Data Found Successfully!";
        }
    }
}
?>

<!-- ================= HTML FRONT-END SECTION ================= -->
<div class="container-fluid py-4">
    
    <!-- Title Banner Area -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-100 shadow-none border">
                <div class="row gx-0 flex-between-center p-3">
                    <div class="col-sm-auto d-flex align-items-center">
                        <div class="avatar avatar-xl me-3 bg-200 rounded-circle text-center d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                            <span class="fas fa-building text-primary fs-2"></span>
                        </div>
                        <div>
                            <h4 class="text-primary fw-bold mb-0">RTO Office Verification Tool</h4>
                            <p class="mb-0 fs-11 text-muted font-sans-serif">Get live mapped location and state district details of any RTO Code. <strong class="text-danger">Cost: <?= $charge; ?> Coins</strong></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Interface Panel Layout -->
    <div class="row g-3 justify-content-center mb-4">
        
        <!-- Left Side: Input Form Box -->
        <div class="col-lg-5 col-12">
            <div class="card h-100 shadow-none border border-top border-4 border-primary">
                <div class="card-header bg-light border-bottom py-3">
                    <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-search me-2"></i>Search RTO Details</h5>
                </div>
                <div class="card-body bg-white p-4">
                    <form method="POST" autocomplete="off" onsubmit="showLoader('Fetching RTO details from Gov Server...')">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold text-800 small">Enter RTO Code</label>
                            <div class="input-group">
                                <span class="input-group-text bg-200"><i class="fas fa-id-card text-secondary"></i></span>
                                <input type="text" class="form-control shadow-none border-200 text-uppercase fw-bold text-monospace" name="rcno" placeholder="e.g. BR06" value="<?= htmlspecialchars($_POST['rcno'] ?? ''); ?>" required>
                            </div>
                            <div class="form-text text-muted fs-11 mt-1">Format: Sirf valid state RTO code daalein bina spaces ke (e.g., BR06, DL01).</div>
                        </div>

                        <div class="mt-4">
                            <button class="btn btn-primary w-100 py-2 fw-bold shadow-none" type="submit">
                                <span class="fas fa-search-location me-2"></span>Fetch RTO Info (-<?= $charge; ?> Coins)
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Side: Real-Time RTO Server Response -->
        <div class="col-lg-7 col-12">
            <div class="card h-100 shadow-none border border-top border-4 border-success">
                <div class="card-header bg-light border-bottom py-3">
                    <h5 class="mb-0 fw-bold text-success"><i class="fas fa-server me-2"></i>RTO Node Server Logs</h5>
                </div>
                <div class="card-body bg-white p-4 d-flex flex-column justify-content-center">
                    
                    <?php if (!empty($rcData)): ?>
                        <!-- SUCCESS RESPONSE RENDER VIEW -->
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0 text-dark">
                                <tbody>
                                    <tr>
                                        <td class="bg-light fw-bold text-secondary" style="width: 35%;">RTO Code</td>
                                        <td class="fw-bold text-primary text-monospace text-uppercase fs-12"><?= htmlspecialchars($rcData['rto_code']); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="bg-light fw-bold text-secondary">Office Name</td>
                                        <td class="fw-bold text-800 text-uppercase"><?= htmlspecialchars($rcData['office_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="bg-light fw-bold text-secondary">State</td>
                                        <td class="fw-medium text-uppercase text-dark"><?= htmlspecialchars($rcData['state']); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="bg-light fw-bold text-secondary">District</td>
                                        <td class="small font-monospace text-muted text-uppercase"><?= htmlspecialchars($rcData['district']); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="bg-light fw-bold text-secondary">Address</td>
                                        <td class="text-uppercase">
                                            <span class="badge bg-info-subtle text-info border border-info fw-bold px-2 py-1 fs-12" style="white-space: normal; text-align: left; line-height: 1.5;">
                                                <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($rcData['address']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="bg-light fw-bold text-secondary">Source Type</td>
                                        <td class="text-uppercase small text-600"><?= htmlspecialchars($rcData['source_type']); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="bg-light fw-bold text-secondary">Token Processed</td>
                                        <td class="fw-bold text-warning-emphasis"><i class="fas fa-coins me-1"></i><?= $charge; ?> Coins</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <!-- IDLE STATE LAYOUT VIEW -->
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-map-marked-alt fa-3x mb-3 text-200"></i>
                            <h6 class="fw-bold mb-1 text-700">Database Interface Standby</h6>
                            <p class="small text-muted mx-auto mb-0" style="max-width: 340px;">
                                Valid RTO Code type karke details process karein. Verified location aur branch information yahan render hogi.
                            </p>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
        
    </div>
</div>

<!-- SweetAlert2 UI alert implementation -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function showLoader(txt) {
    Swal.fire({
        title: txt,
        text: 'Please wait while querying secured state transport registers...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
}

<?php if(!empty($error_message)): ?>
    Swal.fire({
        icon: 'error',
        title: 'Validation Blocked',
        text: '<?= addslashes($error_message); ?>',
        confirmButtonText: 'Try Again'
    });
<?php endif; ?>

<?php if(!empty($success_message)): ?>
    Swal.fire({
        icon: 'success',
        title: 'Data Mapped!',
        text: '<?= addslashes($success_message); ?>',
        showConfirmButton: false,
        timer: 1500
    });
<?php endif; ?>
</script>

<style>
    .form-control { padding: 0.65rem 1rem; border-radius: 0.375rem; }
    .card { border-radius: 0.75rem; overflow: hidden; }
    .text-monospace { font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
    .fs-11 { font-size: 13px !important; }
    .fs-12 { font-size: 12px !important; }
    .bg-info-subtle { background-color: #cff4fc !important; }
</style>

<?php include('footer.php'); ?>