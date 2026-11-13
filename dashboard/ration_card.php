<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php'); // Dashboard layout header file

date_default_timezone_set("Asia/Kolkata"); 

// ================= INIT & FEE SETTINGS =================
$fee = 10.00; 
$fee_query = mysqli_query($DarkXen_conn, "SELECT `ration_check_fee` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    $fee = (float)($fee_row['ration_check_fee'] ?? 10.00);
}

$resdata = [];$error_message = '';
$success_message = '';$appliedby = $udata['phone'] ?? '';$user_id = $udata['id'] ?? '';$date_time = date('Y-m-d H:i:s');

// ================= ACTION =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_rc_btn'])) {
    
    // Fetch Live Wallet Amount
    $chk_user = mysqli_query($DarkXen_conn, "SELECT `balance` FROM `users` WHERE `phone` = '$appliedby' LIMIT 1");
    $user_row = mysqli_fetch_assoc($chk_user);$wallet_amount = isset($user_row['balance']) ? (float)$user_row['balance'] : 0.00;

    $id_no = trim($_POST['id_no'] ?? '');
    $id_type = trim($_POST['id_type'] ?? '');
    $pdftype = trim($_POST['pdftype'] ?? '');

    if (empty($id_no)) {$error_message = "कृपया Document Number दर्ज करें!";
    } elseif ($wallet_amount <$fee) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
              <script>document.addEventListener('DOMContentLoaded', function() {
                  Swal.fire('Low Balance!', 'Please recharge now.', 'error').then(() => { window.location='wallet.php'; });
              });</script>";
        exit;
    } else {
        // ================= API URL CONFIG =================
        $api_key = urlencode($DarkXen_apikey ?? '');
        $api_url_host =$DarkXen_url ?? 'darkxen.in';
        
        // Updated API Link with new parameters
        $url = "https://" . $api_url_host . "/api/v3/RATION/ration_pdf_verification.php?apiKey=$api_key&id_no=$id_no&id_type=$id_type&pdftype=$pdftype";

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

        if ($curl_error) {$error_message = "API Connection Failed or Timeout.";
        } else {
            $resdata = json_decode($response, true) ?? [];

            // Check Status based on New API "status": true
            if (isset($resdata['status']) && $resdata['status'] === true && !empty($resdata['pdf_base64'])) {
                
                mysqli_begin_transaction($DarkXen_conn);
                try {
                    // ================= WALLET DEDUCTION =================
                    $debit_fee = $wallet_amount -$fee;
                    mysqli_query($DarkXen_conn, "UPDATE users SET balance='$debit_fee' WHERE phone='$appliedby'");
                    
                    // ================= WALLET HISTORY =================
                    $purpose = "Ration PDF: " . $pdftype;
                    $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES (?, ?, ?, ?, '1', 'Debit')");
                    mysqli_stmt_bind_param($stmt_wallet, "sdds", $appliedby,$fee, $debit_fee,$purpose);
                    mysqli_stmt_execute($stmt_wallet);

                    // ================= DB SAVE (MAPPED OLD COLUMNS TO NEW DATA) =================
                    $app_no = mysqli_real_escape_string($DarkXen_conn,$resdata['orderId'] ?? 'R_PDF_' . time());
                    $db_rcid = mysqli_real_escape_string($DarkXen_conn, $id_no);$db_fps_id = mysqli_real_escape_string($DarkXen_conn,$resdata['doctype'] ?? $id_type); // Saved Doc Type in place of FPS$db_head_family = mysqli_real_escape_string($DarkXen_conn,$resdata['pdftype'] ?? $pdftype); // Saved PDF Type$db_mobile = "NA"; // Default NA as new API doesn't return mobile
                    $db_pdf  = mysqli_real_escape_string($DarkXen_conn, $resdata['pdf_base64']);$insert_sql = "INSERT INTO `ration_pdf_history` 
                                   (`application_no`, `username`, `rcid`, `fps_id`, `head_family`, `mobile`, `pdf`, `date`) 
                                   VALUES 
                                   ('$app_no', '$appliedby', '$db_rcid', '$db_fps_id', '$db_head_family', '$db_mobile', '$db_pdf', '$date_time')";
                    mysqli_query($DarkXen_conn,$insert_sql);

                    mysqli_commit($DarkXen_conn);
                    $success_message =$resdata['message'] ?? "Ration PDF Generated Successfully!";
                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);$error_message = "Database Transaction Error Occurred";
                }
            } else {
                $error_message =$resdata['message'] ?? 'Document data not found or invalid response.';
            }
        }
    }
}
?>

