<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 

// ================= INIT & FEE SETTINGS =================
$fee = 45.00; // Default Fee
$fee_query = mysqli_query($DarkXen_conn, "SELECT `ll_exam` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if(isset($fee_row['ll_exam']) &&$fee_row['ll_exam'] != '') {
        $fee = (float)$fee_row['ll_exam'];
    }
}

$error_message = '';$success_message = '';
$notice_msg = '';
$appliedby = $udata['phone'] ?? '';$date_time = date('Y-m-d H:i:s');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_btn'])) {
    
    // Live Wallet Fetch
    $chk_user = mysqli_query($DarkXen_conn, "SELECT `balance` FROM `users` WHERE `phone` = '$appliedby' LIMIT 1");
    $user_row = mysqli_fetch_assoc($chk_user);$wallet_amount = isset($user_row['balance']) ? (float)$user_row['balance'] : 0.00;

    $name = trim($_POST['name'] ?? '');
    $app_no = trim($_POST['app_no'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $state = trim($_POST['state'] ?? 'na');
    $password = trim($_POST['password'] ?? '');

    if (empty($app_no) || empty($password)) {$error_message = "कृपया सभी आवश्यक विवरण दर्ज करें!";
    } elseif ($wallet_amount <$fee) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
              <script>document.addEventListener('DOMContentLoaded', function() {
                  Swal.fire('Low Balance!', 'Please recharge now. Required: ₹" . $fee . "', 'error').then(() => { window.location='wallet.php'; });
              });</script>";
        exit;
    } else {
        // ================= API CONFIG =================
        $api_key = urlencode($DarkXen_apikey ?? '');
        $api_url_host =$DarkXen_url ?? 'darkxen.in';
        $url = "https://" . $api_url_host . "/api/v3/EXAM/ll_exam.php?apiKey=$api_key&name=".urlencode($name)."&app_no=".urlencode($app_no)."&dob=".urlencode($dob)."&state=".urlencode($state)."&password=".urlencode($password);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
        ));
        $response = curl_exec($curl);
        $curl_error = curl_error($curl);
        curl_close($curl);

        if ($curl_error) {$error_message = "API Connection Failed: Server Timeout.";
        } else {
            $resdata = json_decode($response, true) ?? [];

            if (isset($resdata['status']) &&$resdata['status'] === true) {
                
                mysqli_begin_transaction($DarkXen_conn);
                try {
                    // 1. Wallet Deduct
                    $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");
                    if (!$update_wallet) throw new Exception("Wallet Update Failed.");
                    
                    $new_balance = $wallet_amount -$fee;

                    // 2. Wallet History
                    $purpose = "LL Exam Pending: " . $app_no;
                    $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES (?, ?, ?, ?, '1', 'Debit')");
                    mysqli_stmt_bind_param($stmt_wallet, "sdds", $appliedby,$fee, $new_balance,$purpose);
                    if (!mysqli_stmt_execute($stmt_wallet)) throw new Exception("Wallet History Insert Failed.");

                    // 3. Save Request to DB as 'pending'
                    $req_id =$resdata['request_id'] ?? 'LLREQ_' . time();
                    
                    $insert_sql = "INSERT INTO `ll_exam_history` (`username`, `request_id`, `name`, `application_no`, `dob`, `state`, `password`, `charge`, `status`, `date`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)";
                    $stmt = mysqli_prepare($DarkXen_conn,$insert_sql);
                    
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "sssssssds", $appliedby, $req_id,$name, $app_no,$dob, $state,$password, $fee,$date_time);
                        if (!mysqli_stmt_execute($stmt)) throw new Exception("Database Insert Failed.");
                    } else {
                        throw new Exception("Prepare Failed.");
                    }

                    mysqli_commit($DarkXen_conn);$success_message = "Request Submitted Successfully!";
                    $notice_msg = $resdata['notice'] ?? "LL Exam process has started. Please wait up to 30 mins.";
                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);$error_message = "DB Error: " . $e->getMessage();
                }
            } else {
                $error_message =$resdata['message'] ?? 'Failed to submit request to government server.';
            }
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-100 shadow-none border">
                <div class="row gx-0 flex-between-center p-3">
                    <div class="col-sm-auto d-flex align-items-center">
                        <div class="icon-item bg-primary-subtle shadow-none me-3" style="width: 50px; height: 50px;">
                            <span class="fas fa-laptop-house text-primary fs-3"></span>
                        </div>
                        <div>
                            <h6 class="text-primary fs-10 mb-0">Transport Services</h6>
                            <h4 class="text-primary fw-bold mb-0">Learning License <span class="text-info fw-medium">Exam Clearance</span></h4>
                            <p class="mb-0 fs-11 text-muted">Clear LL Exam online remotely. <strong class="text-danger">Cost: ₹<?= number_format($fee, 2); ?></strong></p>
                        </div>
                    </div>
                    <div class="col-md-auto mt-3 mt-md-0">
                        <a href="ll_exam_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold">
                            <i class="fas fa-history me-1"></i> Check Live Status
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row justify-content-center mb-4">
        <div class="col-lg-6 col-12">
            <div class="card shadow-sm border-top border-4 border-primary">
                <div class="card-header bg-light border-bottom py-3">
                    <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-file-signature me-2"></i>Submit Application Details</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" id="submitForm" autocomplete="off">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">Application Number</label>
                                <input type="text" class="form-control fw-bold" name="app_no" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">Applicant Name</label>
                                <input type="text" class="form-control text-uppercase" name="name" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">Date of Birth (DD-MM-YYYY)</label>
                                <input type="text" class="form-control" name="dob" placeholder="e.g. 05-11-1999" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">State Code (Optional)</label>
                                <input type="text" class="form-control" name="state" placeholder="e.g. GJ, UP, NA" value="na">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small">LL Login Password</label>
                            <input type="text" class="form-control fw-bold text-danger font-monospace" name="password" required>
                        </div>
                        
                        <button class="btn btn-primary w-100 py-2 fw-bold shadow-none" type="submit" name="submit_btn">
                            <span class="fas fa-paper-plane me-2"></span>Submit Application (-₹<?= number_format($fee, 2); ?>)
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById('submitForm').addEventListener('submit', function() {
    Swal.fire({ title: 'Submitting Data...', text: 'Connecting to server...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
});

<?php if(!empty($error_message)): ?>
    Swal.fire({ icon: 'error', title: 'Failed', text: '<?= addslashes($error_message); ?>' });
<?php endif; ?>

<?php if(!empty($success_message)): ?>
    Swal.fire({ icon: 'success', title: 'Request Submitted!', text: '<?= addslashes($notice_msg); ?>' }).then(() => {
        window.location.href = "ll_exam_list.php";
    });
<?php endif; ?>
</script>

<?php include('footer.php'); ?>