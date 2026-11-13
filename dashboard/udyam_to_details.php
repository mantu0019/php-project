<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 

// ================= INIT & FEE SETTINGS =================
$fee = 10.00; 
$fee_query = mysqli_query($DarkXen_conn, "SELECT `udyam_detail_fee` FROM `service_free` WHERE `id` = 1 LIMIT 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if(isset($fee_row['udyam_detail_fee']) && $fee_row['udyam_detail_fee'] != '') {
        $fee = (float)$fee_row['udyam_detail_fee'];
    }
}

$resdata = [];
$error_message = '';
$success_message = '';
$appliedby = $udata['phone'] ?? '';
$date_time = date('Y-m-d H:i:s');

// ================= ACTION =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['find_btn'])) {
    
    $chk_user = mysqli_query($DarkXen_conn, "SELECT `balance` FROM `users` WHERE `phone` = '$appliedby' LIMIT 1");
    $user_row = mysqli_fetch_assoc($chk_user);
    $wallet_amount = isset($user_row['balance']) ? (float)$user_row['balance'] : 0.00;

    $udyam_number = strtoupper(trim($_POST['udyam_number'] ?? ''));
    
    if (!preg_match('/^UDYAM-[A-Z]{2}-[0-9]{2}-[0-9]{7}$/', $udyam_number)) {
        $error_message = "कृपया सही Udyam नंबर दर्ज करें (e.g. UDYAM-BR-30-0051916)";
    } elseif ($wallet_amount < $fee) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
              <script>document.addEventListener('DOMContentLoaded', function() {
                  Swal.fire('Low Balance!', 'Please recharge now.', 'error').then(() => { window.location='wallet.php'; });
              });</script>";
        exit;
    } else {
        // ================= API CONFIG =================
        $api_key = urlencode($DarkXen_apikey ?? '');
        $api_url_host = $DarkXen_url ?? 'darkxen.in';
        $url = "https://" . $api_url_host . "/api/v3/MSME/udhyam_no_to_deatils.php?apiKey=$api_key&udyam_number=" . urlencode($udyam_number);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 45,
        ));
        $response = curl_exec($curl);
        $curl_error = curl_error($curl);
        curl_close($curl);

        if ($curl_error) {
            $error_message = "API Connection Failed: Server Timeout.";
        } else {
            $resdata = json_decode($response, true) ?? [];

            if (isset($resdata['sampleCode']) && $resdata['sampleCode'] == "200" && isset($resdata['data'])) {
                
                $data = $resdata['data'];
                $ent_data = $data['enterprise_data'] ?? [];
                
                $app_no = $data['application_no'] ?? 'UDYM_' . time();
                $c_name = $ent_data['name'] ?? 'N/A';
                $e_type = $ent_data['enterprise_type'] ?? 'N/A';
                $o_type = $ent_data['organization_type'] ?? 'N/A';
                $mob = $ent_data['mobile'] ?? 'N/A';
                $mail = $ent_data['email'] ?? 'N/A';
                $inc_date = $ent_data['date_of_incorporation'] ?? 'N/A';
                
                // Save entire raw JSON data for perfectly detailed List printing later
                $full_json_data = json_encode($data, JSON_UNESCAPED_UNICODE);

                mysqli_begin_transaction($DarkXen_conn);
                try {
                    // 1. Wallet Deduct
                    $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");
                    if (!$update_wallet) throw new Exception("Wallet Update Failed.");
                    
                    // 2. Wallet History
                    $new_balance = $wallet_amount - $fee;
                    $purpose = "Udyam Details Find: " . $udyam_number;
                    $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES (?, ?, ?, ?, '1', 'Debit')");
                    mysqli_stmt_bind_param($stmt_wallet, "sdds", $appliedby, $fee, $new_balance, $purpose);
                    if (!mysqli_stmt_execute($stmt_wallet)) throw new Exception("Wallet History Insert Failed.");

                    // 3. Database Save with Full Response
                    $insert_sql = "INSERT INTO `udyam_detail_history` (`application_no`, `username`, `udyam_no`, `company_name`, `enterprise_type`, `org_type`, `mobile`, `email`, `inc_date`, `full_response`, `charge`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt_hist = mysqli_prepare($DarkXen_conn, $insert_sql);
                    
                    if ($stmt_hist) {
                        mysqli_stmt_bind_param($stmt_hist, "ssssssssssds", $app_no, $appliedby, $udyam_number, $c_name, $e_type, $o_type, $mob, $mail, $inc_date, $full_json_data, $fee, $date_time);
                        if (!mysqli_stmt_execute($stmt_hist)) throw new Exception("Failed to save record.");
                    } else {
                        throw new Exception("Prepare Failed: " . mysqli_error($DarkXen_conn));
                    }

                    mysqli_commit($DarkXen_conn);
                    $success_message = $resdata['message'] ?? "Enterprise Details Fetched Successfully!";
                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);
                    $error_message = "DB Error: " . $e->getMessage();
                    $resdata = []; 
                }
            } else {
                $error_message = $resdata['message'] ?? 'Details not found for this Udyam number.';
            }
        }
    }
}
?>

