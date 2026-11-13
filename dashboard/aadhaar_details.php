<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set('Asia/Kolkata');

// ================= INIT & FEE CONFIG =================
$fee = 0; // Default fee fallback
$fee_query = mysqli_query($DarkXen_conn, "SELECT `aadhaar_details` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if (!empty($fee_row['aadhaar_details'])) {
        $fee = floatval($fee_row['aadhaar_details']);
    }
}

if (!function_exists('clean')) {
    function clean($conn,$data) {
        return mysqli_real_escape_string($conn, trim($data));
    }
}

// --- DYNAMIC API CONFIGURATION ---
$DarkXen_apikey =$webdata['api_key'] ?? ''; 
$api_domain =$DarkXen_url; // Fallback to darkxen.in if URL not in DB

$resdata = [];$error_message = "";
$status_flag = "";
$uidNumber = "";

// Form processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['find_aadhaar_btn']) && isset($_POST['aadhar_number']))) {

    $uidNumber = clean($DarkXen_conn, $_POST['aadhar_number'] ?? '');$appliedby = clean($DarkXen_conn,$udata['phone'] ?? '');

    // Validation Check (12 numeric digits)
    if (!preg_match('/^[0-9]{12}$/', $uidNumber)) {$error_message = "Invalid Number format. Please enter a valid 12-digit number.";
        $status_flag = "error";
    } else {
        // Wallet Balance Check 
        if (($udata['balance'] ?? 0) >=$fee) {

            $debit_fee = $udata['balance'] -$fee;
            $date_time = date('Y-m-d H:i:s');

            // API Endpoint Configuration
            $url = "https://" . $api_domain . "/api/v3/AADHAR/aadhar-details.php?" . http_build_query([
                'apiKey' => $DarkXen_apikey,
                'uid'    => $uidNumber
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
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36',
            ));

            $response = curl_exec($curl);

            if (curl_errno($curl)) {$error_message = "Server Connection Failed: " . curl_error($curl);$status_flag = "error";
            }
            curl_close($curl);

            $resdata = json_decode($response, true);

            // API Response Validation
            if (isset($resdata['status']) && strtolower($resdata['status']) === "success") {

                $details =$resdata['data'] ?? [];

                $photo        = clean($DarkXen_conn, $details['photo'] ?? '');$name         = clean($DarkXen_conn,$details['name'] ?? '');
                $name_local   = clean($DarkXen_conn, $details['name_local'] ?? '');$gender       = clean($DarkXen_conn,$details['gender'] ?? '');
                $dob          = clean($DarkXen_conn, $details['dob'] ?? '');$state        = clean($DarkXen_conn,$details['state'] ?? ($resdata['found_in_state'] ?? ''));$district     = clean($DarkXen_conn,$details['district'] ?? '');
                $full_address = clean($DarkXen_conn, $details['full_address'] ?? '');$status_msg   = clean($DarkXen_conn,$resdata['message'] ?? 'Fetched Successfully');

                // AUTO-FIX: Create table if it doesn't exist to prevent Fatal Error
                $create_table_sql = "CREATE TABLE IF NOT EXISTS `aadhaar_details_history` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `user_phone` varchar(20) NOT NULL,
                    `aadhar_no` varchar(20) NOT NULL,
                    `photo` longtext DEFAULT NULL,
                    `name` varchar(150) DEFAULT NULL,
                    `gender` varchar(20) DEFAULT NULL,
                    `state` varchar(100) DEFAULT NULL,
                    `district` varchar(100) DEFAULT NULL,
                    `charge_debited` decimal(10,2) NOT NULL DEFAULT '0.00',
                    `status_msg` text DEFAULT NULL,
                    `created_at` datetime DEFAULT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
                mysqli_query($DarkXen_conn,$create_table_sql);

                // Start Transaction
                mysqli_begin_transaction($DarkXen_conn);

                // 1. Deduct Balance
                $update_user = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby' AND balance >=$fee");

                // 2. Insert Wallet Ledger Transaction
                $insert_wallet = mysqli_query($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) 
                    VALUES ('$appliedby', '$fee', '$debit_fee', 'AADHAAR DETAILS FETCH', '1', 'Debit')");

                // 3. Log into history table
                $insert_status = mysqli_query($DarkXen_conn, "INSERT INTO `aadhaar_details_history` (`user_phone`, `aadhar_no`, `photo`, `name`, `gender`, `state`, `district`, `charge_debited`, `status_msg`, `created_at`) 
                    VALUES ('$appliedby', '$uidNumber', '$photo', '$name', '$gender', '$state', '$district', '$fee', '$status_msg', '$date_time')");

                if ($update_user && $insert_wallet &&$insert_status) {
                    mysqli_commit($DarkXen_conn);$status_flag = "success";
                } else {
                    mysqli_rollback($DarkXen_conn);$error_message = "Database synchronization error. Transaction aborted.";
                    $status_flag = "error";
                }

            } else {
                $error_message = $resdata['message'] ?? 'Gateway returned an error response or identity mismatch.';$status_flag = "error";
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
                        <span class="fas fa-fingerprint text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Identity Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Aadhaar <span class="text-info fw-medium">Details Lookup</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-2 mt-md-0">
                    <a href="aadhaar_details_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card h-100 mb-4">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-search me-2"></i>Fetch Aadhaar Information</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-info border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-info"></i>
                    <p class="mb-0 fs-11">Current service charge: <strong>₹<?php echo number_format($fee, 2); ?></strong> will be debited upon successful record retrieval.</p>
                </div>

                <form method="POST" id="fetchDetailsForm">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Aadhaar Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-id-card"></i></span>
                            <input type="text" class="form-control shadow-none fw-bold fs-9" name="aadhar_number" placeholder="Enter 12-digit UID" maxlength="12" pattern="\d{12}" value="<?php echo htmlspecialchars($_POST['aadhar_number'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <button class="btn btn-primary w-100 fw-bold shadow-none py-2" type="submit" name="find_aadhaar_btn" onclick="triggerLoading()">
                        <i class="fas fa-search me-2"></i> Get Aadhaar Details
                    </button>
                </form>
             </div>
        </div>
    </div>

    <!-- Output Display Card -->
    <?php if (isset($resdata['status']) && strtolower($resdata['status']) === "success") { 
        $info =$resdata['data'] ?? [];
    ?>
    <div class="col-lg-8">
        <div class="card border shadow-none mb-4" id="printSection">
            <div class="card-header bg-primary d-flex justify-content-between align-items-center py-2">
                <h5 class="mb-0 text-white fs-10"><i class="fas fa-id-card-alt me-2"></i>Aadhaar Profile Statement</h5>
                <button onclick="printData()" class="btn btn-light btn-sm fw-bold shadow-none py-1 px-3 d-flex align-items-center gap-1">
                    <i class="fas fa-print me-1"></i> Print
                </button>
            </div>
            <div class="card-body p-4 bg-white">
                <div class="row mb-3">
                    <div class="col-md-3 text-center mb-3 mb-md-0">
                        <?php if (!empty($info['photo'])) { ?>
                            <!-- Handling pure base64 output -->
                            <img src="data:image/jpeg;base64,<?php echo htmlspecialchars(str_replace('data:image/jpeg;base64,', '', $info['photo'])); ?>" class="img-thumbnail rounded shadow-sm" style="max-height: 160px; width: 130px; object-fit: cover;" alt="Photo">
                        <?php } else { ?>
                            <div class="bg-light border rounded d-flex align-items-center justify-content-center" style="height: 160px; width: 130px; margin: auto;">
                                <span class="text-muted fs-11">No Photo</span>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="col-md-9">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0 fs-11 align-middle">
                                <tbody>
                                    <tr>
                                        <th width="35%" class="text-secondary fw-bold">Aadhaar Number</th>
                                        <td class="text-dark font-family-monospace fw-bold"><?php echo htmlspecialchars($info['aadhaar_number'] ?? $uidNumber); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-secondary fw-bold">Full Name</th>
                                        <td class="text-dark fw-bold"><?php echo htmlspecialchars($info['name'] ?? 'N/A'); ?> <?php if(!empty($info['name_local'])) { echo '('.htmlspecialchars($info['name_local']).')'; } ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-secondary fw-bold">Gender / DOB</th>
                                        <td class="text-dark"><?php echo htmlspecialchars($info['gender'] ?? 'N/A'); ?> / <?php echo htmlspecialchars(!empty($info['dob']) ?$info['dob'] : 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-secondary fw-bold">District & State</th>
                                        <td class="text-dark"><?php echo htmlspecialchars($info['district'] ?? 'N/A'); ?>, <?php echo htmlspecialchars($info['state'] ?? ($resdata['found_in_state'] ?? 'N/A')); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-secondary fw-bold">Full Address</th>
                                        <td class="text-dark"><?php echo htmlspecialchars($info['full_address'] ?? 'N/A'); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>
</div>

<!-- Scripts Module Initialization -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function triggerLoading() {
    if(document.getElementById('fetchDetailsForm').checkValidity()) {
        Swal.fire({
            title: 'Fetching Details...',
            text: 'Connecting to UIDAI secure servers, please wait.',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
    }
}

function printData() {
    var divToPrint = document.getElementById("printSection");
    if (!divToPrint) return;

    var newWindow = window.open("", "_blank");
    newWindow.document.write('<html><head><title>Print Aadhaar Statement</title>');
    newWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">');
    newWindow.document.write('<style>body{padding:30px;} .btn, button { display:none !important; } .card-header { background-color: #2c7be5 !important; color: white !important; }</style>');
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
            title: 'Details Retrieved',
            text: '<?php echo htmlspecialchars($resdata['message'] ?? 'Aadhaar details fetched successfully.'); ?>',
            confirmButtonColor: '#3085d6'
        });
    <?php } elseif ($status_flag === "error") { ?>
        Swal.fire({
            icon: 'error',
            title: 'Request Failed',
            text: '<?php echo addslashes($error_message); ?>',
            confirmButtonColor: '#d33'
        });
    <?php } elseif ($status_flag === "low_balance") { ?>
        Swal.fire({
            icon: 'warning',
            title: 'Insufficient Balance',
            text: 'Your current account balance is lower than the required service fee.',
            confirmButtonColor: '#f8bb86'
        });
    <?php } ?>
});
</script>

<?php include('footer.php'); ?>