<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

// Set timezone once
date_default_timezone_set("Asia/Kolkata");


// Fetch Fee from Database (Service Charge)
$fee_query = mysqli_query($DarkXen_conn, "SELECT `pan_uid_verify` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if ($fee_row['pan_uid_verify'] !== '' &&$fee_row['pan_uid_verify'] !== null) {
        $fee = (float)$fee_row['pan_uid_verify'];
    }
}

$DarkXen_apikey =$webdata['api_key'] ?? ''; 
$api_domain =$DarkXen_url;

// Check if form is submitted 
if (isset($_POST['find_aadhar_btn'])) {
    $pan_no = strtoupper(trim($_POST['pan_no']));

    // Basic PAN validation
    if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/i', $pan_no)) {$error_message = "Invalid PAN format.";
    } else {
        $username =$udata['phone'];
        $wallet_amount = (float)($udata['balance'] ?? 0);

        // Check wallet balance
        if ($wallet_amount <$fee) {
            ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    Swal.fire({
                        icon: 'error',
                        title: 'Insufficient Balance',
                        text: 'Your wallet balance is too low to process this request',
                    }).then(() => {
                        window.location.href = 'wallet.php';
                    });
                });
            </script>
            <?php
            exit;
        }

        // Call New API
        $url = "https://" . $api_domain . "/api/v3/PAN/pan_to_aadhaar_verification.php?" . http_build_query([
            'apiKey' => $DarkXen_apikey,
            'panNumber' => $pan_no
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {$error_message = "Server Connection Error: $err";
        } else {
            $json = json_decode($response, true);

            // Check for success status from new API (status = true)
            if (isset($json['status']) && $json['status'] === true) {$name = $json['name'] ?? 'Not Available';$aadhaar_no = $json['aadhaar_number'] ?? 'Not Available';$dob = $json['dob'] ?? 'Not Available';$gender = $json['gender'] ?? 'Not Available';$timestamp = date("Y-m-d H:i:s");

                // Start transaction
                mysqli_begin_transaction($DarkXen_conn);

                try {
                    // 1. Deduct fee from the wallet
                    $clean_username = mysqli_real_escape_string($DarkXen_conn,$username);
                    $debit = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone='$clean_username' AND balance >=$fee");
                    
                    if (mysqli_affected_rows($DarkXen_conn) == 0) {
                        throw new Exception("Fee deduction failed due to low balance.");
                    }

                    // 2. Update wallet history
                    $new_balance = $wallet_amount -$fee;
                    $remark = "PAN to Aadhaar Find: $pan_no";
                    $stmt2 = mysqli_prepare($DarkXen_conn, "INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES (?, ?, ?, ?, '1', 'Debit')");
                    mysqli_stmt_bind_param($stmt2, "sdds", $username,$fee, $new_balance,$remark);
                    mysqli_stmt_execute($stmt2);

                    // 3. Insert into PAN fetch history (Mapping aadhaar_no in fathername column as per your previous logic)
                    $insert_sql = "INSERT INTO pan_fetch_history (userid, pan_no, name, fathername, dob, created_at) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt_save = mysqli_prepare($DarkXen_conn,$insert_sql);
                    mysqli_stmt_bind_param($stmt_save, "ssssss", $username,$pan_no, $name,$aadhaar_no, $dob,$timestamp);
                    mysqli_stmt_execute($stmt_save);

                    mysqli_commit($DarkXen_conn);

                    // Set response data for display
                    $resdata = [
                        'status' => 'success',
                        'pan_no' => $pan_no,
                        'aadhaar_no' => $aadhaar_no,
                        'name' => $name,
                        'gender' => $gender,
                        'dob' => $dob,
                        'fetch_time' => date('d/m/Y g:i:s A')
                    ];

                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);$error_message = "Database error: " . $e->getMessage();
                }
            } else {
                $error_message =$json['message'] ?? "Failed to verify PAN or Aadhaar not linked.";
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
                        <span class="fas fa-id-card-alt text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Identity Services</h6>
                        <h4 class="text-primary fw-bold mb-0">PAN To <span class="fw-medium">Aadhaar Find</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="pan_to_aadhar_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View Search History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-primary">
            <div class="card-header bg-body-tertiary border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-search me-2"></i>Find Aadhaar by PAN</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger border-0 d-flex align-items-center mb-4">
                        <i class="fas fa-exclamation-triangle fs-3 me-3"></i>
                        <div>
                            <h6 class="mb-0 fw-bold">Query Failed</h6>
                            <span class="fs-11"><?php echo htmlspecialchars($error_message); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($resdata)): ?>
                    <!-- Success Result Display -->
                    <div class="alert alert-success border-0 mb-4 shadow-sm">
                        <h6 class="alert-heading fw-bold"><i class="fas fa-check-circle me-2"></i>Details Found Successfully!</h6>
                        <hr>
                        <div class="fs-10">
                            <div class="d-flex justify-content-between mb-2 border-bottom pb-1">
                                <span class="text-muted">PAN Number:</span> 
                                <strong class="font-family-monospace"><?php echo htmlspecialchars($resdata['pan_no']); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2 border-bottom pb-1">
                                <span class="text-muted">Aadhaar Number:</span> 
                                <strong class="text-danger font-family-monospace fs-9 tracking-wide"><?php echo htmlspecialchars($resdata['aadhaar_no']); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2 border-bottom pb-1">
                                <span class="text-muted">Full Name:</span> 
                                <strong class="text-uppercase"><?php echo htmlspecialchars($resdata['name']); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2 border-bottom pb-1">
                                <span class="text-muted">Date of Birth:</span> 
                                <strong><?php echo htmlspecialchars($resdata['dob']); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Gender:</span> 
                                <strong class="text-uppercase"><?php echo htmlspecialchars($resdata['gender']); ?></strong>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="alert alert-primary border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-primary"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?php echo number_format($fee, 2); ?></strong> will be deducted upon successful search.</p>
                </div>
                
                <form method="POST" autocomplete="off" id="panToAadharForm">
                    <div class="mb-4">
                        <label class="form-label fw-bold">PAN Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-keyboard text-secondary"></i></span>
                            <input type="text" class="form-control shadow-none fw-bold text-uppercase text-center fs-9 tracking-wide" name="pan_no" maxlength="10" pattern="[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}" placeholder="e.g. ABCDE1234F" required autofocus>
                        </div>
                    </div>
                    
                    <button type="submit" name="find_aadhar_btn" class="btn btn-primary w-100 fw-bold shadow-none py-2" onclick="showLoader('Searching Database...')">
                        <i class="fas fa-fingerprint me-2"></i> Get Linked Aadhaar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Only allow Valid PAN characters
    document.getElementsByName('pan_no')[0].addEventListener('input', function () {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    });

    function showLoader(txt) {
        var panInput = document.getElementsByName('pan_no')[0];
        if(panInput.checkValidity() && panInput.value !== "") {
            Swal.fire({
                title: txt,
                html: 'Communicating with central servers, please wait...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
        }
    }
</script>

<?php include('footer.php'); ?>