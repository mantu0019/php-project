<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set('Asia/Kolkata');

// ================= INIT & FEE CONFIG =================
$fee = 5.00; // Default Fee
$fee_query = mysqli_query($DarkXen_conn, "SELECT `aadhaar_2_pan_link` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if ($fee_row['aadhaar_2_pan_link'] !== '' &&$fee_row['aadhaar_2_pan_link'] !== null) {
        $fee = floatval($fee_row['aadhaar_2_pan_link']);
    }
}

if (!function_exists('clean')) {
    function clean($conn,$data) {
        return mysqli_real_escape_string($conn, trim($data));
    }
}

$DarkXen_apikey =$webdata['api_key'] ?? ''; 
$api_domain =$DarkXen_url;

$resdata = [];$error_message = "";
$status_flag = "";
$uidNumber = "";

// ================= POST FORM PROCESSING =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['uidNumber'])) {

    $uidNumber = clean($DarkXen_conn, $_POST['uidNumber']);$appliedby = clean($DarkXen_conn,$udata['phone'] ?? '');

    // Validation (Strict 12 Digits Check)
    if (!preg_match('/^[0-9]{12}$/', $uidNumber)) {$error_message = "Invalid Validation Number format. Must be 12 digits.";
        $status_flag = "error";
    } else {
        // Wallet Balance Check
        if (($udata['balance'] ?? 0) >=$fee) {

            $debit_fee = $udata['balance'] -$fee;
            $date_time = date('Y-m-d H:i:s');

            // NEW API ENDPOINT
            $url = "https://" . $api_domain . "/api/v3/PAN/pan-aadhaar-status.php?" . http_build_query([
                'apiKey' => $DarkXen_apikey,
                'uidNumber' => $uidNumber
            ]);

            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_SSL_VERIFYPEER => false,
            ));

            $response = curl_exec($curl);
            
            if (curl_errno($curl)) {$error_message = "Server Connection Failed: " . curl_error($curl);$status_flag = "error";
            }
            curl_close($curl);

            if (empty($error_message)) {
                $resdata = json_decode($response, true);

                // SUCCESS RESPONSE CHECK
                if (isset($resdata['status']) &&$resdata['status'] === true) {

                    mysqli_begin_transaction($DarkXen_conn);

                    $api_msg = clean($DarkXen_conn,$resdata['message'] ?? 'Successfully Processed');

                    // 1. Deduct Balance
                    $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby' AND balance >=$fee");

                    // 2. Insert Record in Wallet History
                    $insert_wallet = mysqli_query($DarkXen_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) 
                        VALUES('$appliedby', '$fee', '$debit_fee', 'PAN LINK STATUS CHECK', '1', 'Debit')");

                    // 3. Save Audit Log Data in List table
                    $insert_log = mysqli_query($DarkXen_conn, "INSERT INTO aadhaar_pan_link_records(userid, aadhaar_number, message, status) 
                        VALUES('$appliedby', '$uidNumber', '$api_msg', 'Success')");

                    if ($update_wallet && $insert_wallet &&$insert_log) {
                        mysqli_commit($DarkXen_conn);$status_flag = "success";
                    } else {
                        mysqli_rollback($DarkXen_conn);$error_message = "Database synchronization error. Process aborted.";
                        $status_flag = "error";
                    }

                } else {
                    // Log Failed Transactions
                    $api_msg = clean($DarkXen_conn,$resdata['message'] ?? 'Status check failed');
                    mysqli_query($DarkXen_conn, "INSERT INTO aadhaar_pan_link_records(userid, aadhaar_number, message, status) 
                        VALUES('$appliedby', '$uidNumber', '$api_msg', 'Failed')");
                    
                    $error_message =$api_msg;
                    $status_flag = "error";
                }
            }
        } else {
            $status_flag = "low_balance";
        }
    }
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
                        <h6 class="text-warning fs-10 mb-0">PAN Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Aadhaar PAN <span class="text-warning fw-medium">Link Status</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-control form-control-sm bg-white border-200 shadow-sm px-3">
                            <span class="fas fa-wallet text-success me-2"></span>
                            <span class="fw-bold text-success">Balance: ₹<?php echo number_format((float)($udata['balance'] ?? 0), 2); ?></span>
                        </div>
                        <a href="aadhaar_pan_link_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center g-3">
    <!-- LEFT SIDE: FORM SECTION -->
    <div class="col-lg-5">
        <div class="card h-100 mb-4 shadow-sm border-0 border-top border-4 border-warning">
            <div class="card-header bg-light border-bottom py-3">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-search me-2"></i>Check Seeding Status</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-warning border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-warning"></i>
                    <p class="mb-0 fs-11">Current service charge: <strong>₹<?php echo number_format($fee, 2); ?></strong> will be deducted upon successful query.</p>
                </div>

                <form method="POST" id="linkStatusForm" autocomplete="off">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Aadhaar Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-id-card text-secondary"></i></span>
                            <input type="text" name="uidNumber" class="form-control shadow-none fw-bold text-dark text-center fs-9 tracking-wide" placeholder="Enter 12 Digit Number" required maxlength="12" pattern="\d{12}" value="<?php echo htmlspecialchars($uidNumber); ?>" autofocus>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold shadow-none py-2" onclick="triggerLoadingState()">
                        <i class="fas fa-link me-2"></i> Check Link Status
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- RIGHT SIDE: RESULT SECTION -->
    <div class="col-lg-7">
        <?php if (!empty($error_message) &&$status_flag === "error") { ?>
            <div class="alert alert-danger border-0 rounded-3 shadow-sm mb-4 d-flex align-items-center" role="alert">
                <i class="fas fa-exclamation-triangle fs-3 me-3"></i> 
                <div>
                    <h6 class="mb-0 fw-bold text-danger">Query Failed</h6>
                    <span class="fs-11"><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            </div>
        <?php } ?>

        <?php if (isset($resdata['status']) &&$resdata['status'] === true) { ?>
            <div class="card h-100 border-0 shadow-sm border-top border-4 border-success" id="printSection">
                <div class="card-header bg-success-subtle py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-success fw-bold" style="font-size: 16px;">
                        <i class="fas fa-shield-alt me-1"></i> Integration Link Audit
                    </h5>
                    <span class="badge bg-success px-2 py-1">Completed</span>
                </div>
                <div class="card-body bg-white p-4">
                    
                    <div class="alert bg-light border shadow-sm mb-4">
                        <h6 class="fw-bold text-primary mb-2 border-bottom pb-2"><i class="fas fa-info-circle me-1"></i> Seeding Message</h6>
                        <p class="mb-0 fw-bold text-success lh-base" style="font-size: 14px;">
                            <?php echo htmlspecialchars($resdata['message'] ?? 'Successfully Processed'); ?>
                        </p>
                    </div>

                    <div class="table-responsive border rounded shadow-sm">
                        <table class="table table-bordered table-striped mb-0 align-middle">
                            <tbody>
                                <tr>
                                    <th width="35%" class="text-secondary bg-light fw-bold">Queried Number</th>
                                    <td class="text-dark font-family-monospace fw-bold fs-10">XXXXXXXX<?php echo substr($uidNumber, -4); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-secondary bg-light fw-bold">System Returned UID</th>
                                    <td class="text-primary font-family-monospace fw-bold"><?php echo htmlspecialchars($resdata['uid'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-secondary bg-light fw-bold">Linked PAN</th>
                                    <td class="text-dark fw-bold text-uppercase tracking-wide"><span class="badge bg-warning text-dark px-3 py-1 fs-11"><?php echo htmlspecialchars($resdata['pan'] ?? 'N/A'); ?></span></td>
                                </tr>
                                <tr>
                                    <th class="text-secondary bg-light fw-bold">Date Verified</th>
                                    <td class="text-muted fs-11 fw-medium"><?php echo date('d M Y, h:i A'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light text-end py-2 no-print-element">
                    <button type="button" class="btn btn-sm btn-secondary shadow-none fw-bold" onclick="window.print()">
                        <i class="fas fa-print me-1"></i> Print Report
                    </button>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function triggerLoadingState() {
    if(document.getElementById('linkStatusForm').checkValidity()) {
        Swal.fire({
            title: 'Verifying Link Status...',
            html: 'Connecting to centralized database, please wait...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
    }
}
</script>

<?php
// SweetAlert Handlers 
if (!empty($status_flag)) {
    if ($status_flag === "low_balance") {
        echo "<script>
            setTimeout(function() {
                Swal.fire({
                    title: 'Wallet Balance Low!',
                    text: 'Please Recharge Wallet to check linking status.',
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