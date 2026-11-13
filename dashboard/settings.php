<?php
include('../includes/session.php');
include('../includes/config.php');

// Admin Check
if(checkAdmin($udata['type']) == false){
    ?>
    <script>
        window.location='index.php';
    </script>
    <?php
    die();
}

include('header.php');

$error_message = '';
$success_message = '';

// 1. फॉर्म सबमिशन होने पर डेटा अपडेट करें
if (isset($_POST['update_settings'])) {
    
    // सुरक्षित तरीके से इनपुट लें
    $webname            = mysqli_real_escape_string($DarkXen_conn, $_POST['webname'] ?? '');
    $weburl             = mysqli_real_escape_string($DarkXen_conn, $_POST['weburl'] ?? '');
    $channel            = mysqli_real_escape_string($DarkXen_conn, $_POST['channel'] ?? '');
    $phone              = mysqli_real_escape_string($DarkXen_conn, $_POST['phone'] ?? '');
    $email              = mysqli_real_escape_string($DarkXen_conn, $_POST['email'] ?? '');
    
    // API Keys
    $api_key            = mysqli_real_escape_string($DarkXen_conn, $_POST['api_key'] ?? '');
    $geteway_key        = mysqli_real_escape_string($DarkXen_conn, $_POST['geteway_key'] ?? '');
    $whatshapp_apikey   = mysqli_real_escape_string($DarkXen_conn, $_POST['whatshapp_apikey'] ?? '');
    
    // Registration & Wallet Limits
    $min_amount         = (int)($_POST['min_amount'] ?? 50);
    $registration_status = (int)($_POST['registration_status'] ?? 1);
    
    // 🚀 NEW: Registration Payment & Fees
    $reg_gateway_status    = (int)($_POST['reg_gateway_status'] ?? 0);
    $retailer_fee          = (int)($_POST['retailer_fee'] ?? 99);
    $distributor_fee       = (int)($_POST['distributor_fee'] ?? 199);
    $super_distributor_fee = (int)($_POST['super_distributor_fee'] ?? 299);

    // SQL क्वेरी 
    $update_query = "UPDATE settings SET 
                        webname = '$webname',
                        weburl = '$weburl',
                        channel = '$channel',
                        phone = '$phone',
                        email = '$email',
                        api_key = '$api_key',
                        geteway_key = '$geteway_key',
                        whatshapp_apikey = '$whatshapp_apikey',
                        min_amount = '$min_amount',
                        registration_status = '$registration_status',
                        reg_gateway_status = '$reg_gateway_status',
                        retailer_fee = '$retailer_fee',
                        distributor_fee = '$distributor_fee',
                        super_distributor_fee = '$super_distributor_fee'
                     WHERE id='1'";

    $update = mysqli_query($DarkXen_conn, $update_query);

    if ($update) {
        $success_message = "Settings Updated Successfully!";
    } else {
        $error_message = "Update Failed: " . mysqli_error($DarkXen_conn);
    }
}

