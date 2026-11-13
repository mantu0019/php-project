<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set('Asia/Kolkata');

// Step 1: Dynamic Service Fee Configuration
$charge = 30.00; // Default fallback fee
$fee_query = mysqli_query($DarkXen_conn, "SELECT `VoterMobileLink` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if (!empty($fee_row['VoterMobileLink'])) {
        $charge = (float)$fee_row['VoterMobileLink'];
    }
}

$alert_message = '';$showReceipt = false;
$receiptData = [];

// Step 2: Low Balance Check before page rendering
$user_balance = (float)($udata['balance'] ?? 0);
if ($user_balance <$charge) {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'error',
            title: 'Low Balance',
            text: 'Please Recharge your Wallet. Required Balance: ₹" . number_format($charge, 2) . "',
            confirmButtonText: 'Recharge Now'
        }).then(() => {
            window.location = 'wallet.php';
        });
    });
    </script>";
    exit;
}

// Step 3: Handling Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['initiate_btn'])) {$epic_no = mysqli_real_escape_string($DarkXen_conn, strtoupper(trim($_POST['epic_no'])));
    $mobile_no = mysqli_real_escape_string($DarkXen_conn, trim($_POST['mobile_no']));$appliedby = mysqli_real_escape_string($DarkXen_conn,$udata['phone']);

    if ($user_balance >= $charge) {$api_key = urlencode($webdata['api_key'] ?? $DarkXen_apikey);
        $api_domain =$DarkXen_url;
        
        // NEW API Endpoint Integration
        $url = "https://" . $api_domain . "/api/v3/VOTER/vcmlink.php?apikey=$api_key&mobile=$mobile_no&epic=$epic_no";
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 45, // Increased timeout for reliability
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_SSL_VERIFYPEER => false
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {$alert_message = "Swal.fire('Error', 'API server unreachable. Please try again.', 'error');";
        } else {
            $resdata = json_decode($response, true);
            $status =$resdata['status'] ?? '';
            
            if ($status === "success") {
                $api_epic = mysqli_real_escape_string($DarkXen_conn, $resdata['epic'] ?? $epic_no);
                $api_mobile = mysqli_real_escape_string($DarkXen_conn, $resdata['mobile'] ?? $mobile_no);
                $ref_id = mysqli_real_escape_string($DarkXen_conn, $resdata['refId'] ?? 'N/A');$api_message = mysqli_real_escape_string($DarkXen_conn,$resdata['message'] ?? 'Processing Completed');
                
                // Transaction Processing
                mysqli_begin_transaction($DarkXen_conn);
                try {
                    $newbalance = $user_balance -$charge;
                    $date_now = date('Y-m-d H:i:s');$app_no = 'VML' . time(); // Auto-generate application number

                    // 1. Deduct User Wallet Amount (Safe Deduction)
                    $update_wallet = mysqli_query($DarkXen_conn, "UPDATE `users` SET `balance` = `balance` - $charge WHERE `phone` = '$appliedby' AND `balance` >= $charge");
                    if (mysqli_affected_rows($DarkXen_conn) == 0) {
                        throw new Exception("Insufficient Balance during transaction processing.");
                    }

                    // 2. Insert into Wallet Ledger history
                    $remark = "Voter Mobile Link: " . $api_epic;
                    $insert_wallet_log = mysqli_query($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) 
                                                                       VALUES ('$appliedby', '$charge', '$newbalance', '$remark', '1', 'Debit')");

                    // 3. Save into Voter Linking DB History
                    $insert_query = "INSERT INTO `voter_mobile_link_history` (`userid`, `epic_no`, `mobile_no`, `ref_id`, `application_no`, `charge`, `status`, `message`, `created_at`) 
                                     VALUES ('$appliedby', '$api_epic', '$api_mobile', '$ref_id', '$app_no', '$charge', 'Success', '$api_message', '$date_now')";
                    $insert_history = mysqli_query($DarkXen_conn,$insert_query);

                    if ($update_wallet && $insert_wallet_log &&$insert_history) {
                        mysqli_commit($DarkXen_conn);
                        
                        // Prepare Receipt Data
                        $showReceipt = true;
                        $receiptData = [
                            'epic' => $api_epic,
                            'mobile' => $api_mobile,
                            'ref_id' => $ref_id,
                            'app_no' => $app_no,
                            'message' => $api_message,
                            'charge' => $charge,
                            'date' => $date_now
                        ];
                        
                        $alert_message = "Swal.fire({ icon: 'success', title: 'Linking Successful!', text: 'Mobile number linked successfully.' });";
                    } else {
                        throw new Exception("Database sync failed. Transaction aborted.");
                    }
                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);
                    $alert_message = "Swal.fire('Failed', '" . addslashes($e->getMessage()) . "', 'error');";
                }
            } else {
                $error_msg = mysqli_real_escape_string($DarkXen_conn,$resdata['message'] ?? 'API transaction failed.');
                $alert_message = "Swal.fire('Failed', '$error_msg', 'error');";
            }
        }
    } else {
        $alert_message = "Swal.fire('Warning', 'Low balance detected. Please try again after wallet top-up.', 'warning');";
    }
}
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-success-subtle shadow-none me-3">
                        <span class="fas fa-link text-success fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-success fs-10 mb-0">Election Commission</h6>
                        <h4 class="text-primary fw-bold mb-0">Voter Mobile <span class="fw-medium">Link Instant</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-control form-control-sm bg-white border-200 shadow-sm px-3">
                            <span class="fas fa-wallet text-success me-2"></span>
                            <span class="fw-bold text-success">Balance: ₹<?php echo number_format((float)($udata['balance'] ?? 0), 2); ?></span>
                        </div>
                        <a href="voter_mobile_link_list.php" class="btn btn-sm btn-outline-success shadow-none fw-bold"><i class="fas fa-list me-1"></i> View Link History</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        
        <?php if (!$showReceipt): ?>
        <!-- FORM SECTION -->
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-success">
            <div class="card-header bg-body-tertiary border-bottom">
                <h5 class="mb-0 text-success fw-bold text-center"><i class="fas fa-sync-alt me-2"></i>EPIC Based Mobile Linking</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                
                <div class="alert alert-success border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-success"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?php echo number_format($charge, 2); ?></strong> will be deducted only on successful processing.</p>
                </div>
                
                <form method="POST" autocomplete="off" id="initiateForm" onsubmit="showLoader('Linking Mobile Number...')">
                    <div class="mb-3">
                        <label class="form-label fw-bold">EPIC Number (Voter ID) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control shadow-none text-uppercase fw-bold tracking-wide" name="epic_no" placeholder="e.g., CQR0841098" required autofocus>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Mobile Number to Link <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-phone-alt text-secondary"></i></span>
                            <input type="text" class="form-control shadow-none fw-bold text-dark" name="mobile_no" maxlength="10" pattern="\d{10}" placeholder="Enter 10-digit Mobile No." required>
                        </div>
                    </div>

                    <button type="submit" name="initiate_btn" class="btn btn-success w-100 fw-bold shadow-none py-2">
                        <i class="fas fa-paper-plane me-2"></i> Submit Link Request
                    </button>
                </form>

            </div>
        </div>
        <?php else: ?>
        
        <!-- RECEIPT SECTION -->
        <div class="card border-0 shadow-sm border-top border-4 border-primary">
            <div class="card-header bg-primary-subtle py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-check-circle me-2"></i>Transaction Successful</h5>
            </div>
            <div class="card-body p-4">
                
                <!-- Print Area -->
                <div id="receiptPrintArea" class="border rounded p-4 mb-4 bg-white shadow-sm">
                    <div class="text-center mb-4 border-bottom pb-3">
                        <h4 class="fw-bold text-dark mb-1">Voter Mobile Linking Receipt</h4>
                        <p class="text-muted fs-11 mb-0">Authorized Service Center</p>
                    </div>
                    
                    <table class="table table-borderless table-sm fs-10 mb-0">
                        <tbody>
                            <tr>
                                <th class="text-secondary" width="45%">Transaction ID:</th>
                                <td class="fw-bold text-dark text-end"><?= htmlspecialchars($receiptData['ref_id']) ?></td>
                            </tr>
                            <tr>
                                <th class="text-secondary">Application No:</th>
                                <td class="fw-bold text-dark text-end"><?= htmlspecialchars($receiptData['app_no']) ?></td>
                            </tr>
                            <tr>
                                <td colspan="2"><hr class="my-1"></td>
                            </tr>
                            <tr>
                                <th class="text-secondary">EPIC Number:</th>
                                <td class="fw-bold text-primary font-family-monospace text-uppercase fs-6 text-end tracking-wide"><?= htmlspecialchars($receiptData['epic']) ?></td>
                            </tr>
                            <tr>
                                <th class="text-secondary">Linked Mobile:</th>
                                <td class="fw-bold text-dark font-family-monospace fs-6 text-end"><?= htmlspecialchars($receiptData['mobile']) ?></td>
                            </tr>
                            <tr>
                                <th class="text-secondary">Status Message:</th>
                                <td class="fw-bold text-success text-end"><?= htmlspecialchars($receiptData['message']) ?></td>
                            </tr>
                            <tr>
                                <td colspan="2"><hr class="my-1"></td>
                            </tr>
                            <tr>
                                <th class="text-secondary">Amount Deducted:</th>
                                <td class="fw-bold text-danger text-end fs-6">₹<?= number_format($receiptData['charge'], 2) ?></td>
                            </tr>
                            <tr>
                                <th class="text-secondary">Date & Time:</th>
                                <td class="text-muted text-end"><?= date('d M Y, h:i A', strtotime($receiptData['date'])) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Action Buttons -->
                <div class="d-flex gap-2">
                    <button class="btn btn-primary w-50 fw-bold shadow-none" onclick="printReceipt('receiptPrintArea')">
                        <i class="fas fa-print me-2"></i> Print Receipt
                    </button>
                    <a href="voter_mobile_link.php" class="btn btn-light border w-50 fw-bold shadow-none text-dark">
                        <i class="fas fa-plus me-2"></i> Link New
                    </a>
                </div>
                
            </div>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function showLoader(txt) {
    Swal.fire({
        title: txt,
        html: 'Processing on remote server, please do not refresh...',
        allowOutsideClick: false,
        didOpen: () => { 
            Swal.showLoading(); 
        }
    });
}

