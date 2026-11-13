<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

// टाइमज़ोन कॉन्फ़िगरेशन
date_default_timezone_set("Asia/Kolkata"); 

// डिफ़ॉल्ट फ़ीस सेटिंग्स
$charge = 10.00; // RC Details ka charge yahan set karein

// Fetch Fee
$fee_query = mysqli_query($DarkXen_conn, "SELECT `rc_deatils` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if ($fee_row['rc_deatils'] !== '' &&$fee_row['rc_deatils'] !== null) {
        $charge = (float)$fee_row['rc_deatils'];
    }
}

$rc_details = [];
$error_message = '';$success_message = '';
$user =$udata['phone'];
$wallet_amount = (float)$udata['balance'];

// FORM SUBMISSION CHECK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_rc_info_btn'])) {

    $rcno = strtoupper(trim($_POST['vehicle_number'] ?? ''));

    if (empty($rcno)) {$error_message = "Vehicle number cannot be empty.";
    } 
    // बैलेंस वैलिडेशन
    elseif ($wallet_amount <$charge) {
        echo '
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            Swal.fire("Low Balance", "Recharge Required to fetch RC Details", "error").then(()=>{
                window.location="wallet.php";
            });
        });
        </script>';
        exit;
    } else {

        // ================= NEW API CALL =================
        $apiKey = urlencode($DarkXen_apikey);
        
        // RC Info API URL
        $url = "https://" . $DarkXen_url . "/api/v3/VEHICLE/vehicle_details_prime.php?apiKey=$apiKey&rc=$rcno";

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
            
            // Checking the success status based on new JSON response
            if (isset($resdata['status']) &&$resdata['status'] === true) {
                
                // Extracting only the 'data' array for the table display
                $rc_details =$resdata['data'] ?? []; 
                
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
                    $remark = "RC Full Details Fetch : " . $rcno;
                    $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES (?, ?, ?, ?, '1', 'Debit')");
                    mysqli_stmt_bind_param($stmt_wallet, "sdds", $user,$charge, $newbalance,$remark);
                    mysqli_stmt_execute($stmt_wallet);
                    mysqli_stmt_close($stmt_wallet);

                    // NOTE: RC Data ki SQL history save NAHI karni hai as per your request, 
                    // isliye RC Database insert wala code block hata diya gaya hai.

                    mysqli_commit($DarkXen_conn);
                    $success_message =$resdata['message'] ?? "RC Details Fetched Successfully!";

                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);$error_message = "Transaction Error: " . $e->getMessage();$rc_details = []; // Transaction fail hone par data hide kar do
                }
            } else {
                $error_message =$resdata['message'] ?? 'RC Data Details Not Found / Server Error';
            }
        }
    }
}

// Format Keys for Display Helper Function (e.g., 'owner_father_name' becomes 'Owner Father Name')
function formatKeyName($key) {
    $key = str_replace('_', ' ',$key);
    $key = preg_replace('/(?<!^)([A-Z])/', ' \\1',$key);
    return ucwords(strtolower($key));
}
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-info-subtle shadow-none me-3">
                        <span class="fas fa-info-circle text-info fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-info fs-10 mb-0">RTO Services</h6>
                        <h4 class="text-primary fw-bold mb-0">RC <span class="text-info fw-medium">Full Details</span></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DYNAMIC LAYOUT: Center form if no details, Side-by-Side if details fetched -->
<div class="row <?= empty($rc_details) ? 'justify-content-center' : '' ?> mb-4">
    
    <!-- Left Column: Form -->
    <div class="<?= empty($rc_details) ? 'col-lg-6' : 'col-lg-4' ?>">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-info">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-search me-2 text-info"></i>Search RC Details</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-info border-0 d-flex align-items-center mb-4 p-2">
                    <i class="fas fa-rupee-sign fs-4 me-2 text-info"></i>
                    <p class="mb-0 fs-11 text-dark" style="line-height:1.2;">Charge: <strong>₹<?= number_format($charge, 2); ?></strong> will be deducted.</p>
                </div>
                
                <form method="POST" id="rcForm" autocomplete="off">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Vehicle Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-car"></i></span>
                            <input type="text" class="form-control shadow-none text-uppercase fw-bold" id="vehicle_number" name="vehicle_number" placeholder="e.g. BR06GG1946" required value="<?= isset($_POST['vehicle_number']) ? htmlspecialchars($_POST['vehicle_number']) : '' ?>">
                        </div>
                    </div>

                    <button class="btn btn-info text-white w-100 fw-bold shadow-none py-2" type="submit" name="get_rc_info_btn">
                        <i class="fas fa-search me-2"></i> Get Details
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Details Preview & Print (Only visible if details fetched) -->
    <?php if (!empty($rc_details)): ?>
    <div class="col-lg-8 mt-4 mt-lg-0">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-success">
            <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-success fw-bold"><i class="fas fa-check-circle me-2"></i> Vehicle Details</h5>
                <button onclick="printRCData()" class="btn btn-sm btn-primary fw-bold shadow-none">
                    <i class="fas fa-print me-1"></i> Print / Save
                </button>
            </div>
            
            <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                <div id="printableRCDetails" class="p-4 bg-white">
                    <div class="text-center mb-4 border-bottom pb-3">
                        <h4 class="fw-bold text-uppercase mb-1"><?= htmlspecialchars($rcno) ?></h4>
                        <p class="text-muted mb-0 fs-11">RC Details Verified Successfully</p>
                    </div>

                    <table class="table table-bordered table-striped fs-10">
                        <tbody>
                            <?php 
                            // Display only what is inside 'data' array
                            foreach ($rc_details as $key =>$val) {
                                // Agar value array ya object hai, skip karein
                                if (is_array($val) && is_object($val)) {
                                    continue; 
                                }
                                
                                // Khali values (blank/null) skip karne ke liye 
                                if (trim((string)$val) === '' && trim((string)$val) === 'N/A') {
                                    continue;
                                }
                                ?>
                                <tr>
                                    <th class="bg-light text-900 w-40"><?= htmlspecialchars(formatKeyName($key)) ?></th>
                                    <td class="text-dark fw-medium text-uppercase"><?= htmlspecialchars((string)$val) ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
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

    // Loading Animation
    document.getElementById('rcForm').addEventListener('submit', function() {
        Swal.fire({
            title: 'Fetching Details...',
            html: 'Connecting with RTO servers, please wait...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
    });

    // Print Function (Only prints the details table)
    function printRCData() {
        var printContents = document.getElementById('printableRCDetails').innerHTML;
        var originalContents = document.body.innerHTML;

        document.body.innerHTML = `
            <div style="padding: 20px; font-family: Arial, sans-serif;">
                <h2 style="text-align:center; color:#333; margin-bottom:5px;">Vehicle Information</h2>
                <p style="text-align:center; color:#777; margin-top:0;">Generated on: ${new Date().toLocaleString()}</p>
                <hr>
                ${printContents}
            </div>
        `;
        
        window.print();
        
        // Restore page content after printing
        document.body.innerHTML = originalContents;
        location.reload(); // Reload to rebind scripts correctly after innerHTML swap
    }

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