<!-- ================= HTML FRONT-END SECTION ================= -->
<div class="container-fluid py-4">
    
    <!-- Title Banner Area -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-100 shadow-none border">
                <div class="row gx-0 flex-between-center p-3">
                    <div class="col-sm-auto d-flex align-items-center">
                        <div class="icon-item bg-primary-subtle shadow-none me-3" style="width: 50px; height: 50px;">
                            <span class="fas fa-file-pdf text-primary fs-3"></span>
                        </div>
                        <div>
                            <h6 class="text-primary fs-10 mb-0">PDF Services</h6>
                            <h4 class="text-primary fw-bold mb-0">Advanced Ration <span class="text-info fw-medium">PDF Tool</span></h4>
                            <p class="mb-0 fs-11 text-muted font-sans-serif">Select document type and format to generate high-quality Ration PDF instantly. <strong class="text-danger">Cost: ₹<?= number_format($fee, 2); ?></strong></p>
                        </div>
                    </div>
                    <div class="col-md-auto mt-3 mt-md-0">
                        <a href="ration_pdf_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-history me-1"></i> View PDF History</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Interface Panel Layout -->
    <div class="row g-3 justify-content-center mb-4">
        
        <!-- Left Side: Input Form Box -->
        <div class="col-lg-5 col-12">
            <div class="card h-100 shadow-none border border-top border-4 border-primary">
                <div class="card-header bg-light border-bottom py-3">
                    <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-sliders-h me-2"></i>PDF Generation Parameters</h5>
                </div>
                <div class="card-body bg-white p-4">
                    <form method="POST" id="pdfForm" autocomplete="off">
                        
                        <!-- 1. Document Type Dropdown -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-800 small">Select Document Type</label>
                            <div class="input-group">
                                <span class="input-group-text bg-200"><i class="fas fa-id-card text-secondary"></i></span>
                                <select class="form-select shadow-none border-200 fw-medium" name="id_type" required>
                                    <option value="" disabled selected>-- Choose Document --</option>
                                    <option value="u" <?= (isset($_POST['id_type']) &&$_POST['id_type'] == 'u') ? 'selected' : '' ?>>Aadhaar Card</option>
                                    <option value="r" <?= (isset($_POST['id_type']) &&$_POST['id_type'] == 'r') ? 'selected' : '' ?>>Ration Card</option>
                                </select>
                            </div>
                        </div>

                        <!-- 2. Document Number Input -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-800 small">Document Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-200"><i class="fas fa-keyboard text-secondary"></i></span>
                                <input type="text" class="form-control shadow-none border-200 fw-bold text-monospace" name="id_no" placeholder="Enter Card No. (e.g. 1234XXXXXXXX)" value="<?= htmlspecialchars($_POST['id_no'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <!-- 3. PDF Type Dropdown -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-800 small">PDF Design Format</label>
                            <div class="input-group">
                                <span class="input-group-text bg-200"><i class="fas fa-file-image text-secondary"></i></span>
                                <select class="form-select shadow-none border-200 fw-medium" name="pdftype" required>
                                    <option value="NEW" <?= (isset($_POST['pdftype']) &&$_POST['pdftype'] == 'NEW') ? 'selected' : '' ?>>NEW (Updated Layout)</option>
                                    <option value="OLD" <?= (isset($_POST['pdftype']) &&$_POST['pdftype'] == 'OLD') ? 'selected' : '' ?>>OLD (Classic Layout)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button class="btn btn-primary w-100 py-2 fw-bold shadow-none" type="submit" name="check_rc_btn">
                                <span class="fas fa-cogs me-2"></span>Generate PDF (-₹<?= number_format($fee, 2); ?>)
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Side: Side Preview Panel -->
        <div class="col-lg-7 col-12">
            <div class="card h-100 shadow-none border border-top border-4 border-success">
                <div class="card-header bg-light border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-success"><i class="fas fa-desktop me-2"></i>Live PDF Preview</h5>
                    <?php if (!empty($resdata) && isset($resdata['status']) &&$resdata['status'] === true): ?>
                        <span class="badge bg-success">Status: Generated</span>
                    <?php endif; ?>
                </div>
                
                <div class="card-body bg-200 p-0 d-flex flex-column justify-content-center align-items-center" style="min-height: 400px; position: relative;">
                    
                    <?php if (!empty($resdata) && isset($resdata['status']) &&$resdata['status'] === true): ?>
                        <!-- SUCCESS PDF VIEWER -->
                        <div class="w-100 h-100 p-2" style="height: 500px !important;">
                            <iframe src="<?= htmlspecialchars($resdata['pdf_base64']) ?>" width="100%" height="100%" style="border: none; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);"></iframe>
                        </div>
                        
                        <!-- Overlay Download Action Info -->
                        <div class="w-100 bg-white border-top p-3 d-flex justify-content-between align-items-center">
                            <div>
                                <p class="mb-0 fs-12 text-muted fw-bold">Order ID: <?= htmlspecialchars($resdata['orderId']); ?></p>
                                <p class="mb-0 fs-11 text-secondary">Document: <span class="text-uppercase"><?= htmlspecialchars($resdata['doctype']); ?></span> Layout: <?= htmlspecialchars($resdata['pdftype']); ?></p>
                            </div>
                            <a href="<?= htmlspecialchars($resdata['pdf_base64']) ?>" download="Ration_PDF_<?= time(); ?>.pdf" class="btn btn-success fw-bold shadow-sm">
                                <i class="fas fa-download me-1"></i> Save PDF
                            </a>
                        </div>
                        
                    <?php else: ?>
                        <!-- IDLE STATE -->
                        <div class="text-center text-muted p-4">
                            <i class="fas fa-file-invoice fa-4x mb-3 text-300"></i>
                            <h6 class="fw-bold mb-1 text-700">Preview Standby</h6>
                            <p class="small text-muted mx-auto mb-0" style="max-width: 340px;">
                                Left panel me details fill karke "Generate PDF" click karein. Result PDF yahan load ho jayegi.
                            </p>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
        
    </div>
</div>

<!-- SweetAlert2 UI alert implementation -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.getElementById('pdfForm').addEventListener('submit', function() {
    Swal.fire({
        title: 'Rendering PDF...',
        text: 'Please wait while we connect to secure servers and build your document layout...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
});

<?php if(!empty($error_message)): ?>
    Swal.fire({
        icon: 'error',
        title: 'Generation Failed',
        text: '<?= addslashes($error_message); ?>',
        confirmButtonText: 'Try Again'
    });
<?php endif; ?>

<?php if(!empty($success_message)): ?>
    Swal.fire({
        icon: 'success',
        title: 'Document Ready!',
        text: '<?= addslashes($success_message); ?>',
        showConfirmButton: false,
        timer: 1500
    });
<?php endif; ?>
</script>

<style>
    .form-control, .form-select { padding: 0.65rem 1rem; border-radius: 0.375rem; }
    .card { border-radius: 0.75rem; overflow: hidden; }
    .text-monospace { font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
    .fs-11 { font-size: 13px !important; }
    .fs-12 { font-size: 12px !important; }
    .icon-item { display: flex; align-items: center; justify-content: center; border-radius: 50%; }
</style>

<?php include('footer.php'); ?>