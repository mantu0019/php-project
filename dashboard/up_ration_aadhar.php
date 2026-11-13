<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 

// ================= INIT & FEE SETTINGS =================
$fee = 8.00; // Default Fee
$fee_query = mysqli_query($DarkXen_conn, "SELECT `up_ration_aadhar_fee` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if(isset($fee_row['up_ration_aadhar_fee']) &&$fee_row['up_ration_aadhar_fee'] != '') {
        $fee = (float)$fee_row['up_ration_aadhar_fee'];
    }
}

$resdata = [];$error_message = '';
$success_message = '';$appliedby = $udata['phone'] ?? '';$user_id = $udata['id'] ?? '';$date_time = date('Y-m-d H:i:s');

// ================= ACTION =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['find_btn'])) {
    
    // Live Wallet Fetch
    $chk_user = mysqli_query($DarkXen_conn, "SELECT `balance` FROM `users` WHERE `phone` = '$appliedby' LIMIT 1");
    $user_row = mysqli_fetch_assoc($chk_user);$wallet_amount = isset($user_row['balance']) ? (float)$user_row['balance'] : 0.00;

    $ration_no = trim($_POST['ration_no'] ?? '');

    if (empty($ration_no)) {$error_message = "कृपया Ration Number दर्ज करें!";
    } elseif ($wallet_amount <$fee) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
              <script>document.addEventListener('DOMContentLoaded', function() {
                  Swal.fire('Low Balance!', 'Please recharge now. Required: ₹" . $fee . "', 'error').then(() => { window.location='wallet.php'; });
              });</script>";
        exit;
    } else {
        // API CONFIG
        $api_key = urlencode($DarkXen_apikey ?? '');
        $api_url_host =$DarkXen_url ?? 'darkxen.in';
        $url = "https://" . $api_url_host . "/api/v3/RATION/ration_to_aadhar_up_verification.php?apiKey=$api_key&ration_no=$ration_no";

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 40,
        ));
        $response = curl_exec($curl);
        $curl_error = curl_error($curl);
        curl_close($curl);

        if ($curl_error) {$error_message = "API Connection Failed: Server Timeout.";
        } else {
            $resdata = json_decode($response, true) ?? [];

            if (isset($resdata['status']) && strtolower($resdata['status']) === 'success' && !empty($resdata['family'])) {
                
                mysqli_begin_transaction($DarkXen_conn);
                try {
                    // 1. Wallet Deduct
                    $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");
                    if (!$update_wallet) throw new Exception("Wallet Update Failed.");
                    
                    $new_balance = $wallet_amount -$fee;

                    // 2. Wallet History
                    $purpose = "UP Ration to Aadhar: " . $ration_no;
                    $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES (?, ?, ?, ?, '1', 'Debit')");
                    mysqli_stmt_bind_param($stmt_wallet, "sdds", $appliedby,$fee, $new_balance,$purpose);
                    if (!mysqli_stmt_execute($stmt_wallet)) throw new Exception("Wallet History Insert Failed.");

                    // 3. Save Search Details with JSON Encoded Family Array
                    $order_id =$resdata['order_id'] ?? 'RC2UID_' . time();
                    $total_members =$resdata['total_members'] ?? count($resdata['family']);$family_json = json_encode($resdata['family'], JSON_UNESCAPED_UNICODE);$insert_sql = "INSERT INTO `up_ration_aadhar_history` (`username`, `order_id`, `ration_no`, `total_members`, `family_data`, `charge`, `date`) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt_hist = mysqli_prepare($DarkXen_conn,$insert_sql);
                    
                    if ($stmt_hist) {
                        mysqli_stmt_bind_param($stmt_hist, "sssisds", $appliedby, $order_id,$ration_no, $total_members,$family_json, $fee,$date_time);
                        if (!mysqli_stmt_execute($stmt_hist)) throw new Exception("History Insert Failed.");
                    } else {
                        throw new Exception("History Prepare Failed.");
                    }

                    mysqli_commit($DarkXen_conn);$success_message = "Data Found Successfully!";
                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);$error_message = "DB Error: " . $e->getMessage();
                }
            } else {
                $error_message =$resdata['message'] ?? 'Data not found for this Ration Number.';
            }
        }
    }
}
?>

