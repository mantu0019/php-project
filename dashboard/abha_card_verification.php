<?php
include('../includes/session.php');
include('../includes/config.php');

date_default_timezone_set('Asia/Kolkata');

// ================= INIT & FEE CONFIG =================
$charge = 30.00; // Default Fee
$fee_query = mysqli_query($DarkXen_conn, "SELECT `abha_card_fee` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if (!empty($fee_row['abha_card_fee'])) {
        $charge = floatval($fee_row['abha_card_fee']);
    }
}

if (!function_exists('clean')) {
    function clean($conn,$data) {
        return mysqli_real_escape_string($conn, trim($data));
    }
}

$user = clean($DarkXen_conn,$udata['phone'] ?? '');
$user_balance = (float)($udata['balance'] ?? 0);
$API_KEY = urlencode($webdata['api_key'] ?? '');
$api_domain =$DarkXen_url;

$step = 1;
$error_message = '';$success_message = '';
$aadhaar_no = '';$txn_id = '';
$otp_message = '';$card_file_path = '';

// Check session state to determine the current step
if (isset($_SESSION['abha_aadhaar']) && isset($_SESSION['abha_txnid'])) {$step = 2;
    $aadhaar_no =$_SESSION['abha_aadhaar'];
    $txn_id =$_SESSION['abha_txnid'];
    $otp_message =$_SESSION['abha_otp_msg'] ?? 'OTP sent successfully.';
}

