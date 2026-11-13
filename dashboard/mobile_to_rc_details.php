<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 

$charge = 20.00; // Set your default charge here

// Fetch Fee (Update column name 'mobile_rc_details' if you have added it in DB)
$fee_query = mysqli_query($DarkXen_conn, "SELECT `mobile_rc_details` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if (isset($fee_row['mobile_rc_details']) &&$fee_row['mobile_rc_details'] !== '') {
        $charge = (float)$fee_row['mobile_rc_details'];
    }
}

$vehicle_results = [];$total_found = 0;
$error_message = '';$success_message = '';
$user =$udata['phone'];
$wallet_amount = (float)$udata['balance'];

// FORM SUBMISSION CHECK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_details_btn'])) {

    $mobile_number = trim($_POST['mobile_number'] ?? '');

    if (empty($mobile_number) || strlen($mobile_number) < 10) {$error_message = "Please enter a valid 10-digit mobile number.";
    } elseif ($wallet_amount <$charge) {
        echo '
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            Swal.fire("Low Balance", "Recharge Required to fetch Details", "error").then(()=>{
                window.location="wallet.php";
            });
        });
        </script>';
        exit;
    } else {

        // ================= API CALL =================
        $apiKey = urlencode($DarkXen_apikey);
        $url = "https://" . $DarkXen_url . "/api/v3/VEHICLE/mobile_to_rcdeatils.php?apiKey=$apiKey&mobileNo=$mobile_number";

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
            
            // Check status & found flag
            if (isset($resdata['status']) &&$resdata['status'] === 'success' && isset($resdata['found']) &&$resdata['found'] === true) {
                
                $vehicle_results =$resdata['vehicles'] ?? [];
                $total_found = count($vehicle_results);
                
                if (!empty($vehicle_results)) {
                    // Extract all vehicle numbers for DB history
                    $v_list_array = [];
                    foreach($vehicle_results as$v) {
                        if(isset($v['regNo'])) { $v_list_array[] =$v['regNo']; }
                    }
                    $vehicle_list_string = implode(", ", $v_list_array);

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
                        $remark = "Mobile to RC Details Fetch : " . $mobile_number;
                        $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES (?, ?, ?, ?, '1', 'Debit')");
                        mysqli_stmt_bind_param($stmt_wallet, "sdds", $user,$charge, $newbalance,$remark);
                        mysqli_stmt_execute($stmt_wallet);
                        mysqli_stmt_close($stmt_wallet);

                        // 3. Save to History Table
                        $current_date = date('Y-m-d H:i:s');$insert_sql = "INSERT INTO `mobile_rc_details_history` (`username`, `mobile_no`, `vehicle_count`, `vehicle_list`, `date`) 
                                       VALUES ('$user', '$mobile_number', '$total_found', '$vehicle_list_string', '$current_date')";
                        
                        if (!mysqli_query($DarkXen_conn,$insert_sql)) {
                            throw new Exception("Failed to save record in history.");
                        }

                        mysqli_commit($DarkXen_conn);
                        $success_message = "Total " . $total_found . " Vehicle(s) Details Found!";

                    } catch (Exception $e) {
                        mysqli_rollback($DarkXen_conn);$error_message = "Transaction Error: " . $e->getMessage();$vehicle_results = []; // Hide result on fail
                    }
                } else {
                    $error_message = "No vehicles linked to this mobile number.";
                }
            } else {
                $error_message = "No vehicles found or Server Error";
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
                        <span class="fas fa-truck-moving text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">RTO Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Mobile to <span class="text-info fw-medium">RC Details</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="mobile_rc_details_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row <?= empty($vehicle_results) ? 'justify-content-center' : '' ?> mb-4">
    
    <!-- Left Column: Form -->
    <div class="<?= empty($vehicle_results) ? 'col-lg-5' : 'col-lg-4' ?>">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-primary">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-mobile-alt me-2"></i>Find RC Details</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-primary border-0 d-flex align-items-center mb-4 p-2">
                    <i class="fas fa-rupee-sign fs-4 me-2 text-primary"></i>
                    <p class="mb-0 fs-11 text-dark">Charge: <strong>₹<?= number_format($charge, 2); ?></strong> will be deducted.</p>
                </div>
                
                <form method="POST" id="rcForm" autocomplete="off">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Mobile Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">+91</span>
                            <input type="number" class="form-control shadow-none fw-bold" id="mobile_number" name="mobile_number" placeholder="Enter 10 digit number" required maxlength="10" value="<?= isset($_POST['mobile_number']) ? htmlspecialchars($_POST['mobile_number']) : '' ?>">
                        </div>
                    </div>

                    <button class="btn btn-primary text-white w-100 fw-bold shadow-none py-2" type="submit" name="get_details_btn">
                        <i class="fas fa-search me-2"></i> Find Details
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Result Display -->
    <?php if (!empty($vehicle_results)): ?>
    <div class="col-lg-8 mt-4 mt-lg-0">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-success">
            <div class="card-header bg-success-subtle border-bottom d-flex justify-content-between align-items-center p-3">
                <h5 class="mb-0 text-success fw-bold"><i class="fas fa-check-circle me-2"></i> <?= $total_found ?> Vehicle(s) Details Found</h5>
                <span class="badge bg-success fs-10"><i class="fas fa-phone-alt me-1"></i> <?= htmlspecialchars($mobile_number) ?></span>
            </div>
            
            <!-- Scrollable Area for Multiple Vehicles with Full Details -->
            <div class="card-body p-3 bg-light" style="max-height: 600px; overflow-y: auto;">
                <div class="row">
                    <?php foreach($vehicle_results as$v): 
                        $has_full_details = (isset($v['vehicle_info']) && !isset($v['vehicle_info']['error']));$vInfo = $has_full_details ? $v['vehicle_info'] : [];
                    ?>
                    <div class="col-md-12 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-dark text-white p-2 d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 text-uppercase fw-bold text-warning ms-2"><?= htmlspecialchars($v['regNo'] ?? 'N/A') ?></h5>
                                <?php if($has_full_details): ?>
                                    <span class="badge bg-success">Full Details Available</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Basic Details Only</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-body p-3 fs-10">
                                <div class="row">
                                    <!-- Basic Info Side -->
                                    <div class="<?= $has_full_details ? 'col-md-6 border-end' : 'col-md-12' ?>">
                                        <h6 class="text-primary fw-bold mb-2 border-bottom pb-1">Basic RTO Details</h6>
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr><th class="text-500 w-40">State</th><td class="fw-bold"><?= htmlspecialchars($v['stateName'] ?? 'N/A') ?> (<?= htmlspecialchars($v['stateCd'] ?? '') ?>)</td></tr>
                                            <tr><th class="text-500">RTO Office</th><td class="fw-bold"><?= htmlspecialchars($v['officeName'] ?? 'N/A') ?></td></tr>
                                            <tr><th class="text-500">Regn Upto</th><td class="fw-medium text-dark"><?= htmlspecialchars($v['regnUpto'] ?? 'N/A') ?></td></tr>
                                            <tr><th class="text-500">Fitness Upto</th><td class="fw-medium text-dark"><?= htmlspecialchars($v['fitUpto'] ?? 'N/A') ?></td></tr>
                                            <tr><th class="text-500">Insurance Upto</th><td class="fw-medium text-dark"><?= htmlspecialchars($v['insUpto'] ?? 'N/A') ?></td></tr>
                                            <tr><th class="text-500">PUCC Upto</th><td class="fw-medium text-dark"><?= htmlspecialchars($v['puccUpto'] ?? 'N/A') ?></td></tr>
                                        </table>
                                    </div>
                                    
                                    <!-- Full Detailed Info Side (If Available) -->
                                    <?php if($has_full_details): ?>
                                    <div class="col-md-6">
                                        <h6 class="text-info fw-bold mb-2 border-bottom pb-1">Owner & Technical Details</h6>
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr><th class="text-500 w-40">Owner Name</th><td class="fw-bold text-success text-uppercase"><?= htmlspecialchars($vInfo['owner'] ?? 'N/A') ?></td></tr>
                                            <tr><th class="text-500">Vehicle</th><td class="fw-bold text-dark"><?= htmlspecialchars($vInfo['manufacturer'] ?? '') ?> - <?= htmlspecialchars($vInfo['vehicle'] ?? 'N/A') ?></td></tr>
                                            <tr><th class="text-500">Class</th><td class="fw-medium text-dark"><?= htmlspecialchars($vInfo['vehicleClass'] ?? 'N/A') ?></td></tr>
                                            <tr><th class="text-500">Chassis No</th><td class="fw-medium text-dark"><?= htmlspecialchars($vInfo['chassis'] ?? 'N/A') ?></td></tr>
                                            <tr><th class="text-500">Engine No</th><td class="fw-medium text-dark"><?= htmlspecialchars($vInfo['engine'] ?? 'N/A') ?></td></tr>
                                            <tr><th class="text-500">Financer</th><td class="fw-medium text-dark"><?= htmlspecialchars($vInfo['financerName'] ?? 'None') ?></td></tr>
                                            <tr><th class="text-500">Insurance Co.</th><td class="fw-medium text-dark"><?= htmlspecialchars($vInfo['insuranceCompanyName'] ?? 'N/A') ?></td></tr>
                                        </table>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // Only numbers allowed in mobile input
    document.getElementById('mobile_number').addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
    });

    document.getElementById('rcForm').addEventListener('submit', function(e) {
        let mobile = document.getElementById('mobile_number').value;
        if(mobile.length < 10){
            e.preventDefault();
            Swal.fire('Error', 'Enter a valid 10-digit mobile number', 'error');
            return;
        }

        Swal.fire({
            title: 'Searching...',
            html: 'Extracting FULL vehicle details from RTO database, please wait...',
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