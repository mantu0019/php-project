<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

// टाइमज़ोन सेटिंग्स
date_default_timezone_set("Asia/Kolkata"); 

// डिफ़ॉल्ट चार्ज सेट करें
$charge = 10.00; 

// Fetch Fee from Database
$fee_query = mysqli_query($DarkXen_conn, "SELECT `challan_cheak` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if (!empty($fee_row['challan_cheak'])) {
        $charge = (float)$fee_row['challan_cheak'];
    }
}

// हेल्पर फंक्शन: ISO डेट्स या इनवैलिड डेट्स को साफ़ करने के लिए
function formatApiDate($dateStr) {
    if (empty($dateStr) || strpos($dateStr, '0001-01-01') !== false || $dateStr === 'N/A') {
        return 'N/A';
    }
    $timestamp = strtotime($dateStr);
    return $timestamp ? date('d-m-Y h:i A', $timestamp) : $dateStr;
}

$rcData = [];
$error_message = '';
$success_message = '';
$user = $udata['phone'];
$wallet_amount = (float)$udata['balance'];

// ================= CHALLAN SEARCH SUBMISSION =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_btn'])) {

    $rcno = strtoupper(str_replace(' ', '', trim($_POST['vehicle_number'] ?? '')));

    if (empty($rcno)) {
        $error_message = "Vehicle number cannot be empty.";
    } 
    // ================= BALANCE CHECK =================
    elseif ($wallet_amount < $charge) {
        echo '
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            Swal.fire("Low Balance", "Recharge Required to check challan", "error").then(()=>{
                window.location="wallet.php";
            });
        });
        </script>';
        exit;
    } else {

        // ================= API CALL =================
        $apiKey = urlencode($DarkXen_apikey);
        $url = "https://" . $DarkXen_url . "/api/challan_details.php?token=$apiKey&rc_no=$rcno";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                "Accept: application/json"
            ]
        ]);

        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            $error_message = "Server Timeout or Connection Failed";
        } else {
            $resdata = json_decode($response, true);
            $status = $resdata['status'] ?? false;

            // ================= VALIDATE API RESPONSE =================
            if (!$status || empty($resdata['challans'])) {
                $error_message = $resdata['message'] ?? "No Challan Found for this Vehicle Number!";
            } else {

                $challanDetails = $resdata['challans'] ?? [];
                $owner_name = !empty($challanDetails[0]['accusedName']) ? $challanDetails[0]['accusedName'] : 'N/A';
                $state_code = !empty($challanDetails[0]['state']) ? $challanDetails[0]['state'] : 'N/A';

                // डेटा मैपिंग API रिस्पॉन्स के अनुसार
                $rcData = [
                    'rc_no'          => $resdata['rcno'] ?? $rcno,
                    'owner_name'     => $owner_name,
                    'state'          => $state_code,
                    'total_challans' => $resdata['total_challans'] ?? count($challanDetails),
                    'challans'       => $challanDetails
                ];

                // Database Transaction
                mysqli_begin_transaction($DarkXen_conn);
                try {
                    // 1. Deduct Balance
                    $newbalance = $wallet_amount - $charge;
                    $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance='$newbalance' WHERE phone='$user'");
                    
                    if (!$update_wallet) {
                        throw new Exception("Wallet balance deduction failed.");
                    }

                    // 2. Wallet History Log
                    $remark = "Vehicle Challan Check : " . $rcno;
                    $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES (?, ?, ?, ?, '1', 'Debit')");
                    mysqli_stmt_bind_param($stmt_wallet, "sdds", $user, $charge, $newbalance, $remark);
                    mysqli_stmt_execute($stmt_wallet);
                    mysqli_stmt_close($stmt_wallet);

                    // 3. Save to SQL (vehicle_challans Table)
                    $application_no = 'CH' . time();
                    $db_owner_name = mysqli_real_escape_string($DarkXen_conn, $rcData['owner_name']);
                    $total_challans_count = count($rcData['challans']);
                    $current_date = date('Y-m-d H:i:s');

                    $insert_sql = "INSERT INTO vehicle_challans (application_no, username, vehicle_no, owner_name, total_challans, date) 
                                   VALUES ('$application_no', '$user', '$rcno', '$db_owner_name', '$total_challans_count', '$current_date')";
                    $insert_res = mysqli_query($DarkXen_conn, $insert_sql);

                    if (!$insert_res) {
                        throw new Exception("Failed to insert record into database.");
                    }

                    mysqli_commit($DarkXen_conn);
                    $success_message = "Challan Details Fetched Successfully!";

                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);
                    $error_message = "Transaction Failed: " . $e->getMessage();
                    $rcData = []; 
                }
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
                    <div class="icon-item bg-info-subtle shadow-none me-3">
                        <span class="fas fa-car text-info fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-info fs-10 mb-0">RTO Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Vehicle Challan <span class="text-info fw-medium">Check</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="vehicle_challan_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History List</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm border-top border-4 border-info">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-file-invoice-dollar me-2"></i>Check Challan Details</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-info border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-info"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?= number_format($charge, 2); ?></strong> will be deducted only if challans are found.</p>
                </div>
                
                <form method="POST" id="challanForm">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Vehicle Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-car-side"></i></span>
                            <input type="text" class="form-control shadow-none text-uppercase fw-bold" id="vehicle_number" name="vehicle_number" placeholder="e.g. BR01HW6726" value="<?= htmlspecialchars($_POST['vehicle_number'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <button class="btn btn-info w-100 fw-bold shadow-none py-2 text-white" type="submit" name="verify_btn">
                        <i class="fas fa-search me-2"></i> Check Challan Details
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($rcData)): ?>
<div class="row justify-content-center mb-4">
    <div class="col-lg-12">
        <!-- Vehicle Overview Summary -->
        <div class="card shadow-sm border mb-3">
            <div class="card-header bg-primary text-white py-2">
                <h5 class="mb-0 text-white"><i class="fas fa-info-circle me-2"></i> Vehicle & Challan Summary</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped fs-10 mb-0">
                        <tbody>
                            <tr>
                                <th width="20%">Vehicle Number</th>
                                <td width="30%" class="fw-bold text-primary"><?= htmlspecialchars($rcData['rc_no']); ?></td>
                                <th width="20%">Accused / Owner Name</th>
                                <td width="30%" class="fw-bold"><?= htmlspecialchars($rcData['owner_name']); ?></td>
                            </tr>
                            <tr>
                                <th>State</th>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($rcData['state']); ?></span></td>
                                <th>Total Challans</th>
                                <td><span class="badge bg-danger fs-11"><?= htmlspecialchars($rcData['total_challans']); ?></span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Challans Detailed Table -->
        <div class="card shadow-sm border">
            <div class="card-header bg-dark text-white py-2 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-white"><i class="fas fa-exclamation-triangle text-warning me-2"></i> Challan History / Details (<?= count($rcData['challans']); ?>)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover fs-10 mb-0">
                        <thead class="bg-light">
                            <tr class="text-center align-middle">
                                <th>#</th>
                                <th>Challan No.</th>
                                <th>Date & Time</th>
                                <th>Accused Name</th>
                                <th>Offense Details</th>
                                <th>Challan Place</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $sn = 1;
                            foreach ($rcData['challans'] as $challan): 
                                $isPaid = !empty($challan['isAlreadyPaid']) || strcasecmp($challan['paymentStatus'] ?? '', 'DISPOSED') === 0 || strcasecmp($challan['challanStatus'] ?? '', 'Already Paid') === 0;
                                $badge_class = $isPaid ? 'bg-success' : 'bg-danger';
                                $status_text = $challan['challanStatus'] ?? ($challan['paymentStatus'] ?? 'Pending');
                            ?>
                                <tr class="align-middle">
                                    <td class="text-center"><?= $sn++; ?></td>
                                    <td class="fw-bold text-primary"><?= htmlspecialchars($challan['challanNumber'] ?? 'N/A'); ?></td>
                                    <td><?= htmlspecialchars(formatApiDate($challan['challanDate'] ?? '')); ?></td>
                                    <td><?= htmlspecialchars($challan['accusedName'] ?? 'N/A'); ?></td>
                                    <td class="text-wrap" style="max-width: 250px;"><?= htmlspecialchars($challan['offenseDetails'] ?? 'N/A'); ?></td>
                                    <td class="text-wrap" style="max-width: 200px;"><?= htmlspecialchars($challan['challanPlace'] ?? 'N/A'); ?></td>
                                    <td class="text-danger fw-bold text-center">₹<?= number_format((float)($challan['amount'] ?? 0), 2); ?></td>
                                    <td class="text-center">
                                        <span class="badge <?= $badge_class; ?>"><?= htmlspecialchars($status_text); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.getElementById('challanForm').addEventListener('submit', function() {
        Swal.fire({
            title: 'Fetching Challan...',
            html: 'Fetching data from RTO servers, please wait...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
    });

    <?php if(!empty($error_message)): ?>
        Swal.fire('Error', '<?= addslashes($error_message); ?>', 'error');
    <?php endif; ?>

    <?php if(!empty($success_message)): ?>
        Swal.fire('Success', '<?= addslashes($success_message); ?>', 'success');
    <?php endif; ?>
</script>

<?php include('footer.php'); ?>