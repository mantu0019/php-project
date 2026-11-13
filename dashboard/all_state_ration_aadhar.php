<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 

// ================= INIT & FEE SETTINGS =================
$fee = 10.00; // Default Fee fallback
$fee_query = mysqli_query($DarkXen_conn, "SELECT `all_state_ration_fee` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if(isset($fee_row['all_state_ration_fee']) &&$fee_row['all_state_ration_fee'] != '') {
        $fee = (float)$fee_row['all_state_ration_fee'];
    }
}

$resdata = [];$error_message = '';
$success_message = '';$appliedby = $udata['phone'] ?? '';$user_id = $udata['id'] ?? '';$date_time = date('Y-m-d H:i:s');

// ================= ACTION =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['find_btn'])) {
    
    // Live Wallet Fetch (To prevent balance bugs)
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
        // ================= API CONFIG =================
        $api_key = urlencode($DarkXen_apikey ?? '');
        $api_url_host =$DarkXen_url ?? 'darkxen.in';
        $url = "https://" . $api_url_host . "/api/v3/RATION/ration_to_aadhar_all_state.php?apiKey=$api_key&ration_no=$ration_no";

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

            // Updated status condition (Checking boolean true)
            if (isset($resdata['status']) && $resdata['status'] === true && !empty($resdata['members'])) {
                
                mysqli_begin_transaction($DarkXen_conn);
                try {
                    // 1. Wallet Deduct
                    $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");
                    if (!$update_wallet) throw new Exception("Wallet Update Failed.");
                    
                    $new_balance = $wallet_amount -$fee;

                    // 2. Wallet History
                    $purpose = "All State Ration to Aadhar: " . $ration_no;
                    $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES (?, ?, ?, ?, '1', 'Debit')");
                    mysqli_stmt_bind_param($stmt_wallet, "sdds", $appliedby,$fee, $new_balance,$purpose);
                    if (!mysqli_stmt_execute($stmt_wallet)) throw new Exception("Wallet History Insert Failed.");

                    // 3. Save Search Details (JSON Encode Members)
                    $total_members = count($resdata['members']);
                    $members_json = json_encode($resdata['members'], JSON_UNESCAPED_UNICODE);
                    
                    // Safe Insertion via Prepared Statement
                    $insert_sql = "INSERT INTO `all_state_ration_aadhar_history` (`username`, `ration_no`, `total_members`, `members_data`, `charge`, `date`) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt_hist = mysqli_prepare($DarkXen_conn,$insert_sql);
                    
                    if ($stmt_hist) {
                        mysqli_stmt_bind_param($stmt_hist, "ssisds", $appliedby,$ration_no, $total_members,$members_json, $fee,$date_time);
                        if (!mysqli_stmt_execute($stmt_hist)) throw new Exception("History Insert Failed.");
                    } else {
                        throw new Exception("History Prepare Failed.");
                    }

                    mysqli_commit($DarkXen_conn);
                    $success_message =$resdata['message'] ?? "Data Fetched Successfully!";
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
    <!-- Title Banner Area -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-100 shadow-none border">
                <div class="row gx-0 flex-between-center p-3">
                    <div class="col-sm-auto d-flex align-items-center">
                        <div class="icon-item bg-primary-subtle shadow-none me-3" style="width: 50px; height: 50px;">
                            <span class="fas fa-globe text-primary fs-3"></span>
                        </div>
                        <div>
                            <h6 class="text-primary fs-10 mb-0">National Services</h6>
                            <h4 class="text-primary fw-bold mb-0">All State Ration <span class="text-info fw-medium">to Aadhar</span></h4>
                            <p class="mb-0 fs-11 text-muted">Retrieve Aadhar details linked to any state's Ration Card. <strong class="text-danger">Cost: ₹<?= number_format($fee, 2); ?></strong></p>
                        </div>
                    </div>
                    <div class="col-md-auto mt-3 mt-md-0">
                        <a href="all_state_ration_aadhar_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-history me-1"></i> View History</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- UI Layout -->
    <div class="row g-3 mb-4 justify-content-center">
        <!-- Left Side: Search Form -->
        <div class="col-lg-5 col-12">
            <div class="card h-100 shadow-none border border-top border-4 border-primary">
                <div class="card-header bg-light border-bottom py-3">
                    <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-search me-2"></i>Search Database</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" id="searchForm" autocomplete="off">
                        <div class="mb-4">
                            <label class="form-label fw-bold text-800 small">Enter Ration Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-200"><i class="fas fa-barcode text-secondary"></i></span>
                                <input type="text" class="form-control shadow-none border-200 fw-bold text-monospace" name="ration_no" placeholder="e.g. 101200XXXXXX" value="<?= htmlspecialchars($_POST['ration_no'] ?? ''); ?>" required>
                            </div>
                            <div class="form-text fs-11 mt-1 text-muted">Aap kisi bhi state ka valid ration card number daal sakte hain.</div>
                        </div>
                        <button class="btn btn-primary w-100 py-2 fw-bold shadow-none" type="submit" name="find_btn">
                            <span class="fas fa-satellite-dish me-2"></span>Extract Live Data (-₹<?= number_format($fee, 2); ?>)
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Side: Realtime Result Preview -->
        <div class="col-lg-7 col-12">
            <div class="card h-100 shadow-none border border-top border-4 border-success">
                <div class="card-header bg-light border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-success"><i class="fas fa-id-card-alt me-2"></i>Member Details</h5>
                    <?php if (!empty($resdata) && isset($resdata['status']) &&$resdata['status'] === true): ?>
                        <span class="badge bg-success">Found Members: <?= count($resdata['members']); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="card-body p-0 d-flex flex-column justify-content-center">
                    <?php if (!empty($resdata) && isset($resdata['status']) && $resdata['status'] === true && !empty($resdata['members'])): ?>
                        
                        <div class="p-3 bg-success-subtle border-bottom">
                            <span class="fw-bold text-success-emphasis">Ration Number: <span class="font-monospace"><?= htmlspecialchars($resdata['rationno']); ?></span></span>
                        </div>

                        <div class="table-responsive scrollbar">
                            <table class="table table-sm table-bordered table-striped align-middle fs-10 mb-0">
                                <thead class="bg-200">
                                    <tr>
                                        <th class="text-center" style="width: 10%;">Sl No.</th>
                                        <th style="width: 45%;">Member Name</th>
                                        <th style="width: 45%;">Aadhar Number</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $c = 1;
                                    foreach ($resdata['members'] as$member) { ?>
                                    <tr>
                                        <td class="text-center fw-bold text-dark"><?= $c++; ?></td>
                                        <td class="fw-bold text-primary text-uppercase"><?= htmlspecialchars($member['name'] ?? 'N/A'); ?></td>
                                        <td class="fw-bold text-success font-monospace"><?= htmlspecialchars($member['aadhaar'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                    <?php else: ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-server fa-4x mb-3 text-300"></i>
                            <h6 class="fw-bold mb-1 text-700">Interface Standby</h6>
                            <p class="small text-muted mx-auto mb-0" style="max-width: 320px;">
                                Left form me valid Ration Number enter karein. Pura member data aur unka Aadhar yahan fetch hokar dikhega.
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
        text: 'Please wait while we sync with the central database...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
});
<?php if(!empty($error_message)): ?>
    Swal.fire({ icon: 'error', title: 'Extraction Failed', text: '<?= addslashes($error_message); ?>', confirmButtonText: 'Try Again' });
<?php endif; ?>
<?php if(!empty($success_message)): ?>
    Swal.fire({ icon: 'success', title: 'Data Found!', text: '<?= addslashes($success_message); ?>', showConfirmButton: false, timer: 1500 });
<?php endif; ?>
</script>

<?php include('footer.php'); ?>