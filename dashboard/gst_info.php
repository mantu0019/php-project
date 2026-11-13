<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 

// फीस सेटिंग्स
$fee = 5.00; 
$fee_query = mysqli_query($DarkXen_conn, "SELECT `gst_check_fee` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    $fee = (float)($fee_row['gst_check_fee'] ?? 5.00);
}

$resdata = [];
$error_message = '';
$success_message = '';
$appliedby = $udata['phone'];
$wallet_amount = (float)$udata['balance'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_gst_btn'])) {
    $gstNo = strtoupper(trim($_POST['gst_number'] ?? ''));

    // GST फॉर्मेट वैलिडेशन (15 Characters)
    if (!preg_match("/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/", $gstNo)) {
        $error_message = "Invalid GST Number Format!";
    } elseif ($wallet_amount < $fee) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
              <script>document.addEventListener('DOMContentLoaded', function() {
                  Swal.fire('Low Balance!', 'Please recharge your wallet.', 'error').then(() => { window.location='wallet.php'; });
              });</script>";
        exit;
    } else {
        $api_key = urlencode($DarkXen_apikey);
        $url = "https://" . $DarkXen_url . "/api/gst_details.php?token=$api_key&gst_no=$gstNo";

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

        if ($curl_error) {
            $error_message = "API Server Timeout";
        } else {
            $resdata = json_decode($response, true) ?? [];

            if (isset($resdata['status']) && $resdata['status'] == true && isset($resdata['data'])) {
                mysqli_begin_transaction($DarkXen_conn);
                try {
                    // Wallet Debit
                    $debit_fee = $wallet_amount - $fee;
                    mysqli_query($DarkXen_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
                    
                    // Wallet Log
                    $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES (?, ?, ?, 'GST Detail Check', '1', 'Debit')");
                    mysqli_stmt_bind_param($stmt_wallet, "sdd", $appliedby, $fee, $debit_fee);
                    mysqli_stmt_execute($stmt_wallet);

                    // GST History Log
                    $app_no = 'GST' . time();
                    $legal_name = mysqli_real_escape_string($DarkXen_conn, $resdata['data']['legal_name']);
                    $trade_name = mysqli_real_escape_string($DarkXen_conn, $resdata['data']['trade_name']);
                    $status_gst = mysqli_real_escape_string($DarkXen_conn, $resdata['data']['gst_status']);
                    $c_date = date('Y-m-d H:i:s');

                    $insert_sql = "INSERT INTO `gst_history` (`application_no`, `username`, `gstin`, `legal_name`, `trade_name`, `gst_status`, `date`) 
                                   VALUES ('$app_no', '$appliedby', '$gstNo', '$legal_name', '$trade_name', '$status_gst', '$c_date')";
                    mysqli_query($DarkXen_conn, $insert_sql);

                    mysqli_commit($DarkXen_conn);
                    $success_message = "GST Details Fetched Successfully!";
                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);
                    $error_message = "Transaction Failed";
                }
            } else {
                $error_message = $resdata['message'] ?? 'Invalid GSTIN or Data Not Found.';
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
                    <div class="icon-item bg-primary-subtle shadow-none me-3"><span class="fas fa-file-invoice text-primary fs-4"></span></div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Taxation Services</h6>
                        <h4 class="text-primary fw-bold mb-0">GST <span class="text-info fw-medium">Verification</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="gst_history_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-history me-1"></i> Search History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center mb-4">
    <div class="col-lg-6">
        <div class="card h-100 border-top border-4 border-primary shadow-sm">
            <div class="card-header bg-light text-center border-bottom">
                <h5 class="mb-0 text-primary fw-bold">GSTIN Search Engine</h5>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info border-0 fs-11">Search Charge: <strong>₹<?= number_format($fee, 2); ?></strong></div>
                <form method="POST" id="gstForm">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Enter 15-Digit GST Number</label>
                        <input type="text" class="form-control fw-bold text-uppercase" name="gst_number" placeholder="e.g. 19BOKPS7056D1ZI" maxlength="15" required>
                    </div>
                    <button class="btn btn-primary w-100 fw-bold py-2" type="submit" name="check_gst_btn">Fetch GST Details</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($resdata) && isset($resdata['data'])): 
    $g = $resdata['data'];
    $addr = $g['principal_address']['addr'];
?>
<div class="row justify-content-center mb-4">
    <div class="col-lg-10">
        <div class="card shadow-sm border-<?= $g['gst_status'] == 'Active' ? 'success' : 'danger' ?>">
            <div class="card-header bg-<?= $g['gst_status'] == 'Active' ? 'success' : 'danger' ?> text-white py-2">
                <h5 class="mb-0 text-white">GST Details: <?= $resdata['gstin'] ?></h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless fs-10">
                            <tr><th width="40%">Legal Name:</th><td class="fw-bold text-900"><?= $g['legal_name'] ?></td></tr>
                            <tr><th>Trade Name:</th><td class="text-primary fw-bold"><?= $g['trade_name'] ?></td></tr>
                            <tr><th>GST Status:</th><td><span class="badge badge-subtle-<?= $g['gst_status'] == 'Active' ? 'success' : 'danger' ?>"><?= $g['gst_status'] ?></span></td></tr>
                            <tr><th>Taxpayer Type:</th><td><?= $g['taxpayer_type'] ?></td></tr>
                            <tr><th>Registration Date:</th><td><?= $g['registration_date'] ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless fs-10">
                            <tr><th width="40%">Business Type:</th><td><?= $g['business_type'] ?></td></tr>
                            <tr><th>State Juris:</th><td><?= $g['state_jurisdiction'] ?></td></tr>
                            <tr><th>Center Juris:</th><td><?= $g['center_jurisdiction'] ?></td></tr>
                            <tr><th>Last Updated:</th><td><?= $g['last_updated'] ?></td></tr>
                        </table>
                    </div>
                </div>
                <hr class="my-2">
                <div class="fs-10">
                    <h6 class="fw-bold"><i class="fas fa-map-marker-alt text-danger me-2"></i>Principal Address:</h6>
                    <p class="mb-1 text-700">
                        <?= $addr['bno'] ?>, <?= $addr['flno'] ?>, <?= $addr['st'] ?>, <?= $addr['loc'] ?>, 
                        <?= $addr['locality'] ?>, <?= $addr['dst'] ?>, <?= $addr['stcd'] ?> - <?= $addr['pncd'] ?>
                    </p>
                    <small class="text-muted">Landmark: <?= $addr['landMark'] ?></small>
                </div>
                <hr class="my-2">
                <div class="fs-10">
                    <h6 class="fw-bold"><i class="fas fa-briefcase text-info me-2"></i>Nature of Business:</h6>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach($g['nature_of_business'] as $nob): ?>
                            <span class="badge bg-200 text-primary border"><?= $nob ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.getElementById('gstForm').addEventListener('submit', function() {
        Swal.fire({ title: 'Searching...', html: 'Verifying GSTIN records...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
    });
    <?php if($error_message) echo "Swal.fire('Error', '$error_message', 'error');"; ?>
    <?php if($success_message) echo "Swal.fire('Success', '$success_message', 'success');"; ?>
</script>

<?php include('footer.php'); ?>