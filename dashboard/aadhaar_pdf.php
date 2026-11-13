<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set('Asia/Kolkata');

// ================= INIT & FEE CONFIG =================
$fee = 10.00; // Default Standard Fee
$fee_query = mysqli_query($DarkXen_conn, "SELECT `aadhar_otp_pdf` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if (!empty($fee_row['aadhar_otp_pdf'])) {
        $fee = floatval($fee_row['aadhar_otp_pdf']);
    }
}

if (!function_exists('clean')) {
    function clean($conn,$data) {
        return mysqli_real_escape_string($conn, trim($data));
    }
}

$appliedby = clean($DarkXen_conn,$udata['phone']);

// --- API URL CONSTRUCTION ---
$DarkXen_apikey =$webdata['api_key']; 
$api_key = urlencode($DarkXen_apikey);

$error_message = '';
$status_flag = '';$step = 1; // 1 = Enter Aadhaar + Captcha, 2 = Enter OTP, 3 = Download PDF
$aadhar = '';$txnId = '';
$pdf_base64 = '';$resdata = [];

// ================= STEP 3: VERIFY OTP & GENERATE PDF =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp_btn'])) {$aadhar = clean($DarkXen_conn,$_POST['aadhar_number'] ?? '');
    $txnId  = clean($DarkXen_conn, $_POST['txn_id'] ?? '');$otp    = clean($DarkXen_conn,$_POST['otp'] ?? '');

    if (!empty($aadhar) && !empty($txnId) && !empty($otp)) {
        if ($udata['balance'] >= $fee) {$url = "https://" . $DarkXen_url . "/api/v3/AADHAR/verify_otp.php?" . http_build_query([
                'apiKey' => $api_key,
                'aadhar' => $aadhar,
                'token'  => $txnId,
                'otp'    => $otp
            ]);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0'
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            $resdata = json_decode($response, true);

            // Assuming response contains status: true and PDF base64 inside data array or directly
            $pdf_data =$resdata['data']['pdf'] ?? $resdata['pdf'] ?? $resdata['pdfBase64'] ?? '';

            if ((isset($resdata['status']) && $resdata['status'] == true) && !empty($pdf_data)) {
                
                mysqli_begin_transaction($DarkXen_conn);$debit_fee = $udata['balance'] -$fee;
                $date_time = date('Y-m-d H:i:s');$remark = "Aadhaar PDF Download Debit";
                
                $eid = clean($DarkXen_conn, $resdata['data']['eid'] ?? '');$vid = clean($DarkXen_conn,$resdata['data']['vid'] ?? '');
                $pdf_base64 =$pdf_data;

                // 1. Deduct balance
                $update_user = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby' AND balance >=$fee");

                // 2. Insert into Wallet History
                $insert_wallet = mysqli_query($DarkXen_conn, "INSERT INTO `wallethistory`
                    (`userid`, `amount`, `balance`, `purpose`, `status`, `type`)
                    VALUES ('$appliedby', '$fee', '$debit_fee', '$remark', '1', 'Debit')");

                // 3. Insert record into History Table
                $insert_history = mysqli_query($DarkXen_conn, "INSERT INTO `aadhar_pdf_history` 
                    (`user_phone`, `pdf`, `aadhar_no`, `eid`, `vid`, `charge_debited`, `created_at`) 
                    VALUES 
                    ('$appliedby','$pdf_base64','$aadhar', '$eid', '$vid', '$fee', '$date_time')");

                if ($update_user &&$insert_wallet) {
                    mysqli_commit($DarkXen_conn);
                    $step = 3; // Download ready$status_flag = "pdf_ready";
                } else {
                    mysqli_rollback($DarkXen_conn);$error_message = "Wallet transaction failed! Please contact support.";
                    $status_flag = "error";
                    $step = 1;
                }
            } else {
                $step = 2; // Keep on OTP page so user can re-enter$error_message = $resdata['message'] ?? 'Invalid OTP or PDF generation failed.';$status_flag = "error";
            }
        } else {
            $status_flag = "low_balance";
            $step = 1;         }     } else {$error_message = "All fields (Aadhaar, Txn ID, and OTP) are required.";
        $status_flag = "error";
        $step = 1;
    }
}

// ================= STEP 2: SEND OTP =================
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_otp_btn'])) {$aadhar = clean($DarkXen_conn,$_POST['aadhar_number'] ?? '');
    $captchaVal = clean($DarkXen_conn, $_POST['captcha_value'] ?? '');$captchaTxnId = clean($DarkXen_conn,$_POST['captcha_txn_id'] ?? '');

    if (!empty($aadhar) && strlen($aadhar) === 12) {
        if ($udata['balance'] >= $fee) {$url = "https://" . $DarkXen_url . "/api/v3/AADHAR/uid_send_otp.php?" . http_build_query([
                'apiKey'       => $api_key,
                'aadhaar_no'   => $aadhar,
                'captchaValue' => $captchaVal,
                'captchaTxnId' => $captchaTxnId
            ]);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0'
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            $resdata = json_decode($response, true);

            if (isset($resdata['status']) &&$resdata['status'] == true) {
                // Fetching token/txnId from response (depends on how API returns it)
                $txnId =$resdata['txnId'] ?? $resdata['token'] ?? $resdata['data']['txnId'] ?? '';
                $step = 2; // Move to OTP Step$status_flag = "otp_sent";
            } else {
                $error_message =$resdata['message'] ?? 'OTP Generation failed. Please try again.';
                $status_flag = "error";
                $step = 1; // Send back to step 1 to reload captcha
            }
        } else {
            $status_flag = "low_balance";
            $step = 1;         }     } else {$error_message = "Invalid Aadhaar Number! Please enter a valid 12-digit number.";
        $status_flag = "error";
        $step = 1;
    }
}

