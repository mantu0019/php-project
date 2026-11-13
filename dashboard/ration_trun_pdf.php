<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 

// फ़ीस सेटिंग्स
$fee = 10.00; 
$fee_query = mysqli_query($DarkXen_conn, "SELECT `ration_check_fee` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    $fee = (float)($fee_row['ration_check_fee'] ?? 10.00);
}

$resdata = [];
$error_message = '';
$success_message = '';
$appliedby = $udata['phone'];
$wallet_amount = (float)$udata['balance'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_rc_btn'])) {
    $rcid = trim($_POST['rcid'] ?? '');

    if (empty($rcid) || $rcid == "0") {
        $error_message = "कृपया RC ID दर्ज करें!";
    } elseif ($wallet_amount < $fee) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
              <script>document.addEventListener('DOMContentLoaded', function() {
                  Swal.fire('Low Balance!', 'Please recharge now.', 'error').then(() => { window.location='wallet.php'; });
              });</script>";
        exit;
    } else {
        $api_key = urlencode($DarkXen_apikey);
        $url = "https://webapi.in.net/api/ration_bihar_trans_pdf.php?token=$api_key&rcid=$rcid";

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
            $error_message = "API Connection Failed";
        } else {
            $resdata = json_decode($response, true) ?? [];

            if (isset($resdata['status']) && $resdata['status'] === true) {
                mysqli_begin_transaction($DarkXen_conn);
                try {
                    // वॉलेट से पैसा काटना
                    $debit_fee = $wallet_amount - $fee;
                    mysqli_query($DarkXen_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
                    
                    // वॉलेट हिस्ट्री
                    $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES (?, ?, ?, 'BSRC Transaction PDF Fetch', '1', 'Debit')");
                    mysqli_stmt_bind_param($stmt_wallet, "sdd", $appliedby, $fee, $debit_fee);
                    mysqli_stmt_execute($stmt_wallet);

                    // सर्च हिस्ट्री / डेटा सेव करना
                    $app_no = 'PDF' . time();
                    $fps_id = $resdata['fps_id'] ?? '';
                    $head_family = $resdata['head_family'] ?? '';
                    $mobile = $resdata['mobile'] ?? '';
                    $c_date = date('Y-m-d H:i:s');
                    $pdf = $resdata['pdf_base64'];
                    
                    
                    $insert_sql = "INSERT INTO `ration_pdf_history` (`application_no`, `username`, `rcid`, `fps_id`, `head_family`, `mobile`, `pdf`, `date`) 
                                   VALUES ('$app_no', '$appliedby', '$rcid', '$fps_id', '$head_family', '$mobile', '$pdf', '$c_date')";
                    mysqli_query($DarkXen_conn, $insert_sql);

                    mysqli_commit($DarkXen_conn);
                    $success_message = "PDF Fetched Successfully!";
                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);
                    $error_message = "Database Error Occurred";
                }
            } else {
                $error_message = $resdata['message'] ?? 'Data not found for this RC ID.';
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
                    <div class="icon-item bg-primary-subtle shadow-none me-3"><span class="fas fa-file-pdf text-primary fs-4"></span></div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Bihar Services</h6>
                        <h4 class="text-primary fw-bold mb-0">BSRC Transaction <span class="text-info fw-medium">PDF Download</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="ration_pdf_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-history me-1"></i> View History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center mb-4">
    <div class="col-lg-6">
        <div class="card h-100 border-top border-4 border-primary shadow-sm">
            <div class="card-header bg-light text-center border-bottom">
                <h5 class="mb-0 text-primary fw-bold">Fetch Transaction PDF</h5>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-primary border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-info-circle fs-3 me-3 text-primary"></i>
                    <p class="mb-0 fs-11">Charge: <strong>₹<?= number_format($fee, 2); ?></strong> will be deducted per search.</p>
                </div>
                <form method="POST" id="pdfForm">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Enter RC ID / SRC Number</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-barcode"></i></span>
                            <input type="text" class="form-control fw-bold" name="rcid" placeholder="e.g. 102370149625512300000076" value="<?= htmlspecialchars($_POST['rcid'] ?? '') ?>" required>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100 fw-bold py-2" type="submit" name="check_rc_btn">Fetch PDF</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($resdata) && isset($resdata['status']) && $resdata['status'] === true): ?>
<div class="row justify-content-center mb-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border">
            <div class="card-header bg-success py-2 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-white"><i class="fas fa-check-circle me-2"></i> PDF Details Summary</h5>
                <span class="badge bg-white text-success fw-bold">RC ID: <?= htmlspecialchars($resdata['rc_id'] ?? '') ?></span>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <strong>FPS ID:</strong> <span class="text-muted"><?= htmlspecialchars($resdata['fps_id'] ?? 'N/A') ?></span>
                    </div>
                    <div class="col-md-6 mb-2">
                        <strong>Head of Family:</strong> <span class="text-primary fw-bold"><?= htmlspecialchars($resdata['head_family'] ?? 'N/A') ?></span>
                    </div>
                    <div class="col-md-6 mb-2">
                        <strong>Mobile No:</strong> <span class="text-muted"><?= htmlspecialchars($resdata['mobile'] ?? 'N/A') ?></span>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <?php if (!empty($resdata['pdf_base64'])): ?>
                        <a href="<?= htmlspecialchars($resdata['pdf_base64']) ?>" download="Ration_Transaction_<?= htmlspecialchars($resdata['rc_id']) ?>.pdf" class="btn btn-danger fw-bold px-4 py-2 shadow-sm">
                            <i class="fas fa-file-pdf me-2"></i> Download PDF Document
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.getElementById('pdfForm').addEventListener('submit', function() {
        Swal.fire({ title: 'Processing...', html: 'Fetching transaction PDF data...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
    });
    <?php if($error_message) echo "Swal.fire('Error', " . json_encode($error_message) . ", 'error');"; ?>
    <?php if($success_message) echo "Swal.fire('Success', " . json_encode($success_message) . ", 'success');"; ?>
</script>

<?php include('footer.php'); ?>