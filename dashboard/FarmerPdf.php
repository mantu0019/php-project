<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

// Step 1: Fetch dynamic service fee from config
$fee = 20.00; // Default fallback fee
$fee_query = mysqli_query($DarkXen_conn, "SELECT `AgriFarmerCard` FROM `service_free` WHERE `id` = 1 LIMIT 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if ($fee_row['AgriFarmerCard'] !== '' &&$fee_row['AgriFarmerCard'] !== null) {
        $fee = (float)$fee_row['AgriFarmerCard'];
    }
}

$alert_message = '';
$resdata = [];$pdf_base64 = '';
$generated_name = '';$generated_aadhaar = '';

// Step 2: Form submit handling
if (isset($_POST['get_card_btn'])) {
    
    // Refresh User Balance (Prevents Race Condition)
    $appliedby =$udata['phone'];
    $chk_user = mysqli_query($DarkXen_conn, "SELECT `balance` FROM `users` WHERE `phone` = '$appliedby' LIMIT 1");
    $user_row = mysqli_fetch_assoc($chk_user);$current_balance = isset($user_row['balance']) ? (float)$user_row['balance'] : 0.00;

    // Remove any spaces from Aadhaar
    $uidNumber = preg_replace('/\s+/', '', trim($_POST['aadhar_number']));
    $state_code = isset($_POST['state_code']) ? trim($_POST['state_code']) : '';$debit_fee = $current_balance -$fee;

    if (!preg_match('/^[0-9]{12}$/', $uidNumber)) {$alert_message = "Swal.fire('Invalid Input', 'Please enter a valid 12-digit Aadhaar Number.', 'error');";
    } elseif ($current_balance < $fee) {$alert_message = "
            Swal.fire({
                title: 'Wallet Balance is Low!',
                text: 'Please recharge your wallet to access this service. (Required: ₹" . $fee . ")',
                icon: 'error',
                confirmButtonText: 'Recharge Now'
            }).then(() => {
                window.location='wallet.php';
            });
        ";
    } else {
        // ================= API CONFIG =================
        $api_key = urlencode($DarkXen_apikey ?? '');
        $api_url_host =$DarkXen_url;
        
        // Base API URL
        $url = "https://" . $api_url_host . "/api/v3/FARMER/agri_farmer_card_pdf_verification.php?apiKey=$api_key&aadhar=$uidNumber";
        
        // Append state only if selected (Optional handling)
        if (!empty($state_code)) {
            $url .= "&state=" . urlencode($state_code);
        }
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 50,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {$alert_message = "Swal.fire('Error', 'API server unreachable. Please try again.', 'error');";
        } else {
            // Processing dynamic response JSON
            $resdata = json_decode(trim($response), true);$sampleCode = isset($resdata['sampleCode']) ? (string)$resdata['sampleCode'] : '';
            $pdf =$resdata['pdf'] ?? '';
            $name =$resdata['fullname'] ?? 'N/A';

            // Check if API actually returned the PDF string
            if (($sampleCode === '200' || !empty($pdf)) && !empty($pdf)) {
                
                // Format Base64 prefix if not present for Iframe rendering
                if (strpos($pdf, 'data:application/pdf;base64,') === false) {
                    $pdf_base64 = 'data:application/pdf;base64,' .$pdf;
                } else {
                    $pdf_base64 =$pdf;
                }

                $generated_name =$name;
                $generated_aadhaar =$uidNumber;

                mysqli_begin_transaction($DarkXen_conn);
                try {
                    // 1. Update User Balance
                    $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");
                    if (!$update_wallet) throw new Exception("Wallet deduction failed.");
                    
                    // 2. Insert into wallet ledger
                    mysqli_query($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) 
                                   VALUES ('$appliedby', '$fee', '$debit_fee', 'AgriStack Farmer Card PDF', '1', 'Debit')");
                    
                    // 3. Save Data (Added application_no = 'NA' to prevent SQL crash)
                    $date_now = date('Y-m-d H:i:s');$app_no = "NA";
                    
                    $insert_history = "INSERT INTO `farmer_pdf_history` (`userid`, `aadhaar_number`, `farmer_name`, `state`, `pdf_url`, `charge`, `status`, `application_no`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, 'Success', ?, ?)";
                    $stmt = mysqli_prepare($DarkXen_conn,$insert_history);
                    
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "sssssdss", $appliedby,$uidNumber, $name,$state_code, $pdf_base64,$fee, $app_no,$date_now);
                        if (!mysqli_stmt_execute($stmt)) throw new Exception("Failed to save PDF in history.");
                    } else {
                        throw new Exception("SQL Prepare Failed: " . mysqli_error($DarkXen_conn));
                    }
                    
                    mysqli_commit($DarkXen_conn);$alert_message = "Swal.fire({
                        title: 'Card Generated!',
                        text: 'Farmer Card has been generated successfully.',
                        icon: 'success',
                        showConfirmButton: false,
                        timer: 2000
                    });";

                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);
                    $error_msg = mysqli_real_escape_string($DarkXen_conn, $e->getMessage());$alert_message = "Swal.fire('Database Error', '$error_msg', 'error');";
                }

            } else {
                // Better Error capturing if PDF fails
                $error_msg =$resdata['message'] ?? $resdata['status'] ?? 'Farmer details not found or Invalid Data.';$error_msg = mysqli_real_escape_string($DarkXen_conn,$error_msg);
                $alert_message = "Swal.fire('Notice', '$error_msg', 'error');";
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
                    <div class="icon-item bg-success-subtle shadow-none me-3" style="width: 50px; height: 50px;">
                        <span class="fas fa-tractor text-success fs-3"></span>
                    </div>
                    <div>
                        <h6 class="text-success fs-10 mb-0">Agri Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Farmer <span class="text-success fw-medium">Card PDF Print</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="FarmerPdf_List.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View PDF History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center g-4 mb-4">
    <!-- Left Side: Form Card -->
    <div class="col-lg-5 col-12">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-success">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-leaf me-2 text-success"></i>Generate Farmer PDF</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-success border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-success"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?php echo number_format($fee, 2); ?></strong> will be deducted upon successful PDF generation.</p>
                </div>
                
                <form method="POST" id="farmerForm" autocomplete="off" onsubmit="showProcessingLoader()">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-900 small">Aadhaar Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-id-card"></i></span>
                            <input type="text" class="form-control shadow-none fw-bold font-monospace" name="aadhar_number" id="aadhar_number" placeholder="Enter 12-digit number" maxlength="12" pattern="\d{12}" value="<?= htmlspecialchars($_POST['aadhar_number'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-900 small">Select State <span class="text-muted">(Optional)</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-map-marker-alt"></i></span>
                            <select class="form-select shadow-none" name="state_code">
                                <option value="" selected>All India (Auto Detect)</option>
                                <option value="an">Andaman and Nicobar Islands</option>
                                <option value="ap">Andhra Pradesh</option>
                                <option value="ar">Arunachal Pradesh</option>
                                <option value="as">Assam</option>
                                <option value="br">Bihar</option>
                                <option value="ch">Chandigarh</option>
                                <option value="cg">Chhattisgarh</option>
                                <option value="dl">Delhi</option>
                                <option value="ga">Goa</option>
                                <option value="gj">Gujarat</option>
                                <option value="hr">Haryana</option>
                                <option value="hp">Himachal Pradesh</option>
                                <option value="jk">Jammu and Kashmir</option>
                                <option value="jh">Jharkhand</option>
                                <option value="ka">Karnataka</option>
                                <option value="kl">Kerala</option>
                                <option value="la">Ladakh</option>
                                <option value="mp">Madhya Pradesh</option>
                                <option value="mh">Maharashtra</option>
                                <option value="mn">Manipur</option>
                                <option value="ml">Meghalaya</option>
                                <option value="mz">Mizoram</option>
                                <option value="nl">Nagaland</option>
                                <option value="od">Odisha</option>
                                <option value="py">Puducherry</option>
                                <option value="pb">Punjab</option>
                                <option value="rj">Rajasthan</option>
                                <option value="sk">Sikkim</option>
                                <option value="tn">Tamil Nadu</option>
                                <option value="tg">Telangana</option>
                                <option value="tr">Tripura</option>
                                <option value="up">Uttar Pradesh</option>
                                <option value="uk">Uttarakhand</option>
                                <option value="wb">West Bengal</option>
                            </select>
                        </div>
                    </div>
                    
                    <button class="btn btn-success w-100 fw-bold shadow-none py-2" type="submit" name="get_card_btn">
                        <i class="fas fa-file-pdf me-2"></i> Fetch & Generate PDF (-₹<?= number_format($fee, 2); ?>)
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Side: Side Preview Panel -->
    <div class="col-lg-7 col-12">
        <div class="card h-100 shadow-none border border-top border-4 border-primary">
            <div class="card-header bg-light border-bottom py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-desktop me-2"></i>Live PDF Preview</h5>
                <?php if (!empty($pdf_base64)): ?>
                    <span class="badge bg-success">Status: Ready</span>
                <?php endif; ?>
            </div>
            
            <div class="card-body bg-200 p-0 d-flex flex-column justify-content-center align-items-center" style="min-height: 400px; position: relative;">
                
                <?php if (!empty($pdf_base64)): ?>
                    <!-- SUCCESS PDF VIEWER -->
                    <div class="w-100 h-100 p-2" style="height: 500px !important;">
                        <iframe src="<?= htmlspecialchars($pdf_base64) ?>" width="100%" height="100%" style="border: none; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);"></iframe>
                    </div>
                    
                    <!-- Overlay Download Action Info -->
                    <div class="w-100 bg-white border-top p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-0 fs-12 text-muted fw-bold">Name: <span class="text-uppercase text-dark"><?= htmlspecialchars($generated_name); ?></span></p>
                            <p class="mb-0 fs-11 text-secondary">Aadhaar: <span class="font-monospace text-dark"><?= htmlspecialchars($generated_aadhaar); ?></span></p>
                        </div>
                        <a href="<?= htmlspecialchars($pdf_base64) ?>" download="Farmer_Card_<?= htmlspecialchars($generated_aadhaar); ?>.pdf" class="btn btn-success fw-bold shadow-sm">
                            <i class="fas fa-download me-1"></i> Save PDF
                        </a>
                    </div>
                    
                <?php else: ?>
                    <!-- IDLE STATE -->
                    <div class="text-center text-muted p-4">
                        <i class="fas fa-file-invoice fa-4x mb-3 text-300"></i>
                        <h6 class="fw-bold mb-1 text-700">Preview Standby</h6>
                        <p class="small text-muted mx-auto mb-0" style="max-width: 340px;">
                            Left form me Aadhaar Number daal kar Submit karein. Farmer Card PDF yahan load ho jayegi.
                        </p>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function showProcessingLoader() {
        Swal.fire({
            title: 'Verifying Details...',
            html: 'Connecting to AgriStack Servers and building PDF Layout...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    }
    
    // Trigger Server Alerts
    $(document).ready(function() {
        <?php if(!empty($alert_message)) { echo$alert_message; } ?>
    });
</script>

<?php include('footer.php'); ?>