// ================= STEP 1: FETCH CAPTCHA =================
// Only fetch if we are on Step 1 to save API calls
$captchaImage = '';
$captchaText = '';$captchaTxnId = '';

if ($step === 1) {$ch = curl_init("https://" . $DarkXen_url . "/api/v3/AADHAR/aadhar_captcha_verification.php");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    $capRes = curl_exec($ch);
    curl_close($ch);

    $capData = json_decode($capRes, true);
    if (isset($capData['status']) &&$capData['status'] == true) {
        $captchaImage =$capData['captchaImageBase64'] ?? '';
        $captchaText  =$capData['captchaText'] ?? '';
        $captchaTxnId =$capData['transactionId'] ?? '';
    } else {
        $error_message = "Failed to load Captcha from server.";
        $status_flag = "error";
    }
}
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-file-pdf text-danger fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">UIDAI Services</h6>
                        <h4 class="text-dark fw-bold mb-0">Aadhaar <span class="text-danger fw-medium">PDF Download</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-control form-control-sm bg-white border-200 shadow-sm px-3">
                            <span class="fas fa-wallet text-success me-2"></span>
                            <span class="fw-bold text-success">Balance: ₹<?php echo number_format((float)($udata['balance'] ?? 0), 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm border-top border-4 border-danger">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-dark fw-bold text-center">
                    <i class="fas fa-id-card me-2 text-danger"></i> Aadhaar PDF Generation
                </h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">

                <div class="alert alert-info border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-info-circle fs-3 me-3 text-info"></i>
                    <p class="mb-0 fs-11">Download Fee: <strong>₹<?php echo number_format($fee, 2); ?></strong>. Balance will be deducted only upon successful PDF generation.</p>
                </div>

                <!-- STEP 1: AADHAAR & CAPTCHA INPUT FORM -->
                <?php if ($step === 1) { ?>
                <form method="POST" autocomplete="off" id="sendOtpForm">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-900">Aadhaar Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-fingerprint text-secondary"></i></span>
                            <input type="text" class="form-control shadow-none fw-bold fs-9" name="aadhar_number" value="<?php echo htmlspecialchars($aadhar); ?>" placeholder="Enter 12-digit Aadhaar Number" maxlength="12" pattern="\d{12}" required autofocus>
                        </div>
                    </div>
                    
                    <?php if(!empty($captchaImage)): ?>
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Security Captcha <span class="text-danger">*</span></label>
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="bg-white border rounded p-1">
                                
                                <!-- Base64 Image rendered directly -->
                                <img src="data:image/jpeg;base64,<?php echo $captchaImage; ?>" alt="UIDAI Captcha" style="height: 50px;">
                            </div>
                            <a href="aadhaar_pdf.php" class="btn btn-sm btn-outline-primary shadow-none" title="Reload Captcha"><i class="fas fa-sync-alt"></i></a>
                        </div>
                        
                        <input type="hidden" name="captcha_txn_id" value="<?php echo htmlspecialchars($captchaTxnId); ?>">
                        
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-shield-alt text-secondary"></i></span>
                            <!-- Value auto-filled but editable by user -->
                            <input type="text" class="form-control shadow-none fw-bold fs-9" name="captcha_value" value="<?php echo htmlspecialchars($captchaText); ?>" placeholder="Enter Captcha" required>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <button class="btn btn-danger w-100 fw-bold shadow-none py-2 mt-2" type="submit" name="send_otp_btn" onclick="triggerOtpLoading()">
                        <i class="fas fa-paper-plane me-2"></i> Send OTP to Linked Mobile
                    </button>
                </form>
                <?php } ?>

                <!-- STEP 2: OTP VERIFY FORM -->
                <?php if ($step === 2) { ?>
                <form method="POST" autocomplete="off" id="verifyOtpForm">
                    <input type="hidden" name="aadhar_number" value="<?php echo htmlspecialchars($aadhar); ?>">
                    <input type="hidden" name="txn_id" value="<?php echo htmlspecialchars($txnId); ?>">

                    <div class="alert alert-success border-0 mb-3 fs-11">
                        <i class="fas fa-check-circle me-1"></i> OTP has been sent successfully to mobile linked with Aadhaar: <b>XXXX-XXXX-<?php echo substr($aadhar, -4); ?></b>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Enter 6-Digit OTP <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-key text-secondary"></i></span>
                            <input type="text" class="form-control shadow-none fw-bold text-center fs-8 text-primary" name="otp" placeholder="Enter Received OTP" maxlength="6" pattern="\d{6}" required autofocus>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <a href="aadhaar_pdf.php" class="btn btn-secondary w-50 fw-bold shadow-none">Cancel</a>
                        <button class="btn btn-success w-50 fw-bold shadow-none py-2" type="submit" name="verify_otp_btn" onclick="triggerVerifyLoading()">
                            <i class="fas fa-download me-2"></i> Verify & Get PDF
                        </button>
                    </div>
                </form>
                <?php } ?>

                <!-- STEP 3: SUCCESS & DOWNLOAD -->
                <?php if ($step === 3 && !empty($pdf_base64)) { ?>
                <div class="text-center py-3">
                    <div class="mb-3">
                        <i class="fas fa-file-pdf text-danger fa-4x animate__animated animate__bounceIn"></i>
                    </div>
                    <h5 class="text-success fw-bold">Aadhaar PDF Generated Successfully!</h5>
                    
                    <!-- PASSWORD INSTRUCTION NOTE ADDED HERE -->
                    <div class="alert alert-warning border-0 mt-3 mb-3 text-start fs-10 shadow-sm">
                        <i class="fas fa-lock text-warning me-2"></i> <strong>Note:</strong> Your Aadhaar PDF is password protected.<br>
                        Password is the <b>First 4 letters of your name</b> in CAPITAL followed by your <b>Year of Birth</b>.<br>
                        <span class="text-muted">Example: Name = <i>Shivam Kumar</i>, DOB = <i>01-01-2006</i> ➔ Password = <b>SHIV2006</b></span>
                    </div>
                    
                    <a href="data:application/pdf;base64,<?php echo $pdf_base64; ?>" download="Aadhaar_<?php echo htmlspecialchars($aadhar); ?>.pdf" class="btn btn-primary btn-lg fw-bold px-4 mt-2 shadow">
                        <i class="fas fa-download me-2"></i> Download Aadhaar PDF
                    </a>
                    
                    <div class="mt-4">
                        <a href="aadhaar_pdf.php" class="btn btn-sm btn-outline-secondary">Download Another PDF</a>
                    </div>
                </div>
                <?php } ?>

            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function triggerOtpLoading() {
    if(document.getElementById('sendOtpForm').checkValidity()) {
        Swal.fire({
            title: 'Sending OTP...',
            text: 'Requesting OTP from UIDAI gateway, please wait.',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
    }
}

function triggerVerifyLoading() {
    if(document.getElementById('verifyOtpForm').checkValidity()) {
        Swal.fire({
            title: 'Generating PDF...',
            text: 'Verifying OTP and rendering e-Aadhaar PDF document...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
    }
}
</script>

<?php
// SweetAlert Alerts Handler
if (!empty($status_flag)) {
    if ($status_flag === "otp_sent") {
        echo "<script>
            Swal.fire({
                title: 'OTP Sent!',
                text: 'OTP has been dispatched to linked mobile number.',
                icon: 'success',
                confirmButtonText: 'Enter OTP'
            });
        </script>";
    } 
    elseif ($status_flag === "pdf_ready") {
        echo "<script>
            Swal.fire({
                title: 'Success!',
                text: 'Your Aadhaar PDF has been generated.',
                icon: 'success',
                confirmButtonText: 'Great!'
            });
        </script>";
    }
    elseif ($status_flag === "low_balance") {
        echo "<script>
            Swal.fire({
                title: 'Insufficient Balance!',
                text: 'Please recharge your wallet to proceed.',
                icon: 'error',
                showConfirmButton: false,
                timer: 2000
            });
            setTimeout(function() { window.location = 'wallet.php'; }, 2000);
        </script>";
    }
    elseif ($status_flag === "error") {
        $msg = addslashes($error_message);
        echo "<script>
            Swal.fire({
                title: 'Failed!',
                text: '$msg',
                icon: 'error',
                confirmButtonText: 'Try Again'
            });
        </script>";
    }
}

include('footer.php'); 
?>