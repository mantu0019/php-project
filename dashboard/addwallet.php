<?php
include('../includes/session.php');
include('../includes/config.php');

$phone = $_SESSION['phone'];

// 🔒 1. CSRF TOKEN GENERATION
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ✅ FETCH GATEWAY KEY & MIN AMOUNT FROM SETTINGS
$setting_query = mysqli_query($DarkXen_conn, "SELECT geteway_key, min_amount FROM settings ORDER BY id ASC LIMIT 1");
$setting_data = mysqli_fetch_assoc($setting_query);
$gateway_token = isset($setting_data['geteway_key']) ? $setting_data['geteway_key'] : '';
// अगर DB में min_amount सेट है तो वो लेगा, वरना डिफ़ॉल्ट 50 लेगा
$min_amount = (isset($setting_data['min_amount']) && $setting_data['min_amount'] > 0) ? (int)$setting_data['min_amount'] : 50; 

// 🛡️️ 2. SECURE HIDDEN IFRAME HANDLER (For Background Gateway Verification)
if(isset($_GET['secure_frame']) && isset($_SESSION['secure_gate_url'])) {
    header("Location: " . $_SESSION['secure_gate_url']);
    exit;
}

// 🛡️ 3. ULTRA-SECURE AJAX POLLING
if(isset($_POST['secure_ping']) && $_POST['secure_ping'] == '1'){
    ob_clean(); 
    header('Content-Type: application/json');
    
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['status' => 'error', 'msg' => 'Invalid Token']);
        exit;
    }

    if(!isset($_SESSION['active_txn_id'])) {
        echo json_encode(['status' => 'error']);
        exit;
    }

    $order_id = $_SESSION['active_txn_id'];
    $check = mysqli_query($DarkXen_conn, "SELECT * FROM wallet WHERE txn_id='$order_id'");
    $row = mysqli_fetch_assoc($check);

    if($row && $row['status'] == "pending"){
        $url = base64_decode("aHR0cHM6Ly9kYXJreGVuZ2V0ZXdheS5zaG9wL2FwaS9jaGVjay1vcmRlci1zdGF0dXM=");
        $postData = array("user_token" => $gateway_token, "order_id" => $order_id);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);

        $verifyResult = json_decode($response, true);

        if(isset($verifyResult["status"]) && $verifyResult["status"] == "COMPLETED"){
            $status = isset($verifyResult["result"]["status"]) ? $verifyResult["result"]["status"] : "";
            $amount = isset($verifyResult["result"]["amount"]) ? $verifyResult["result"]["amount"] : 0;
            $utr = isset($verifyResult["result"]["utr"]) ? $verifyResult["result"]["utr"] : "";

            if($status == "SUCCESS"){
                mysqli_query($DarkXen_conn, "UPDATE users SET balance=balance+$amount WHERE phone='$phone'");
                mysqli_query($DarkXen_conn, "UPDATE wallet SET status='success', BANKTXNID='$utr' WHERE txn_id='$order_id'");
                unset($_SESSION['active_txn_id']);
                echo json_encode(['status' => 'success', 'amount' => $amount]);
                exit;
            } else if($status == "FAILURE" || $status == "AMOUNT_MISMATCH"){
                mysqli_query($DarkXen_conn, "UPDATE wallet SET status='failed' WHERE txn_id='$order_id'");
                unset($_SESSION['active_txn_id']);
                echo json_encode(['status' => 'failed']);
                exit;
            }
        }
        echo json_encode(['status' => 'pending']);
        exit;
    } else if ($row && $row['status'] == "success") {
        unset($_SESSION['active_txn_id']);
        echo json_encode(['status' => 'success', 'amount' => $row['amount']]);
        exit;
    }
    echo json_encode(['status' => 'error']);
    exit;
}

include('header.php');

$show_qr_inline = false;
$upi_intent_url = "";
$generated_amount = 0;