// 2. डेटाबेस से वर्तमान सेटिंग्स को फ़ैच करें
$settings_query = mysqli_query($DarkXen_conn, "SELECT * FROM settings WHERE id='1' LIMIT 1");
$settings = mysqli_fetch_assoc($settings_query);
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="avatar avatar-xl me-3 bg-200 rounded-circle text-center d-flex align-items-center justify-content-center">
                        <span class="fas fa-cogs text-primary fs-2"></span>
                    </div>
                    <div>
                        <h4 class="text-primary fw-bold mb-0">System Settings Portal</h4>
                        <p class="mb-0 fs-11 text-muted">Configure your website names, APIs, Registration limits and pricing.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 justify-content-center mb-4">
    <div class="col-lg-8">
        <div class="card h-100 shadow-none border border-top border-4 border-primary">
            <div class="card-header bg-light border-bottom py-3">
                <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-sliders-h me-2"></i>Global Configurations</h5>
            </div>
            <div class="card-body bg-white p-4">
                <form method="POST" class="row g-3" autocomplete="off">
                    
                    <!-- वेबसाइट विवरण -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-900">Website Name</label>
                        <input type="text" class="form-control shadow-none border-200" name="webname" value="<?= htmlspecialchars($settings['webname'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-900">Website URL</label>
                        <input type="url" class="form-control shadow-none border-200" name="weburl" value="<?= htmlspecialchars($settings['weburl'] ?? ''); ?>" required>
                    </div>

                    <!-- कांटेक्ट इन्फो -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-900">Support Phone</label>
                        <input type="text" class="form-control shadow-none border-200" name="phone" value="<?= htmlspecialchars($settings['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-900">Support Email</label>
                        <input type="email" class="form-control shadow-none border-200" name="email" value="<?= htmlspecialchars($settings['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label fw-bold text-900">WhatsApp Channel URL</label>
                        <input type="text" class="form-control shadow-none border-200 text-monospace" name="channel" value="<?= htmlspecialchars($settings['channel'] ?? ''); ?>">
                    </div>

                    <hr class="my-4 border-200" />
                    <h5 class="text-info fw-bold mb-1"><i class="fas fa-users-cog me-2"></i>User & Wallet Limits</h5>
                    
                    <!-- मिनिमम वॉलेट अमाउंट -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-900">Minimum Wallet Add Amount (₹)</label>
                        <input type="number" class="form-control shadow-none border-200 text-primary fw-bold" name="min_amount" value="<?= htmlspecialchars($settings['min_amount'] ?? '50'); ?>" min="1" required>
                    </div>

                    <!-- रजिस्ट्रेशन ON/OFF -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-900">User Registration Portal</label>
                        <select class="form-select shadow-none border-200 fw-bold" name="registration_status">
                            <option value="1" class="text-success" <?= (isset($settings['registration_status']) && $settings['registration_status'] == 1) ? 'selected' : ''; ?>>ON (Allowed)</option>
                            <option value="0" class="text-danger" <?= (isset($settings['registration_status']) && $settings['registration_status'] == 0) ? 'selected' : ''; ?>>OFF (Closed)</option>
                        </select>
                    </div>

                    <!-- 🚀 NEW SECTION: Registration Gateway & Pricing -->
                    <div class="col-12 mt-4">
                        <h6 class="fw-bold text-primary border-bottom border-200 pb-2 mb-3"><i class="fas fa-qrcode me-2"></i>Registration Gateway & Fees</h6>
                    </div>

                    <!-- गेटवे ON/OFF -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-900">Registration Payment Gateway</label>
                        <select class="form-select shadow-none border-200 fw-bold" name="reg_gateway_status">
                            <option value="1" class="text-primary" <?= (isset($settings['reg_gateway_status']) && $settings['reg_gateway_status'] == 1) ? 'selected' : ''; ?>>ON (Paid via QR)</option>
                            <option value="0" class="text-success" <?= (isset($settings['reg_gateway_status']) && $settings['reg_gateway_status'] == 0) ? 'selected' : ''; ?>>OFF (Free Register)</option>
                        </select>
                    </div>

                    <!-- रिटेलर फीस -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-900">Retailer Fee (₹)</label>
                        <input type="number" class="form-control shadow-none border-200" name="retailer_fee" value="<?= htmlspecialchars($settings['retailer_fee'] ?? '99'); ?>" min="0" required>
                    </div>

                    <!-- डिस्ट्रीब्यूटर फीस -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-900">Distributor Fee (₹)</label>
                        <input type="number" class="form-control shadow-none border-200" name="distributor_fee" value="<?= htmlspecialchars($settings['distributor_fee'] ?? '199'); ?>" min="0" required>
                    </div>

                    <!-- सुपर डिस्ट्रीब्यूटर फीस -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-900">Super Distributor Fee (₹)</label>
                        <input type="number" class="form-control shadow-none border-200" name="super_distributor_fee" value="<?= htmlspecialchars($settings['super_distributor_fee'] ?? '299'); ?>" min="0" required>
                    </div>
                    
                    <hr class="my-4 border-200" />
                    <h5 class="text-info fw-bold mb-1"><i class="fas fa-key me-2"></i>API Credentials</h5>
                    <p class="fs-12 text-muted mb-3">Keep these keys confidential. Do not share them with anyone.</p>

                    <!-- API और गेटवे चाबियाँ -->
                    <div class="col-12">
                        <label class="form-label fw-bold text-900">DARKXEN API Key</label>
                        <input type="text" class="form-control shadow-none border-200 text-monospace" name="api_key" value="<?= htmlspecialchars($settings['api_key'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label fw-bold text-900">DARKXEN GATEWAY API Key</label>
                        <input type="text" class="form-control shadow-none border-200 text-monospace" name="geteway_key" value="<?= htmlspecialchars($settings['geteway_key'] ?? ''); ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold text-900">WhatsApp API Key</label>
                        <input type="text" class="form-control shadow-none border-200 text-monospace" name="whatshapp_apikey" value="<?= htmlspecialchars($settings['whatshapp_apikey'] ?? ''); ?>">
                    </div>

                    <!-- सबमिट बटन -->
                    <div class="col-12 mt-4 pt-2">
                        <button class="btn btn-primary px-5 py-2 fw-bold shadow-none float-end" name="update_settings" type="submit">
                            <span class="fas fa-save me-2"></span>Save All Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 नोटिफिकेशन्स प्लगइन -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    <?php if(!empty($error_message)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Update Failed',
            text: '<?= addslashes($error_message); ?>',
            confirmButtonText: 'OK'
        });
    <?php endif; ?>

    <?php if(!empty($success_message)): ?>
        Swal.fire({
            icon: 'success',
            title: '<?= addslashes($success_message); ?>',
            showConfirmButton: false,
            timer: 1500
        }).then(() => { 
            window.location.href = window.location.pathname; 
        });
    <?php endif; ?>
</script>

<style>
    .form-control, .form-select { padding: 0.7rem 1rem; border-radius: 0.4rem; }
    .card { border-radius: 0.75rem; overflow: hidden; }
    .text-monospace { font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
</style>

<?php include('footer.php'); ?>