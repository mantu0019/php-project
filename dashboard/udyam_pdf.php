<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 

// ================= INIT & FEE SETTINGS =================
$fee = 10.00; // Default Fee Fallback
$fee_query = mysqli_query($DarkXen_conn, "SELECT `udyam_pdf_fee` FROM `service_free` WHERE `id` = 1 LIMIT 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if(isset($fee_row['udyam_pdf_fee']) && $fee_row['udyam_pdf_fee'] != '') {
        $fee = (float)$fee_row['udyam_pdf_fee'];
    }
}

$resdata = [];
$error_message = '';
$success_message = '';
$pdf_base64 = '';
$generated_cname = '';
$generated_udyam = '';
$appliedby = $udata['phone'] ?? '';
$date_time = date('Y-m-d H:i:s');

// ================= ACTION =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_btn'])) {
    
    // Live Wallet Fetch
    $chk_user = mysqli_query($DarkXen_conn, "SELECT `balance` FROM `users` WHERE `phone` = '$appliedby' LIMIT 1");
    $user_row = mysqli_fetch_assoc($chk_user);
    $wallet_amount = isset($user_row['balance']) ? (float)$user_row['balance'] : 0.00;

    $udyam_number = strtoupper(trim($_POST['udyam_number'] ?? ''));
    
    // Exact MSME Format Validation
    if (!preg_match('/^UDYAM-[A-Z]{2}-[0-9]{2}-[0-9]{7}$/', $udyam_number)) {
        $error_message = "कृपया सही Udyam नंबर दर्ज करें (e.g. UDYAM-BR-30-0051916)";
    } elseif ($wallet_amount < $fee) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
              <script>document.addEventListener('DOMContentLoaded', function() {
                  Swal.fire('Low Balance!', 'Please recharge now. Required: ₹" . $fee . "', 'error').then(() => { window.location='wallet.php'; });
              });</script>";
        exit;
    } else {
        // ================= API CONFIG =================
        $api_key = urlencode($DarkXen_apikey ?? '');
        $api_url_host = $DarkXen_url ?? 'darkxen.in';
        $url = "https://" . $api_url_host . "/api/v3/MSME/udhyam_no_to_pdf.php?apiKey=$api_key&udyam_number=" . urlencode($udyam_number);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 60, // PDF generation might take slightly longer
        ));
        $response = curl_exec($curl);
        $curl_error = curl_error($curl);
        curl_close($curl);

        if ($curl_error) {
            $error_message = "API Connection Failed: Server Timeout.";
        } else {
            $resdata = json_decode($response, true) ?? [];

            // API Status Check (Looking for sampleCode: "200")
            if (isset($resdata['sampleCode']) && $resdata['sampleCode'] == "200" && !empty($resdata['data']['pdf'])) {
                
                $data = $resdata['data'];
                $ent_data = $data['enterprise_data'] ?? [];
                
                $app_no = $data['application_no'] ?? 'UPDF_' . time();
                $c_name = $ent_data['name'] ?? 'N/A';
                $mob = $ent_data['mobile'] ?? 'N/A';
                $mail = $ent_data['email'] ?? 'N/A';
                
                $pdf_raw = $data['pdf'];

                // Ensure Safe Base64 Formatting
                if (strpos($pdf_raw, 'data:application/pdf;base64,') === false) {
                    $pdf_base64 = 'data:application/pdf;base64,' . $pdf_raw;
                } else {
                    $pdf_base64 = $pdf_raw;
                }

                $generated_cname = $c_name;
                $generated_udyam = $udyam_number;

                mysqli_begin_transaction($DarkXen_conn);
                try {
                    // 1. Wallet Deduct
                    $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");
                    if (!$update_wallet) throw new Exception("Wallet Update Failed.");
                    
                    $new_balance = $wallet_amount - $fee;

                    // 2. Wallet History
                    $purpose = "Udyam PDF Gen: " . $udyam_number;
                    $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES (?, ?, ?, ?, '1', 'Debit')");
                    mysqli_stmt_bind_param($stmt_wallet, "sdds", $appliedby, $fee, $new_balance, $purpose);
                    if (!mysqli_stmt_execute($stmt_wallet)) throw new Exception("Wallet History Insert Failed.");

                    // 3. Database Save (Using Prepared Statement for Longtext PDF)
                    $insert_sql = "INSERT INTO `udyam_pdf_history` (`application_no`, `username`, `udyam_no`, `company_name`, `mobile`, `email`, `pdf_url`, `charge`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt_pdf = mysqli_prepare($DarkXen_conn,$insert_sql);
                    
                    if ($stmt_pdf) {
                        mysqli_stmt_bind_param($stmt_pdf, "sssssssds", $app_no, $appliedby,$udyam_number, $c_name,$mob, $mail,$pdf_base64, $fee,$date_time);
                        if (!mysqli_stmt_execute($stmt_pdf)) throw new Exception("Failed to save PDF in Database.");
                    } else {
                        throw new Exception("History Prepare Failed: " . mysqli_error($DarkXen_conn));
                    }

                    mysqli_commit($DarkXen_conn);
                    $success_message =$resdata['message'] ?? "Udyam PDF Generated Successfully!";
                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);$error_message = "DB Error: " . $e->getMessage();$pdf_base64 = ''; // Clear preview on DB failure
                }
            } else {
                $error_message =$resdata['message'] ?? 'Data not found or PDF generation failed on the Govt Server.';
            }
        }
    }
}
?>

