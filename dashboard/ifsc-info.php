<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

// टाइमज़ोन कॉन्फ़िगरेशन
date_default_timezone_set("Asia/Kolkata"); 

// डिफ़ॉल्ट फ़ीस सेटिंग्स (IFSC चेक के लिए)
$fee = 1.00; 

// डेटाबेस से IFSC फीस निकालें 
$fee_query = mysqli_query($DarkXen_conn, "SELECT `ifsc_Verification` FROM `service_free` WHERE `id` = 1 LIMIT 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if ($fee_row['ifsc_Verification'] !== '' &&$fee_row['ifsc_Verification'] !== null) {
        $fee = (float)$fee_row['ifsc_Verification'];
    }
}

$resdata = [];
$error_message = '';$success_message = '';
$appliedby =$udata['phone'];

// FORM SUBMISSION CHECK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_ifsc_btn'])) {

    // Fetch Live Wallet Balance to prevent race conditions
    $chk_user = mysqli_query($DarkXen_conn, "SELECT `balance` FROM `users` WHERE `phone` = '$appliedby' LIMIT 1");
    $user_row = mysqli_fetch_assoc($chk_user);$wallet_amount = isset($user_row['balance']) ? (float)$user_row['balance'] : 0.00;

    $ifscCode = strtoupper(trim($_POST['ifsc_code'] ?? ''));

    // इनपुट वैलिडेशन (IFSC 11 अंकों का होता है)
    if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifscCode)) {$error_message = "Invalid IFSC Code Format (e.g. SBIN0018040)";
    } 
    // बैलेंस वैलिडेशन
    elseif ($wallet_amount <$fee) {
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire('Wallet Balance Low!', 'Please Recharge Now! Required: ₹" . $fee . "', 'error').then(() => {
                window.location='wallet.php';
            });
        });
        </script>";
        exit;
    } else {

        // ================= API URL (Updated for v3) =================
        $api_key = urlencode($DarkXen_apikey ?? '');
        $api_url_host =$DarkXen_url;
        $url = "https://" . $api_url_host . "/api/v3/ifsc_code_verification.php?apiKey=$api_key&ifscCode=$ifscCode";

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ));

        $response = curl_exec($curl);
        $curl_error = curl_error($curl);
        curl_close($curl);

        if ($curl_error) {$error_message = "API Server Connection Failed";
        } else {
            $resdata = json_decode($response, true) ?? [];

            // API रिस्पॉन्स चेक (New API gives status: "100")
            if (isset($resdata['status']) &&$resdata['status'] == 100) {

                mysqli_begin_transaction($DarkXen_conn);

                try {
                    // 1. बैलेंस कम करें
                    $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
                    if (!$update_wallet) {
                        throw new Exception("Wallet deduction failed.");
                    }
                    
                    $debit_fee = $wallet_amount -$fee;

                    // 2. वॉलेट हिस्ट्री लॉग
                    $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES (?, ?, ?, 'IFSC Details Check', '1', 'Debit')");
                    mysqli_stmt_bind_param($stmt_wallet, "sdd", $appliedby, $fee,$debit_fee);
                    mysqli_stmt_execute($stmt_wallet);
                    mysqli_stmt_close($stmt_wallet);

                    // डेटाबेस के लिए डेटा तैयार करें (Mapped to new API response)
                    $bank_name = mysqli_real_escape_string($DarkXen_conn,$resdata['bank'] ?? 'N/A');
                    $branch = mysqli_real_escape_string($DarkXen_conn, $resdata['branch'] ?? 'N/A');$application_no = 'IFSC' . time();
                    $current_date = date('Y-m-d H:i:s');

                    // 3. हिस्ट्री में सेव करें 
                    $insert_sql = "INSERT INTO `ifsc_history` (`application_no`, `username`, `ifsc_code`, `bank_name`, `branch`, `date`) 
                                   VALUES ('$application_no', '$appliedby', '$ifscCode', '$bank_name', '$branch', '$current_date')";
                    
                    if(!mysqli_query($DarkXen_conn,$insert_sql)) {
                        throw new Exception("History Database Error.");
                    }

                    mysqli_commit($DarkXen_conn);
                    $success_message =$resdata['message'] ?? "Bank Details Fetched Successfully!";

                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);$error_message = "Transaction Failed: " . $e->getMessage();$resdata = [];
                }

            } else {
                $error_message =$resdata['message'] ?? 'Invalid IFSC Code or Data Not Found.';
            }
        }
    }
}
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-search-location text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Banking Utility</h6>
                        <h4 class="text-primary fw-bold mb-0">IFSC <span class="text-info fw-medium">Finder</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="ifsc_history_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-history me-1"></i> View History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center mb-4">
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-primary">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-university me-2"></i>Fetch Bank Details by IFSC</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-info border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-info-circle fs-3 me-3 text-info"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?= number_format($fee, 2); ?></strong> will be deducted per search.</p>
                </div>
                
                <form method="POST" id="ifscForm" autocomplete="off">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Enter IFSC Code <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-barcode"></i></span>
                            <input type="text" class="form-control shadow-none fw-bold text-uppercase" id="ifsc_code" name="ifsc_code" placeholder="e.g. SBIN0018040" maxlength="11" value="<?= htmlspecialchars($_POST['ifsc_code'] ?? ''); ?>" required autofocus>
                        </div>
                    </div>
                    
                    <button class="btn btn-primary w-100 fw-bold shadow-none py-2" type="submit" name="check_ifsc_btn">
                        <i class="fas fa-search me-2"></i> Search Details (-₹<?= number_format($fee, 2); ?>)
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php 
// UI Result Rendering Condition updated for new API structure
if (!empty($resdata) && isset($resdata['status']) &&$resdata['status'] == 100): 
    $d =$resdata; 
