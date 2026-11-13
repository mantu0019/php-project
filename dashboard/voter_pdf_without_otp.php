<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set('Asia/Kolkata');

// ================= INIT & FEE CONFIG =================
$charge = 30.00; // Default fallback fee
$fee_query = mysqli_query($DarkXen_conn, "SELECT `voter_pdf_without_otp` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if (!empty($fee_row['voter_pdf_without_otp'])) {
        $charge = floatval($fee_row['voter_pdf_without_otp']);
    }
}

if (!function_exists('clean')) {
    function clean($conn,$data) {
        return mysqli_real_escape_string($conn, trim($data));
    }
}

$user = clean($DarkXen_conn,$udata['phone']);
$API_KEY = urlencode($webdata['api_key'] ?? '');
$api_domain =$DarkXen_url ?? 'darkxen.in';

$alert_message = '';
$user_balance = (float)($udata['balance'] ?? 0);

// ================= LOW BALANCE REDIRECT =================
if ($user_balance < $charge &&$_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'error',
            title: 'Low Balance',
            text: 'Please Recharge your Wallet. Required Balance: ₹" . number_format($charge, 2) . "',
            confirmButtonText: 'Recharge Now',
            allowOutsideClick: false
        }).then(() => {
            window.location = 'wallet.php';
        });
    });
    </script>";
    exit;
}

// ================= FORM POST PROCESSING =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_pdf_btn'])) {$epic = strtoupper(clean($DarkXen_conn,$_POST['epic_no'] ?? ''));

    if (empty($epic)) {$alert_message = "Swal.fire('Error', 'Please enter a valid EPIC Number.', 'warning');";
    } elseif ($user_balance < $charge) {$alert_message = "Swal.fire('Error', 'Low wallet balance. Please recharge.', 'error');";
    } else {
        
        // 🚀 PRO FEATURE: Check for Duplicate Pending Request
        $check_dup = mysqli_query($DarkXen_conn, "SELECT `id` FROM `voter_pdf_without_otp_history` WHERE `userid` = '$user' AND `epic_no` = '$epic' AND `status` = 'Pending'");
        
        if (mysqli_num_rows($check_dup) > 0) {$alert_message = "Swal.fire('Already in Queue!', 'A request for this EPIC number is already pending. Please wait for it to complete.', 'info');";
        } else {
            
            // NEW API URL CALL
            $url = "https://" . $api_domain . "/api/v3/VOTER/VCMpdf.php?apikey=$API_KEY&epic=" . urlencode($epic);$ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => ["cache-control: no-cache"],
            ]);
            $response = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {$alert_message = "Swal.fire('Error', 'API connection failed: " . addslashes($err) . "', 'error');";
            } else {
                $resdata = json_decode($response, true);
                $status = strtolower($resdata['status'] ?? '');

                if ($status === "success") {
                    $ref_id = mysqli_real_escape_string($DarkXen_conn, $resdata['ref_id'] ?? 'N/A');$api_message = mysqli_real_escape_string($DarkXen_conn,$resdata['message'] ?? 'Request Submitted Successfully');
                    
                    // Transaction Database Operations Begin
                    mysqli_begin_transaction($DarkXen_conn);
                    
                    try {
                        $date_time = date('Y-m-d H:i:s');$remark = "Voter PDF Without OTP Processed for EPIC: " . $epic;
                        $newbalance = $user_balance -$charge;

                        // A. Deduct user balance safely
                        $update_user = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $charge WHERE phone = '$user' AND balance >=$charge");
                        if (mysqli_affected_rows($DarkXen_conn) == 0) {
                            throw new Exception("Insufficient Balance during transaction processing.");
                        }

                        // B. Insert into wallet history
                        $insert_wallet = mysqli_query($DarkXen_conn, "INSERT INTO `wallethistory`
                            (`userid`,`amount`,`balance`,`purpose`,`status`,`type`)
                            VALUES ('$user','$charge','$newbalance','$remark','1','Debit')");

                        // C. Insert into voter pdf without otp history
                        $insert_history = mysqli_query($DarkXen_conn, "INSERT INTO `voter_pdf_without_otp_history` 
                            (`userid`, `epic_no`, `ref_id`, `charge`, `status`, `message`, `created_at`) 
                            VALUES ('$user', '$epic', '$ref_id', '$charge', 'Pending', '$api_message', '$date_time')");

                        if ($update_user && $insert_wallet &&$insert_history) {
                            mysqli_commit($DarkXen_conn);$alert_message = "
                                Swal.fire({
                                    title: 'Submitted Successfully!',
                                    text: '" . addslashes($api_message) . "',
                                    icon: 'success',
                                    confirmButtonText: 'Go to History',
                                    allowOutsideClick: false
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        window.location.href = 'voter_pdf_without_otp_list.php';
                                    }
                                });
                            ";
                        } else {
                            throw new Exception("Database synchronization error. Order Aborted.");
                        }
                    } catch (Exception $e) {
                        mysqli_rollback($DarkXen_conn);
                        $alert_message = "Swal.fire('Error', '" . addslashes($e->getMessage()) . "', 'error');";
                    }
                } else {
                    $err_msg =$resdata['message'] ?? 'Failed to submit request. Please try again.';
                    $alert_message = "Swal.fire('Failed', '" . addslashes($err_msg) . "', 'error');";
                }
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
                    <div class="icon-item bg-info-subtle shadow-none me-3">
                        <span class="fas fa-file-pdf text-info fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-info fs-10 mb-0">Election Commission</h6>
                        <h4 class="text-primary fw-bold mb-0">Voter PDF <span class="fw-medium">Without OTP</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-control form-control-sm bg-white border-200 shadow-sm px-3">
                            <span class="fas fa-wallet text-success me-2"></span>
                            <span class="fw-bold text-success">Balance: ₹<?php echo number_format((float)($udata['balance'] ?? 0), 2); ?></span>
                        </div>
                        <a href="voter_pdf_without_otp_list.php" class="btn btn-sm btn-outline-info shadow-none fw-bold"><i class="fas fa-list me-1"></i> View Requests History</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-info">
            <div class="card-header bg-body-tertiary border-bottom">
                <h5 class="mb-0 text-info fw-bold text-center"><i class="fas fa-cloud-upload-alt me-2"></i>Submit Voter PDF Request</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                
                <div class="alert alert-info border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-info"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?php echo number_format($charge, 2); ?></strong>. Your request will be queued and processed automatically in the background.</p>
                </div>
                
                <form method="POST" autocomplete="off" id="pdfRequestForm" onsubmit="showLoader('Submitting Request...')">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">EPIC Number (Voter ID) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-id-card text-info"></i></span>
                            <input type="text" class="form-control shadow-none text-uppercase text-dark fw-bold tracking-wide" name="epic_no" placeholder="e.g., CQR0841098" required autofocus>
                        </div>
                    </div>
                    
                    <button type="submit" name="submit_pdf_btn" class="btn btn-info text-white w-100 fw-bold shadow-none py-2">
                        <i class="fas fa-paper-plane me-2"></i> Submit PDF Request
                    </button>
                </form>

            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Prevent double clicking on submit
function showLoader(txt) {
    Swal.fire({
        title: txt,
        html: 'Communicating with central servers, please wait...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
}

// Inject PHP Alert Messages
<?php if (!empty($alert_message)) { echo$alert_message; } ?>
</script>

<?php include('footer.php'); ?>