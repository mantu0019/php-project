<?php
include('../includes/session.php');
include('../includes/config.php');

$userRole = strtolower($udata['type'] ?? '');
$isAdmin = checkAdmin($udata['type'] ?? '');

// 🛡️ ROLE-BASED ACCESS CONTROL (सिर्फ Admin, Distributor और Super Distributor ही आ सकते हैं)
if (!$isAdmin && $userRole !== 'distributor' &&$userRole !== 'super distributor') {
    echo "<script>window.location='index.php';</script>";
    die();
}

include('header.php'); 

$error_message = '';$success_message = '';

// --- यूज़र क्रिएशन लॉजिक ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user_btn'])) {
    
    $name = mysqli_real_escape_string($DarkXen_conn, $_POST['name'] ?? '');$phone = mysqli_real_escape_string($DarkXen_conn,$_POST['phone'] ?? '');
    $email = mysqli_real_escape_string($DarkXen_conn, $_POST['email'] ?? '');$password = mysqli_real_escape_string($DarkXen_conn,$_POST['password'] ?? '');
    
    // रोल/टाइप का निर्धारण (सुरक्षा के लिए Server-side Validation)
    $type = 'retailer'; // Default fallback
    
    if ($isAdmin) {
        // Admin can create anyone
        $type = mysqli_real_escape_string($DarkXen_conn,$_POST['type'] ?? 'retailer');
    } elseif ($userRole === 'super distributor') {
        // Super Distributor can create Retailer or Distributor
        $posted_type = strtolower($_POST['type'] ?? 'retailer');
        if (in_array($posted_type, ['retailer', 'distributor'])) {
            $type =$posted_type;
        }
    } elseif ($userRole === 'distributor') {
        // Distributor can ONLY create Retailer
        $type = 'retailer';
    }

    if (empty($name) || empty($phone) && empty($email) || empty($password)) {$error_message = "All fields marked with an asterisk (*) are required!";
    } else {
        // 1. चेक करें कि फ़ोन नंबर या ईमेल पहले से मौजूद तो नहीं है
        $check_duplicate = mysqli_query($DarkXen_conn, "SELECT id FROM users WHERE phone='$phone' OR email='$email' LIMIT 1");
        
        if (mysqli_num_rows($check_duplicate) > 0) {$error_message = "User already exists with this Phone Number or Email ID!";
        } else {
            // 2. पासवर्ड को सुरक्षित रूप से हैश करें
            $vpass = password_hash($password, PASSWORD_DEFAULT);
            $parent_account =$udata['phone']; // पैरेंट अकाउंट ट्रैक करने के लिए (किसने बनाया)
            
            // 3. डेटाबेस में इंसर्ट करें (balance डिफ़ॉल्ट 0 के साथ)
            // 'shop' कॉलम खाली छोड़ दिया गया है या आप UI में shop name भी जोड़ सकते हैं, अभी के लिए '' रखा है
            $insert_query = "INSERT INTO users (`parent`, `name`, `shop`, `phone`, `email`, `type`, `password`, `status`, `balance`) 
                             VALUES ('$parent_account', '$name', '', '$phone', '$email', '$type', '$vpass', '1', '0.00')";
            
            $insert = mysqli_query($DarkXen_conn,$insert_query);

            if ($insert) {$success_message = "User Account Created Successfully!";
            } else {
                $error_message = "Database Insertion Failed: " . mysqli_error($DarkXen_conn);
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
                    <div class="avatar avatar-xl me-3 bg-200 rounded-circle text-center d-flex align-items-center justify-content-center">
                        <span class="fas fa-user-plus text-primary fs-2"></span>
                    </div>
                    <div>
                        <h4 class="text-primary fw-bold mb-0">Onboard New Portal User</h4>
                        <p class="mb-0 fs-11 text-muted">Register a new client, retailer, or partner under your network seamlessly.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 justify-content-center mb-4">
    <div class="col-lg-7">
        <div class="card h-100 shadow-sm border border-top border-4 border-primary rounded-3">
            <div class="card-header bg-light border-bottom py-3">
                <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-id-card me-2"></i>Account Registration Form</h5>
            </div>
            <div class="card-body bg-white p-4">
                <form method="POST" autocomplete="off">
                    
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-900">Full Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-200 border-200"><i class="fas fa-user text-500"></i></span>
                                <input type="text" class="form-control shadow-none border-200" name="name" placeholder="Enter full name" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-900">Mobile Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-200 border-200"><i class="fas fa-phone-alt text-500"></i></span>
                                <input type="tel" pattern="[0-9]{10}" class="form-control shadow-none border-200" maxlength="10" name="phone" placeholder="10-digit mobile number" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-900">Email Address <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-200 border-200"><i class="fas fa-envelope text-500"></i></span>
                                <input type="email" class="form-control shadow-none border-200" name="email" placeholder="name@example.com" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-900">Account Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-200 border-200"><i class="fas fa-lock text-500"></i></span>
                                <input type="password" class="form-control shadow-none border-200" name="password" placeholder="Create strong password" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-900">Account Role / Type</label>
                            
                            <?php if ($isAdmin): ?>
                                <!-- Admin sees everything -->
                                <select class="form-select shadow-none border-200 fw-semi-bold text-primary" name="type">
                                    <option value="retailer" selected>Retailer</option>
                                    <option value="distributor">Distributor</option>
                                    <option value="super distributor">Super Distributor</option>
                                </select>
                            
                            <?php elseif ($userRole === 'super distributor'): ?>
                                <!-- Super Distributor sees Retailer & Distributor -->
                                <select class="form-select shadow-none border-200 fw-semi-bold text-primary" name="type">
                                    <option value="retailer" selected>Retailer</option>
                                    <option value="distributor">Distributor</option>
                                </select>
                            
                            <?php else: ?>
                                <!-- Distributor sees only Retailer -->
                                <input type="hidden" name="type" value="retailer">
                                <input type="text" class="form-control bg-100 border-200 text-600 fw-semi-bold" value="Retailer (Fixed)" readonly>
                            <?php endif; ?>
                            
                        </div>

                        <div class="col-12 mt-4 pt-2">
                            <button class="btn btn-primary w-100 py-3 fw-bold shadow-sm rounded-pill" type="submit" name="create_user_btn" style="font-size: 1.1rem;">
                                <span class="fas fa-user-check me-2"></span>Create & Activate Account
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    <?php if(!empty($error_message)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Registration Blocked',
            text: '<?= addslashes($error_message); ?>',
            confirmButtonText: 'Try Again',
            confirmButtonColor: '#d33'
        });
    <?php endif; ?>

    <?php if(!empty($success_message)): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?= addslashes($success_message); ?>',
            showConfirmButton: false,
            timer: 1500
        }).then(() => { 
            window.location.href = 'users.php'; // Redirects to user list
        });
    <?php endif; ?>
</script>

<style>
    .form-control, .form-select, .input-group-text { padding: 0.7rem 1rem; border-radius: 0.5rem; }
    .input-group > .input-group-text { border-top-right-radius: 0; border-bottom-right-radius: 0; }
    .input-group > .form-control { border-top-left-radius: 0; border-bottom-left-radius: 0; }
    .form-control:focus, .form-select:focus { border-color: #0d6efd; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.1); }
</style>

<?php include('footer.php'); ?>