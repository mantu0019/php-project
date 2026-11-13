<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set('Asia/Kolkata');

// ================= INIT & FEE CONFIG =================
$fee_query = mysqli_query($DarkXen_conn, "SELECT `pan_to_aadhar_mask` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if ($fee_row['pan_to_aadhar_mask'] !== '' &&$fee_row['pan_to_aadhar_mask'] !== null) {
        $fee = floatval($fee_row['pan_to_aadhar_mask']);
    }
}

if (!function_exists('clean')) {
    function clean($conn,$data) {
        return mysqli_real_escape_string($conn, trim($data));
    }
}

$DarkXen_apikey =$webdata['api_key'] ?? ''; 
$api_domain =$DarkXen_url;

$resdata = [];$error_message = "";
$status_flag = "";
$panNumber = "";

// Form processing 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['find_aadhar_btn']) && isset($_POST['pan_number']))) {

    $panNumber = strtoupper(clean($DarkXen_conn, $_POST['pan_number'] ?? ''));$appliedby = clean($DarkXen_conn,$udata['phone']);

    // PAN Validation Check 
    if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/i', $panNumber)) {$error_message = "Invalid PAN Number format.";
        $status_flag = "error";
    } else {

        // Wallet Balance Check 
        if (($udata['balance'] ?? 0) >=$fee) {

            $debit_fee = $udata['balance'] -$fee;
            $date_time = date('Y-m-d H:i:s');

            // API Configuration
            $url = "https://" . $api_domain . "/api/v3/PAN/pan_to_aadhar_mask_verification.php?" . http_build_query([
                'apiKey' => $DarkXen_apikey,
                'pan' => $panNumber
            ]);

            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_SSL_VERIFYPEER => false,
            ));

            $response = curl_exec($curl);
            
            if (curl_errno($curl)) {$error_message = "Server Connection Failed. Gateway Timeout.";
                $status_flag = "error";
            }
            curl_close($curl);

            $resdata = json_decode($response, true);

            // SUCCESS RESPONSE CHECK 
            if (isset($resdata['status']) &&$resdata['status'] == "100") {

                mysqli_begin_transaction($DarkXen_conn);

                // Sanitize Response Array Parameters
                $aadhar_no  = clean($DarkXen_conn,$resdata['aadhar_no'] ?? '');
                $fullname   = clean($DarkXen_conn, $resdata['fullname'] ?? '');$dob        = clean($DarkXen_conn,$resdata['dob'] ?? '');
                $gender     = clean($DarkXen_conn, $resdata['gender'] ?? '');$status_msg = clean($DarkXen_conn,$resdata['message'] ?? 'Successfully Processed');

                // 1. Deduct Balance Safely
                $update_user = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby' AND balance >=$fee");

                // 2. Insert Wallet Ledger Transaction History
                $insert_wallet = mysqli_query($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) 
                    VALUES ('$appliedby', '$fee', '$debit_fee', 'PAN TO AADHAAR MASK CHECK', '1', 'Debit')");

                // 3. Save Logs to Track Status Table 
                $insert_status = mysqli_query($DarkXen_conn, "INSERT INTO `pan_aadhar_mask_status` (`user_phone`, `pan_no`, `aadhar_no`, `fullname`, `dob`, `gender`, `status_msg`, `charge_debited`, `created_at`) 
                    VALUES ('$appliedby', '$panNumber', '$aadhar_no', '$fullname', '$dob', '$gender', '$status_msg', '$fee', '$date_time')");

                if ($update_user && $insert_wallet &&$insert_status) {
                    mysqli_commit($DarkXen_conn);$status_flag = "success";
                } else {
                    mysqli_rollback($DarkXen_conn);$error_message = "Database synchronization error. Process Aborted.";
                    $status_flag = "error";
                }

            } else {
                $error_message =$resdata['message'] ?? $resdata['statusMessage'] ?? 'Gateway returned an error response or identity mismatch.';$status_flag = "error";
            }

        } else {
            $status_flag = "low_balance";
        }
    }
}
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-id-card-alt text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">PAN Services</h6>
                        <h4 class="text-primary fw-bold mb-0">PAN To <span class="fw-medium">Mask Aadhaar</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-control form-control-sm bg-white border-200 shadow-sm px-3">
                            <span class="fas fa-wallet text-success me-2"></span>
                            <span class="fw-bold text-success">Balance: ₹<?php echo number_format((float)($udata['balance'] ?? 0), 2); ?></span>
                        </div>
                        <a href="pan_aadhar_mask_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-5">
        <div class="card h-100 mb-4 border-0 shadow-sm border-top border-4 border-primary">
            <div class="card-header bg-light border-bottom py-3">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-search me-2"></i>Find Masked Aadhaar</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-primary border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-primary"></i>
                    <p class="mb-0 fs-11">Current service charge: <strong>₹<?php echo number_format($fee, 2); ?></strong> will be deducted upon successful query.</p>
                </div>
                
                <form method="POST" id="panMaskForm" autocomplete="off">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">PAN Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-keyboard text-secondary"></i></span>
                            <input type="text" class="form-control shadow-none fw-bold text-center text-uppercase text-dark fs-9 tracking-wide" name="pan_number" placeholder="e.g. ABCDE1234F" maxlength="10" pattern="[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}" value="<?php echo htmlspecialchars($panNumber); ?>" required autofocus>
                        </div>
                    </div>
                    
                    <button class="btn btn-primary w-100 fw-bold shadow-none py-2" type="submit" name="find_aadhar_btn" onclick="triggerLoadingState()">
                        <i class="fas fa-search-plus me-2"></i> Find Details
                    </button>
                </form>
             </div>
        </div>
    </div>

    <!-- Dynamic Success Response Panel Card Structure -->
    <?php if (isset($resdata['status']) &&$resdata['status'] == "100") { ?>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-4 border-top border-4 border-success" id="printSection">
                <div class="card-header bg-success-subtle d-flex justify-content-between align-items-center py-3 border-bottom">
                    <h5 class="mb-0 text-success fs-10 fw-bold"><i class="fas fa-file-invoice me-2"></i>Identity Ledger Statement</h5>
                    <button onclick="printData()" class="btn btn-light btn-sm fw-bold shadow-none py-1 px-3 d-flex align-items-center gap-1">
                        <i class="fas fa-print me-1"></i> Print
                    </button>
                </div>
                <div class="card-body p-4 bg-white">
                    <div class="table-responsive border rounded shadow-sm">
                        <table class="table table-bordered table-striped mb-0 fs-11 align-middle text-dark">
                            <tbody>
                                <tr>
                                    <th width="35%" class="text-secondary fw-bold bg-light">PAN Reference</th>
                                    <td class="text-dark font-family-monospace fw-bold tracking-wide text-uppercase">
                                        <?php echo htmlspecialchars($panNumber); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-secondary fw-bold bg-light">Masked Aadhaar</th>
                                    <td>
                                        <b class="text-danger font-family-monospace text-uppercase fs-9 tracking-wide">
                                            <?php echo htmlspecialchars($resdata['aadhar_no'] ?? 'N/A'); ?>
                                        </b>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-secondary fw-bold bg-light">Full Name</th>
                                    <td class="fw-bold text-uppercase">
                                        <?php echo htmlspecialchars($resdata['fullname'] ?? 'N/A'); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-secondary fw-bold bg-light">Date of Birth</th>
                                    <td class="fw-bold">
                                        <?php echo htmlspecialchars($resdata['dob'] ?? 'N/A'); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-secondary fw-bold bg-light">Gender</th>
                                    <td class="fw-bold text-uppercase">
                                        <?php echo htmlspecialchars($resdata['gender'] ?? 'N/A'); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-secondary fw-bold bg-light">Network Message</th>
                                    <td class="text-success fw-bold italic">
                                        <?php echo htmlspecialchars($resdata['message'] ?? 'Successfully Processed'); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-secondary fw-bold bg-light">Date Verified</th>
                                    <td class="text-muted fs-11 fw-medium"><?php echo date('d M Y, h:i A'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>
</div>

<!-- Scripts Module Initialization -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Format input to uppercase automatically
document.getElementsByName('pan_number')[0].addEventListener('input', function () {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
});

// Loading Spinner
function triggerLoadingState() {
    if(document.getElementById('panMaskForm').checkValidity()) {
        Swal.fire({
            title: 'Locating Records...',
            html: 'Communicating with central database, please hold...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
    }
}

// Print Utility Handler for Statement Record Cards
function printData() {
    var divToPrint = document.getElementById("printSection");
    if (!divToPrint) return;
    
    var newWindow = window.open("", "_blank");
    newWindow.document.write('<html><head><title>Print Statement</title>');
    newWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">');
    newWindow.document.write('<style>body{padding:30px;} .btn, button { display:none !important; } .card-header { background-color: #d1e7dd !important; color: #0f5132 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } th { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }</style>');
    newWindow.document.write('</head><body>');
    newWindow.document.write(divToPrint.outerHTML);
    newWindow.document.write('</body></html>');
    newWindow.document.close();
    
    newWindow.addEventListener('load', function() {
        newWindow.print();
        newWindow.close();
    });
}

document.addEventListener("DOMContentLoaded", function() {
    <?php if ($status_flag === "success") { ?>
        Swal.fire({
            icon: 'success',
            title: 'Record Processed',
            html: '<div class="text-start fs-11">' +
                  '<p class="mb-2 text-secondary fw-bold"><?php echo htmlspecialchars($resdata['message'] ?? 'Aadhaar link status verified.'); ?></p>' +
                  '<hr class="my-2">' +
                  '<b>Retrieved Masked Aadhaar:</b> <span class="text-danger fw-bold font-family-monospace text-uppercase ms-1 tracking-wide"><?php echo htmlspecialchars($resdata['aadhar_no'] ?? 'N/A'); ?></span>' +
                  '</div>',
            confirmButtonColor: '#3085d6'
        });
    <?php } elseif ($status_flag === "error") { ?>
        Swal.fire({
            icon: 'error',
            title: 'Verification Failed',
            text: '<?php echo addslashes($error_message); ?>',
            confirmButtonColor: '#d33'
        });
    <?php } elseif ($status_flag === "low_balance") { ?>
        Swal.fire({
            icon: 'warning',
            title: 'Insufficient Funds',
            text: 'Your current account wallet balances are lower than the operational cost requirement.',
            confirmButtonColor: '#f8bb86'
        }).then(() => {
            window.location = 'wallet.php';
        });
    <?php } ?>
});
</script>

<?php include('footer.php'); ?>