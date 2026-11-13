<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 

// Fetch Fee
$fee = 10; // Default fee fallback
$fee_query = mysqli_query($DarkXen_conn, "SELECT `Agri_farmer_Status_AllState` FROM `service_free` WHERE `id` = 1 LIMIT 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if ($fee_row['Agri_farmer_Status_AllState'] !== '' && $fee_row['Agri_farmer_Status_AllState'] !== null) {
        $fee = (float)$fee_row['Agri_farmer_Status_AllState'];
    }
}

$resdata = [];
$error_message = "";
$success_message = "";
$appliedby = $udata['phone'] ?? '';
$date_time = date('Y-m-d H:i:s');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {

    // Fetch Live Wallet Balance to prevent race conditions
    $chk_user = mysqli_query($DarkXen_conn, "SELECT `balance` FROM `users` WHERE `phone` = '$appliedby' LIMIT 1");
    $user_row = mysqli_fetch_assoc($chk_user);
    $wallet_amount = isset($user_row['balance']) ? (float)$user_row['balance'] : 0.00;

    $uidNumber = trim($_POST['uidNumber']);
    $state_name = trim($_POST['state_name']); // Now receives short codes like 'mh', 'up'

    if (!preg_match('/^[0-9]{12}$/', $uidNumber)) {
        $error_message = "Invalid Aadhaar Number Format!";
    } else if ($wallet_amount < $fee) {
        $error_message = "Low Wallet Balance! Please recharge.";
    } else {
        
        // ================= API CONFIG =================
        $api_key = urlencode($DarkXen_apikey ?? '');
        $api_url_host = $DarkXen_url;
        
        // Updated URL and Parameters
        $url = "https://" . $api_url_host . "/api/v3/FARMER/agriinfo_deatils.php?apiKey=$api_key&aadhar=$uidNumber&state=" . urlencode($state_name);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 40,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET"
        ]);

        $response = curl_exec($curl);
        
        if (curl_errno($curl)) {
            $error_message = "API Connection Failed: Server Timeout.";
        }
        curl_close($curl);

        if(empty($error_message)) {
            $resdata = json_decode(trim($response), true);

            // Checking new status format ("success")
            if (
                json_last_error() == JSON_ERROR_NONE &&
                isset($resdata['status']) && 
                strtolower($resdata['status']) === 'success' &&
                isset($resdata['data'])
            ) {
                $data = $resdata['data'];

                // Safe variables for SQL Insertion
                $db_farmerName = mysqli_real_escape_with_null($DarkXen_conn, $data['farmerName'] ?? '');
                $db_farmerNumber = mysqli_real_escape_with_null($DarkXen_conn, $data['farmerNumber'] ?? '');
                $db_centralId = mysqli_real_escape_with_null($DarkXen_conn, $data['centralId'] ?? '');
                $db_approvalStatus = mysqli_real_escape_with_null($DarkXen_conn, $data['approvalStatus'] ?? '');
                $db_regStatus = mysqli_real_escape_with_null($DarkXen_conn, $data['registrationStatus'] ?? '');
                
                // Format dates safely for MySQL
                $db_enrollDate = !empty($data['enrollmentDate']) ? date("Y-m-d H:i:s", strtotime($data['enrollmentDate'])) : NULL;
                $db_verifyDate = !empty($data['verifiedOn']) ? date("Y-m-d H:i:s", strtotime($data['verifiedOn'])) : NULL;

                mysqli_begin_transaction($DarkXen_conn);
                try {
                    // 1. Debit Wallet Balance
                    $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");
                    if(!$update_wallet) throw new Exception("Wallet deduction failed.");
                    
                    $new_balance = $wallet_amount -$fee;

                    // 2. Save data to SQL Database
                    $insert_query = "INSERT INTO `agristack_history` 
                        (`userid`, `aadhaar_number`, `farmer_name`, `farmer_number`, `central_id`, `approval_status`, `registration_status`, `state_name`, `enrollment_date`, `verified_on`) 
                        VALUES 
                        ('$appliedby', '$uidNumber', " . 
                        ($db_farmerName ? "'$db_farmerName'" : "NULL") . ", " . 
                        ($db_farmerNumber ? "'$db_farmerNumber'" : "NULL") . ", " . 
                        ($db_centralId ? "'$db_centralId'" : "NULL") . ", " . 
                        ($db_approvalStatus ? "'$db_approvalStatus'" : "NULL") . ", " . 
                        ($db_regStatus ? "'$db_regStatus'" : "NULL") . ", " . 
                        "'$state_name', " . 
                        ($db_enrollDate ? "'$db_enrollDate'" : "NULL") . ", " . 
                        ($db_verifyDate ? "'$db_verifyDate'" : "NULL") . ")";
                    
                    if(!mysqli_query($DarkXen_conn,$insert_query)) throw new Exception("Database record saving failed.");

                    // 3. Add to Wallet History Log
                    $purpose = "AgriStack Status Check: " . strtoupper($state_name);
                    mysqli_query($DarkXen_conn, "INSERT INTO wallethistory (userid,amount,balance,purpose,status,type) VALUES ('$appliedby', '$fee', '$new_balance', '$purpose', '1', 'Debit')");
                    
                    mysqli_commit($DarkXen_conn);$success_message = "Data found and Wallet debited successfully!";
                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);$error_message = "System Error: " . $e->getMessage();
                }

            } else {
                $error_message =$resdata['message'] ?? "No Record Found or Invalid Data.";
            }
        }
    }
}

