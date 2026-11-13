<?php
include('../includes/session.php');
include('../includes/config.php');



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
    // कनेक्शन ऑब्जेक्ट को आपके प्रोजेक्ट के अनुसार $DarkXen_conn पर सिंक किया गया है
    $popap  = mysqli_real_escape_string($DarkXen_conn, $_POST['popap'] ?? '');

    // FIXED: Extra comma (,) extracted from before WHERE clause to prevent SQL syntax errors
    $update_query = "UPDATE settings SET popap = '$popap' WHERE id='1'";

    $update = mysqli_query($DarkXen_conn, $update_query);

    if ($update) {
        $success_message = "Notification Updated Successfully!";
    } else {
        $error_message = "Update Failed: " . mysqli_error($DarkXen_conn);
    }
}

// 2. डेटाबेस से वर्तमान सेटिंग्स को फ़ैच करें ताकि वे इनपुट फ़ील्ड्स में दिखाई दें
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
                        <h4 class="text-primary fw-bold mb-0">System Notification</h4>
                        <p class="mb-0 fs-11 text-muted">Configure your Notification contact info securely.</p>
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
                <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-sliders-h me-2"></i>Notification Configurations</h5>
            </div>
            <div class="card-body bg-white p-4">
                <form method="POST" class="row g-3" autocomplete="off">
                    
                    <!-- कांटेक्ट इन्फो / पॉपअप मैसेज -->
                    <div class="col-12">
                        <label class="form-label fw-bold text-900">Support / Popup Notification Text</label>
                        <textarea class="form-control shadow-none border-200 text-monospace fs-11" name="popap" rows="4" placeholder="Enter notification message here..."><?= htmlspecialchars($settings['popap'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- सबमिट बटन -->
                    <div class="col-12 mt-4 pt-2">
                        <button class="btn btn-primary px-5 py-2 fw-bold shadow-none float-end" name="update_settings" type="submit">
                            <span class="fas fa-save me-2"></span>Save Notification 
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
            window.location.href = window.location.pathname; // पेज रीफ्रेश करके नए डेटा को तुरंत रिफ्लेक्ट करेगा
        });
    <?php endif; ?>
</script>

<style>
    .form-control { padding: 0.7rem 1rem; border-radius: 0.4rem; }
    .card { border-radius: 0.75rem; overflow: hidden; }
    .text-monospace { font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    .fs-11 { font-size: 14px !important; }
</style>

<?php include('footer.php'); ?>