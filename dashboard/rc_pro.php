<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

// Timezone Configuration
date_default_timezone_set("Asia/Kolkata"); 

// Default Fee Settings
$charge = 20.00;

// Fetch Fee
$fee_query = mysqli_query($DarkXen_conn, "SELECT `vehicle_pdf_find` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if ($fee_row['vehicle_pdf_find'] !== '' &&$fee_row['vehicle_pdf_find'] !== null) {
        $charge = (float)$fee_row['vehicle_pdf_find'];
    }
}

if (!function_exists('clean')) {
    function clean($conn,$data) {
        return mysqli_real_escape_string($conn, trim($data));
    }
}

$pdf_link = '';$error_message = '';
$success_message = '';$user = clean($DarkXen_conn,$udata['phone'] ?? '');
$wallet_amount = (float)($udata['balance'] ?? 0);
$api_domain =$DarkXen_url;
$apiKey = urlencode($webdata['api_key'] ?? $DarkXen_apikey ?? '');

// FORM SUBMISSION CHECK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_rc_btn'])) {

    $rcno = strtoupper(clean($DarkXen_conn, trim($_POST['vehicle_number'] ?? '')));$cardtype = clean($DarkXen_conn, trim($_POST['cardtype'] ?? ''));

    if (empty($rcno)) {$error_message = "Vehicle number cannot be empty.";
    } elseif (empty($cardtype)) {$error_message = "Please select RC Format Type (Chip/Non-Chip).";
    }
    // Balance Validation
    elseif ($wallet_amount <$charge) {
        echo '
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            Swal.fire("Low Balance", "Recharge Required to fetch PDF", "error").then(()=>{
                window.location="wallet.php";
            });
        });
        </script>';
        exit;
    } else {

        // ================= NEW API CALL (With Card Type) =================
        $url = "https://" . $api_domain . "/api/v3/VEHICLE/vehicle_rcpdf_verification.php?apiKey=$apiKey&rcno=" . urlencode($rcno) . "&cardtype=" . urlencode($cardtype);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => ["cache-control: no-cache"]
        ]);

        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {$error_message = "API Server Timeout: " . $curl_error;
        } else {
            $resdata = json_decode($response, true);
            $code =$resdata['sampleCode'] ?? '';

            // Status format check ("sampleCode" => "200")
            if ($code == '200') {
                
                $pdf_base64 =$resdata['pdf'] ?? '';

                if (!empty($pdf_base64)) {
                    
                    // --- PDF CORRUPTION FIX ---
                    if (strpos($pdf_base64, ',') !== false) {
                        $base64_parts = explode(',',$pdf_base64);
                        $pdf_base64 = end($base64_parts);
                    }
                    
                    // Clean and pad the base64 string
                    $pdf_base64 = str_replace([' ', "\r", "\n"], ['+', '', ''], $pdf_base64);
                    
                    // Add padding if missing (fixes strict base64 decoding issues)
                    $pad_len = 4 - (strlen($pdf_base64) % 4);
                    if ($pad_len > 0 &&$pad_len < 4) {
                        $pdf_base64 .= str_repeat('=',$pad_len);
                    }

                    $pdfData = base64_decode($pdf_base64);

                    // We ensure it is saved as a PDF since the API provides a PDF
                    if ($pdfData !== false) {
                        
                        // Create folder if not exists
                        $folder = "uploads/rc_pdfs/";
                        if (!is_dir($folder)) {
                            mkdir($folder, 0777, true);
                        }

                        // File name generate - ALWAYS .pdf
                        $safe_rc = preg_replace('/[^A-Za-z0-9]/', '',$rcno);
                        $filename = 'RC_' .$safe_rc . '_' . time() . '.pdf';
                        $filepath = $folder .$filename;

                        // Save File to local storage
                        file_put_contents($filepath,$pdfData);
                        $pdf_link =$filepath;

                        // ================= SAFE DATABASE TRANSACTION =================
                        mysqli_begin_transaction($DarkXen_conn);

                        try {
                            // 1. Balance Deduction
                            $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $charge WHERE phone='$user' AND balance >=$charge");
                            if (mysqli_affected_rows($DarkXen_conn) == 0) {
                                throw new Exception("Insufficient Balance during transaction.");
                            }

                            // Fetch new balance for log
                            $bal_check = mysqli_query($DarkXen_conn, "SELECT balance FROM users WHERE phone='$user'");
                            $newbalance = mysqli_fetch_assoc($bal_check)['balance'];

                            // 2. Wallet History Log
                            $remark = "RC PDF Download : " . $safe_rc;
                            $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES (?, ?, ?, ?, '1', 'Debit')");
                            mysqli_stmt_bind_param($stmt_wallet, "sdds", $user,$charge, $newbalance,$remark);
                            mysqli_stmt_execute($stmt_wallet);
                            mysqli_stmt_close($stmt_wallet);

                            // 3. Save Download Log in SQL
                            $application_no =$resdata['orderId'] ?? ('RC_' . time());
                            $current_date = date('Y-m-d H:i:s');$insert_sql = "INSERT INTO `rc_pdf_history` (`application_no`, `username`, `vehicle_no`, `pdf_path`, `date`) 
                                           VALUES ('$application_no', '$user', '$safe_rc', '$filepath', '$current_date')";
                            
                            if (!mysqli_query($DarkXen_conn,$insert_sql)) {
                                throw new Exception("Failed to save PDF log in database.");
                            }

                            mysqli_commit($DarkXen_conn);
                            
                            $success_message =$resdata['message'] ?? "RC PDF Generated Successfully!";

                        } catch (Exception $e) {
                            mysqli_rollback($DarkXen_conn);$error_message = "Transaction Error: " . $e->getMessage();
                            if (file_exists($filepath)) { unlink($filepath); } // Delete file on error$pdf_link = '';
                        }

                    } else {
                        $error_message = "Received corrupted data from the API. Please try again.";
                    }
                } else {
                    $error_message = "PDF content stream not found in server response.";
                }
            } else {
                $error_message =$resdata['message'] ?? 'RC Data Details Not Found / Server Error';
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
                    <div class="icon-item bg-warning-subtle shadow-none me-3">
                        <span class="fas fa-car-side text-warning fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-warning fs-10 mb-0">RTO Services</h6>
                        <h4 class="text-primary fw-bold mb-0">RC PRO <span class="text-warning fw-medium">PDF Print</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-control form-control-sm bg-white border-200 shadow-sm px-3">
                            <span class="fas fa-wallet text-success me-2"></span>
                            <span class="fw-bold text-success">Balance: ₹<?php echo number_format((float)($udata['balance'] ?? 0), 2); ?></span>
                        </div>
                        <a href="rc_pro_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DYNAMIC LAYOUT: Centered if no PDF, Side-by-Side if PDF exists -->
<div class="row <?php echo empty($pdf_link) ? 'justify-content-center' : ''; ?> mb-4">
    
    <!-- LEFT SIDE (FORM) -->
    <div class="<?php echo empty($pdf_link) ? 'col-lg-6' : 'col-lg-5'; ?>">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-warning">
            <div class="card-header bg-light border-bottom py-3">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-file-invoice me-2 text-warning"></i>Fetch RC PRO PDF</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                
                <div class="alert alert-warning border-0 d-flex align-items-center mb-4 p-3">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-warning"></i>
                    <div>
                        <p class="mb-0 fw-bold text-dark">Service Charge: ₹<?= number_format($charge, 2); ?></p>
                        <small class="fs-10 text-dark">Amount will be deducted only upon successful fetch.</small>
                    </div>
                </div>
                
                <form method="POST" id="rcForm" autocomplete="off" onsubmit="showLoader('Connecting with RTO servers to download document, please wait...')">
                    
                    <!-- CARD TYPE SELECTION -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Select RC Format Type <span class="text-danger">*</span></label>
                        <select name="cardtype" class="form-select fw-bold border-warning shadow-none" required>
                            <option value="" disabled selected>--- Choose Format ---</option>
                            <option value="1" <?php if(isset($_POST['cardtype']) &&$_POST['cardtype'] == '1') echo 'selected'; ?>>With Chip</option>
                            <option value="2" <?php if(isset($_POST['cardtype']) &&$_POST['cardtype'] == '2') echo 'selected'; ?>>Non-Chip</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Vehicle Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-truck-pickup text-warning"></i></span>
                            <input type="text" class="form-control shadow-none text-uppercase fw-bold" id="vehicle_number" name="vehicle_number" placeholder="e.g. MH34AA7482" value="<?php echo isset($_POST['vehicle_number']) ? htmlspecialchars($_POST['vehicle_number']) : ''; ?>" required>
                        </div>
                        <small class="text-500 mt-1 d-block">Enter without spaces.</small>
                    </div>
                    
                    <button class="btn btn-warning text-dark w-100 fw-bold shadow-none py-2" type="submit" name="get_rc_btn">
                        <i class="fas fa-download me-2"></i> Get RC PDF
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- RIGHT SIDE (PREVIEW - Only visible when file is generated) -->
    <?php if (!empty($pdf_link)): ?>
    <div class="col-lg-7 mt-4 mt-lg-0">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-success">
            <div class="card-body p-4 bg-light">
                
                <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom">
                    <div>
                        <h5 class="text-success fw-bold mb-1"><i class="fas fa-check-circle me-1"></i> RC Document Ready</h5>
                        <p class="text-muted mb-0 small">Vehicle Number: <b class="text-uppercase"><?php echo htmlspecialchars($rcno); ?></b></p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?php echo htmlspecialchars($pdf_link); ?>" download class="btn btn-success fw-bold shadow-none">
                            <i class="fas fa-file-download me-1"></i> Download
                        </a>
                        <a href="<?php echo htmlspecialchars($pdf_link); ?>" target="_blank" class="btn btn-outline-primary fw-bold shadow-none">
                            <i class="fas fa-external-link-alt me-1"></i> Open
                        </a>
                    </div>
                </div>
                
                <!-- PDF Preview -->
                <div class="ratio ratio-4x3" style="min-height: 550px;">
                    <iframe src="<?php echo htmlspecialchars($pdf_link); ?>#toolbar=0&navpanes=0&scrollbar=0" class="rounded border bg-white" allowfullscreen></iframe>
                </div>

            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // Remove spaces and uppercase formatting
    document.getElementById('vehicle_number').addEventListener('input', function() {
        this.value = this.value.toUpperCase().replace(/\s/g, '');
    });

    // Sweetalert Loader
    function showLoader(txt) {
        Swal.fire({
            title: 'Generating Document...',
            html: txt,
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
    }

    <?php if(!empty($error_message)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?php echo addslashes($error_message); ?>',
            confirmButtonColor: '#d33'
        });
    <?php endif; ?>

    <?php if(!empty($success_message)): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?php echo addslashes($success_message); ?>',
            confirmButtonColor: '#28a745',
            timer: 3000,
            showConfirmButton: false
        });
    <?php endif; ?>
</script>

<?php include('footer.php'); ?>