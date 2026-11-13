<?php
// ==========================================
//        DARKXEN API - CORE SYSTEM
// ==========================================
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

// Timezone configuration
date_default_timezone_set("Asia/Kolkata"); 

// Fetch Fee from Database
$fee_query = mysqli_query($DarkXen_conn, "SELECT `npci_status_check` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if ($fee_row['npci_status_check'] !== '' &&$fee_row['npci_status_check'] !== null) {
        $fee = (float)$fee_row['npci_status_check'];
    }
}

$resdata = [];
$error_message = '';$success_message = '';
$appliedby =$udata['phone'];
$wallet_amount = (float)$udata['balance'];

// FORM SUBMISSION CHECK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_npci_btn'])) {

    $uidNumber = trim($_POST['aadhar_number'] ?? '');

    // Input Validation
    if (!preg_match('/^[0-9]{12}$/', $uidNumber)) {$error_message = "Invalid 12-digit Aadhaar Number Format";
    } 
    // Balance Validation
    elseif ($wallet_amount <$fee) {
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Wallet Balance Low!',
                text: 'Please Recharge Now!',
                icon: 'error',
                confirmButtonColor: '#ef4444'
            }).then(() => {
                window.location='wallet.php';
            });
        });
        </script>";
        exit;
    } else {

            // --- API URL CONSTRUCTION ---
            $DarkXen_apikey =$webdata['api_key']; 
            $api_key = urlencode($DarkXen_apikey);
        
        $url = "https://" . $DarkXen_url . "/api/v3/NPCI/check_npci.php?apiKey=" . urlencode($api_key) . "&aadhaar=" . urlencode($uidNumber);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ));

        $response = curl_exec($curl);
        $curl_error = curl_error($curl);
        curl_close($curl);

        if ($curl_error) {$error_message = "API Server Connection Failed: " . $curl_error;
        } else {
            $resdata = json_decode($response, true) ?? [];

            // JSON Response Validation
            if (isset($resdata['status']) && $resdata['status'] === "success" && isset($resdata['npcidata'])) {

                // DB Transaction Start
                mysqli_begin_transaction($DarkXen_conn);

                try {
                    // 1. Deduct Balance
                    $debit_fee = $wallet_amount -$fee;
                    $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
                    if (!$update_wallet) {
                        throw new Exception("Wallet deduction failed.");
                    }

                    // 2. Wallet History Log
                    $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES (?, ?, ?, 'NPCI Status Check', '1', 'Debit')");
                    mysqli_stmt_bind_param($stmt_wallet, "sdd", $appliedby, $fee,$debit_fee);
                    mysqli_stmt_execute($stmt_wallet);
                    mysqli_stmt_close($stmt_wallet);

                    // --- PARSE NEW JSON KEYS ---
                    $bank_name = mysqli_real_escape_string($DarkXen_conn,$resdata['npcidata']['bank_Name'] ?? 'N/A');
                    
                    // Status Mapping (A = Active)
                    $raw_status =$resdata['npcidata']['status'] ?? '';
                    $status_msg = ($raw_status === 'A' || strtolower($raw_status) === 'active') ? 'Active' : 'Inactive';$status_msg = mysqli_real_escape_string($DarkXen_conn,$status_msg);
                    
                    // Extract Name and Mobile from uidaidata
                    $cust_name = mysqli_real_escape_string($DarkXen_conn, $resdata['uidaidata']['name'] ?? 'N/A');$cust_mobile = mysqli_real_escape_string($DarkXen_conn,$resdata['uidaidata']['mobile'] ?? 'N/A');
                    
                    // Mask Aadhaar for security
                    $masked_aadhar = 'XXXX-XXXX-' . substr($uidNumber, -4);$application_no = mysqli_real_escape_string($DarkXen_conn,$resdata['order_id'] ?? 'NP' . time());
                    $current_date = date('Y-m-d H:i:s');

                    // 3. Save to History Table (INCLUDING NEW COLUMNS)
                    $insert_sql = "INSERT INTO `npci_status_history` (`application_no`, `username`, `aadhar_no`, `customer_name`, `mobile_no`, `bank_name`, `status_msg`, `date`) 
                                   VALUES ('$application_no', '$appliedby', '$masked_aadhar', '$cust_name', '$cust_mobile', '$bank_name', '$status_msg', '$current_date')";
                    
                    if (!mysqli_query($DarkXen_conn,$insert_sql)) {
                        throw new Exception("Failed to save history log into SQL.");
                    }

                    mysqli_commit($DarkXen_conn);$success_message = "NPCI Bank Mapping Status Fetched Successfully!";

                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);$error_message = "Transaction Failed: " . $e->getMessage();$resdata = [];
                }

            } else {
                $error_message =$resdata['message'] ?? 'Data Not Found for this Aadhaar ID.';
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
                        <span class="fas fa-university text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Banking Services</h6>
                        <h4 class="text-primary fw-bold mb-0">NPCI / Bank <span class="text-info fw-medium">Status</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="npci_status_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center mb-4">
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-primary">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-search-dollar me-2 text-primary"></i>Check Bank Linking Status</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-primary border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-primary"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?= number_format($fee, 2); ?></strong> will be deducted upon successful fetching.</p>
                </div>
                
                <form method="POST" id="npciForm" autocomplete="off">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Aadhaar Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-fingerprint"></i></span>
                            <input type="text" class="form-control shadow-none fw-bold" id="aadhar_number" name="aadhar_number" placeholder="Enter 12-digit Aadhaar number" maxlength="12" required autofocus>
                        </div>
                    </div>
                    
                    <button class="btn btn-primary w-100 fw-bold shadow-none py-2" type="submit" name="check_npci_btn">
                        <i class="fas fa-university me-2"></i> Fetch NPCI Status
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($resdata) && isset($resdata['npcidata'])): ?>
<div class="row justify-content-center mb-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border">
            <div class="card-header bg-success text-white py-2">
                <h5 class="mb-0 text-white"><i class="fas fa-check-circle me-2"></i> Mapping Information Found</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped fs-10 mb-0">
                        <tbody>
                            <!-- UIDAI Data -->
                            <?php if(isset($resdata['uidaidata'])): ?>
                            <tr>
                                <th width="35%">Name</th>
                                <td class="fw-bold text-dark">
                                    <?= htmlspecialchars($resdata['uidaidata']['name'] ?? 'N/A'); ?> 
                                    <span class="text-muted ms-2">(<?= htmlspecialchars($resdata['uidaidata']['localName'] ?? ''); ?>)</span>
                                </td>
                            </tr>
                            <tr>
                                <th>Linked Mobile</th>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($resdata['uidaidata']['mobile'] ?? 'N/A'); ?></td>
                            </tr>
                            <?php endif; ?>
                            
                            <tr>
                                <th width="35%">Aadhaar Number</th>
                                <td class="fw-bold text-900">
                                    <?php 
                                        $raw_aadhar =$_POST['aadhar_number'] ?? 'XXXXXXXXXXXX';
                                        echo 'XXXX-XXXX-' . substr($raw_aadhar, -4);
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Bank Seeding Status</th>
                                <td>
                                    <?php 
                                        $status_val = $resdata['npcidata']['status'] ?? '';$is_active = ($status_val === 'A' || strtolower($status_val) === 'active');
                                    ?>
                                    <span class="badge bg-<?= $is_active ? 'success' : 'danger'; ?> px-2 py-1">
                                        <?= $is_active ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Last Updated Date</th>
                                <td class="text-muted">
                                    <?php 
                                        $update_date =$resdata['npcidata']['last_Updated_Date'] ?? '';
                                        echo $update_date ? date('d M Y, h:i A', strtotime($update_date)) : 'N/A';
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Linked Bank Name</th>
                                <td class="fw-bold text-primary fs-9"><?= htmlspecialchars($resdata['npcidata']['bank_Name'] ?? 'N/A'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.getElementById('aadhar_number').addEventListener('input', function () {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    document.getElementById('npciForm').addEventListener('submit', function() {
        Swal.fire({
            title: 'Connecting NPCI Gateways...',
            html: 'Fetching live bank mapping records, please wait...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
    });

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
            title: 'Success!',
            text: '<?= $success_message; ?>',
            icon: 'success',
            confirmButtonColor: '#10b981'
        });
    <?php endif; ?>
</script>

<?php include('footer.php'); ?>