<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 

$charge = 10.00; // Default charge

// Fetch Fee (Update column name 'rc_mobile' if yours is different)
$fee_query = mysqli_query($DarkXen_conn, "SELECT `rc_mobile` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if (isset($fee_row['rc_mobile']) &&$fee_row['rc_mobile'] !== '') {
        $charge = (float)$fee_row['rc_mobile'];
    }
}

$mobile_result = '';
$error_message = '';$success_message = '';
$user =$udata['phone'];
$wallet_amount = (float)$udata['balance'];

// FORM SUBMISSION CHECK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_mobile_btn'])) {

    $rcno = strtoupper(trim($_POST['vehicle_number'] ?? ''));

    if (empty($rcno)) {$error_message = "Vehicle number cannot be empty.";
    } elseif ($wallet_amount <$charge) {
        echo '
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            Swal.fire("Low Balance", "Recharge Required to fetch Mobile No.", "error").then(()=>{
                window.location="wallet.php";
            });
        });
        </script>';
        exit;
    } else {

        // ================= API CALL =================
        $apiKey = urlencode($DarkXen_apikey);
        $url = "https://" . $DarkXen_url . "/api/v3/VEHICLE/vehicle_2_mobile_verification?apiKey=$apiKey&rc=$rcno";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {$error_message = "API Server Timeout: " . $curl_error;
        } else {
            $resdata = json_decode($response, true);
            
            // Check status & code
            if (isset($resdata['status']) && $resdata['status'] === 'success' &&$resdata['code'] == 200) {
                
                $mobile_result =$resdata['mobile_no'] ?? '';
                
                if (!empty($mobile_result)) {
                    // ================= SAFE DATABASE TRANSACTION =================
                    mysqli_begin_transaction($DarkXen_conn);

                    try {
                        // 1. Deduct Balance
                        $newbalance = $wallet_amount -$charge;
                        $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance='$newbalance' WHERE phone='$user'");
                        if (!$update_wallet) {
                            throw new Exception("Wallet deduction failed.");
                        }

                        // 2. Add Wallet History
                        $remark = "RC to Mobile Fetch : " . $rcno;
                        $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES (?, ?, ?, ?, '1', 'Debit')");
                        mysqli_stmt_bind_param($stmt_wallet, "sdds", $user,$charge, $newbalance,$remark);
                        mysqli_stmt_execute($stmt_wallet);
                        mysqli_stmt_close($stmt_wallet);

                        // 3. Save to History Table
                        $current_date = date('Y-m-d H:i:s');$insert_sql = "INSERT INTO `rc_mobile_history` (`username`, `vehicle_no`, `mobile_no`, `date`) 
                                       VALUES ('$user', '$rcno', '$mobile_result', '$current_date')";
                        
                        if (!mysqli_query($DarkXen_conn,$insert_sql)) {
                            throw new Exception("Failed to save record in history.");
                        }

                        mysqli_commit($DarkXen_conn);
                        $success_message =$resdata['message'] ?? "Mobile Number Fetched Successfully!";

                    } catch (Exception $e) {
                        mysqli_rollback($DarkXen_conn);$error_message = "Transaction Error: " . $e->getMessage();$mobile_result = ''; // Hide result on fail
                    }
                } else {
                    $error_message = "Mobile number not found in response.";
                }
            } else {
                $error_message =$resdata['message'] ?? 'Details Not Found / Server Error';
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
                        <span class="fas fa-mobile-alt text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">RTO Services</h6>
                        <h4 class="text-primary fw-bold mb-0">RC to <span class="text-info fw-medium">Mobile Number</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="rc_mobile_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row <?= empty($mobile_result) ? 'justify-content-center' : '' ?> mb-4">
    
    <!-- Left Column: Form -->
    <div class="<?= empty($mobile_result) ? 'col-lg-6' : 'col-lg-5' ?>">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-primary">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-search me-2"></i>Find Mobile Number</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-primary border-0 d-flex align-items-center mb-4 p-2">
                    <i class="fas fa-rupee-sign fs-4 me-2 text-primary"></i>
                    <p class="mb-0 fs-11 text-dark">Charge: <strong>₹<?= number_format($charge, 2); ?></strong> will be deducted.</p>
                </div>
                
                <form method="POST" id="rcForm" autocomplete="off">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Vehicle Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-car"></i></span>
                            <input type="text" class="form-control shadow-none text-uppercase fw-bold" id="vehicle_number" name="vehicle_number" placeholder="e.g. BR06GG1946" required value="<?= isset($_POST['vehicle_number']) ? htmlspecialchars($_POST['vehicle_number']) : '' ?>">
                        </div>
                    </div>

                    <button class="btn btn-primary text-white w-100 fw-bold shadow-none py-2" type="submit" name="get_mobile_btn">
                        <i class="fas fa-mobile-alt me-2"></i> Fetch Mobile No
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Result Display -->
    <?php if (!empty($mobile_result)): ?>
    <div class="col-lg-7 mt-4 mt-lg-0">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-success bg-success-subtle text-center d-flex justify-content-center align-items-center p-4">
            <div>
                <div class="icon-item bg-success text-white shadow-none mb-3 mx-auto" style="width: 80px; height: 80px;">
                    <span class="fas fa-check-circle fs-1"></span>
                </div>
                <h5 class="text-success fw-bold mb-1">Details Found Successfully!</h5>
                <p class="text-muted mb-4">Vehicle: <strong class="text-dark text-uppercase"><?= htmlspecialchars($rcno); ?></strong></p>
                
                <div class="p-3 bg-white border rounded shadow-sm">
                    <span class="d-block text-500 fs-11 mb-1">Registered Mobile Number</span>
                    <h2 class="text-primary fw-bold mb-0"><i class="fas fa-phone-alt me-2 fs-4"></i> <?= htmlspecialchars($mobile_result); ?></h2>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.getElementById('vehicle_number').addEventListener('input', function() {
        this.value = this.value.toUpperCase().replace(/\s/g, '');
    });

    document.getElementById('rcForm').addEventListener('submit', function() {
        Swal.fire({
            title: 'Fetching...',
            html: 'Extracting mobile number from RTO, please wait...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
    });

    <?php if(!empty($error_message)): ?>
        Swal.fire('Error', '<?= $error_message; ?>', 'error');
    <?php endif; ?>

    <?php if(!empty($success_message)): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '<?= $success_message; ?>',
            timer: 2000,
            showConfirmButton: false
        });
    <?php endif; ?>
</script>

<?php include('footer.php'); ?>