function printReceipt(divId) {
    var printContent = document.getElementById(divId).innerHTML;
    var originalContent = document.body.innerHTML;
    
    var printWindow = window.open('', '', 'height=600,width=800');
    printWindow.document.write('<html><head><title>Print Receipt</title>');
    printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">');
    printWindow.document.write('<style>');
    printWindow.document.write('body { font-family: Arial, sans-serif; padding: 20px; }');
    printWindow.document.write('.text-end { text-align: right !important; }');
    printWindow.document.write('.table-borderless th, .table-borderless td { border: none; padding: 8px 0; }');
    printWindow.document.write('hr { margin: 10px 0; border-color: #ddd; }');
    printWindow.document.write('</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<div style="max-width: 400px; margin: 0 auto; border: 1px dashed #ccc; padding: 20px;">');
    printWindow.document.write(printContent);
    printWindow.document.write('</div>');
    printWindow.document.write('</body></html>');
    
    printWindow.document.close();
    printWindow.focus();
    
    // Allow CSS to load before printing
    setTimeout(function() {
        printWindow.print();
        printWindow.close();
    }, 500);
}

// Execute any PHP-injected SweetAlert scripts
<?php if(!empty($alert_message)) { echo$alert_message; } ?>
</script>

<?php include('footer.php'); ?>