<?php
session_start();
include('../includes/config.php');

if (isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

$alert_script = '';

// 1. Fetch Registration Status & Webname from Settings
$setting_query = mysqli_query($DarkXen_conn, "SELECT registration_status, webname FROM settings ORDER BY id ASC LIMIT 1");
$setting_data = mysqli_fetch_assoc($setting_query);
$registration_status = isset($setting_data['registration_status']) ? (int)$setting_data['registration_status'] : 1; // 1 = ON, 0 = OFF

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $username = mysqli_real_escape_string($DarkXen_conn, trim($_POST['username']));
    $password = trim($_POST['password']);

    $fetch = mysqli_query(
        $DarkXen_conn, 
        "SELECT * FROM users 
         WHERE phone='$username' 
         OR email='$username' 
         OR name='$username' 
         LIMIT 1"
    );

    if ($fetch && mysqli_num_rows($fetch) > 0) {

        $userData = mysqli_fetch_assoc($fetch);

        if ($userData['status'] == 1) {

            if (
                password_verify($password, $userData['password']) || 
                $password === $userData['password']
            ) {

                session_regenerate_id(true);
                $new_token = bin2hex(random_bytes(16));

                mysqli_query(
                    $DarkXen_conn, 
                    "UPDATE users 
                     SET reset_token='$new_token' 
                     WHERE id='".$userData['id']."'"
                );

                $_SESSION['id']    = $userData['id'];
                $_SESSION['name']  = $userData['name'];
                $_SESSION['phone'] = $userData['phone'];
                $_SESSION['token'] = $new_token;

                $alert_script = "
                    Swal.fire({
                        icon: 'success',
                        title: 'Login Successful',
                        text: 'Welcome " . htmlspecialchars($userData['name']) . "',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'index.php';
                    });
                    
                    confetti({
                        particleCount: 150,
                        spread: 80,
                        origin: { y: 0.6 }
                    });
                ";

            } else {
                $alert_script = "Swal.fire({ icon: 'error', title: 'Incorrect Password', text: 'Please check your password and try again.' });";
            }

        } else {
            $alert_script = "Swal.fire({ icon: 'warning', title: 'Account Inactive', text: 'Your account has been suspended or is inactive.' });";
        }

    } else {
        $alert_script = "Swal.fire({ icon: 'error', title: 'User Not Found', text: 'No account registered with these details.' });";
    }
}
?>