// 💸 4. PROCESS SECURE ADD MONEY REQUEST
if(isset($_POST['amount']) && isset($_POST['csrf_token'])){
    
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo "<script>alert('Security Verification Failed!'); window.location.href='addwallet.php';</script>";
        exit;
    }

    $amount = intval($_POST['amount']);
    
    // यहाँ डेटाबेस से आई $min_amount से चेक हो रहा है
    if($amount < $min_amount){
        echo "<script>alert('Minimum amount is ₹" . $min_amount . "');</script>";
    } else if(empty($gateway_token)){
        echo "<script>alert('Gateway Config Error! geteway_key is missing.');</script>";
    } else {
        $ORDER_ID = "TXN" . time() . rand(1000,9999);
        
        $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $redirect_uri = $protocol . $_SERVER['HTTP_HOST'] . explode('?', $_SERVER['REQUEST_URI'], 2)[0];

        $data = array(
            "customer_mobile" => $phone,
            "user_token" => $gateway_token,
            "amount" => $amount,
            "order_id" => $ORDER_ID,
            "redirect_url" => $redirect_uri
        );

        $apiUrl = base64_decode("aHR0cHM6Ly9kYXJreGVuZ2V0ZXdheS5zaG9wL2FwaS9jcmVhdGUtb3JkZXI=");
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $apiResult = json_decode($response, true);

        if(isset($apiResult['status']) && $apiResult['status'] === true){
            
            $payment_url = $apiResult['result']['payment_url'];
            
            $ch2 = curl_init($payment_url);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
            $html_response = curl_exec($ch2);
            curl_close($ch2);
            
            if (preg_match('/(upi:\/\/pay\?[^"\']+)/i', $html_response, $matches)) {
                $upi_intent_url = html_entity_decode($matches[1]);
            }

            if(!empty($upi_intent_url)) {
                $_SESSION['active_txn_id'] = $ORDER_ID;
                $_SESSION['secure_gate_url'] = $payment_url;
                $generated_amount = $amount;
                
                mysqli_query($DarkXen_conn,"INSERT INTO wallet(phone,amount,txn_id,status) VALUES('$phone','$amount','$ORDER_ID','pending')");
                $show_qr_inline = true; 
            } else {
                echo "<script>alert('Internal Secure Routing Failed.');</script>";
            }
        } else {
            echo "<script>alert('Service Unavailable. Buy Your darkxengeteway Plan .');</script>";
        }
    }
}
?>

<style>
/* 🎨 EXISTING THEME CSS */
.fade-in-up { animation: fadeInUp 0.4s ease-out; }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