?>
<div class="row justify-content-center mb-4">
    <div class="col-lg-10">
        <div class="card shadow-sm border border-top border-4 border-success">
            <div class="card-header bg-success text-white py-2">
                <h5 class="mb-0 text-white"><i class="fas fa-check-circle me-2"></i> Bank Information Found</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless fs-10">
                            <tr>
                                <th width="40%" class="text-700">Bank Name:</th>
                                <td class="fw-bold text-primary fs-9 text-uppercase"><?= htmlspecialchars($d['bank'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <th class="text-700">IFSC Code:</th>
                                <td class="fw-bold text-900 font-monospace"><?= htmlspecialchars($d['ifsc'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <th class="text-700">Branch:</th>
                                <td class="text-uppercase"><?= htmlspecialchars($d['branch'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <th class="text-700">MICR Code:</th>
                                <td><?= htmlspecialchars($d['micr'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <th class="text-700">Contact:</th>
                                <td class="fw-bold"><?= htmlspecialchars($d['contact'] ?? 'N/A') ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless fs-10">
                            <tr>
                                <th width="40%" class="text-700">State:</th>
                                <td class="text-uppercase"><?= htmlspecialchars($d['state'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <th class="text-700">District:</th>
                                <td class="text-uppercase"><?= htmlspecialchars($d['district'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <th class="text-700">City:</th>
                                <td class="text-uppercase"><?= htmlspecialchars($d['city'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <th class="text-700">Address:</th>
                                <td class="small text-uppercase"><?= htmlspecialchars($d['address'] ?? 'N/A') ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <hr class="my-2">
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <span class="badge badge-subtle-<?= !empty($d['neft']) ? 'success' : 'danger' ?> border">NEFT: <?= !empty($d['neft']) ? 'Yes' : 'No' ?></span>
                    <span class="badge badge-subtle-<?= !empty($d['imps']) ? 'success' : 'danger' ?> border">IMPS: <?= !empty($d['imps']) ? 'Yes' : 'No' ?></span>
                    <span class="badge badge-subtle-<?= !empty($d['rtgs']) ? 'success' : 'danger' ?> border">RTGS: <?= !empty($d['rtgs']) ? 'Yes' : 'No' ?></span>
                    <span class="badge badge-subtle-<?= !empty($d['upi']) ? 'success' : 'danger' ?> border">UPI: <?= !empty($d['upi']) ? 'Yes' : 'No' ?></span>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.getElementById('ifscForm').addEventListener('submit', function() {
        Swal.fire({
            title: 'Searching...',
            html: 'Fetching bank details from server...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
    });

    <?php if(!empty($error_message)): ?>
        Swal.fire('Error', '<?= addslashes($error_message); ?>', 'error');
    <?php endif; ?>

    <?php if(!empty($success_message)): ?>
        Swal.fire('Success', '<?= addslashes($success_message); ?>', 'success');
    <?php endif; ?>
</script>

<?php include('footer.php'); ?>