<!-- UI SECTION -->
<div class="container-fluid py-4">
    <!-- Title Area -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-100 shadow-none border">
                <div class="row gx-0 flex-between-center p-3">
                    <div class="col-sm-auto d-flex align-items-center">
                        <div class="icon-item bg-primary-subtle shadow-none me-3" style="width: 50px; height: 50px;">
                            <span class="fas fa-file-pdf text-primary fs-3"></span>
                        </div>
                        <div>
                            <h6 class="text-primary fs-10 mb-0">MSME Services</h6>
                            <h4 class="text-primary fw-bold mb-0">Udyam Registration <span class="text-info fw-medium">PDF Download</span></h4>
                            <p class="mb-0 fs-11 text-muted">Generate original MSME Certificate PDF using Udyam Number. <strong class="text-danger">Cost: ₹<?= number_format($fee, 2); ?></strong></p>
                        </div>
                    </div>
                    <div class="col-md-auto mt-3 mt-md-0">
                        <a href="udyam_pdf_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-history me-1"></i> View Generated PDFs</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <!-- Left Side: Form -->
        <div class="col-lg-5 col-12">
            <div class="card h-100 shadow-none border border-top border-4 border-primary">
                <div class="card-header bg-light border-bottom py-3">
                    <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-certificate me-2"></i>Enter Registration Number</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" id="pdfForm" autocomplete="off">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-800 small">Udyam Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-200"><i class="fas fa-industry text-secondary"></i></span>
                                <input type="text" class="form-control shadow-none border-200 fw-bold font-monospace text-uppercase" name="udyam_number" placeholder="UDYAM-BR-30-0000000" pattern="^UDYAM-[A-Za-z]{2}-[0-9]{2}-[0-9]{7}$" value="<?= htmlspecialchars($_POST['udyam_number'] ?? ''); ?>" required autofocus>
                            </div>
                        </div>

                        <button class="btn btn-primary w-100 py-2 fw-bold shadow-none" type="submit" name="download_btn">
                            <span class="fas fa-cogs me-2"></span>Generate MSME Certificate (-₹<?= number_format($fee, 2); ?>)
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Side: PDF Preview -->
        <div class="col-lg-7 col-12">
            <div class="card h-100 shadow-none border border-top border-4 border-success">
                <div class="card-header bg-light border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-success"><i class="fas fa-desktop me-2"></i>Live PDF View</h5>
                    <?php if (!empty($pdf_base64)): ?>
                        <span class="badge bg-success">Certificate Ready</span>
                    <?php endif; ?>
                </div>
                
                <div class="card-body bg-200 p-0 d-flex flex-column justify-content-center align-items-center" style="min-height: 400px; position: relative;">
                    <?php if (!empty($pdf_base64)): ?>
                        
                        <!-- PDF VIEWER -->
                        <div class="w-100 h-100 p-2" style="height: 500px !important;">
                            <iframe src="<?= htmlspecialchars($pdf_base64) ?>" width="100%" height="100%" style="border: none; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);"></iframe>
                        </div>
                        
                        <!-- Actions & Info Overlay -->
                        <div class="w-100 bg-white border-top p-3 d-flex justify-content-between align-items-center">
                            <div>
                                <p class="mb-0 fs-12 text-muted fw-bold">Enterprise: <span class="text-uppercase text-dark"><?= htmlspecialchars($generated_cname); ?></span></p>
                                <p class="mb-0 fs-11 text-secondary">Udyam No: <span class="text-success font-monospace fw-bold"><?= htmlspecialchars($generated_udyam); ?></span></p>
                            </div>
                            <a href="<?= htmlspecialchars($pdf_base64) ?>" download="Udyam_Certificate_<?= htmlspecialchars(str_replace('-', '_', $generated_udyam)); ?>.pdf" class="btn btn-success fw-bold shadow-sm">
                                <i class="fas fa-download me-1"></i> Save Document
                            </a>
                        </div>
                        
                    <?php else: ?>
                        <!-- STANDBY STATE -->
                        <div class="text-center text-muted p-4">
                            <i class="fas fa-file-pdf fa-4x mb-3 text-300"></i>
                            <h6 class="fw-bold mb-1 text-700">Preview Standby</h6>
                            <p class="small text-muted mx-auto mb-0" style="max-width: 340px;">
                                Enter the Udyam Registration Number on the left. The full MSME PDF Certificate will be rendered here.
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
document.getElementById('pdfForm').addEventListener('submit', function() {
    Swal.fire({
        title: 'Rendering Certificate...',
        html: 'Compiling secure MSME Certificate PDF from Government servers. Please wait...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
});
<?php if(!empty($error_message)): ?>
    Swal.fire({ icon: 'error', title: 'Generation Failed', text: '<?= addslashes($error_message); ?>', confirmButtonText: 'Try Again' });
<?php endif; ?>
<?php if(!empty($success_message)): ?>
    Swal.fire({ icon: 'success', title: 'Document Ready!', text: '<?= addslashes($success_message); ?>', showConfirmButton: false, timer: 1500 });
<?php endif; ?>
</script>

<?php include('footer.php'); ?>