<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set('Asia/Kolkata');

// ================= INIT & FEE CONFIG =================
$fee = 5.00; // Default fallback fee
$fee_query = mysqli_query($DarkXen_conn, "SELECT `aadhaar_2_pan_mask` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if ($fee_row['aadhaar_2_pan_mask'] !== '' &&$fee_row['aadhaar_2_pan_mask'] !== null) {
        $fee = floatval($fee_row['aadhaar_2_pan_mask']);
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
$uidNumber = "";

// Form processing synced across button & raw payload indices
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['find_pan_btn']) && isset($_POST['aadhar_number']))) {

    $uidNumber = clean($DarkXen_conn, $_POST['aadhar_number'] ?? '');$appliedby = clean($DarkXen_conn,$udata['phone']);

    // Aadhaar Validation Check (12 Digits)
    if (!preg_match('/^[0-9]{12}$/', $uidNumber)) {$error_message = "Invalid Aadhaar Number format. Must be 12 digits.";
        $status_flag = "error";
    } else {

        // Wallet Balance Check 
        if (($udata['balance'] ?? 0) >=$fee) {

            $debit_fee = $udata['balance'] -$fee;
            $date_time = date('Y-m-d H:i:s');

            // NEW PAN MASK API Configuration
            $url = "https://" . $api_domain . "/api/v3/PAN/pan_mask_verification.php?" . http_build_query([
                'apiKey' => $DarkXen_apikey,
                'aadhar' => $uidNumber
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

            // SUCCESS RESPONSE CHECK (New API returns "100" for success)
            if (isset($resdata['status']) &&$resdata['status'] == "100") {

                mysqli_begin_transaction($DarkXen_conn);

                // Sanitize Response Array Parameters
                $pan_no     = clean($DarkXen_conn,$resdata['pan'] ?? '');
                $status_msg = clean($DarkXen_conn, $resdata['message'] ?? $resdata['statusMessage'] ?? 'Successfully Processed');

                // 1. Deduct Balance Safely
                $update_user = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby' AND balance >=$fee");

                // 2. Insert Wallet Ledger Transaction History
                $insert_wallet = mysqli_query($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) 
                    VALUES ('$appliedby', '$fee', '$debit_fee', 'PAN MASK CHECK', '1', 'Debit')");

                // 3. Save Logs to Track Status Table Configuration
                $insert_status = mysqli_query($DarkXen_conn, "INSERT INTO `aadhar_pan_status` (`user_phone`, `aadhar_no`, `pan_no`, `status_msg`, `charge_debited`, `created_at`) 
                    VALUES ('$appliedby', '$uidNumber', '$pan_no', '$status_msg', '$fee', '$date_time')");

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
                    <div class="icon-item bg-warning-subtle shadow-none me-3">
                        <span class="fas fa-id-badge text-warning fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-warning fs-10 mb-0">PAN Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Aadhaar to <span class="text-warning fw-medium">Mask PAN</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-control form-control-sm bg-white border-200 shadow-sm px-3">
                            <span class="fas fa-wallet text-success me-2"></span>
                            <span class="fw-bold text-success">Balance: ₹<?php echo number_format((float)($udata['balance'] ?? 0), 2); ?></span>
                        </div>
                        <a href="aadhaar_mask_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card h-100 mb-4 border-0 shadow-sm border-top border-4 border-warning">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-search me-2"></i>Find PAN Details</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-warning border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-warning"></i>
                    <p class="mb-0 fs-11">Current service charge: <strong>₹<?php echo number_format($fee, 2); ?></strong> will be deducted upon successful query.</p>
                </div>
                
                <form method="POST" id="panMaskForm" autocomplete="off">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Aadhaar Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-fingerprint text-secondary"></i></span>
                            <input type="text" class="form-control shadow-none fw-bold text-center text-dark fs-9 tracking-wide" name="aadhar_number" placeholder="Enter 12-digit number" maxlength="12" pattern="\d{12}" value="<?php echo htmlspecialchars($uidNumber); ?>" required autofocus>
                        </div>
                    </div>
                    
                    <button class="btn btn-warning w-100 fw-bold shadow-none py-2 text-dark" type="submit" name="find_pan_btn" onclick="triggerLoadingState()">
                        <i class="fas fa-search-plus me-2"></i> Find Masked PAN
                    </button>
                </form>
             </div>
        </div>
    </div>

    <!-- Dynamic Success Response Panel Card Structure -->
    <?php if (isset($resdata['status']) &&$resdata['status'] == "100") { ?>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4 border-top border-4 border-success" id="printSection">
                <div class="card-header bg-success-subtle d-flex justify-content-between align-items-center py-3 border-bottom">
                    <h5 class="mb-0 text-success fs-10 fw-bold"><i class="fas fa-file-invoice me-2"></i>Identity Ledger Statement</h5>
                    <button onclick="printData()" class="btn btn-light btn-sm fw-bold shadow-none py-1 px-3 d-flex align-items-center gap-1">
                        <i class="fas fa-print me-1"></i> Print
                    </button>
                </div>
                <div class="card-body p-4 bg-white">
                    <div class="table-responsive border rounded shadow-sm">
                        <table class="table table-bordered table-striped mb-0 fs-11 align-middle">
                            <tbody>
                                <tr>
                                    <th width="35%" class="text-secondary fw-bold bg-light">Aadhaar Reference</th>
                                    <td class="text-dark font-family-monospace fw-bold tracking-wide">
                                        XXXXXXXX<?php echo substr($uidNumber, -4); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-secondary fw-bold bg-light">Masked PAN</th>
                                    <td>
                                        <b class="text-danger font-family-monospace text-uppercase fs-9 tracking-wide">
                                            <?php echo htmlspecialchars($resdata['pan'] ?? 'N/A'); ?>
                                        </b>
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
// Loading Spinner
function triggerLoadingState() {
    if(document.getElementById('panMaskForm').checkValidity()) {
        Swal.fire({
            title: 'Locating Records...',
            html: 'Communicating with central PAN database, please hold...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
    }
}

// Print Utility Handler for Statement Record Cards
function printData() {
    var divToPrint = document.getElementById("printSection");
    if (!divToPrint) return;
    
    // Create new window interface
    var newWindow = window.open("", "_blank");
    newWindow.document.write('<html><head><title>Print Statement</title>');
    // Add essential bootstrap and clean framework custom stylings for explicit print modes
    newWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">');
    newWindow.document.write('<style>body{padding:30px;} .btn, button { display:none !important; } .card-header { background-color: #d1e7dd !important; color: #0f5132 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } th { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }</style>');
    newWindow.document.write('</head><body>');
    newWindow.document.write(divToPrint.outerHTML);
    newWindow.document.write('</body></html>');
    newWindow.document.close();
    
    // Auto initiate device printer frame
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
                  '<p class="mb-2 text-secondary fw-bold"><?php echo htmlspecialchars($resdata['message'] ?? 'PAN link status verified.'); ?></p>' +
                  '<hr class="my-2">' +
                  '<b>Retrieved Masked PAN:</b> <span class="text-danger fw-bold font-family-monospace text-uppercase ms-1 tracking-wide"><?php echo htmlspecialchars($resdata['pan'] ?? 'N/A'); ?></span>' +
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