// Helper function to escape SQL strings safely
function mysqli_real_escape_with_null($conn,$value) {
    if ($value === null && $value === '') {
        return null;
    }
    return mysqli_real_escape_string($conn, trim($value));
}
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-success-subtle shadow-none me-3">
                        <span class="fas fa-tractor text-success fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-success fs-10 mb-0">Agri Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Farmer <span class="text-success fw-medium">Card Print & History</span></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Success Alert -->
<?php if (!empty($success_message)) { ?>
    <div class="alert alert-success border-0 d-flex align-items-center mb-3" role="alert">
        <span class="fas fa-check-circle fs-3 me-2"></span>
        <p class="mb-0"><?php echo htmlspecialchars($success_message); ?></p>
    </div>
<?php } ?>

<!-- Error Alert -->
<?php if (!empty($error_message)) { ?>
    <div class="alert alert-danger border-0 d-flex align-items-center mb-3" role="alert">
        <span class="fas fa-exclamation-circle fs-3 me-2"></span>
        <p class="mb-0"><?php echo htmlspecialchars($error_message); ?></p>
    </div>
<?php } ?>

<!-- Grid Row (Form & Preview Card) -->
<div class="row justify-content-center g-4 mb-4">
    <!-- Left Side: Form Card -->
    <div class="col-lg-5">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-success">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-leaf me-2 text-success"></i>Generate Verification Card</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-success border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-success"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?php echo number_format($fee, 2); ?></strong> All State Agriculture Verification Matrix Integrated.</p>
                </div>
                                
                <form action="" method="POST" class="row g-3" id="agriForm" onsubmit="showLoader()">
                    <div class="col-12 mb-3">
                        <label for="uidNumber" class="form-label font-weight-500 text-dark">Enter Aadhaar Number</label>
                        <input name="uidNumber" type="text" id="uidNumber" value="<?php echo isset($_POST['uidNumber']) ? htmlspecialchars($_POST['uidNumber']) : ''; ?>" maxlength="12" minlength="12" placeholder="Enter 12 Digit Aadhaar" class="form-control fw-bold font-monospace shadow-none" required pattern="[0-9]{12}">
                    </div>
                    
                    <div class="col-12 mb-4">
                        <label for="state_name" class="form-label font-weight-500 text-dark">Select State</label>
                        <select name="state_name" id="state_name" class="form-select shadow-none" required>
                            <option value="">-- Choose State --</option>
                            <option value="mh" <?= (isset($_POST['state_name']) &&$_POST['state_name'] == 'mh') ? 'selected' : '' ?>>Maharashtra</option>
                            <option value="hr" <?= (isset($_POST['state_name']) &&$_POST['state_name'] == 'hr') ? 'selected' : '' ?>>Haryana</option>
                            <option value="br" <?= (isset($_POST['state_name']) &&$_POST['state_name'] == 'br') ? 'selected' : '' ?>>Bihar</option>
                            <option value="mp" <?= (isset($_POST['state_name']) &&$_POST['state_name'] == 'mp') ? 'selected' : '' ?>>Madhya Pradesh</option>
                            <option value="cg" <?= (isset($_POST['state_name']) &&$_POST['state_name'] == 'cg') ? 'selected' : '' ?>>Chhattisgarh</option>
                            <option value="pb" <?= (isset($_POST['state_name']) &&$_POST['state_name'] == 'pb') ? 'selected' : '' ?>>Punjab</option>
                            <option value="as" <?= (isset($_POST['state_name']) &&$_POST['state_name'] == 'as') ? 'selected' : '' ?>>Assam</option>
                            <option value="mz" <?= (isset($_POST['state_name']) &&$_POST['state_name'] == 'mz') ? 'selected' : '' ?>>Mizoram</option>
                            <option value="ar" <?= (isset($_POST['state_name']) &&$_POST['state_name'] == 'ar') ? 'selected' : '' ?>>Arunachal Pradesh</option>
                            <option value="ap" <?= (isset($_POST['state_name']) &&$_POST['state_name'] == 'ap') ? 'selected' : '' ?>>Andhra Pradesh</option>
                            <option value="tg" <?= (isset($_POST['state_name']) &&$_POST['state_name'] == 'tg') ? 'selected' : '' ?>>Telangana</option>
                            <option value="tr" <?= (isset($_POST['state_name']) &&$_POST['state_name'] == 'tr') ? 'selected' : '' ?>>Tripura</option>
                            <option value="uk" <?= (isset($_POST['state_name']) &&$_POST['state_name'] == 'uk') ? 'selected' : '' ?>>Uttarakhand</option>
                            <option value="jh" <?= (isset($_POST['state_name']) &&$_POST['state_name'] == 'jh') ? 'selected' : '' ?>>Jharkhand</option>
                            <option value="kl" <?= (isset($_POST['state_name']) &&$_POST['state_name'] == 'kl') ? 'selected' : '' ?>>Kerala</option>
                            <option value="rj" <?= (isset($_POST['state_name']) &&$_POST['state_name'] == 'rj') ? 'selected' : '' ?>>Rajasthan</option>
                            <option value="wb" <?= (isset($_POST['state_name']) &&$_POST['state_name'] == 'wb') ? 'selected' : '' ?>>West Bengal</option>
                            <option value="tn" <?= (isset($_POST['state_name']) &&$_POST['state_name'] == 'tn') ? 'selected' : '' ?>>Tamil Nadu</option>
                            <option value="ka" <?= (isset($_POST['state_name']) &&$_POST['state_name'] == 'ka') ? 'selected' : '' ?>>Karnataka</option>
                            <option value="or" <?= (isset($_POST['state_name']) &&$_POST['state_name'] == 'or') ? 'selected' : '' ?>>Odisha</option>
                            <option value="gj" <?= (isset($_POST['state_name']) &&$_POST['state_name'] == 'gj') ? 'selected' : '' ?>>Gujarat</option>
                            <option value="up" <?= (isset($_POST['state_name']) &&$_POST['state_name'] == 'up') ? 'selected' : '' ?>>Uttar Pradesh</option>
                            <option value="hp" <?= (isset($_POST['state_name']) &&$_POST['state_name'] == 'hp') ? 'selected' : '' ?>>Himachal Pradesh</option>
                        </select>
                    </div>
                    
                    <div class="col-12">
                        <hr class="mt-0 mb-4">
                        <div class="row align-items-center">
                            <div class="col-6">
                                <input class="form-control font-weight-600 text-success bg-light border-0" value="Fee: ₹ <?php echo htmlspecialchars($fee); ?>" readonly style="box-shadow: none;">
                            </div>
                            <div class="col-6 text-end">
                                <button type="submit" class="btn btn-success w-100 fw-bold shadow-none" name="submit" id="submit">
                                    <i class="bx bx-check-circle"></i> Submit
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Side: Premium Preview Table -->
    <div class="col-lg-7">
        <?php if (isset($resdata['status']) && strtolower($resdata['status']) === 'success' && !empty($resdata['data'])) { 
            $data =$resdata['data'];
        ?>
            <div class="card shadow-sm border-0 rounded-3 h-100" id="printSection">
                <div class="card-header bg-success py-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0 text-white font-weight-600"><i class="bx bx-data me-1"></i> AgriStack Verification Result</h5>
                    <button onclick="window.print()" class="btn btn-sm btn-light d-flex align-items-center gap-1 text-success fw-bold shadow-none">
                        <i class="bx bx-printer"></i> Print Statement
                    </button>
                </div>

                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <tbody>
                                <tr>
                                    <th width="35%">Status Message</th>
                                    <td class="font-weight-600 text-success"><?= htmlspecialchars($resdata['message'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <th>Aadhaar Number</th>
                                    <td class="font-weight-600 text-dark font-monospace"><?= htmlspecialchars($uidNumber) ?></td>
                                </tr>
                                <tr>
                                    <th>Approval Status</th>
                                    <td><span class="badge bg-success-subtle text-success border px-2 py-1 font-weight-500 text-uppercase"><?= htmlspecialchars($data['approvalStatus'] ?? 'N/A') ?></span></td>
                                </tr>
                                <tr>
                                    <th>Farmer Name</th>
                                    <td class="font-weight-600 text-dark text-uppercase"><?= htmlspecialchars($data['farmerName'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <th>Enrollment Date</th>
                                    <td class="small text-muted"><?= !empty($data['enrollmentDate']) ? date("d-m-Y h:i A", strtotime($data['enrollmentDate'])) : 'N/A' ?></td>
                                </tr>
                                <tr>
                                    <th>Verified On</th>
                                    <td class="small text-muted"><?= !empty($data['verifiedOn']) ? date("d-m-Y h:i A", strtotime($data['verifiedOn'])) : 'N/A' ?></td>
                                </tr>
                                <tr>
                                    <th>Farmer Number</th>
                                    <td><code class="fs-11 fw-bold"><?= htmlspecialchars($data['farmerNumber'] ?? 'N/A') ?></code></td>
                                </tr>
                                <tr>
                                    <th>Central ID</th>
                                    <td class="text-primary font-weight-500 font-monospace"><?= htmlspecialchars($data['centralId'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <th>Registration Status</th>
                                    <td><?= htmlspecialchars($data['registrationStatus'] ?? 'N/A') ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php } else { ?>
            <!-- Default Empty State Card -->
            <div class="card shadow-sm border-0 rounded-3 text-center p-5 h-100" style="display: flex; align-items: center; justify-content: center;">
                <div class="text-muted">
                    <i class="bx bx-search-alt-2 display-4 mb-3 text-secondary"></i>
                    <p class="mb-0 fs-5">Enter details and click submit to fetch farmer information.</p>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<!-- ================= LIST VIEW (HISTORY TABLE) ================= -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm border-top border-4 border-info">
            <div class="card-header bg-light border-bottom py-3">
                <h5 class="mb-0 text-dark font-weight-600"><i class="fas fa-history me-2 text-info"></i> Recent Verifications History</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light text-dark font-weight-600">
                            <tr>
                                <th>#ID</th>
                                <th>Aadhaar</th>
                                <th>Farmer Name</th>
                                <th>Farmer Number</th>
                                <th>Central ID</th>
                                <th class="text-center">State</th>
                                <th>Approval Status</th>
                                <th>Verified On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $curr_user =$udata['phone'];
                            // Query to get recent 10 records for logged-in user
                            $list_query = mysqli_query($DarkXen_conn, "SELECT * FROM `agristack_history` WHERE `userid` = '$curr_user' ORDER BY `id` DESC LIMIT 10");
                            
                            if (mysqli_num_rows($list_query) > 0) {
                                while ($row = mysqli_fetch_assoc($list_query)) {
                            ?>
                                <tr>
                                    <td><strong>#<?= $row['id'] ?></strong></td>
                                    <td class="font-monospace fw-bold"><?= htmlspecialchars($row['aadhaar_number'] ?? 'N/A') ?></td>
                                    <td class="font-weight-600 text-dark text-uppercase"><?= htmlspecialchars($row['farmer_name'] ?? 'N/A') ?></td>
                                    <td><code class="fw-bold"><?= htmlspecialchars($row['farmer_number'] ?? 'N/A') ?></code></td>
                                    <td class="text-primary font-weight-500 font-monospace"><?= htmlspecialchars($row['central_id'] ?? 'N/A') ?></td>
                                    <td class="text-center"><span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($row['state_name'] ?? 'N/A') ?></span></td>
                                    <td>
                                        <span class="badge bg-success-subtle text-success px-2 py-1 font-weight-500 text-uppercase border">
                                            <?= htmlspecialchars($row['approval_status'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td class="text-muted">
                                        <?= !empty($row['verified_on']) ? date("d M Y, h:i A", strtotime($row['verified_on'])) : 'N/A' ?>
                                    </td>
                                </tr>
                            <?php 
                                }
                            } else {
                            ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        <i class="bx bx-info-circle fs-3 d-block mb-2"></i> No records found in your database history.
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function showLoader() {
    Swal.fire({
        title: 'Verifying Details...',
        text: 'Please wait while we connect to AgriStack servers...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
}
</script>

<?php include('footer.php'); ?>