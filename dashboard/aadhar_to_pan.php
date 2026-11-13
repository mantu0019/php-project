<?php
// ==========================================
//        DARKXEN API - CORE SYSTEM
// ==========================================

include('../includes/session.php');
include('../includes/config.php');
include('header.php');

// Set Timezone
date_default_timezone_set("Asia/Kolkata"); 

// Fetch Fee and Settings from Database
$webres = mysqli_query($DarkXen_conn, "SELECT api_key FROM settings WHERE id=1");
$webdata = mysqli_fetch_assoc($webres);

$fee_query = mysqli_query($DarkXen_conn, "SELECT `aadhaar_to_pan` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if ($fee_row['aadhaar_to_pan'] !== '' &&$fee_row['aadhaar_to_pan'] !== null) {
        $fee = (float)$fee_row['aadhaar_to_pan'];
    }
}

$resdata = [];$error_message = "";
$success_message = "";

// Form Submission Check
if (isset($_POST['req_pan_btn'])) {

    $uidNumber = trim($_POST['aadhaar_no'] ?? '');
    $appliedby =$udata['phone'];
    $wallet_amount = (float)$udata['balance'];

    // Identity Validation
    if (!preg_match('/^[0-9]{12}$/', $uidNumber)) {$error_message = "Invalid 12-Digit Identity Number";
    } else {

        // Wallet Balance Check
        if ($wallet_amount >=$fee) {
            
            // --- API URL CONSTRUCTION ---
            $DarkXen_apikey =$webdata['api_key']; 
            $api_key = urlencode($DarkXen_apikey);
            
            // Updated Endpoint Path and Parameter Names
            $url = "https://" . $DarkXen_url . "/api/v3/PAN/aadhaar_to_pan_verification.php?apiKey=$api_key&uidNumber=$uidNumber";

            // --- CURL REQUEST EXACTLY AS PROVIDED ---
            $curl = curl_init();
            curl_setopt_array($curl, array(
              CURLOPT_URL => $url,
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 30,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'GET',
              CURLOPT_SSL_VERIFYPEER => false,
              CURLOPT_SSL_VERIFYHOST => 0
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);

            if ($err) {$error_message = "Server Connection Failed: " . $err;
            } else {
                $resdata = json_decode($response, true);

                // ===== SUCCESS RESPONSE MATCHING NEW JSON SCHEMA =====
                // The API returns status: 100 and statusMessage: "success"
                if (isset($resdata['status']) &&$resdata['status'] == '100') {
                    
                    // Begin Database Transaction
                    mysqli_begin_transaction($DarkXen_conn);

                    try {
                        // 1. Deduct Balance
                        $debit = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");
                        if (!$debit) {
                            throw new Exception("Wallet deduction failed.");
                        }

                        // 2. Wallet History Update
                        $debit_fee = $wallet_amount -$fee;
                        $history_purpose = "PAN FIND CHARGE";
                        $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES (?, ?, ?, ?, '1', 'Debit')");
                        mysqli_stmt_bind_param($stmt_wallet, "sdds", $appliedby,$fee, $debit_fee,$history_purpose);
                        mysqli_stmt_execute($stmt_wallet);
                        mysqli_stmt_close($stmt_wallet);

                        // 3. Save to PAN FIND History using New JSON Keys
                        $application_no = mysqli_real_escape_string($DarkXen_conn,$resdata['applicationNo'] ?? 'TNX' . time());
                        $aadhaar_no_db = mysqli_real_escape_string($DarkXen_conn,$resdata['uidNumber'] ?? $uidNumber);$full_pan = mysqli_real_escape_string($DarkXen_conn,$resdata['panNumber'] ?? '');
                        
                        // Fallback logic for Half PAN
                        $half_pan = substr($full_pan, 0, 5) . 'XXXX' . substr($full_pan, -1);$order_id = 'ORD' . rand(100000, 999999); 
                        $date = date('Y-m-d H:i:s');

                        $insert_query = "INSERT INTO pan_find (application_no, username, aadhaar_no, pan_no, date, order_id, full_pan) 
                                         VALUES ('$application_no', '$appliedby', '$aadhaar_no_db', '$half_pan', '$date', '$order_id', '$full_pan')";
                        
                        if (!mysqli_query($DarkXen_conn,$insert_query)) {
                            throw new Exception("Failed to save PAN record to database.");
                        }

                        mysqli_commit($DarkXen_conn);$success_message = "PAN Fetched and Recorded Successfully!";

                    } catch (Exception $e) {
                        mysqli_rollback($DarkXen_conn);$error_message = "Transaction Error: " . $e->getMessage();
                    }

                } else {
                    $error_message =$resdata['statusMessage'] ?? "Something went wrong with API Response";
                }
            }
        } else {
            $error_message = "Wallet Balance Low. Please add funds.";
        }
    }
}
?>

<!-- FRONTEND UI (Premium Look) -->
<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border" style="border-radius: 14px;">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3" style="width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 12px;">
                        <span class="fas fa-id-card text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0 text-uppercase tracking-wide">Identity Services</h6>
                        <h4 class="text-primary fw-bolder mb-0">AADHAR To PAN <span class="fw-medium text-dark">INSTANT FIND</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="aadhar_to_pan_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold rounded-pill px-4"><i class="fas fa-list-ul me-1"></i> Track Requests</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-primary" style="border-radius: 16px;">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 text-dark fw-bolder text-center"><i class="fas fa-search me-2 text-primary"></i>AADHAR To PAN FIND INSTANT</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4" style="background: linear-gradient(to bottom, #ffffff, #f8fafc);">
                <div class="alert bg-soft-primary border-0 d-flex align-items-center mb-4 p-3 shadow-sm" style="border-radius: 12px;">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-primary"></i>
                    <p class="mb-0 fs-11 text-dark">Service charge: <strong class="text-primary">₹<?= number_format($fee, 2); ?></strong> will be deducted from your wallet for this request.</p>
                </div>
                
                <form method="POST" autocomplete="off" id="reqForm">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark">12-Digit Identity Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-primary"><i class="fas fa-fingerprint"></i></span>
                            <input type="text" class="form-control border-start-0 shadow-none fw-bold tracking-wide py-2" id="aadhaar_no" name="aadhaar_no" maxlength="12" placeholder="Enter 12-digit Number" required autofocus>
                        </div>
                    </div>
                    
                    <button type="submit" name="req_pan_btn" class="btn btn-primary w-100 fw-bold shadow-sm py-2 rounded-pill hover-lift">
                        <i class="fas fa-cloud-upload-alt me-2"></i> Submit Verification Request
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .hover-lift { transition: transform 0.2s; }
    .hover-lift:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(79, 70, 229, 0.2) !important; }
    .tracking-wide { letter-spacing: 0.05em; }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // Allow only numeric input
    document.getElementById('aadhaar_no').addEventListener('input', function () {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    // Premium Loading SweetAlert
    document.getElementById('reqForm').addEventListener('submit', function(e) {
        // Simple client-side validation
        const val = document.getElementById('aadhaar_no').value;
        if(val.length !== 12) {
            e.preventDefault();
            Swal.fire({
                title: 'Invalid Format',
                text: 'Please enter a valid 12-digit number.',
                icon: 'warning',
                confirmButtonColor: '#ef4444'
            });
            return;
        }

        Swal.fire({
            title: 'Processing Verification',
            html: 'Connecting to PAN FIND ...<br><br><b>Please do not refresh the page.</b>',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => { Swal.showLoading(); }
        });
    });

    // PHP Message Alerts
    <?php if(!empty($error_message)): ?>
        Swal.fire({
            title: 'Request Failed', 
            text: '<?= $error_message; ?>', 
            icon: 'error',
            confirmButtonColor: '#ef4444'
        });
    <?php endif; ?>

    <?php if(!empty($success_message)): ?>
        Swal.fire({
            title: 'Record Found!',
            text: '<?= $success_message; ?>',
            icon: 'success',
            confirmButtonColor: '#10b981'
        }).then(() => {
            window.location.href = 'aadhar_to_pan_list.php';
        });
    <?php endif; ?>
</script>

<?php include('footer.php'); ?>