<!-- UI SECTION -->
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-100 shadow-none border">
                <div class="row gx-0 flex-between-center p-3">
                    <div class="col-sm-auto d-flex align-items-center">
                        <div class="icon-item bg-primary-subtle shadow-none me-3" style="width: 50px; height: 50px;">
                            <span class="fas fa-building text-primary fs-3"></span>
                        </div>
                        <div>
                            <h6 class="text-primary fs-10 mb-0">MSME Services</h6>
                            <h4 class="text-primary fw-bold mb-0">Udyam No to <span class="text-info fw-medium">All Details</span></h4>
                            <p class="mb-0 fs-11 text-muted">Retrieve detailed Enterprise records including NIC codes & Units. <strong class="text-danger">Cost: ₹<?= number_format($fee, 2); ?></strong></p>
                        </div>
                    </div>
                    <div class="col-md-auto mt-3 mt-md-0">
                        <a href="udyam_details_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-history me-1"></i> View Extracted History</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <!-- Search Form -->
        <div class="col-lg-4 col-12">
            <div class="card h-100 shadow-none border border-top border-4 border-primary">
                <div class="card-header bg-light border-bottom py-3">
                    <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-keyboard me-2"></i>Enter Udyam ID</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" id="searchForm" autocomplete="off">
                        <div class="mb-4">
                            <label class="form-label fw-bold text-800 small">Udyam Registration Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-200"><i class="fas fa-certificate text-secondary"></i></span>
                                <input type="text" class="form-control shadow-none border-200 fw-bold font-monospace text-uppercase" name="udyam_number" placeholder="UDYAM-BR-30-0000000" pattern="^UDYAM-[A-Za-z]{2}-[0-9]{2}-[0-9]{7}$" value="<?= htmlspecialchars($_POST['udyam_number'] ?? ''); ?>" required autofocus>
                            </div>
                        </div>
                        <button class="btn btn-primary w-100 py-2 fw-bold shadow-none" type="submit" name="find_btn">
                            <i class="fas fa-search me-2"></i> Extract Full Data (-₹<?= number_format($fee, 2); ?>)
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Live Server Response (ALL DATA) -->
        <div class="col-lg-8 col-12">
            <div class="card h-100 shadow-none border border-top border-4 border-success">
                <div class="card-header bg-light border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-success"><i class="fas fa-database me-2"></i>Extracted Enterprise Profile</h5>
                    <?php if (!empty($resdata) && isset($resdata['sampleCode']) && $resdata['sampleCode'] == "200"): ?>
                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Data Fully Loaded</span>
                    <?php endif; ?>
                </div>
                
                <div class="card-body p-0 d-flex flex-column bg-body-tertiary">
                    <?php if (!empty($resdata) && isset($resdata['sampleCode']) && $resdata['sampleCode'] == "200" && isset($resdata['data'])): 
                        $edata = $resdata['data']['enterprise_data'] ?? [];
                        $nic_data = $resdata['data']['nic_data'] ?? [];
                        $units = $resdata['data']['enterprise_units'] ?? [];
                        $classifications = $resdata['data']['enterprise_type_data'] ?? [];
                    ?>
                        <div class="p-4 scrollbar" style="max-height: 600px; overflow-y: auto;">
                            
                            <!-- 1. Header Information -->
                            <div class="d-flex align-items-center mb-4 pb-3 border-bottom border-secondary-subtle">
                                <div class="bg-success-subtle p-3 rounded me-3">
                                    <i class="fas fa-industry fs-3 text-success"></i>
                                </div>
                                <div>
                                    <h4 class="fw-bold text-primary text-uppercase mb-1"><?= htmlspecialchars($edata['name'] ?? 'N/A') ?></h4>
                                    <span class="badge bg-secondary me-2"><?= htmlspecialchars($edata['enterprise_type'] ?? 'N/A') ?> Enterprise</span>
                                    <span class="badge border border-info text-info"><?= htmlspecialchars($edata['organization_type'] ?? 'N/A') ?></span>
                                    <span class="badge bg-danger ms-2"><?= htmlspecialchars($edata['social_category'] ?? '') ?></span>
                                </div>
                            </div>
                            
                            <!-- 2. Primary Details -->
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fas fa-info-circle me-2"></i>Primary Details</h6>
                            <div class="row g-3 fs-10 mb-4 bg-white p-2 rounded shadow-sm border mx-0">
                                <div class="col-md-6">
                                    <span class="text-600 d-block small">Udyam Number</span>
                                    <strong class="text-success font-monospace"><?= htmlspecialchars($edata['document_id'] ?? 'N/A') ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-600 d-block small">Major Activity</span>
                                    <strong class="text-dark"><?= htmlspecialchars($edata['major_activity'] ?? 'N/A') ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-600 d-block small">Registration Date</span>
                                    <strong class="text-dark"><i class="fas fa-calendar-alt me-1 text-primary"></i> <?= htmlspecialchars($edata['date_of_udyam_registration'] ?? 'N/A') ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-600 d-block small">Commencement Date</span>
                                    <strong class="text-dark"><i class="fas fa-calendar-check me-1 text-primary"></i> <?= htmlspecialchars($edata['date_of_commencement'] ?? 'N/A') ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-600 d-block small">DIC Node</span>
                                    <strong class="text-dark"><?= htmlspecialchars($edata['dic'] ?? 'N/A') ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-600 d-block small">MSME DI</span>
                                    <strong class="text-dark"><?= htmlspecialchars($edata['msme_di'] ?? 'N/A') ?></strong>
                                </div>
                            </div>

                            <!-- 3. Contact Details -->
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fas fa-address-book me-2"></i>Contact & Address</h6>
                            <div class="row g-3 fs-10 mb-4 bg-white p-2 rounded shadow-sm border mx-0">
                                <div class="col-md-6">
                                    <span class="text-600 d-block small">Mobile</span>
                                    <strong class="text-dark font-monospace"><?= htmlspecialchars($edata['mobile'] ?? 'N/A') ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-600 d-block small">Email</span>
                                    <strong class="text-primary"><?= htmlspecialchars($edata['email'] ?? 'N/A') ?></strong>
                                </div>
                                <div class="col-12">
                                    <span class="text-600 d-block small">Full Address</span>
                                    <strong class="text-dark text-uppercase"><?= htmlspecialchars($edata['address'] ?? 'N/A') ?></strong>
                                </div>
                            </div>

                            <!-- 4. ALL NIC CODES DATA -->
                            <?php if(!empty($nic_data)): ?>
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fas fa-project-diagram me-2"></i>NIC Code Details</h6>
                            <div class="table-responsive bg-white rounded shadow-sm border mb-4">
                                <table class="table table-sm table-striped table-bordered align-middle fs-10 mb-0">
                                    <thead class="bg-200">
                                        <tr>
                                            <th>Activity Type</th>
                                            <th>NIC 2 Digit</th>
                                            <th>NIC 4 Digit</th>
                                            <th>NIC 5 Digit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($nic_data as $nic): ?>
                                        <tr>
                                            <td class="fw-bold text-primary"><?= htmlspecialchars($nic['activity_type'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($nic['nic_2_digit'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($nic['nic_4_digit'] ?? '') ?></td>
                                            <td class="fw-bold"><?= htmlspecialchars($nic['nic_5_digit'] ?? '') ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>

                            <!-- 5. ALL ENTERPRISE UNITS / BRANCHES -->
                            <?php if(!empty($units)): ?>
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fas fa-sitemap me-2"></i>Enterprise Units / Plants</h6>
                            <div class="table-responsive bg-white rounded shadow-sm border mb-4">
                                <table class="table table-sm table-striped table-bordered align-middle fs-10 mb-0">
                                    <thead class="bg-200">
                                        <tr>
                                            <th>Unit Name</th>
                                            <th>Address Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($units as $u): ?>
                                        <tr>
                                            <td class="fw-bold text-dark text-uppercase"><?= htmlspecialchars($u['name'] ?? '') ?></td>
                                            <td class="text-uppercase"><?= htmlspecialchars($u['address'] ?? '') ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>

                        </div>
                    <?php else: ?>
                        <!-- STANDBY -->
                        <div class="text-center text-muted p-5 my-auto">
                            <i class="fas fa-boxes fa-4x mb-3 text-300"></i>
                            <h6 class="fw-bold mb-1 text-700">Interface Standby</h6>
                            <p class="small text-muted mx-auto mb-0" style="max-width: 340px;">
                                Submit the Udyam Number. Complete A-to-Z enterprise details, NIC Codes and Branches will be parsed here.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById('searchForm').addEventListener('submit', function() {
    Swal.fire({
        title: 'Extracting Details...',
        text: 'Fetching MSME Data from Central Registry...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
});
<?php if(!empty($error_message)): ?>
    Swal.fire({ icon: 'error', title: 'Extraction Failed', text: '<?= addslashes($error_message); ?>', confirmButtonText: 'Try Again' });
<?php endif; ?>
<?php if(!empty($success_message)): ?>
    Swal.fire({ icon: 'success', title: 'Data Found!', text: '<?= addslashes($success_message); ?>', showConfirmButton: false, timer: 1500 });
<?php endif; ?>
</script>

<?php include('footer.php'); ?>