// API Curl Handler
function callApi($url) {$ch = curl_init();
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

    // --- STEP 0: CANCEL / RESTART ---
    if (isset($_POST['cancel_process'])) {
        unset($_SESSION['abha_aadhaar']);
        unset($_SESSION['abha_txnid']);
        unset($_SESSION['abha_otp_msg']);$step = 1;
        $aadhaar_no = '';
    }

    // --- STEP 1: SEND OTP ---
    elseif (isset($_POST['send_otp_btn'])) {$aadhaar_input = clean($DarkXen_conn,$_POST['aadhaar_no'] ?? '');

        if (empty($aadhaar_input)) {$error_message = "Please enter a valid Aadhaar Number.";
        } elseif ($user_balance < $charge) {$error_message = "Insufficient wallet balance. Recharge required: ₹" . number_format($charge, 2);
        } else {
            // Send OTP API Call
            $url = "https://" . $api_domain . "/api/v3/AADHAR/AbhaVerification_byAadhaar.php?apiKey=$API_KEY&aadhaar=" . urlencode($aadhaar_input);
            $resdata = callApi($url);

            if (isset($resdata['success']) && $resdata['success'] === true && !empty($resdata['txnId'])) {
                $_SESSION['abha_aadhaar'] =$aadhaar_input;
                $_SESSION['abha_txnid'] = $resdata['txnId'];$_SESSION['abha_otp_msg'] = $resdata['message'] ?? 'OTP sent to registered mobile number.';$step = 2;
                $otp_message =$_SESSION['abha_otp_msg'];
            } else {
                $error_message =$resdata['message'] ?? "Failed to send OTP. Please check the details or try again later.";
                $step = 1;
            }
        }
    }

    // --- STEP 2: VERIFY OTP & GENERATE CARD ---
    elseif (isset($_POST['verify_otp_btn'])) {$otp_val = clean($DarkXen_conn,$_POST['otp_code'] ?? '');
        $stored_txn_id =$_SESSION['abha_txnid'] ?? '';
        $stored_aadhaar =$_SESSION['abha_aadhaar'] ?? '';

        if (empty($otp_val) || empty($stored_txn_id)) {$error_message = "Invalid session or missing OTP.";
        } else {
            // Verification API URL
            $url = "https://" . $api_domain . "/api/v3/AADHAR/AbhaVerification_VerifyAadhaar.php?apiKey=$API_KEY&otpval=" . urlencode($otp_val) . "&txnid=" . urlencode($stored_txn_id);
            $resdata = callApi($url);

            if (isset($resdata['success']) &&$resdata['success'] === true && isset($resdata['data'])) {$data = $resdata['data'];$abha_number = clean($DarkXen_conn,$data['abhaNumber'] ?? 'N/A');
                $user_name = clean($DarkXen_conn, $data['name'] ?? 'Unknown');$card_base64 = $data['card_base64'] ?? '';$api_msg = clean($DarkXen_conn,$resdata['message'] ?? 'ABHA Card Generated');

                if (empty($card_base64)) {$error_message = "Verification successful, but Card data is missing from the server.";
                } else {
                    // Create directory if it doesn't exist
                    $dir = "uploads/abha_cards";
                    if (!is_dir($dir)) {
                        mkdir($dir, 0777, true);
                    }

                    // --- IMAGE / PDF CORRUPTION FIX ---
                    if (strpos($card_base64, ',') !== false) {
                        $base64_parts = explode(',',$card_base64);
                        $card_base64 = end($base64_parts);
                    }
                    
                    $card_base64 = str_replace([' ', "\r", "\n"], ['+', '', ''], $card_base64);
                    $card_decoded = base64_decode($card_base64);

                    if ($card_decoded === false) {$error_message = "Received corrupted data from the API. Please try generating again.";
                    } else {
                        // Smart Extension Detection
                        $ext = 'jpg'; // Default to JPG
                        if (strpos(substr($card_decoded, 0, 10), '\%PDF') !== false) {$ext = 'pdf';
                        } elseif (strpos(substr($card_decoded, 0, 10), "\x89PNG") !== false) {
                            $ext = 'png';
                        }

                        // Save the file safely
                        $safe_filename = "ABHA_" . time() . "_" . uniqid() . "." . $ext;
                        $full_card_path = $dir . "/" . $safe_filename;
                        file_put_contents($full_card_path,$card_decoded);
                        $card_file_path =$full_card_path;

                        // --- TRANSACTION BEGIN ---
                        mysqli_begin_transaction($DarkXen_conn);
                        try {
                            $date_time = date('Y-m-d H:i:s');$remark = "ABHA Card Generated for: " . $user_name;

                            // 1. Deduct Balance
                            $update_user = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $charge WHERE phone = '$user' AND balance >=$charge");
                            if (mysqli_affected_rows($DarkXen_conn) == 0) {
                                throw new Exception("Insufficient Balance during transaction processing.");
                            }

                            // Get the new balance for the log
                            $bal_check = mysqli_query($DarkXen_conn, "SELECT balance FROM users WHERE phone='$user'");
                            $newbal = mysqli_fetch_assoc($bal_check)['balance'];

                            // 2. Insert into Wallet History
                            $insert_wallet = mysqli_query($DarkXen_conn, "INSERT INTO `wallethistory`
                                (`userid`,`amount`,`balance`,`purpose`,`status`,`type`)
                                VALUES ('$user','$charge','$newbal','$remark','1','Debit')");

                            // 3. Insert into ABHA History (Note: Database column is `pdf_path`, it works fine for images too)
                            $placeholder_aadhaar = "[Aadhaar Redacted]";
                            $insert_history = mysqli_query($DarkXen_conn, "INSERT INTO `abha_card_history` 
                                (`userid`, `aadhaar_no`, `txn_id`, `abha_number`, `pdf_path`, `charge`, `status`, `message`, `created_at`) 
                                VALUES ('$user', '$placeholder_aadhaar', '$stored_txn_id', '$abha_number', '$full_card_path', '$charge', 'Success', '$api_msg', '$date_time')");

                            if ($update_user && $insert_wallet &&$insert_history) {
                                mysqli_commit($DarkXen_conn);
                                
                                // Clear session
                                unset($_SESSION['abha_aadhaar']);
                                unset($_SESSION['abha_txnid']);
                                unset($_SESSION['abha_otp_msg']);
                                
                                $step = 3; // Success state$success_message = "ABHA Card Generated Successfully!";
                            } else {
                                throw new Exception("Database sync failed. Transaction aborted.");
                            }
                        } catch (Exception $e) {
                            mysqli_rollback($DarkXen_conn);
                            $error_message =$e->getMessage();
                        }
                    }
                }
            } else {
                $err_msg =$resdata['message'] ?? 'OTP Verification Failed.';
                $error_message =$err_msg;
                
                unset($_SESSION['abha_aadhaar']);
                unset($_SESSION['abha_txnid']);
                unset($_SESSION['abha_otp_msg']);$step = 1;
            }
        }
    }
}

// ================= UI RENDERING =================
include('header.php');
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-id-card text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Health Services</h6>
                        <h4 class="text-primary fw-bold mb-0">ABHA Card <span class="text-info fw-medium">Generator</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-control form-control-sm bg-white border-200 shadow-sm px-3">
                            <span class="fas fa-wallet text-success me-2"></span>
                            <span class="fw-bold text-success">Balance: ₹<?php echo number_format((float)($udata['balance'] ?? 0), 2); ?></span>
                        </div>
                        <a href="abha_card_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold">
                            <i class="fas fa-list me-1"></i> View History
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FORM AREA CENTERED -->
<div class="row justify-content-center">
    <div class="col-lg-6 mb-4">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-primary">
            <div class="card-header bg-body-tertiary border-bottom py-3">
                <h5 class="mb-0 text-primary fw-bold text-center">
                    <?php 
                        if($step == 1) echo '<i class="fas fa-fingerprint me-2"></i>Generate via Aadhaar OTP';
                        elseif($step == 2) echo '<i class="fas fa-key me-2"></i>Verify OTP';
                        else echo '<i class="fas fa-check-circle me-2 text-success"></i>Download Ready';
                    ?>
                </h5>
            </div>
            
            <div class="card-body bg-body-tertiary p-4">
                
                <?php if ($step == 1 && $step == 2): ?>
                <div class="alert alert-info border-0 d-flex align-items-center mb-4 p-3">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-info"></i>
                    <div>
                        <p class="mb-0 fw-bold">Service Charge: ₹<?php echo number_format($charge, 2); ?></p>
                        <small class="fs-10">Amount will be deducted only upon successful card generation.</small>
                    </div>
                </div>
                <?php endif; ?>

                <!-- STEP 1: REQUEST OTP -->
                <?php if ($step == 1): ?>
                <form method="POST" autocomplete="off" onsubmit="showLoader('Requesting OTP...')">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Aadhaar Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-id-card text-primary"></i></span>
                            <input type="text" class="form-control shadow-none fw-bold tracking-wide" name="aadhaar_no" maxlength="12" pattern="\d{12}" placeholder="Enter 12-digit Aadhaar Number" required autofocus>
                        </div>
                    </div>
                    
                    <button type="submit" name="send_otp_btn" class="btn btn-primary w-100 fw-bold shadow-none py-2">
                        <i class="fas fa-paper-plane me-2"></i> Send OTP
                    </button>
                </form>
                <?php endif; ?>

                <!-- STEP 2: VERIFY OTP -->
                <?php if ($step == 2): ?>
                <form method="POST" autocomplete="off" onsubmit="showLoader('Verifying and Generating ABHA Card...')">
                    
                    <div class="alert alert-warning border-0 fs-11 mb-4 py-3">
                        <i class="fas fa-info-circle me-2"></i> 
                        <strong>OTP Sent:</strong> <?php echo htmlspecialchars($otp_message); ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Enter OTP Code <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-unlock-alt text-warning"></i></span>
                            <input type="text" class="form-control shadow-none text-center fw-bolder text-dark fs-5 tracking-wide" name="otp_code" placeholder="· · · · · ·" required autofocus>
                        </div>
                    </div>
                    
                    <button type="submit" name="verify_otp_btn" class="btn btn-success w-100 fw-bold shadow-none py-2 mb-3">
                        <i class="fas fa-check-double me-2"></i> Verify & Generate Card
                    </button>
                    
                    <button type="submit" name="cancel_process" class="btn btn-light border text-danger w-100 fw-bold shadow-none py-2" formnovalidate>
                        <i class="fas fa-times me-2"></i> Cancel / Restart
                    </button>
                </form>
                <?php endif; ?>

                <!-- STEP 3: SUCCESS & DOWNLOAD -->
                <?php if ($step == 3): ?>
                <div class="text-center py-4">
                    
                    <!-- DYNAMICALLY SHOW IMAGE IF IT IS NOT A PDF -->
                    <?php 
                        $file_ext = strtolower(pathinfo($card_file_path, PATHINFO_EXTENSION));
                        if (in_array($file_ext, ['jpg', 'jpeg', 'png'])): 
                    ?>
                        <div class="mb-4">
                            <img src="<?php echo htmlspecialchars($card_file_path); ?>" class="img-fluid rounded shadow-sm border" alt="ABHA Card Preview">
                        </div>
                    <?php else: ?>
                        <div class="mb-3 text-success">
                            <i class="fas fa-check-circle display-4"></i>
                        </div>
                    <?php endif; ?>

                    <h5 class="fw-bold text-success mb-2">ABHA Card Generated!</h5>
                    <p class="text-muted fs-11 mb-4">Your document is ready.</p>
                    
                    <a href="<?php echo htmlspecialchars($card_file_path); ?>" target="_blank" class="btn btn-primary shadow-sm px-4 py-2 fw-bold w-100 mb-3">
                        <i class="fas fa-download me-2"></i> View / Download
                    </a>
                    
                    <form method="POST">
                         <button type="submit" name="cancel_process" class="btn btn-outline-secondary w-100 fw-bold shadow-none py-2">
                            Generate Another Card
                        </button>
                    </form>
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
        html: 'Communicating with central servers, please wait...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
}

<?php if (!empty($error_message)): ?>
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: '<?php echo addslashes($error_message); ?>',
        confirmButtonColor: '#d33'
    });
<?php endif; ?>

<?php if (!empty($success_message)): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '<?php echo addslashes($success_message); ?>',
        confirmButtonColor: '#28a745',
        timer: 3000,
        showConfirmButton: false
    });
<?php endif; ?>
</script>

<?php include('footer.php'); ?>