/* 🚀 NEW PREMIUM QR STANDEE CSS (Paytm/PhonePe Style) */
.qr-standee-container { max-width: 380px; margin: 0 auto; background: #ffffff; border-radius: 24px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12), 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; overflow: hidden; position: relative; }
.standee-header { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff; padding: 20px 15px; text-align: center; border-bottom: 4px solid #3b82f6; }
.standee-title { font-size: 18px; font-weight: 800; letter-spacing: 0.5px; margin: 0; text-transform: uppercase; }
.standee-subtitle { font-size: 12px; font-weight: 500; color: #94a3b8; margin-top: 5px; }
.standee-body { padding: 25px 20px 30px; text-align: center; background-image: radial-gradient(#e2e8f0 1px, transparent 1px); background-size: 20px 20px; background-position: center; }
.standee-amount { font-size: 42px; font-weight: 900; color: #0f172a; line-height: 1; margin-bottom: 20px; text-shadow: 2px 2px 0px #ffffff; }
.standee-amount span { font-size: 24px; vertical-align: super; color: #64748b; }
.qr-scan-box { position: relative; display: inline-block; padding: 15px; background: #ffffff; border-radius: 16px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); margin-bottom: 20px; }
.qr-scan-box img { width: 220px; height: 220px; display: block; border-radius: 10px; }
.scan-corner { position: absolute; width: 30px; height: 30px; border-color: #3b82f6; border-style: solid; }
.corner-tl { top: -2px; left: -2px; border-width: 4px 0 0 4px; border-top-left-radius: 16px; }
.corner-tr { top: -2px; right: -2px; border-width: 4px 4px 0 0; border-top-right-radius: 16px; }
.corner-bl { bottom: -2px; left: -2px; border-width: 0 0 4px 4px; border-bottom-left-radius: 16px; }
.corner-br { bottom: -2px; right: -2px; border-width: 0 4px 4px 0; border-bottom-right-radius: 16px; }
.laser-line { position: absolute; top: 10px; left: 5%; width: 90%; height: 3px; background: #22c55e; box-shadow: 0 0 10px #22c55e, 0 0 20px #22c55e; animation: scanAnimation 2.5s infinite linear alternate; border-radius: 50%; z-index: 10; }
@keyframes scanAnimation { 0% { top: 15px; opacity: 0; } 10% { opacity: 1; } 90% { opacity: 1; } 100% { top: calc(100% - 15px); opacity: 0; } }
.live-status { display: inline-flex; align-items: center; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 8px 18px; border-radius: 50px; color: #15803d; font-size: 14px; font-weight: 700; box-shadow: 0 2px 10px rgba(34, 197, 94, 0.1); }
.pulsing-dot { width: 10px; height: 10px; background-color: #22c55e; border-radius: 50%; margin-right: 8px; animation: pulse 1.5s infinite; }
@keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(34,197,94, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(34,197,94, 0); } 100% { box-shadow: 0 0 0 0 rgba(34,197,94, 0); } }
.standee-footer { background: #f8fafc; padding: 15px; border-top: 1px dashed #cbd5e1; text-align: center; }
.footer-title { font-size: 11px; font-weight: 700; color: #64748b; letter-spacing: 1px; margin-bottom: 10px; display: block; }
.upi-brands { display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; }
.brand-badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 800; font-family: Arial, sans-serif; letter-spacing: -0.2px; }
.bg-phonepe { background: #5f259f; color: #fff; }
.bg-gpay { background: #fff; color: #3c4043; border: 1px solid #dadce0; }
.bg-gpay span:nth-child(1) { color: #4285f4; } .bg-gpay span:nth-child(2) { color: #ea4335; } .bg-gpay span:nth-child(3) { color: #fbbc04; } .bg-gpay span:nth-child(4) { color: #34a853; }
.bg-paytm { background: #002970; color: #00baf2; }
.bg-bhim { background: #fff; color: #000; border: 1px solid #e2e8f0; border-bottom: 3px solid #ff7a00; border-top: 3px solid #00a400; }
.mobile-pay-btn { background: #0f172a; color: #fff; font-weight: 700; border-radius: 12px; padding: 12px; margin-top: 15px; transition: 0.3s; }
.mobile-pay-btn:hover { background: #1e293b; color: #fff; transform: scale(0.98); }
</style>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-wallet text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Current Balance</h6>
                        <h4 class="text-primary fw-bold mb-0">₹<?= round($udata['balance'], 2) ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- DYNAMIC LEFT COLUMN: FORM OR QR -->
    <div class="col-lg-6">
        
        <?php if(!$show_qr_inline): ?>
        <div class="card shadow-sm border-0 h-100 border-top border-4 border-primary fade-in-up">
            <div class="card-header bg-light border-bottom text-center">
                <h5 class="mb-0 fw-bold"><i class="fas fa-bolt me-2 text-warning"></i>Instant Wallet TopUp</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <form method="POST" autocomplete="off" onsubmit="showProcessing()">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="mb-4">
                        <label class="form-label fs-10 text-900 fw-bold">Enter Amount (₹)</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-white text-900 fw-bold">₹</span>
                            <!-- HTML में min="50" की जगह $min_amount कर दिया गया है -->
                            <input type="number" class="form-control shadow-sm fw-bold text-primary" placeholder="Min. ₹<?php echo $min_amount; ?>" name="amount" min="<?php echo $min_amount; ?>" max="5000" required>
                        </div>
                        <div class="form-text text-danger mt-1 fs-12 fw-semi-bold"><i class="fas fa-info-circle"></i> Minimum deposit amount is ₹<?php echo $min_amount; ?></div>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg fw-semi-bold shadow-sm" style="border-radius: 12px;">
                            <i class="fas fa-qrcode me-2"></i>Generate QR & Pay
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php else: ?>
        <!-- 🚀 NEW PREMIUM STANDEE UI -->
        <div class="fade-in-up h-100 d-flex align-items-center justify-content-center pt-2 pb-4">
            
            <div class="qr-standee-container w-100">
                <!-- Header -->
                <div class="standee-header">
                    <h4 class="standee-title"><i class="fas fa-shield-check me-2"></i>Scan & Pay</h4>
                    <div class="standee-subtitle">Secured UPI Payment Network</div>
                </div>

                <!-- Body -->
                <div class="standee-body">
                    <div class="standee-amount">
                        <span>₹</span><?php echo $generated_amount; ?>
                    </div>

                    <!-- Animated QR Box -->
                    <div class="qr-scan-box">
                        <span class="scan-corner corner-tl"></span>
                        <span class="scan-corner corner-tr"></span>
                        <span class="scan-corner corner-bl"></span>
                        <span class="scan-corner corner-br"></span>
                        
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=0&color=0f172a&data=<?php echo urlencode($upi_intent_url); ?>" alt="Payment QR">
                        <div class="laser-line"></div>
                    </div>

                    <!-- Live Waiting Status -->
                    <div class="live-status mt-2">
                        <div class="pulsing-dot"></div>
                        Waiting for payment...
                    </div>

                    <!-- Mobile Only Direct Pay Button -->
                    <a href="<?php echo $upi_intent_url; ?>" class="btn mobile-pay-btn w-100 d-md-none">
                        <i class="fas fa-paper-plane me-2"></i> Pay Directly via UPI App
                    </a>
                    
                    <div class="mt-3">
                        <a href="addwallet.php" class="text-danger fw-bold fs-12 text-decoration-none"><i class="fas fa-times-circle me-1"></i> Cancel Payment</a>
                    </div>
                </div>

                <!-- Footer / Accepted Brands -->
                <div class="standee-footer">
                    <span class="footer-title">100% SECURE & ACCEPTED HERE</span>
                    <div class="upi-brands">
                        <span class="brand-badge bg-phonepe">पे PhonePe</span>
                        <span class="brand-badge bg-gpay"><span>G</span><span>P</span><span>a</span><span>y</span></span>
                        <span class="brand-badge bg-paytm">Paytm</span>
                        <span class="brand-badge bg-bhim">BHIM UPI</span>
                    </div>
                </div>
            </div>

            <!-- 🛡️ HIDDEN PROXY FRAME -->
            <iframe src="?secure_frame=1" style="display:none;" sandbox="allow-scripts allow-same-origin"></iframe>
        </div>
        <?php endif; ?>
        
    </div>

    <!-- TRANSACTIONS TABLE -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100 border-top border-4 border-info">
            <div class="card-header bg-light border-bottom">
                <h6 class="mb-0 fw-bold"><i class="fas fa-history me-2 text-info"></i>Recent Transactions</h6>
            </div>
            <div class="card-body p-0 bg-white">
                <div class="table-responsive scrollbar">
                    <table class="table table-sm table-striped table-hover fs-10 mb-0">
                        <thead class="bg-200 text-900">
                            <tr>
                                <th class="ps-3">Date</th>
                                <th>Amount</th>
                                <th>Txn Hash</th>
                                <th class="text-end pe-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $res = mysqli_query($DarkXen_conn,"SELECT * FROM wallet WHERE phone='$phone' ORDER BY id DESC LIMIT 15");
                        if(mysqli_num_rows($res) > 0) {
                            while($data = mysqli_fetch_assoc($res)){
                                if ($data['status'] == "success") {
                                    $badge_class = "badge-subtle-success text-success";
                                    $status_text = "Success";
                                } elseif ($data['status'] == "pending") {
                                    $badge_class = "badge-subtle-warning text-warning"; 
                                    $status_text = "Pending";
                                } else {
                                    $badge_class = "badge-subtle-danger text-danger";
                                    $status_text = "Failed";
                                }
                        ?>
                            <tr>
                                <td class="ps-3 py-2 text-600"><?php echo $data['txn_date'] ?? date('Y-m-d'); ?></td>
                                <td class="fw-bold text-success">₹<?php echo $data['amount']; ?></td>
                                <td class="text-500 font-monospace"><?php echo strtoupper($data['txn_id']); ?></td>
                                <td class="text-end pe-3">
                                   <span class="badge <?php echo $badge_class; ?>"><?php echo $status_text; ?></span>
                                </td>
                            </tr>
                        <?php 
                            } 
                        } else {
                            echo "<tr><td colspan='4' class='text-center py-3 text-muted'>No records found.</td></tr>";
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// 🛡️ ANTI-INSPECT SECURITY SHIELD
document.addEventListener('contextmenu', event => event.preventDefault());
document.onkeydown = function(e) {
    if(e.keyCode == 123) return false; 
    if(e.ctrlKey && e.shiftKey && (e.keyCode == 'I'.charCodeAt(0) || e.keyCode == 'C'.charCodeAt(0) || e.keyCode == 'J'.charCodeAt(0))) return false;
    if(e.ctrlKey && e.keyCode == 'U'.charCodeAt(0)) return false; 
};

function showProcessing() {
    Swal.fire({
        title: 'Generating QR...',
        text: 'Fetching details securely.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
}

// 🛡️ BLIND AJAX POLLING (Checking every 3 sec)
<?php if(isset($_SESSION['active_txn_id'])): ?>
let _cInt = setInterval(function() {
    $.ajax({
        url: window.location.href, 
        type: 'POST',
        data: {
            secure_ping: '1',
            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
        },
        dataType: 'json',
        success: function(res) {
            if(res.status === 'success') {
                clearInterval(_cInt);
                Swal.fire({
                    title: 'Payment Successful!',
                    html: 'Wallet credited with <b>₹' + res.amount + '</b>.',
                    icon: 'success',
                    confirmButtonColor: '#22c55e',
                    allowOutsideClick: false
                }).then(() => {
                    window.location.href = 'addwallet.php'; 
                });
            } else if(res.status === 'failed' || res.status === 'error') {
                clearInterval(_cInt);
                Swal.fire('Declined', 'Transaction failed or altered.', 'error').then(() => {
                    window.location.href = 'addwallet.php';
                });
            }
        }
    });
}, 3000);
<?php endif; ?>
</script>

<?php include('footer.php'); ?>