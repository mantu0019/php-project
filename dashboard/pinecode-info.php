<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

// टाइमज़ोन सेट करें
date_default_timezone_set("Asia/Kolkata"); 

// फीस सेटिंग्स
$fee = 1.00; 
$fee_query = mysqli_query($DarkXen_conn, "SELECT `pincode_check_fee` FROM `service_free` WHERE `id` = 1 LIMIT 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if(isset($fee_row['pincode_check_fee']) &&$fee_row['pincode_check_fee'] != '') {
        $fee = (float)$fee_row['pincode_check_fee'];
    }
}

$resdata = [];
$error_message = '';$success_message = '';
$appliedby =$udata['phone'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_pincode_btn'])) {
    
    // Fetch Live Wallet Balance to prevent race conditions
    $chk_user = mysqli_query($DarkXen_conn, "SELECT `balance` FROM `users` WHERE `phone` = '$appliedby' LIMIT 1");
    $user_row = mysqli_fetch_assoc($chk_user);$wallet_amount = isset($user_row['balance']) ? (float)$user_row['balance'] : 0.00;

    $pincode = trim($_POST['pincode_no'] ?? '');

    if (!preg_match('/^[0-9]{6}$/', $pincode)) {$error_message = "Invalid 6-digit Pincode Format";
    } elseif ($wallet_amount <$fee) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
              <script>document.addEventListener('DOMContentLoaded', function() {
                  Swal.fire('Low Balance!', 'Please recharge now. Required: ₹" . $fee . "', 'error').then(() => { window.location='wallet.php'; });
              });</script>";
        exit;
    } else {
        
        // ================= API URL (Updated for v3) =================
        $api_key = urlencode($DarkXen_apikey ?? '');
        $api_url_host =$DarkXen_url;
        $url = "https://" . $api_url_host . "/api/v3/PincodeVerification.php?apiKey=$api_key&pincode=$pincode";

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
        ));
        $response = curl_exec($curl);
        $curl_error = curl_error($curl);
        curl_close($curl);

        if ($curl_error) {$error_message = "API Connection Failed: Server Timeout";
        } else {
            $resdata = json_decode($response, true) ?? [];

            // Check new API response format (status: "100" and key: "data")
            if (isset($resdata['status']) && $resdata['status'] == "100" && !empty($resdata['data'])) {
                
                mysqli_begin_transaction($DarkXen_conn);
                try {
                    // 1. Wallet Deduction
                    $debit_fee = $wallet_amount -$fee;
                    $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone='$appliedby'");
                    if(!$update_wallet) throw new Exception("Wallet deduction failed.");
                    
                    // 2. History Log
                    $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES (?, ?, ?, 'Pincode Details Check', '1', 'Debit')");
                    mysqli_stmt_bind_param($stmt_wallet, "sdd", $appliedby, $fee,$debit_fee);
                    mysqli_stmt_execute($stmt_wallet);

                    // 3. Pincode History Save
                    $app_no = 'PIN' . time();
                    $state =$resdata['data'][0]['State'] ?? 'N/A';
                    $district =$resdata['data'][0]['District'] ?? 'N/A';
                    $count = (int)($resdata['count'] ?? count($resdata['data']));$c_date = date('Y-m-d H:i:s');

                    $insert_sql = "INSERT INTO `pincode_history` (`application_no`, `username`, `pincode`, `state`, `district`, `total_offices`, `date`) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt_hist = mysqli_prepare($DarkXen_conn,$insert_sql);
                    
                    if($stmt_hist) {
                        mysqli_stmt_bind_param($stmt_hist, "sssssis", $app_no, $appliedby,$pincode, $state,$district, $count,$c_date);
                        if(!mysqli_stmt_execute($stmt_hist)) throw new Exception("Failed to save history.");
                    } else {
                        throw new Exception("Database prepare error.");
                    }

                    mysqli_commit($DarkXen_conn);
                    $success_message =$resdata['message'] ?? "Post Office Data Found!";
                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);$error_message = "Database Error: " . $e->getMessage();$resdata = [];
                }
            } else {
                $error_message =$resdata['message'] ?? 'Data not found for this pincode.';
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
                    <div class="icon-item bg-primary-subtle shadow-none me-3" style="width: 50px; height: 50px;">
                        <span class="fas fa-map-marker-alt text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Utility Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Pincode / Post Office <span class="text-info fw-medium">Finder</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="pincode_history_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-history me-1"></i> View History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center mb-4">
    <div class="col-lg-6">
        <div class="card h-100 border-top border-4 border-primary shadow-sm">
            <div class="card-header bg-light text-center border-bottom">
                <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-search-location me-2"></i>Search Post Office by Pincode</h5>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-primary border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-info-circle fs-3 me-3 text-primary"></i>
                    <p class="mb-0 fs-11">Charge: <strong>₹<?= number_format($fee, 2); ?></strong> will be deducted per search.</p>
                </div>
                <form method="POST" id="pincodeForm">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Enter Pincode <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-map-pin"></i></span>
                            <input type="text" class="form-control fw-bold font-monospace shadow-none" name="pincode_no" placeholder="e.g. 110001" maxlength="6" value="<?= htmlspecialchars($_POST['pincode_no'] ?? ''); ?>" required autofocus>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100 fw-bold py-2 shadow-none" type="submit" name="check_pincode_btn">
                        <i class="fas fa-search me-2"></i> Search Offices (-₹<?= number_format($fee, 2); ?>)
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($resdata) && isset($resdata['data'])): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border border-top border-4 border-success">
            <div class="card-header bg-success py-2 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-white"><i class="fas fa-list me-2"></i> Results for Pincode: <?= htmlspecialchars($resdata['pincode']) ?></h5>
                <span class="badge bg-white text-success fw-bold px-2 py-1 fs-11">Total: <?= htmlspecialchars($resdata['count']) ?> Offices Found</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive scrollbar">
                    <table class="table table-sm table-striped table-hover align-middle fs-10 mb-0">
                        <thead class="bg-200 text-900">
                            <tr>
                                <th class="py-3 text-center">#</th>
                                <th class="py-3">Office Name</th>
                                <th class="py-3">Branch Type</th>
                                <th class="py-3">Delivery Status</th>
                                <th class="py-3">District</th>
                                <th class="py-3">Region</th>
                                <th class="py-3">State</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $sl = 1; foreach ($resdata['data'] as$office): ?>
                            <tr>
                                <td class="py-2 text-center fw-bold text-dark"><?= $sl++; ?></td>
                                <td class="py-2 fw-bold text-primary text-uppercase"><?= htmlspecialchars($office['Name'] ?? 'N/A') ?></td>
                                <td class="py-2 text-uppercase fw-medium"><?= htmlspecialchars($office['BranchType'] ?? 'N/A') ?></td>
                                <td class="py-2">
                                    <span class="badge badge-subtle-<?= (isset($office['DeliveryStatus']) &&$office['DeliveryStatus'] == 'Delivery') ? 'success' : 'warning' ?> border">
                                        <?= htmlspecialchars($office['DeliveryStatus'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="py-2 text-uppercase"><?= htmlspecialchars($office['District'] ?? 'N/A') ?></td>
                                <td class="py-2 text-uppercase"><?= htmlspecialchars($office['Region'] ?? 'N/A') ?></td>
                                <td class="py-2 fw-bold text-dark text-uppercase"><?= htmlspecialchars($office['State'] ?? 'N/A') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light py-2 border-top">
                <p class="mb-0 fs-11 text-600 text-center">Source: <?= htmlspecialchars($resdata['source'] ?? 'India Post API') ?></p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.getElementById('pincodeForm').addEventListener('submit', function() {
        Swal.fire({ title: 'Searching...', html: 'Fetching Pincode details from servers...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
    });
    <?php if($error_message) echo "Swal.fire('Error', " . json_encode($error_message) . ", 'error');"; ?>
    <?php if($success_message) echo "Swal.fire('Success', " . json_encode($success_message) . ", 'success');"; ?>
</script>

<?php include('footer.php'); ?>