<?php
session_start();
include('../includes/config.php');

if (isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

// 🔒 CSRF TOKEN GENERATION
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ✅ FETCH ALL SETTINGS FROM DB
$set_q = mysqli_query($DarkXen_conn, "SELECT * FROM settings WHERE id='1' LIMIT 1");
$set_d = mysqli_fetch_assoc($set_q);

$webname = $set_d['webname'] ?? 'Portal';
$registration_status = (int)($set_d['registration_status'] ?? 1); // 1 = ON, 0 = OFF
$gateway_status = (int)($set_d['reg_gateway_status'] ?? 0); // 1 = Paid, 0 = Free
$gateway_token = $set_d['geteway_key'] ?? '';

// Role based fees
$fees = [
    'Retailer' => (int)($set_d['retailer_fee'] ?? 99),
    'Distributor' => (int)($set_d['distributor_fee'] ?? 199),
    'Super Distributor' => (int)($set_d['super_distributor_fee'] ?? 299)
];

// 🛡️ SECURE HIDDEN IFRAME HANDLER (For Background Gateway)
if(isset($_GET['secure_frame']) && isset($_SESSION['reg_proxy_url'])) {
    header("Location: " . $_SESSION['reg_proxy_url']);
    exit;
}

// 🔄 AJAX POLLING: Check Payment Status
if(isset($_POST['action']) && $_POST['action'] == 'check_payment') {
    ob_clean(); header('Content-Type: application/json');
    
    if(!isset($_SESSION['reg_active_txn']) || !isset($_SESSION['reg_phone'])) {
        echo json_encode(['status' => 'error']); exit;
    }

    $order_id = $_SESSION['reg_active_txn'];
    $phone = $_SESSION['reg_phone'];

    $check = mysqli_query($DarkXen_conn, "SELECT * FROM wallet WHERE txn_id='$order_id'");
    $row = mysqli_fetch_assoc($check);

    if($row && $row['status'] == "pending") {
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

        if(isset($verifyResult["status"]) && $verifyResult["status"] == "COMPLETED") {
            $status = $verifyResult["result"]["status"] ?? "";
            $utr = $verifyResult["result"]["utr"] ?? "";

            if($status == "SUCCESS"){
                // Update User Status to Active (1)
                mysqli_query($DarkXen_conn, "UPDATE users SET status='1' WHERE phone='$phone'");
                // Mark Wallet TXN as success
                mysqli_query($DarkXen_conn, "UPDATE wallet SET status='success', BANKTXNID='$utr' WHERE txn_id='$order_id'");
                
                unset($_SESSION['reg_active_txn'], $_SESSION['reg_phone'], $_SESSION['reg_proxy_url']);
                echo json_encode(['status' => 'success']);
                exit;
            } else if($status == "FAILURE" || $status == "AMOUNT_MISMATCH"){
                // Delete the inactive user so they can try again
                mysqli_query($DarkXen_conn, "DELETE FROM users WHERE phone='$phone' AND status='0'");
                mysqli_query($DarkXen_conn, "UPDATE wallet SET status='failed' WHERE txn_id='$order_id'");
                
                unset($_SESSION['reg_active_txn'], $_SESSION['reg_phone'], $_SESSION['reg_proxy_url']);
                echo json_encode(['status' => 'failed']);
                exit;
            }
        }
        echo json_encode(['status' => 'pending']);
        exit;
    }
    echo json_encode(['status' => 'error']);
    exit;
}

// 📝 AJAX FORM SUBMISSION: Register & Generate QR (If Paid)
if(isset($_POST['action']) && $_POST['action'] == 'register') {
    ob_clean(); header('Content-Type: application/json');

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['status' => 'error', 'msg' => 'Security Token Invalid!']); exit;
    }

    if($registration_status == 0) {
        echo json_encode(['status' => 'error', 'msg' => 'Registration is currently disabled by Admin.']); exit;
    }

    $name = mysqli_real_escape_string($DarkXen_conn, $_POST['name']);
    $shop = mysqli_real_escape_string($DarkXen_conn, $_POST['shop_name']);
    $email = mysqli_real_escape_string($DarkXen_conn, $_POST['email']);
    $phone = mysqli_real_escape_string($DarkXen_conn, $_POST['phone']);
    $usertype = mysqli_real_escape_string($DarkXen_conn, $_POST['user_type']);
    $password = $_POST['password'];

    // Check existing
    $check = mysqli_query($DarkXen_conn, "SELECT id FROM users WHERE phone='$phone' OR email='$email'");
    if (mysqli_num_rows($check) > 0) {
        echo json_encode(['status' => 'error', 'msg' => 'Email or Mobile already registered.']); exit;
    }

    $vpass = password_hash($password, PASSWORD_DEFAULT);
    $fee = isset($fees[$usertype]) ? $fees[$usertype] : 0;

    if($gateway_status == 1 && $fee > 0 && !empty($gateway_token)) {
        // 🔥 PAID REGISTRATION (Gateway ON)
        $ORDER_ID = "REG" . time() . rand(1000,9999);
        $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $redirect_uri = $protocol . $_SERVER['HTTP_HOST'] . explode('?', $_SERVER['REQUEST_URI'], 2)[0];

        $data = array(
            "customer_mobile" => $phone,
            "user_token" => $gateway_token,
            "amount" => $fee,
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
            
            // Extract UPI Intent
            $ch2 = curl_init($payment_url);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
            $html_response = curl_exec($ch2);
            curl_close($ch2);
            
            $upi_intent_url = "";
            if (preg_match('/(upi:\/\/pay\?[^"\']+)/i', $html_response, $matches)) {
                $upi_intent_url = html_entity_decode($matches[1]);
            }

            if(!empty($upi_intent_url)) {
                // Insert User as Inactive (status = 0)
                mysqli_query($DarkXen_conn, "INSERT INTO users (name, shop, phone, email, password, type, status, balance) VALUES ('$name', '$shop', '$phone', '$email', '$vpass', '$usertype', '0', '0')");
                
                // Insert into wallet for tracking
                mysqli_query($DarkXen_conn,"INSERT INTO wallet(phone,amount,txn_id,status) VALUES('$phone','$fee','$ORDER_ID','pending')");

                $_SESSION['reg_active_txn'] = $ORDER_ID;
                $_SESSION['reg_phone'] = $phone;
                $_SESSION['reg_proxy_url'] = $payment_url;
                
                echo json_encode([
                    'status' => 'pay',
                    'amount' => $fee,
                    'order_id' => $ORDER_ID,
                    'upi_url' => $upi_intent_url
                ]);
                exit;
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Internal Secure Routing Failed.']); exit;
            }
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Gateway Service Unavailable.']); exit;
        }
    } else {
        // 🎉 FREE REGISTRATION (Gateway OFF or Fee is 0)
        mysqli_query($DarkXen_conn, "INSERT INTO users (name, shop, phone, email, password, type, status, balance) VALUES ('$name', '$shop', '$phone', '$email', '$vpass', '$usertype', '1', '0')");
        
        echo json_encode(['status' => 'success_free', 'msg' => 'Registration Successful!']);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Create Account | <?php echo htmlspecialchars($webname); ?></title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            /* 🎨 Premium Color Scheme: Deep Indigo & Slate (Same as Login) */
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --bg-body: #f1f5f9;
            --side-gradient: linear-gradient(135deg, #0f172a 0%, #312e81 100%);
            --card-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
            --input-bg: #f8fafc;
            --text-main: #1e293b;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-body);
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 30px 30px;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            margin: 0; padding: 20px;
        }

        .auth-container {
            background: #ffffff; border-radius: 30px; box-shadow: var(--card-shadow);
            width: 100%; max-width: 1100px; display: flex; overflow: hidden;
            border: 1px solid #e2e8f0; animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        /* Side Info */
        .auth-side {
            background: var(--side-gradient); padding: 50px; width: 40%;
            color: #ffffff; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden;
        }
        
        .auth-side::before {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 60%); z-index: 0;
        }
        .auth-side > div { z-index: 1; position: relative; }

        .auth-side h2 { font-weight: 800; font-size: 2.2rem; margin-bottom: 20px; }

        .feature-box {
            background: rgba(255, 255, 255, 0.08); padding: 18px 20px; border-radius: 16px;
            margin-bottom: 20px; backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.3s ease;
        }
        .feature-box:hover { transform: translateX(10px); background: rgba(255, 255, 255, 0.12); }

        /* Form Area */
        .auth-form-area { padding: 50px 60px; width: 60%; background: #ffffff; }

        .brand-logo-sq {
            width: 50px; height: 50px; background: var(--primary-color); border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 1.5rem; margin-bottom: 20px;
            box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
        }

        .form-label { font-weight: 700; font-size: 0.8rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }

        .form-control, .form-select {
            height: 52px; border-radius: 14px; border: 2px solid #e2e8f0;
            background: var(--input-bg); padding-left: 15px; font-weight: 600; color: var(--text-main);
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus { background: #fff; border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15); }

        .btn-register {
            height: 55px; background: var(--primary-color); border: none;
            border-radius: 14px; color: white; font-weight: 700; font-size: 1.1rem;
            transition: all 0.3s; width: 100%; margin-top: 10px;
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.3);
        }
        .btn-register:hover { background: var(--primary-hover); transform: translateY(-3px); box-shadow: 0 15px 30px rgba(79, 70, 229, 0.4); }

        .reg-disabled-box { background: #fffbeb; border: 1px solid #fde68a; color: #d97706; border-radius: 14px; padding: 15px; font-weight: 600; text-align: center; }

        /* 🚀 PREMIUM QR STANDEE CSS (Paytm/PhonePe Style) */
        .qr-standee-container { max-width: 380px; margin: 0 auto; background: #ffffff; border-radius: 24px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12), 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; overflow: hidden; position: relative; }
        .standee-header { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff; padding: 20px 15px; text-align: center; border-bottom: 4px solid #4f46e5; }
        .standee-title { font-size: 18px; font-weight: 800; letter-spacing: 0.5px; margin: 0; text-transform: uppercase; }
        .standee-subtitle { font-size: 12px; font-weight: 500; color: #94a3b8; margin-top: 5px; }
        .standee-body { padding: 25px 20px 30px; text-align: center; background-image: radial-gradient(#e2e8f0 1px, transparent 1px); background-size: 20px 20px; background-position: center; }
        .standee-amount { font-size: 42px; font-weight: 900; color: #0f172a; line-height: 1; margin-bottom: 20px; text-shadow: 2px 2px 0px #ffffff; }
        .standee-amount span { font-size: 24px; vertical-align: super; color: #64748b; }
        .qr-scan-box { position: relative; display: inline-block; padding: 15px; background: #ffffff; border-radius: 16px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); margin-bottom: 20px; }
        .qr-scan-box img { width: 220px; height: 220px; display: block; border-radius: 10px; }
        .scan-corner { position: absolute; width: 30px; height: 30px; border-color: #4f46e5; border-style: solid; }
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
        .mobile-pay-btn { background: #0f172a; color: #fff; font-weight: 700; border-radius: 12px; padding: 12px; margin-top: 15px; transition: 0.3s; text-decoration: none; display: block; }
        .mobile-pay-btn:hover { background: #1e293b; color: #fff; transform: scale(0.98); }

        @media (max-width: 992px) {
            .auth-side { display: none; }
            .auth-form-area { width: 100%; padding: 40px 30px; }
            .auth-container { max-width: 500px; }
        }
        @media (max-width: 576px) {
            body { background: #ffffff; padding: 0; background-image: none;}
            .auth-container { border-radius: 0; border: none; box-shadow: none; min-height: 100vh;}
            .auth-form-area { justify-content: flex-start; padding-top: 40px; }
        }
    </style>
</head>
<body>

    <div class="auth-container">
        <!-- Side Illustration -->
        <div class="auth-side">
            <div>
                <h2>Welcome to <?php echo htmlspecialchars($webname); ?></h2>
                <p class="opacity-75 mb-4">Start your digital service journey with India's Premium Portal.</p>
                
                <div class="feature-box">
                    <div class="d-flex align-items-center gap-3">
                        <i class="fas fa-bolt text-warning fs-4"></i>
                        <div>
                            <h6 class="mb-0 fw-bold">Instant Activation</h6>
                            <small class="opacity-75">Get your ID within seconds</small>
                        </div>
                    </div>
                </div>

                <div class="feature-box">
                    <div class="d-flex align-items-center gap-3">
                        <i class="fas fa-headset text-info fs-4"></i>
                        <div>
                            <h6 class="mb-0 fw-bold">VLE Support</h6>
                            <small class="opacity-75">Dedicated support for your shop</small>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-top border-white border-opacity-10 text-center">
                    <p class="mb-2">Already have an account?</p>
                    <a href="login.php" class="btn btn-outline-light w-100 fw-bold rounded-pill">Sign In to Dashboard</a>
                </div>
            </div>
        </div>

        <!-- Right Side Area -->
        <div class="auth-form-area position-relative">
            
            <?php if ($registration_status === 1): ?>
                
                <!-- 📝 REGISTRATION FORM -->
                <div id="form-container">
                    <div class="brand-logo-sq"><i class="fas fa-user-plus"></i></div>
                    <h1 class="h3 fw-bold text-dark mb-1">Create Account</h1>
                    <p class="text-muted mb-4 small fw-semibold">Enter your details to register your shop portal.</p>

                    <form id="regForm" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="name" id="name" class="form-control" placeholder="Enter Name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Shop Name</label>
                                <input type="text" name="shop_name" id="shop_name" class="form-control" placeholder="Shop/Center Name" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" id="email" class="form-control" placeholder="example@mail.com" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mobile Number</label>
                                <input type="tel" name="phone" id="phone" class="form-control" placeholder="10 Digit No." maxlength="10" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label d-flex justify-content-between">
                                    <span>User Role</span>
                                    <span id="fee_display" class="text-primary fw-bold text-end"></span>
                                </label>
                                <select class="form-select fw-bold" name="user_type" id="user_type" onchange="updateFee()" required>
                                    <option value="" disabled selected>Select Account Type</option>
                                    <option value="Retailer">Retailer Account</option>
                                    <option value="Distributor">Distributor Account</option>
                                    <option value="Super Distributor">Super Distributor Account</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="••••••••" required>
                            </div>
                        </div>

                        <div class="form-check mt-3 mb-4">
                            <input class="form-check-input" type="checkbox" id="terms" required>
                            <label class="form-check-label text-muted small" for="terms">
                                I agree to the <a href="#" class="text-primary fw-bold text-decoration-none">Terms & Privacy Policy</a>.
                            </label>
                        </div>

                        <button type="submit" class="btn btn-register" id="regBtn">
                            <span>Register Account</span> <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </form>

                    <div class="mt-4 text-center d-md-none">
                        <p class="small text-muted fw-semibold">Joined us before? <a href="login.php" class="fw-bold text-primary">Login Now</a></p>
                    </div>
                </div>

                <!-- 🚀 PAYMENT QR STANDEE (Hidden Initially) -->
                <div id="qr-container" style="display: none; height: 100%; flex-direction: column; justify-content: center;">
                    <div class="qr-standee-container w-100">
                        <div class="standee-header d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="standee-title text-start"><i class="fas fa-shield-check me-2"></i>Account Fee</h4>
                                <div class="standee-subtitle text-start text-info">Txn: <span id="display_order_id"></span></div>
                            </div>
                        </div>

                        <div class="standee-body">
                            <div class="standee-amount">
                                <span>₹</span><span id="display_amount"></span>
                            </div>

                            <div class="qr-scan-box">
                                <span class="scan-corner corner-tl"></span>
                                <span class="scan-corner corner-tr"></span>
                                <span class="scan-corner corner-bl"></span>
                                <span class="scan-corner corner-br"></span>
                                <img id="qr_image" src="" alt="Payment QR">
                                <div class="laser-line"></div>
                            </div>

                            <div class="live-status mt-2">
                                <div class="pulsing-dot"></div> Waiting for payment...
                            </div>

                            <a id="mobile_pay_btn" href="#" class="btn mobile-pay-btn w-100 d-md-none">
                                <i class="fas fa-paper-plane me-2"></i> Pay Directly via UPI App
                            </a>
                            
                            <div class="mt-3">
                                <button onclick="window.location.reload();" class="btn btn-link text-danger fw-bold fs-12 text-decoration-none shadow-none"><i class="fas fa-times-circle me-1"></i> Cancel Registration</button>
                            </div>
                        </div>

                        <div class="standee-footer">
                            <span class="footer-title">ACTIVATE YOUR ID INSTANTLY</span>
                        </div>
                    </div>

                    <!-- 🛡️ HIDDEN PROXY FRAME -->
                    <iframe id="proxy_frame" src="" style="display:none;" sandbox="allow-scripts allow-same-origin"></iframe>
                </div>

            <?php else: ?>
                <!-- Registration OFF Message -->
                <div class="d-flex flex-column justify-content-center h-100 text-center px-4">
                    <div class="mb-4 text-warning"><i class="fas fa-user-lock fa-4x"></i></div>
                    <h2 class="fw-bold text-dark mb-3">Registrations Closed</h2>
                    <div class="reg-disabled-box shadow-sm mb-4">
                        New account registration is currently disabled by the Administrator. Please try again later.
                    </div>
                    <a href="login.php" class="btn btn-primary btn-lg rounded-pill fw-bold w-100 shadow-sm mt-2">Go to Login</a>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    
    <script>
        // Check if gateway is ON and fees are available
        const gatewayOn = <?= $gateway_status ?>;
        const fees = <?= json_encode($fees) ?>;

        function updateFee() {
            let type = document.getElementById('user_type').value;
            let feeBox = document.getElementById('fee_display');
            if(gatewayOn == 1 && fees[type] > 0) {
                feeBox.innerHTML = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">Fee: ₹' + fees[type] + '</span>';
            } else {
                feeBox.innerHTML = '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Free Setup</span>';
            }
        }

        let pollingInterval;

        function startPolling() {
            pollingInterval = setInterval(function() {
                $.ajax({
                    url: window.location.href, 
                    type: 'POST',
                    data: { action: 'check_payment' },
                    dataType: 'json',
                    success: function(res) {
                        if(res.status === 'success') {
                            clearInterval(pollingInterval);
                            
                            confetti({ particleCount: 150, spread: 80, origin: { y: 0.6 } });
                            
                            Swal.fire({
                                title: 'Payment Successful!',
                                text: 'Your ID has been created and activated.',
                                icon: 'success',
                                confirmButtonColor: '#4f46e5',
                                allowOutsideClick: false
                            }).then(() => {
                                window.location.href = 'login.php'; 
                            });
                        } else if(res.status === 'failed' || res.status === 'error') {
                            clearInterval(pollingInterval);
                            Swal.fire('Declined', 'Transaction failed. Please try registering again.', 'error').then(() => {
                                window.location.reload();
                            });
                        }
                    }
                });
            }, 3000);
        }

        $('#regForm').on('submit', function(e) {
            e.preventDefault();

            // Validate Passwords
            let pass = $('#password').val();
            let cpass = $('#confirm_password').val();
            let phone = $('#phone').val();

            if(pass !== cpass) {
                Swal.fire('Error', 'Passwords do not match.', 'error');
                return;
            }
            if(phone.length !== 10) {
                Swal.fire('Error', 'Invalid Mobile Number.', 'error');
                return;
            }

            let formData = $(this).serialize() + "&action=register";
            let btn = $('#regBtn');
            let originalText = btn.html();

            btn.html('<span class="spinner-border spinner-border-sm me-2"></span> Processing...').prop('disabled', true);

            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if(response.status === 'success_free') {
                        // Free Registration Success
                        confetti({ particleCount: 150, spread: 80, origin: { y: 0.6 } });
                        Swal.fire({
                            title: 'Registration Successful!',
                            text: 'Your account has been created successfully.',
                            icon: 'success',
                            confirmButtonColor: '#4f46e5'
                        }).then(() => {
                            window.location.href = 'login.php';
                        });
                    } 
                    else if(response.status === 'pay') {
                        // Paid Registration - Show QR
                        Swal.close();
                        $('#display_amount').text(response.amount);
                        $('#display_order_id').text('#' + response.order_id);
                        
                        let qrImgUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=0&color=0f172a&data=" + encodeURIComponent(response.upi_url);
                        $('#qr_image').attr('src', qrImgUrl);
                        $('#mobile_pay_btn').attr('href', response.upi_url);
                        $('#proxy_frame').attr('src', '?secure_frame=1');

                        // Switch UI
                        $('#form-container').fadeOut('fast', function() {
                            $('#qr-container').css('display', 'flex').hide().fadeIn('slow');
                        });

                        startPolling();
                    } 
                    else {
                        btn.html(originalText).prop('disabled', false);
                        Swal.fire('Error!', response.msg, 'error');
                    }
                },
                error: function() {
                    btn.html(originalText).prop('disabled', false);
                    Swal.fire('Failed', 'Network error. Try again.', 'error');
                }
            });
        });
    </script>
</body>
</html>