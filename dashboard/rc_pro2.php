<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

// टाइमज़ोन कॉन्फ़िगरेशन
date_default_timezone_set("Asia/Kolkata"); 

// डिफ़ॉल्ट फ़ीस सेटिंग्स
$charge = 25.00;

// Fetch Fee
$fee_query = mysqli_query($DarkXen_conn, "SELECT `vehicle_pdf_find1` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if ($fee_row['vehicle_pdf_find1'] !== '' &&$fee_row['vehicle_pdf_find1'] !== null) {
        $charge = (float)$fee_row['vehicle_pdf_find1'];
    }
}

$pdf_link = '';
$error_message = '';$success_message = '';
$user =$udata['phone'];
$wallet_amount = (float)$udata['balance'];

// FORM SUBMISSION CHECK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_rc_btn'])) {

    $rcno = strtoupper(trim($_POST['vehicle_number'] ?? ''));
    $father_name = trim($_POST['father_name'] ?? '');
    $cardtype = trim($_POST['cardtype'] ?? '');

    if (empty($rcno)) {$error_message = "Vehicle number cannot be empty.";
    } elseif (empty($father_name)) {$error_message = "Father name cannot be empty.";
    } elseif (empty($cardtype)) {$error_message = "Please select a card type.";
    } 
    // बैलेंस वैलिडेशन
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

        // ================= NEW API CALL =================
        $apiKey = urlencode($DarkXen_apikey);
        $father_name_encoded = urlencode($father_name);
        
        // Use exact API path
        $url = "https://" . $DarkXen_url . "/api/v3/VEHICLE/vehicle_rcpdf_father.php?apiKey=$apiKey&rcno=$rcno&father_name=$father_name_encoded&cardtype=$cardtype";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {$error_message = "API Server Timeout: " . $curl_error;
        } else {
            $resdata = json_decode($response, true);
            
            $code =$resdata['sampleCode'] ?? 0;

            if ($code == 200) {
                $pdf_base64 =$resdata['pdf'] ?? '';

                if (!empty($pdf_base64)) {
                    // Base64 प्रिफिक्स हटाएं
                    $pdf_base64 = str_replace('data:application/pdf;base64,', '',$pdf_base64);
                    $pdfData = base64_decode($pdf_base64);

                    if ($pdfData !== false) {
                        // फोल्डर बनाएं
                        $folder = "uploads/";
                        if (!is_dir($folder)) {
                            mkdir($folder, 0777, true);
                        }

                        $filename =$rcno . '_' . time() . '.pdf';
                        $filepath = $folder .$filename;

                        // लोकल स्टोरेज में PDF सेव करें
                        file_put_contents($filepath,$pdfData);
                        $pdf_link =$filepath;

                        // ================= SAFE DATABASE TRANSACTION =================
                        mysqli_begin_transaction($DarkXen_conn);

                        try {
                            // 1. बैलेंस अपडेट करें
                            $newbalance = $wallet_amount -$charge;
                            $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance='$newbalance' WHERE phone='$user'");
                            if (!$update_wallet) {
                                throw new Exception("Wallet deduction failed.");
                            }

                            // 2. वॉलेट हिस्ट्री लॉग जोड़ें
                            $remark = "RC PDF (Father) : " . $rcno;
                            $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES (?, ?, ?, ?, '1', 'Debit')");
                            mysqli_stmt_bind_param($stmt_wallet, "sdds", $user,$charge, $newbalance,$remark);
                            mysqli_stmt_execute($stmt_wallet);
                            mysqli_stmt_close($stmt_wallet);

                            // 3. डाउनलोड लॉग SQL में सेव करें
                            $application_no = 'RC-FATHER' . time();
                            $current_date = date('Y-m-d H:i:s');$insert_sql = "INSERT INTO `rc_pdf_history` (`application_no`, `username`, `vehicle_no`, `pdf_path`, `date`) 
                                           VALUES ('$application_no', '$user', '$rcno', '$filepath', '$current_date')";
                            
                            if (!mysqli_query($DarkXen_conn,$insert_sql)) {
                                throw new Exception("Failed to save PDF log in database.");
                            }

                            mysqli_commit($DarkXen_conn);
                            $success_message =$resdata['message'] ?? "RC PRO PDF Generated & Logged Successfully!";

                        } catch (Exception $e) {
                            mysqli_rollback($DarkXen_conn);$error_message = "Transaction Error: " . $e->getMessage();
                            if (file_exists($filepath)) { unlink($filepath); }$pdf_link = '';
                        }

                    } else {
                        $error_message = "Invalid PDF Base64 Decoding Data";
                    }
                } else {
                    $error_message = "PDF content stream not found in server response";
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
                    <a href="rc_pro_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DYNAMIC LAYOUT: Center form if no PDF, Side-by-Side if PDF is generated -->
<div class="row <?= empty($pdf_link) ? 'justify-content-center' : '' ?> mb-4">
    
    <!-- Left Column: Form -->
    <div class="<?= empty($pdf_link) ? 'col-lg-6' : 'col-lg-4' ?>">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-warning">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-file-invoice me-2 text-warning"></i>Fetch RC PDF</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-warning border-0 d-flex align-items-center mb-4 p-2">
                    <i class="fas fa-rupee-sign fs-4 me-2 text-warning"></i>
                    <p class="mb-0 fs-11 text-dark" style="line-height:1.2;">Charge: <strong>₹<?= number_format($charge, 2); ?></strong> will be deducted.</p>
                </div>
                
                <form method="POST" id="rcForm" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-900">Vehicle Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-truck-pickup"></i></span>
                            <input type="text" class="form-control shadow-none text-uppercase fw-bold" id="vehicle_number" name="vehicle_number" placeholder="e.g. MH34AA7482" required value="<?= isset($_POST['vehicle_number']) ? htmlspecialchars($_POST['vehicle_number']) : '' ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-900">Father Name <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control shadow-none text-uppercase fw-bold" id="father_name" name="father_name" placeholder="Enter Father Name" required value="<?= isset($_POST['father_name']) ? htmlspecialchars($_POST['father_name']) : '' ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Card Type <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-id-card"></i></span>
                            <select class="form-select shadow-none fw-bold" name="cardtype" required>
                                <option value="" disabled <?= !isset($_POST['cardtype']) ? 'selected' : '' ?>>Select Card Type</option>
                                <option value="1" <?= (isset($_POST['cardtype']) &&$_POST['cardtype'] == '1') ? 'selected' : '' ?>>Chip (1)</option>
                                <option value="2" <?= (isset($_POST['cardtype']) &&$_POST['cardtype'] == '2') ? 'selected' : '' ?>>Non Chip (2)</option>
                            </select>
                        </div>
                    </div>
                    
                    <button class="btn btn-warning text-dark w-100 fw-bold shadow-none py-2" type="submit" name="get_rc_btn">
                        <i class="fas fa-download me-2"></i> Get RC PDF
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Side Preview (Only visible if PDF is generated) -->
    <?php if (!empty($pdf_link)): ?>
    <div class="col-lg-8 mt-4 mt-lg-0">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-success bg-success-subtle">
            <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-success fw-bold"><i class="fas fa-check-circle me-2"></i> Your PDF File Is Ready!</h5>
                <a href="<?= htmlspecialchars($pdf_link); ?>" target="_blank" class="btn btn-sm btn-success fw-bold shadow-none" download>
                    <i class="fas fa-file-download me-1"></i> Download File
                </a>
            </div>
            <!-- PDF Iframe Preview -->
            <div class="card-body p-0" style="min-height: 500px; overflow: hidden; border-radius: 0 0 0.375rem 0.375rem;">
                <iframe src="<?= htmlspecialchars($pdf_link); ?>#toolbar=0" width="100%" height="100%" style="border: none; min-height: 550px;">
                    This browser does not support PDFs. Please download the PDF to view it.
                </iframe>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // Formatting Inputs
    document.getElementById('vehicle_number').addEventListener('input', function() {
        this.value = this.value.toUpperCase().replace(/\s/g, '');
    });

    document.getElementById('father_name').addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });

    // Loading Animation
    document.getElementById('rcForm').addEventListener('submit', function() {
        Swal.fire({
            title: 'Generating PDF...',
            html: 'Connecting with RTO servers to download document, please wait...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
    });

    // Alerts
    <?php if(!empty($error_message)): ?>
        Swal.fire('Error', '<?= $error_message; ?>', 'error');
    <?php endif; ?>

    <?php if(!empty($success_message)): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '<?= $success_message; ?>',
            timer: 2000,
            showConfirmButton: false
        });
    <?php endif; ?>
</script>

<?php include('footer.php'); ?>