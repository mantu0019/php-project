<?php

include('../includes/session.php');
include('../includes/config.php');
include('header.php');

$phone = $_SESSION['phone'];

// User Data Fetch
$user_query = mysqli_query($DarkXen_conn, "SELECT * FROM users WHERE phone='$phone'");
$userdata = mysqli_fetch_assoc($user_query);

$script = ""; // SweetAlert स्क्रिप्ट्स को स्टोर करने के लिए वेरिएबल

/* ================= PASSWORD CHANGE ================= */

if (
    isset($_POST['current_pass']) &&
    isset($_POST['new_pass']) &&
    isset($_POST['confirm_pass'])
) {

    $curpass = trim($_POST['current_pass']);
    $npass = trim($_POST['new_pass']);
    $cpass = trim($_POST['confirm_pass']);

    if (
        !empty($curpass) &&
        !empty($npass) &&
        !empty($cpass)
    ) {

        if (password_verify($curpass, $userdata['password'])) {

            if ($npass == $cpass) {

                $newpass = password_hash($npass, PASSWORD_DEFAULT);

                $update = mysqli_query(
                    $DarkXen_conn,
                    "UPDATE users
                    SET password='$newpass'
                    WHERE phone='$phone'"
                );

                if ($update) {
                    // FIX: Vanilla JS used and redirect handled properly
                    $script = "
                        Swal.fire('Password Changed Successfully', '', 'success').then(() => {
                            window.location.href='';
                        });
                    ";
                }

            } else {
                $script = "Swal.fire('Confirm Password Not Match', 'Please enter same password', 'error');";
            }

        } else {
            $script = "Swal.fire('Current Password Wrong', 'Please enter correct password', 'error');";
        }
    }
}


/* ================= PROFILE UPDATE ================= */

if (
    isset($_POST['name']) &&
    isset($_POST['email'])
) {

    $name = mysqli_real_escape_string($DarkXen_conn, trim($_POST['name']));
    $email = mysqli_real_escape_string($DarkXen_conn, trim($_POST['email']));

    if (!empty($name) && !empty($email)) {

        $update = mysqli_query(
            $DarkXen_conn,
            "UPDATE users
            SET name='$name', email='$email'
            WHERE phone='$phone'"
        );

        if ($update) {
            $script = "
                Swal.fire('Profile Updated Successfully', '', 'success').then(() => {
                    window.location.href='';
                });
            ";
        } else {
            $script = "Swal.fire('Something Went Wrong', 'Try Again', 'error');";
        }
    }
}
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center">
                <div class="col-sm-auto d-flex align-items-center">
                    <img class="ms-n2" src="../assets/img/illustrations/crm-bar-chart.png" alt="" width="90" />
                    <div>
                        <h6 class="text-primary fs-11 mb-0">Manage your Account,</h6>
                        <h4 class="text-primary fw-bold mb-0"><?php echo web('webname'); ?> <span class="text-info fw-medium">CRM</span></h4>
                    </div>
                    <img class="ms-n4 d-md-none d-lg-block" src="../assets/img/illustrations/crm-line-chart.png" alt="" width="150" />
                </div>
                <div class="col-md-auto p-3">
                    <div class="row align-items-center g-3">
                        <div class="col-auto"><h6 class="text-700 mb-0">Today: </h6></div>
                        <div class="col-md-auto">
                            <div class="form-control form-control-sm d-flex align-items-center bg-white border-200" style="min-width: 140px;">
                                <span class="fas fa-calendar-day text-primary me-2"></span>
                                <span class="fw-bold text-primary">06 Jul, 2026</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-light">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0 text-1000 fw-bold"><i class="fas fa-user-circle me-2"></i>Profile Settings</h5>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <form method="POST" class="p-4">
                    <div class="mb-3">
                        <label class="form-label fs-10 text-900" for="name">Full Name</label>
                        <input class="form-control" name="name" id="name" type="text" value="<?= htmlspecialchars($userdata['name'] ?? '') ?>" required />
                    </div>
                    <div class="mb-3">
                        <label class="form-label fs-10 text-900" for="phone">Mobile Number (Login ID)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-200 border-200"><i class="fas fa-lock fs-11"></i></span>
                            <input class="form-control bg-200" id="phone" type="text" value="<?= htmlspecialchars($userdata['phone'] ?? '') ?>" readonly />
                        </div>
                        <div class="fs-11 text-danger mt-1"><i class="fas fa-info-circle me-1"></i>Mobile number cannot be changed.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fs-10 text-900" for="email">Email Address</label>
                        <input class="form-control" name="email" id="email" type="email" value="<?= htmlspecialchars($userdata['email'] ?? '') ?>" required />
                    </div>
                    <div class="mt-4 text-end">
                        <button class="btn btn-primary btn-sm px-4 fw-semi-bold" type="submit" name="update_profile">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-light">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0 text-1000 fw-bold"><i class="fas fa-shield-alt me-2"></i>Security</h5>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <form method="POST" class="p-4">
                    <div class="mb-3">
                        <label class="form-label fs-10 text-900" for="current_pass">Current Password</label>
                        <input class="form-control" name="current_pass" id="current_pass" type="password" placeholder="••••••••" required />
                    </div>
                    <div class="mb-3">
                        <label class="form-label fs-10 text-900" for="new_pass">New Password</label>
                        <input class="form-control" name="new_pass" id="new_pass" type="password" placeholder="Min 6 chars" required />
                    </div>
                    <div class="mb-3">
                        <label class="form-label fs-10 text-900" for="confirm_pass">Confirm Password</label>
                        <input class="form-control" name="confirm_pass" id="confirm_pass" type="password" placeholder="••••••••" required />
                    </div>
                    <div class="mt-4 text-end">
                        <button class="btn btn-danger btn-sm px-4 fw-semi-bold" type="submit" name="change_pass">
                            <i class="fas fa-key me-2"></i>Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Scripts Section -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// FIX: Using Native JS DOMContentLoaded to ensure SweetAlert runs without jQuery errors
document.addEventListener("DOMContentLoaded", function() {
    <?php if(!empty($script)) { echo $script; } ?>
});
</script>

<?php include('footer.php'); ?>