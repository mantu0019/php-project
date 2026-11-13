<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 

// फीस सेटिंग्स
$fee = 5.00; 
$fee_query = mysqli_query($DarkXen_conn, "SELECT `number_info_fee` FROM `service_free` WHERE `id` = 1 LIMIT 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if(isset($fee_row['number_info_fee']) &&$fee_row['number_info_fee'] != '') {
        $fee = (float)$fee_row['number_info_fee'];
    }
}

$resdata = [];
$error_message = '';$success_message = '';
$appliedby =$udata['phone'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_number_btn'])) {
    
    // Fetch Live Wallet Balance
    $chk_user = mysqli_query($DarkXen_conn, "SELECT `balance` FROM `users` WHERE `phone` = '$appliedby' LIMIT 1");
    $user_row = mysqli_fetch_assoc($chk_user);$wallet_amount = isset($user_row['balance']) ? (float)$user_row['balance'] : 0.00;
    
    $mobile = trim($_POST['mobile_no'] ?? '');

    if (!preg_match('/^[0-9]{10}$/', $mobile)) {$error_message = "Invalid 10-digit Mobile Number!";
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
        $url = "https://" . $api_url_host . "/api/v3/mobile-to-detail-verification.php?apiKey=$api_key&mobile=$mobile";

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

        if ($curl_error) {$error_message = "API Server Connection Failed: Timeout";
        } else {
            $resdata = json_decode($response, true) ?? [];

            if (isset($resdata['status']) && $resdata['status'] === 'success' && !empty($resdata['results'])) {
                
                mysqli_begin_transaction($DarkXen_conn);
                try {
                    // 1. Wallet Update
                    $debit_fee = $wallet_amount -$fee;
                    $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");
                    if(!$update_wallet) throw new Exception("Wallet deduction failed.");
                    
                    // 2. Wallet History
                    $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES (?, ?, ?, 'Mobile Number Info Check', '1', 'Debit')");
                    mysqli_stmt_bind_param($stmt_wallet, "sdd", $appliedby, $fee,$debit_fee);
                    mysqli_stmt_execute($stmt_wallet);

                    // 3. Extract Full Data for Database (Taking 1st result)
                    $first_result = $resdata['results'][0];$app_no = 'NUM' . time();
                    $searched_mobile =$mobile;
                    $total = (int)($resdata['total'] ?? count($resdata['results']));$c_date = date('Y-m-d H:i:s');
                    
                    // Saari values dynamically API se li ja rahi hain
                    $found_name =$first_result['name'] ?? 'N/A';
                    $found_father_name =$first_result['father_name'] ?? 'N/A';
                    $found_address = preg_replace('/!+/', ', ',$first_result['address'] ?? 'N/A'); // Clean address
                    $found_alt_mobile =$first_result['alt_mobile'] ?? 'N/A';
                    $found_circle =$first_result['circle'] ?? 'N/A';
                    $found_id_number =$first_result['id_number'] ?? 'N/A';
                    $found_email =$first_result['email'] ?? 'N/A';

                    // 4. Save to Database using Prepared Statement
                    $insert_sql = "INSERT INTO `number_info_history` 
                        (`application_no`, `username`, `searched_mobile`, `total_found`, `found_name`, `found_father_name`, `found_address`, `found_alt_mobile`, `found_circle`, `found_id_number`, `found_email`, `charge`, `date`) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt_hist = mysqli_prepare($DarkXen_conn,$insert_sql);
                    if ($stmt_hist) {
                        mysqli_stmt_bind_param($stmt_hist, "sssisssssssds", $app_no, $appliedby,$searched_mobile, $total,$found_name, $found_father_name,$found_address, $found_alt_mobile,$found_circle, $found_id_number,$found_email, $fee,$c_date);
                        if(!mysqli_stmt_execute($stmt_hist)) throw new Exception("Failed to save history.");
                    } else {
                        throw new Exception("SQL Prepare Error: " . mysqli_error($DarkXen_conn));
                    }

                    mysqli_commit($DarkXen_conn);$success_message = "Records found successfully!";
                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);$error_message = "Database Error: " . $e->getMessage();$resdata = [];
                }
            } else {
                $error_message =$resdata['message'] ?? 'No records found for this number.';
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
                    <div class="icon-item bg-primary-subtle shadow-none me-3" style="width: 50px; height: 50px;">
                        <span class="fas fa-id-card text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Search Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Mobile <span class="text-info fw-medium">Ownership Info</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="number_info_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-history me-1"></i> View History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm border-top border-4 border-primary">
            <div class="card-header bg-light text-center border-bottom">
                <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-search me-2"></i>Enter Mobile Number to Search</h5>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info border-0 fs-11 d-flex align-items-center mb-4">
                    <i class="fas fa-info-circle fs-3 me-3 text-info"></i>
                    <p class="mb-0">Charge: <strong>₹<?= number_format($fee, 2); ?></strong> will be deducted per successful search.</p>
                </div>
                <form method="POST" id="numForm" autocomplete="off">
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-800">10-Digit Mobile Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white fw-bold">+91</span>
                            <input type="text" class="form-control fw-bold font-monospace shadow-none" name="mobile_no" placeholder="81460XXXXX" maxlength="10" value="<?= htmlspecialchars($_POST['mobile_no'] ?? ''); ?>" required autofocus>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100 fw-bold py-2 shadow-none" type="submit" name="search_number_btn">
                        <i class="fas fa-search me-2"></i> Search Records (-₹<?= number_format($fee, 2); ?>)
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($resdata['results'])): ?>
<div class="row justify-content-center mb-4">
    <div class="col-lg-10">
        <h5 class="mb-3 text-800"><i class="fas fa-database me-2 text-success"></i> Found <?= htmlspecialchars($resdata['total'] ?? count($resdata['results'])) ?> Record(s)</h5>
        <div class="row g-3">
            <?php foreach ($resdata['results'] as$row): 
                // Address cleaning (Replace multiple ! or !! with a single comma and space)
                $clean_address = preg_replace('/!+/', ', ',$row['address'] ?? '');
            ?>
            <div class="col-md-6">
                <div class="card border shadow-sm h-100 border-top border-3 border-success">
                    <div class="card-body fs-10">
                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                            <div class="avatar avatar-2xl me-3 bg-primary-subtle rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <span class="fs-3 fw-bold text-primary"><?= strtoupper(substr($row['name'] ?? 'U', 0, 1)) ?></span>
                            </div>
                            <div>
                                <h5 class="mb-0 text-primary fw-bold text-uppercase"><?= htmlspecialchars($row['name'] ?? 'N/A') ?></h5>
                                <p class="mb-0 small text-600 fw-bold"><i class="fas fa-broadcast-tower me-1 text-success"></i> <?= htmlspecialchars($row['circle'] ?? 'N/A') ?></p>
                            </div>
                        </div>
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <th class="ps-0 text-700" width="35%">Father's Name:</th>
                                <td class="text-900 fw-bold text-uppercase"><?= htmlspecialchars($row['father_name'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <th class="ps-0 text-700">Mobile Number:</th>
                                <td class="text-success fw-bold font-monospace fs-11"><?= htmlspecialchars($row['mobile'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <th class="ps-0 text-700">Alt Mobile:</th>
                                <td class="text-900 font-monospace"><?= htmlspecialchars($row['alt_mobile'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <th class="ps-0 text-700">Email:</th>
                                <td class="text-900"><?= htmlspecialchars($row['email'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <th class="ps-0 text-700">ID Ref:</th>
                                <!-- Poora ID number fetch hokar yahan show hoga -->
                                <td class="text-900 fw-bold font-monospace"><?= htmlspecialchars($row['id_number'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <th class="ps-0 text-700 align-top">Address:</th>
                                <td class="text-800 small text-uppercase" style="line-height: 1.5;"><?= htmlspecialchars($clean_address) ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.getElementById('numForm').addEventListener('submit', function() {
        Swal.fire({ title: 'Searching Records...', html: 'Fetching KYC data from registry. Please wait...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
    });
    <?php if(!empty($error_message)) echo "Swal.fire('Error', " . json_encode($error_message) . ", 'error');"; ?>
    <?php if(!empty($success_message)) echo "Swal.fire('Success', " . json_encode($success_message) . ", 'success');"; ?>
</script>

<?php include('footer.php'); ?>