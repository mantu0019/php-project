<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set('Asia/Kolkata');

// ================= INIT & FEE CONFIG =================
$charge = 25.00; // Default Fee
$fee_query = mysqli_query($DarkXen_conn, "SELECT `voter_otp_pdf` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if (!empty($fee_row['voter_otp_pdf'])) {
        $charge = floatval($fee_row['voter_otp_pdf']);
    }
}

if (!function_exists('clean')) {
    function clean($conn,$data) {
        return mysqli_real_escape_string($conn, trim($data));
    }
}

$user = clean($DarkXen_conn,$udata['phone']);
$API_KEY = urlencode($webdata['api_key'] ?? '');
$api_domain =$DarkXen_url;

$showOtpForm = false;
$showPdfButton = false;
$epic = '';$pdfFile = '';
$error_message = '';$status = '';

// Session state persistent rakhne ke liye check
if (!empty($_SESSION['otp_sent']) && !empty($_SESSION['otp_epic'])) {$showOtpForm = true;
    $epic =$_SESSION['otp_epic'];
}

// API Curl Handler
function callApi($url){$ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ["cache-control: no-cache"],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// ================= FORM POST PROCESSING =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 0. RESET / CANCEL OTP (Back Button Logic)
    if (isset($_POST['cancel_otp_btn'])) {
        unset($_SESSION['otp_sent']);
        unset($_SESSION['otp_epic']);
        unset($_SESSION['otp_message']);$showOtpForm = false;
        $epic = '';
    }
    
    // 1. SEND OTP REQUEST
    elseif (isset($_POST['send_otp_btn'])) {$epic = strtoupper(clean($DarkXen_conn,$_POST['epic_no'] ?? ''));

        if (!empty($epic)) {
            // NEW OTP SEND API URL
            $url = "https://" . $api_domain . "/api/v3/VOTER/voter_otp_verification.php?apiKey=$API_KEY&epic=" . urlencode($epic);
            $resdata = callApi($url);

            // API Boolean Status Check
            if (isset($resdata['status']) && $resdata['status'] === true) {$_SESSION['otp_sent'] = true;
                $_SESSION['otp_epic'] =$epic;
                $showOtpForm = true;
                $status = "otp_sent_success";
                
                // Set custom success message from API if available
                $api_message =$resdata['message'] ?? 'OTP has been triggered successfully.';
                $_SESSION['otp_message'] =$api_message;
            } else {
                $error_message =$resdata['message'] ?? 'Failed to send OTP. Invalid EPIC or Server Error.';
            }
        } else {
            $error_message = "Please enter a valid EPIC Number.";
        }
    }

    // 2. VERIFY OTP & GENERATE PDF REQUEST
    elseif (isset($_POST['verify_otp_btn'])) {
        $epic = strtoupper(clean($DarkXen_conn, $_POST['epic_hidden'] ?? ''));$otp  = clean($DarkXen_conn,$_POST['otp_code'] ?? '');

        if (!empty($epic) && !empty($otp)) {
            if ($udata['balance'] >=$charge) {
                
                // NEW VERIFY OTP API URL
                $url = "https://" . $api_domain . "/api/v3/VOTER/voter_pdf_verification.php?apiKey=$API_KEY&epic=" . urlencode($epic) . "&otp=" . urlencode($otp);
                $resdata = callApi($url);

                if (isset($resdata['status']) &&$resdata['status'] === true) {
                    if (empty($resdata['pdf_base64'])) {$error_message = "OTP Verified but PDF Data not received from API.";
                    } else {
                        // Create directory structure dynamically if missing
                        $dir = "uploads/vot_pdfs";
                        if (!is_dir($dir)) {
                            mkdir($dir, 0777, true);
                        }

                        $safeEpic = preg_replace('/[^a-zA-Z0-9]/', '_', $epic);$fileName = $dir . "/" . $safeEpic . "_" . time() . ".pdf";
                        
                        // Save physical file asset 
                        file_put_contents($fileName, base64_decode($resdata['pdf_base64']));
                        $pdfFile =$fileName;

                        // Transaction Database Operations Begin
                        mysqli_begin_transaction($DarkXen_conn);
                        
                        try {
                            $date_time = date('Y-m-d H:i:s');$remark = "Voter Card OTP PDF Processed for EPIC: " . $epic;
                            $newbalance = $udata['balance'] -$charge;

                            // A. Deduct active account balance metrics safely
                            $update_user = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $charge WHERE phone = '$user' AND balance >=$charge");
                            if (mysqli_affected_rows($DarkXen_conn) == 0) {
                                throw new Exception("Insufficient Balance during transaction.");
                            }

                            // B. Insert Log into main tracking list for reports
                            $voter_name = clean($DarkXen_conn,$resdata['name'] ?? 'VOTER CARD');
                            $insert_list = mysqli_query($DarkXen_conn, "INSERT INTO `voter_pdf` 
                                (`username`, `epicno`, `name`, `pdfpath`, `status`, `created_at`) 
                                VALUES ('$user', '$epic', '$voter_name', '$pdfFile', 'Success', '$date_time')");

                            // C. Log into wallet audit logs
                            $insert_wallet = mysqli_query($DarkXen_conn, "INSERT INTO `wallethistory`
                                (`userid`,`amount`,`balance`,`purpose`,`status`,`type`)
                                VALUES ('$user','$charge','$newbalance','$remark','1','Debit')");

                            if ($insert_list &&$insert_wallet) {
                                mysqli_commit($DarkXen_conn);
                                
                                unset($_SESSION['otp_sent']);
                                unset($_SESSION['otp_epic']);
                                unset($_SESSION['otp_message']);$showPdfButton = true;
                                $showOtpForm = false;
                                $status = "pdf_download_success";
                            } else {
                                throw new Exception("Database synchronization error. Order Aborted.");
                            }
                        } catch (Exception $e) {
                            mysqli_rollback($DarkXen_conn);
                            $error_message =$e->getMessage();
                        }
                    }
                } else {
                    $error_message =$resdata['message'] ?? 'Invalid OTP entered or verification expired.';
                    // Clear state to enforce fresh start on single failure
                    unset($_SESSION['otp_sent']);
                    unset($_SESSION['otp_epic']);
                    unset($_SESSION['otp_message']);$showOtpForm = false;
                }
            } else {
                $error_message = "Insufficient Wallet Balance! Please recharge.";
            }
        } else {
            $error_message = "Mandatory execution targets are missing.";
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
                        <h4 class="text-primary fw-bold mb-0">Voter Card <span class="fw-medium">PDF Download</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-control form-control-sm bg-white border-200 shadow-sm px-3">
                            <span class="fas fa-wallet text-success me-2"></span>
                            <span class="fw-bold text-success">Balance: ₹<?php echo number_format((float)($udata['balance'] ?? 0), 2); ?></span>
                        </div>
                        <a href="voter_pdf_list.php" class="btn btn-sm btn-outline-info shadow-none fw-bold"><i class="fas fa-list me-1"></i> View Download History</a>
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
                <h5 class="mb-0 text-info fw-bold text-center"><i class="fas fa-download me-2"></i>Download Original Voter PDF</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                
                <div class="alert alert-info border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-info"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?php echo number_format($charge, 2); ?></strong>. Deducted only when target PDF fetches successfully.</p>
                </div>
                
                <?php if (!$showOtpForm && !$showPdfButton): ?>
                <!-- STEP 1: REQUEST OTP FORM -->
                <form method="POST" autocomplete="off" id="sendOtpForm">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">EPIC Number (Voter ID) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-id-card text-info"></i></span>
                            <input type="text" class="form-control shadow-none text-uppercase text-dark fw-bold tracking-wide" name="epic_no" placeholder="e.g., ABC1234567" required autofocus>
                        </div>
                    </div>
                    
                    <button type="submit" name="send_otp_btn" class="btn btn-info text-white w-100 fw-bold shadow-none py-2" onclick="showLoader('Sending OTP Requests...')">
                        <i class="fas fa-paper-plane me-2"></i> Get OTP
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($showOtpForm): ?>
                <!-- STEP 2: VERIFY OTP FORM -->
                <form method="POST" autocomplete="off" id="verifyOtpForm">
                    <input type="hidden" name="epic_hidden" value="<?php echo htmlspecialchars($epic); ?>">
                    
                    <div class="alert alert-warning border-0 fs-11 mb-3 py-2">
                        <i class="fas fa-info-circle me-2"></i> 
                        <?php echo htmlspecialchars($_SESSION['otp_message'] ?? "OTP has been dispatched to the mobile number registered with EPIC: " . $epic); ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Enter 6-Digit OTP Code <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-key text-warning"></i></span>
                            <input type="text" class="form-control shadow-none tracking-wide text-center fw-bolder text-dark fs-5" maxlength="6" pattern="\d{6}" name="otp_code" placeholder="· · · · · ·" required autofocus>
                        </div>
                    </div>
                    
                    <button type="submit" name="verify_otp_btn" class="btn btn-success w-100 fw-bold shadow-none py-2 mb-2" onclick="showLoader('Verifying Code & Downloading PDF...')">
                        <i class="fas fa-check-double me-2"></i> Verify & Download
                    </button>
                    
                    <!-- NEW CANCEL / BACK BUTTON -->
                    <button type="submit" name="cancel_otp_btn" class="btn btn-light border text-danger w-100 fw-bold shadow-none py-2" formnovalidate>
                        <i class="fas fa-arrow-left me-2"></i> Change EPIC / Go Back
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($showPdfButton): ?>
                <!-- STEP 3: SUCCESSFUL DOWNLOAD REDIRECT BUTTON -->
                <div class="text-center py-3">
                    <div class="mb-3 text-success">
                        <i class="fas fa-check-circle display-4"></i>
                    </div>
                    <h5 class="fw-bold text-success mb-3">Your Digital Voter ID File is Ready!</h5>
                    <a href="<?php echo htmlspecialchars($pdfFile); ?>" target="_blank" class="btn btn-lg btn-primary shadow-sm px-4 fw-bold">
                        <i class="fas fa-file-download me-2"></i> Open / Download PDF File
                    </a>
                    <div class="mt-4">
                        <a href="" class="btn btn-link btn-sm text-secondary text-decoration-none fw-bold">Download Another Card</a>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function showLoader(txt) {
    Swal.fire({
        title: txt,
        html: 'Communicating with central servers, please hold on...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
}
</script>

<?php 
// SweetAlert Interceptors for App Messaging
if (!empty($status) &&$status === "otp_sent_success") {
    echo "<script>
        setTimeout(function() {
            Swal.fire({
                title: 'OTP Transmitted!',
                text: 'OTP has been triggered successfully to the linked profile.',
                icon: 'success',
                confirmButtonText: 'Enter OTP Now'
            });
        }, 100);
    </script>";
}

if (!empty($status) &&$status === "pdf_download_success") {
    echo "<script>
        setTimeout(function() {
            Swal.fire({
                title: 'Generated!',
                text: 'Voter card parsed and saved successfully.',
                icon: 'success',
                confirmButtonText: 'Awesome'
            });
        }, 100);
    </script>";
}

if (!empty($error_message)) {
    echo "<script>
        setTimeout(function() {
            Swal.fire({
                title: 'Execution Failed!',
                text: '" . addslashes($error_message) . "',
                icon: 'error',
                confirmButtonText: 'Dismiss'
            });
        }, 100);
    </script>";
}

include('footer.php'); 
?>