<div class="container-fluid py-4">
    <!-- Title Area -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-100 shadow-none border">
                <div class="row gx-0 flex-between-center p-3">
                    <div class="col-sm-auto d-flex align-items-center">
                        <div class="icon-item bg-primary-subtle shadow-none me-3" style="width: 50px; height: 50px;">
                            <span class="fas fa-users text-primary fs-3"></span>
                        </div>
                        <div>
                            <h6 class="text-primary fs-10 mb-0">UP Govt Services</h6>
                            <h4 class="text-primary fw-bold mb-0">Ration to Aadhar <span class="text-info fw-medium">Finder</span></h4>
                            <p class="mb-0 fs-11 text-muted">Get family Aadhar list linked to UP Ration card. <strong class="text-danger">Cost: ₹<?= number_format($fee, 2); ?></strong></p>
                        </div>
                    </div>
                    <div class="col-md-auto mt-3 mt-md-0">
                        <a href="up_ration_aadhar_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-history me-1"></i> View History</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <!-- Left Side: Form -->
        <div class="col-lg-4 col-12">
            <div class="card h-100 shadow-none border border-top border-4 border-primary">
                <div class="card-header bg-light border-bottom py-3">
                    <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-search me-2"></i>Search Details</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" id="searchForm" autocomplete="off">
                        <div class="mb-4">
                            <label class="form-label fw-bold text-800 small">UP Ration Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-200"><i class="fas fa-barcode text-secondary"></i></span>
                                <input type="text" class="form-control shadow-none border-200 fw-bold text-monospace" name="ration_no" placeholder="e.g. 219320XXXXXX" value="<?= htmlspecialchars($_POST['ration_no'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <button class="btn btn-primary w-100 py-2 fw-bold shadow-none" type="submit" name="find_btn">
                            <span class="fas fa-cogs me-2"></span>Extract Data (-₹<?= number_format($fee, 2); ?>)
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Side: Data Preview -->
        <div class="col-lg-8 col-12">
            <div class="card h-100 shadow-none border border-top border-4 border-success">
                <div class="card-header bg-light border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-success"><i class="fas fa-list-alt me-2"></i>Family Members Details</h5>
                    <?php if (!empty($resdata) && isset($resdata['status']) && strtolower($resdata['status']) === 'success'): ?>
                        <span class="badge bg-success">Members: <?= htmlspecialchars($resdata['total_members']); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="card-body p-0">
                    <?php if (!empty($resdata) && isset($resdata['status']) && strtolower($resdata['status']) === 'success' && !empty($resdata['family'])): ?>
                        
                        <div class="p-3 bg-success-subtle border-bottom d-flex justify-content-between">
                            <span class="fw-bold text-success-emphasis">Ration No: <?= htmlspecialchars($resdata['ration_no']); ?></span>
                            <span class="fw-bold text-success-emphasis">Order ID: <?= htmlspecialchars($resdata['order_id']); ?></span>
                        </div>

                        <div class="table-responsive scrollbar">
                            <table class="table table-sm table-bordered table-striped align-middle fs-10 mb-0">
                                <thead class="bg-200">
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th>Name</th>
                                        <th>Relation</th>
                                        <th>Father Name</th>
                                        <th>DOB</th>
                                        <th>Gender</th>
                                        <th>UID (Aadhar)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $c = 1;
                                    foreach ($resdata['family'] as$member) { ?>
                                    <tr>
                                        <td class="text-center fw-bold text-dark"><?= $c++; ?></td>
                                        <td class="fw-bold text-primary"><?= htmlspecialchars($member['name'] ?? 'N/A'); ?></td>
                                        <td><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($member['relation'] ?? 'N/A'); ?></span></td>
                                        <td class="text-muted"><?= htmlspecialchars($member['father_name'] ?? 'N/A'); ?></td>
                                        <td class="text-muted"><?= htmlspecialchars($member['dob'] ?? 'N/A'); ?></td>
                                        <td><?= htmlspecialchars($member['gender'] ?? 'N/A'); ?></td>
                                        <td class="fw-bold text-success font-monospace"><?= htmlspecialchars($member['uid'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                    <?php else: ?>
                        <div class="text-center text-muted p-5">
                            <i class="fas fa-users-slash fa-4x mb-3 text-300"></i>
                            <h6 class="fw-bold mb-1 text-700">Preview Standby</h6>
                            <p class="small text-muted mx-auto mb-0" style="max-width: 340px;">
                                Left panel me Ration Number daal kar Submit karein. Family aur unke Aadhar details yahan render honge.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById('searchForm').addEventListener('submit', function() {
    Swal.fire({
        title: 'Extracting Data...',
        text: 'Fetching family members from UP Food & Civil Supplies Database...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
});
<?php if(!empty($error_message)): ?>
    Swal.fire({ icon: 'error', title: 'Extraction Failed', text: '<?= addslashes($error_message); ?>', confirmButtonText: 'Try Again' });
<?php endif; ?>
<?php if(!empty($success_message)): ?>
    Swal.fire({ icon: 'success', title: 'Success!', text: '<?= addslashes($success_message); ?>', showConfirmButton: false, timer: 1500 });
<?php endif; ?>
</script>

<?php include('footer.php'); ?>