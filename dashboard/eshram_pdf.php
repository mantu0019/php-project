<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 

// ================= INIT & FEE SETTINGS =================
$fee = 10.00; // Default Fee Fallback
$fee_query = mysqli_query($DarkXen_conn, "SELECT `eshram_pdf_fee` FROM `service_free` WHERE `id` = 1 LIMIT 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if(isset($fee_row['eshram_pdf_fee']) &&$fee_row['eshram_pdf_fee'] != '') {
        $fee = (float)$fee_row['eshram_pdf_fee'];
    }
}

$resdata = [];
$error_message = '';$success_message = '';
$pdf_base64 = '';$generated_name = '';
$generated_uan = '';$appliedby = $udata['phone'] ?? '';$date_time = date('Y-m-d H:i:s');

// ================= ACTION =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_btn'])) {
    
    // Live Wallet Fetch
    $chk_user = mysqli_query($DarkXen_conn, "SELECT `balance` FROM `users` WHERE `phone` = '$appliedby' LIMIT 1");
    $user_row = mysqli_fetch_assoc($chk_user);$wallet_amount = isset($user_row['balance']) ? (float)$user_row['balance'] : 0.00;

    $aadhaar_no = trim($_POST['aadhaar_no'] ?? '');
    $raw_dob = trim($_POST['dob'] ?? ''); 
    
    // DOB Format Conversion: Y-m-d (from HTML date input) to d/m/Y (Required by API)
    $api_dob = date('d/m/Y', strtotime($raw_dob));

    if (empty($aadhaar_no) || empty($raw_dob)) {$error_message = "कृपया सभी आवश्यक विवरण (Aadhaar & DOB) दर्ज करें!";
    } elseif ($wallet_amount <$fee) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
              <script>document.addEventListener('DOMContentLoaded', function() {
                  Swal.fire('Low Balance!', 'Please recharge now. Required: ₹" . $fee . "', 'error').then(() => { window.location='wallet.php'; });
              });</script>";
        exit;
    } else {
        // ================= API CONFIG =================
        $api_key = urlencode($DarkXen_apikey ?? '');
        $api_url_host =$DarkXen_url;
        $url = "https://" . $api_url_host . "/api/v3/AADHAR/uid_eshram_pdf_verification.php?apiKey=$api_key&aadhaar_no=$aadhaar_no&dob=" . urlencode($api_dob);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 45,
        ));
        $response = curl_exec($curl);
        $curl_error = curl_error($curl);
        curl_close($curl);

        if ($curl_error) {$error_message = "API Connection Failed: Server Timeout.";
        } else {
            $resdata = json_decode($response, true) ?? [];

            // API Status Check
            if (isset($resdata['StatusCode']) && $resdata['StatusCode'] == 100 && !empty($resdata['data']['pdf'])) {
                
                $data =$resdata['data'];
                $app_no =$resdata['application_no'] ?? 'ESHRM_' . time();
                $name =$data['name'] ?? 'N/A';
                $dob =$data['dob'] ?? 'N/A';
                $gender =$data['gender'] ?? 'N/A';
                $uan =$data['uan'] ?? 'N/A';
                $address =$data['address'] ?? 'N/A';
                $pdf_raw =$data['pdf'];

                // Safe Base64 Formatting
                if (strpos($pdf_raw, 'data:application/pdf;base64,') === false) {
                    $pdf_base64 = 'data:application/pdf;base64,' .$pdf_raw;
                } else {
                    $pdf_base64 =$pdf_raw;
                }

                $generated_name =$name;
                $generated_uan =$uan;

                mysqli_begin_transaction($DarkXen_conn);
                try {
                    // 1. Wallet Deduct
                    $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");
                    if (!$update_wallet) throw new Exception("Wallet Update Failed.");
                    
                    $new_balance = $wallet_amount -$fee;

                    // 2. Wallet History
                    $purpose = "E-Shram PDF: " . $uan;
                    $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES (?, ?, ?, ?, '1', 'Debit')");
                    mysqli_stmt_bind_param($stmt_wallet, "sdds", $appliedby,$fee, $new_balance,$purpose);
                    if (!mysqli_stmt_execute($stmt_wallet)) throw new Exception("Wallet History Insert Failed.");

                    // 3. Database Save (Using Prepared Statement for Longtext PDF)
                    $insert_sql = "INSERT INTO `eshram_pdf_history` (`application_no`, `username`, `name`, `dob`, `gender`, `uan_no`, `address`, `pdf_url`, `charge`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt_pdf = mysqli_prepare($DarkXen_conn,$insert_sql);
                    
                    if ($stmt_pdf) {
                        mysqli_stmt_bind_param($stmt_pdf, "ssssssssds", $app_no,$appliedby, $name,$dob, $gender,$uan, $address,$pdf_base64, $fee,$date_time);
                        if (!mysqli_stmt_execute($stmt_pdf)) throw new Exception("Failed to save PDF in Database.");
                    } else {
                        throw new Exception("History Prepare Failed: " . mysqli_error($DarkXen_conn));
                    }

                    mysqli_commit($DarkXen_conn);
                    $success_message =$resdata['message'] ?? "E-Shram PDF Generated Successfully!";
                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);$error_message = "DB Error: " . $e->getMessage();
                }
            } else {
                $error_message =$resdata['message'] ?? 'Data not found or invalid response from Govt Server.';
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
                            <h6 class="text-primary fs-10 mb-0">E-Shram Services</h6>
                            <h4 class="text-primary fw-bold mb-0">E-Shram Card <span class="text-info fw-medium">PDF Download</span></h4>
                            <p class="mb-0 fs-11 text-muted">Generate original E-Shram PDF using Aadhaar & DOB. <strong class="text-danger">Cost: ₹<?= number_format($fee, 2); ?></strong></p>
                        </div>
                    </div>
                    <div class="col-md-auto mt-3 mt-md-0">
                        <a href="eshram_pdf_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-history me-1"></i> View History</a>
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
                    <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-id-card me-2"></i>Enter Beneficiary Details</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" id="searchForm" autocomplete="off">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold text-800 small">Aadhaar Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-200"><i class="fas fa-barcode text-secondary"></i></span>
                                <input type="text" class="form-control shadow-none border-200 fw-bold font-monospace" name="aadhaar_no" placeholder="12-digit Aadhaar Number" maxlength="12" pattern="\d{12}" value="<?= htmlspecialchars($_POST['aadhaar_no'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-800 small">Date of Birth <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-200"><i class="fas fa-calendar-alt text-secondary"></i></span>
                                <!-- Using date picker, PHP will convert Y-m-d to d/m/Y automatically -->
                                <input type="date" class="form-control shadow-none border-200 fw-bold" name="dob" value="<?= htmlspecialchars($_POST['dob'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <button class="btn btn-primary w-100 py-2 fw-bold shadow-none" type="submit" name="generate_btn">
                            <span class="fas fa-file-download me-2"></span>Generate E-Shram PDF (-₹<?= number_format($fee, 2); ?>)
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Side: PDF Preview -->
        <div class="col-lg-7 col-12">
            <div class="card h-100 shadow-none border border-top border-4 border-success">
                <div class="card-header bg-light border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-success"><i class="fas fa-desktop me-2"></i>Live PDF Preview</h5>
                    <?php if (!empty($pdf_base64)): ?>
                        <span class="badge bg-success">Status: Ready</span>
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
                                <p class="mb-0 fs-12 text-muted fw-bold">Name: <span class="text-uppercase text-dark"><?= htmlspecialchars($generated_name); ?></span></p>
                                <p class="mb-0 fs-11 text-secondary">UAN Number: <span class="text-success font-monospace fw-bold"><?= htmlspecialchars($generated_uan); ?></span></p>
                            </div>
                            <a href="<?= htmlspecialchars($pdf_base64) ?>" download="EShram_<?= htmlspecialchars(str_replace(' ', '', $generated_uan)); ?>.pdf" class="btn btn-success fw-bold shadow-sm">
                                <i class="fas fa-download me-1"></i> Save PDF
                            </a>
                        </div>
                        
                    <?php else: ?>
                        <!-- STANDBY STATE -->
                        <div class="text-center text-muted p-4">
                            <i class="fas fa-file-pdf fa-4x mb-3 text-300"></i>
                            <h6 class="fw-bold mb-1 text-700">Preview Standby</h6>
                            <p class="small text-muted mx-auto mb-0" style="max-width: 340px;">
                                Left panel me Aadhaar and DOB enter karke submit karein. E-Shram PDF layout yahan load ho jayega.
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
        title: 'Rendering PDF...',
        text: 'Fetching original E-Shram document from server...',
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