<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Secure Access | <?php echo htmlspecialchars($setting_data['webname'] ?? 'Portal'); ?></title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script disable-devtool-auto src="https://cdn.jsdelivr.net/npm/disable-devtool@latest"></script>
    
    <style>
        :root {
            /* 🎨 Premium Color Scheme: Deep Indigo & Slate */
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
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }

        .auth-container {
            background: #ffffff;
            border-radius: 30px;
            box-shadow: var(--card-shadow);
            width: 100%;
            max-width: 1050px;
            display: flex;
            overflow: hidden;
            min-height: 620px;
            border: 1px solid #e2e8f0;
        }

        /* Side Premium Illustration Section */
        .auth-side {
            background: var(--side-gradient);
            padding: 60px;
            width: 45%;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        
        /* Subtle glow effect inside sidebar */
        .auth-side::before {
            content: '';
            position: absolute;
            top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 60%);
            z-index: 0;
        }
        .auth-side > div { z-index: 1; position: relative; }

        .auth-side h2 { font-weight: 800; font-size: 2.5rem; letter-spacing: -1px; margin-bottom: 5px; }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 25px;
            background: rgba(255, 255, 255, 0.08);
            padding: 18px 20px;
            border-radius: 16px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.3s ease;
        }
        .feature-item:hover { transform: translateX(10px); background: rgba(255, 255, 255, 0.12); }
        .feature-item i { font-size: 1.4rem; }

        /* Form Section */
        .auth-form-area {
            padding: 60px 70px;
            width: 55%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #ffffff;
        }

        .brand-wrapper { display: flex; align-items: center; gap: 15px; margin-bottom: 35px; }
        .brand-logo-sq {
            width: 50px; height: 50px;
            background: var(--primary-color);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 1.5rem;
            box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
        }
        .brand-name { font-weight: 800; font-size: 1.8rem; color: var(--text-main); margin: 0; }

        .form-label { font-weight: 700; font-size: 0.85rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }

        .input-group-custom { position: relative; margin-bottom: 22px; }
        .input-group-custom i.fa-envelope, .input-group-custom i.fa-lock {
            position: absolute; left: 18px; top: 50%; transform: translateY(-50%);
            color: #94a3b8; z-index: 10; font-size: 1.1rem;
        }

        .form-control {
            height: 60px; padding-left: 50px;
            border-radius: 16px; border: 2px solid #e2e8f0;
            background: var(--input-bg); font-weight: 600; color: var(--text-main);
            transition: all 0.3s ease;
        }
        .form-control:focus {
            background: #ffffff; border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15);
        }

        .btn-login {
            height: 60px; background: var(--primary-color);
            border: none; border-radius: 16px;
            color: white; font-weight: 700; font-size: 1.1rem;
            transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%;
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.3);
        }
        .btn-login:hover { background: var(--primary-hover); transform: translateY(-3px); box-shadow: 0 15px 30px rgba(79, 70, 229, 0.4); }

        .pass-toggle {
            position: absolute; right: 18px; top: 50%; transform: translateY(-50%);
            cursor: pointer; color: #94a3b8; z-index: 10; border: none; background: none; font-size: 1.1rem;
        }
        .pass-toggle:hover { color: var(--primary-color); }

        /* Reg Disabled Alert */
        .reg-disabled-box {
            background: #fffbeb; border: 1px solid #fde68a; color: #d97706;
            border-radius: 14px; padding: 15px; font-weight: 600; font-size: 0.9rem;
        }

        /* Mobile Responsive */
        @media (max-width: 992px) {
            .auth-side { display: none; }
            .auth-form-area { width: 100%; padding: 50px 40px; }
            .auth-container { max-width: 500px; border-radius: 30px; }
        }
        @media (max-width: 576px) {
            body { background: #ffffff; padding: 0; background-image: none; }
            .auth-container { border-radius: 0; box-shadow: none; border: none; min-height: 100vh; }
            .auth-form-area { justify-content: flex-start; padding-top: 60px; padding-left: 25px; padding-right: 25px; }
        }
    </style>
</head>
<body>

    <div class="auth-container">
        <!-- Side Content -->
        <div class="auth-side">
            <div>
                <h2><?php echo htmlspecialchars($setting_data['webname'] ?? 'Portal'); ?></h2>
                <p class="opacity-75 mt-2 fs-6">India's Premium Digital Service Portal.</p>
                
                <div class="mt-5 pt-3">
                    <div class="feature-item">
                        <i class="fas fa-bolt text-warning"></i>
                        <span><strong>Lightning Fast</strong><br><small class="opacity-75">Zero lag, seamless processing</small></span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-shield-halved text-info"></i>
                        <span><strong>Bank-Grade Security</strong><br><small class="opacity-75">256-bit data encryption</small></span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-headset text-success"></i>
                        <span><strong>24/7 Priority Support</strong><br><small class="opacity-75">Dedicated VLE assistance</small></span>
                    </div>
                </div>
            </div>
            
            <div class="footer-note">
                <small class="opacity-50 fw-semibold">© <?php echo date('Y'); ?> <?php echo htmlspecialchars($setting_data['webname'] ?? 'Portal'); ?>. All rights reserved.</small>
            </div>
        </div>

        <!-- Login Form Area -->
        <div class="auth-form-area">
            <div class="brand-wrapper">
                <div class="brand-logo-sq"><i class="fas fa-fingerprint"></i></div>
                <h1 class="brand-name">Welcome Back</h1>
            </div>

            <p class="text-muted fw-semibold mb-4 pb-2">Enter your credentials to securely access your portal.</p>

            <form method="POST" id="loginForm">
                <div class="mb-3">
                    <label class="form-label">Username / Phone / Email</label>
                    <div class="input-group-custom">
                        <i class="fas fa-envelope"></i>
                        <input type="text" name="username" class="form-control" placeholder="Enter your User ID" required>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="d-flex justify-content-between">
                        <label class="form-label">Password</label>
                    </div>
                    <div class="input-group-custom">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" id="passInput" class="form-control" placeholder="••••••••" required>
                        <button type="button" class="pass-toggle" onclick="togglePass()">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" name="submit" class="btn btn-login" id="submitBtn">
                    <span>Secure Sign In</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <!-- Registration Logic Implementation -->
            <div class="mt-5 text-center">
                <?php if ($registration_status === 1): ?>
                    <p class="text-muted fw-semibold mb-3">Don't have an account yet?</p>
                    <a href="register.php" class="btn btn-outline-primary w-100 fw-bold py-3 border-2" style="border-radius: 14px; font-size: 1rem;">
                        Create a New Account
                    </a>
                <?php else: ?>
                    <!-- When Registration is OFF -->
                    <div class="reg-disabled-box shadow-sm mt-4">
                        <i class="fas fa-info-circle me-2"></i> New account registration is currently disabled by Administrator.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <!-- Added Confetti JS Library -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function togglePass() {
            const input = document.getElementById('passInput');
            const icon = document.getElementById('eyeIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Form loading state
        document.getElementById('loginForm').onsubmit = function() {
            const btn = document.getElementById('submitBtn');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Authenticating...';
            btn.disabled = true;
        };

        // Backend Alerts
        <?php if (!empty($alert_script)) { echo $alert_script; } ?>
    </script>
</body>
</html>