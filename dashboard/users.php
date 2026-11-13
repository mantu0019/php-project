<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include('../includes/session.php');
include('../includes/config.php');

$userRole = strtolower($udata['type'] ?? '');
$isAdmin = checkAdmin($udata['type'] ?? '');
$myPhone = mysqli_real_escape_string($DarkXen_conn, $udata['phone']);

// 🛡️ ROLE-BASED ACCESS CONTROL (सिर्फ Admin, Distributor और Super Distributor ही आ सकते हैं)
if (!$isAdmin && $userRole !== 'distributor' && $userRole !== 'super distributor') {
    echo "<script>window.location='index.php';</script>";
    die();
}

include('header.php');

// 🛡️ STRICT ADMIN ONLY ACTIONS (सिर्फ एडमिन ही एडिट, डिलीट, पासवर्ड रिसेट या लॉगिन ऐज यूज़र कर सकता है)
if ($isAdmin) {
    
    // --- 1. पासवर्ड रीसेट लॉजिक ---
    if (isset($_GET['id']) && isset($_GET['reset']) && $_GET['reset'] == 1) {
        $id = base64_decode(mysqli_real_escape_string($DarkXen_conn, $_GET['id']));
        $vpass = rand(100000, 999999);
        $pass = password_hash($vpass, PASSWORD_DEFAULT);
        
        $reset = mysqli_query($DarkXen_conn, "UPDATE users SET password='$pass' WHERE id='$id'");
        if ($reset) {
            echo "
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Password Reset Successful',
                    html: '<div class=\"mt-2\">Temporary Password: <br><b class=\"text-danger fs-3 tracking-widest\">$vpass</b><br><small class=\"text-muted mt-2 d-block\">Please copy this password and share it securely with the user.</small></div>',
                    confirmButtonText: 'Understood',
                    confirmButtonColor: '#4f46e5'
                }).then(() => { window.location='users.php'; });
            });
            </script>";
        }
    }

    // --- 2. LOGIN AS USER LOGIC ---
    if (isset($_GET['login']) && $_GET['login'] == 1 && !empty($_GET['phone'])) {
        $phone = base64_decode(mysqli_real_escape_string($DarkXen_conn, $_GET['phone']));
        $_SESSION['phone'] = $phone;
        $_SESSION['adminasuser']  = true;
        $_SESSION['adminusername']  = $udata['phone'];
        echo "<script>window.location='../index.php';</script>"; 
        exit;
    }

    // --- 3. यूज़र डिलीट, बैन और एक्टिवेट लॉजिक ---
    if (isset($_GET['deleteuser']) && $_GET['deleteuser'] == 1 && !empty($_GET['id'])) {
        $id = base64_decode(mysqli_real_escape_string($DarkXen_conn, $_GET['id']));
        $delete = mysqli_query($DarkXen_conn, "DELETE FROM users WHERE id='$id'");
        if ($delete) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({ icon: 'success', title: 'Deleted!', text: 'User has been permanently removed.', timer: 1500, showConfirmButton: false }).then(() => { window.location='users.php'; });
                });
            </script>";
        }
    }

    if (isset($_GET['banid']) && isset($_GET['ban']) && $_GET['ban'] == '1') {
        $banid = base64_decode(mysqli_real_escape_string($DarkXen_conn, $_GET['banid']));
        $ban = mysqli_query($DarkXen_conn, "UPDATE users SET status='0' WHERE id='$banid'");
        if ($ban) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({ icon: 'warning', title: 'Account Suspended', text: 'User access has been revoked.', timer: 1500, showConfirmButton: false }).then(() => { window.location='users.php'; });
                });
            </script>";
        }
    }

    if (isset($_GET['apid']) && isset($_GET['approve']) && $_GET['approve'] == '1') {
        $apid = base64_decode(mysqli_real_escape_string($DarkXen_conn, $_GET['apid']));
        $approve = mysqli_query($DarkXen_conn, "UPDATE users SET status='1' WHERE id='$apid'");
        if ($approve) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({ icon: 'success', title: 'Account Activated', text: 'User can now log in to the portal.', timer: 1500, showConfirmButton: false }).then(() => { window.location='users.php'; });
                });
            </script>";
        }
    }
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css">

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="avatar avatar-xl me-3 bg-200 rounded-circle text-center d-flex align-items-center justify-content-center">
                        <span class="fas fa-users text-primary fs-2"></span>
                    </div>
                    <div>
                        <h4 class="text-primary fw-bold mb-0">Network Directory</h4>
                        <p class="mb-0 fs-11 text-muted">
                            <?= $isAdmin ? "Monitor all portal users and track creators." : "Manage the retailers and distributors registered under your network."; ?>
                        </p>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="adduser.php" class="btn btn-sm btn-primary shadow-sm fw-bold rounded-pill px-4 py-2">
                        <i class="fas fa-user-plus me-2"></i> Register New User
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border mb-4">
    <div class="card-header bg-light border-bottom py-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
        <h5 class="mb-0 text-900 fw-bold"><i class="fas fa-list-alt me-2 text-primary"></i><?= $isAdmin ? "All Registered Members" : "My Network Members"; ?></h5>
        <div class="search-box" style="width: 100%; max-width: 300px;">
            <div class="position-relative">
                <input type="text" id="userSearch" class="form-control form-control-sm shadow-none pe-4 rounded-pill" placeholder="Search by name, phone, email...">
                <i class="fas fa-search position-absolute text-400" style="right: 12px; top: 50%; transform: translateY(-50%);"></i>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped fs-11 mb-0 text-center table-hover align-middle">
                <thead class="bg-200 text-900 text-uppercase">
                    <tr>
                        <th class="py-3">#</th>
                        <th class="py-3">Mobile Number</th>
                        <th class="py-3 text-start ps-3">Full Name</th>
                        <th class="py-3 text-start ps-2">Email Address</th>
                        <th class="py-3">Account Role</th>
                        
                        <!-- 🟢 ONLY ADMIN SEES 'CREATED BY' COLUMN -->
                        <?php if ($isAdmin): ?>
                        <th class="py-3">Created By</th>
                        <?php endif; ?>

                        <th class="py-3">Wallet Balance</th>
                        
                        <!-- 🟢 ONLY ADMIN SEES 'ACTIONS & MANAGEMENT' COLUMN -->
                        <?php if ($isAdmin): ?>
                        <th class="py-3 text-end pe-4">Actions & Management</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="userTableBody">
                    <?php
                    // 🚀 DYNAMIC SQL QUERY: Admin sees all (with join), others see only their own network
                    if ($isAdmin) {
                        $sql = "SELECT u.*, p.name as creator_name, p.type as creator_type 
                                FROM users u 
                                LEFT JOIN users p ON u.parent = p.phone 
                                ORDER BY u.id DESC";
                    } else {
                        $sql = "SELECT * FROM users WHERE parent = '$myPhone' ORDER BY id DESC";
                    }

                    $res = mysqli_query($DarkXen_conn, $sql);
                    
                    if (mysqli_num_rows($res) > 0) {
                        $count = 0;
                        while ($data = mysqli_fetch_assoc($res)) {
                            // एडमिन को लिस्ट में न दिखाएं
                            if (strtolower($data['type']) == 'admin') {
                                continue;
                            }
                            $count++;
                            $u_id = $data['id'];
                            $u_status = $data['status'];
                            $u_balance = !empty($data['balance']) ? (float)$data['balance'] : 0.00;
                            
                            // Role Badge Colors
                            $role_class = 'bg-primary-subtle text-primary border border-primary-subtle';
                            if(strtolower($data['type']) == 'distributor') $role_class = 'bg-success-subtle text-success border border-success-subtle';
                            if(strtolower($data['type']) == 'super distributor') $role_class = 'bg-warning-subtle text-warning border border-warning-subtle';
                            ?>
                            <tr>
                                <td class="fw-semi-bold py-2"><?= $count; ?></td>
                                <td class="fw-bold text-900 py-2"><?= htmlspecialchars($data['phone']); ?></td>
                                <td class="fw-bold text-start ps-3 text-primary py-2"><?= htmlspecialchars($data['name']); ?></td>
                                <td class="text-muted text-start ps-2 py-2"><?= htmlspecialchars($data['email']); ?></td>
                                <td class="py-2">
                                    <span class="badge <?= $role_class; ?> fw-bold px-2 py-1 fs-11">
                                        <?= strtoupper(htmlspecialchars($data['type'])); ?>
                                    </span>
                                </td>

                                <!-- 🟢 ADMIN 'CREATED BY' DISPLAY LOGIC -->
                                <?php if ($isAdmin): ?>
                                <td class="py-2">
                                    <?php 
                                    if (!empty($data['creator_name'])) {
                                        // Creator Found
                                        echo "<span class='fw-bold text-dark'>" . htmlspecialchars($data['creator_name']) . "</span><br>";
                                        echo "<span class='badge bg-secondary-subtle text-secondary mt-1 fs-11'>" . strtoupper(htmlspecialchars($data['creator_type'])) . "</span>";
                                    } elseif (!empty($data['parent'])) {
                                        // Parent Phone Exists but user deleted
                                        echo "<span class='badge bg-info-subtle text-info fs-11'>Mobile: " . htmlspecialchars($data['parent']) . "</span>";
                                    } else {
                                        // Created by Admin directly or self-registered
                                        echo "<span class='badge bg-danger-subtle text-danger fs-11'>Admin / System</span>";
                                    }
                                    ?>
                                </td>
                                <?php endif; ?>

                                <!-- WALLET FUND BUTTON (Visible to everyone authorized) -->
                                <td class="py-2">
                                    <div class="d-flex flex-column align-items-center">
                                        <span class="fw-bold text-success mb-1 fs-10">₹<?= number_format($u_balance, 2); ?></span>
                                        <form action="wallet_transfer.php" method="POST">
                                            <input type="hidden" name="username" value="<?= htmlspecialchars($data['email']); ?>">
                                            <button class="btn btn-xs btn-success py-0 px-2 shadow-none fs-12 fw-semi-bold rounded-pill" type="submit">
                                                <i class="fas fa-plus me-1"></i>Fund
                                            </button>
                                        </form>
                                    </div>
                                </td>
                                
                                <!-- 🟢 ONLY ADMIN SEES ACTION BUTTONS -->
                                <?php if ($isAdmin): ?>
                                <td class="py-2 text-end pe-3">
                                    <div class="fs-4 d-flex justify-content-end gap-2">
                                        
                                        <!-- Login As User -->
                                        <a class="btn btn-sm btn-subtle-success shadow-none rounded-circle p-2" title="Login to this Account" href="users.php?phone=<?= base64_encode($data['phone']); ?>&login=1">
                                            <i class="bx bx-log-in-circle fs-6"></i>
                                        </a>
                                        
                                        <!-- Edit User -->
                                        <a class="btn btn-sm btn-subtle-primary shadow-none rounded-circle p-2" title="Edit Profile Details" href="EditUser.php?id=<?= base64_encode($u_id); ?>">
                                            <i class='bx bx-pencil fs-6'></i>
                                        </a>
                                        
                                        <!-- Reset Password -->
                                        <a class="btn btn-sm btn-subtle-info shadow-none rounded-circle p-2" title="Reset Password" href="users.php?id=<?= base64_encode($u_id); ?>&reset=1" onclick="return confirm('Generate a new random password for this user?');">
                                            <i class='bx bx-refresh fs-6'></i>
                                        </a>
                                        
                                        <!-- Suspend / Activate -->
                                        <?php if ($u_status == '0'): ?>
                                            <a class="btn btn-sm btn-subtle-warning shadow-none rounded-circle p-2" title="Account Suspended - Click to Activate" href="?apid=<?= base64_encode($u_id); ?>&approve=1">
                                                <i class='bx bx-check fs-6'></i>
                                            </a>
                                        <?php else: ?>
                                            <a class="btn btn-sm btn-subtle-secondary shadow-none rounded-circle p-2" title="Account Active - Click to Suspend" href="?banid=<?= base64_encode($u_id); ?>&ban=1">
                                                <i class='bx bx-block fs-6'></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <!-- Delete User -->
                                        <a class="btn btn-sm btn-subtle-danger shadow-none rounded-circle p-2" title="Delete User Permanently" href="?deleteuser=1&id=<?= base64_encode($u_id); ?>" onclick="return confirm('CRITICAL WARNING! This will permanently delete the user and all their records. Proceed?');">
                                            <i class='bx bx-trash fs-6'></i>
                                        </a>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php
                        }
                        
                        // No users logic (adjusting colspan dynamically based on Admin vs Non-Admin)
                        if ($count == 0) {
                            $colspan = $isAdmin ? 8 : 6;
                            echo '<tr><td colspan="'.$colspan.'" class="py-5 text-center"><div class="text-muted"><i class="fas fa-users-slash fa-3x mb-3 opacity-50"></i><h5 class="fw-bold text-600">No Users Found</h5><p class="fs-10">No active network members to display.</p></div></td></tr>';
                        }
                        
                    } else {
                        $colspan = $isAdmin ? 8 : 6;
                        echo '<tr><td colspan="'.$colspan.'" class="py-5 text-center"><div class="text-muted"><i class="fas fa-users-slash fa-3x mb-3 opacity-50"></i><h5 class="fw-bold text-600">No Users Found</h5><p class="fs-10">Your network directory is currently empty.</p></div></td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function(){
        // Live Search Logic
        $("#userSearch").on("keyup", function() {
            var value = $(this).val().toLowerCase();
            $("#userTableBody tr").filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });
    });
</script>

<style>
    .card { border-radius: 1rem; overflow: hidden; }
    .btn-xs { padding: 0.25rem 0.6rem; font-size: 0.75rem; border-radius: 0.3rem; }
    .gap-2 { gap: 0.4rem !important; }
    .fs-6 { font-size: 1.2rem !important; line-height: 1; }
    .btn-sm.rounded-circle { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; }
    
    /* Subtle Hover Colors for Action Buttons */
    .btn-subtle-success:hover { background-color: #198754; color: #fff !important; }
    .btn-subtle-primary:hover { background-color: #0d6efd; color: #fff !important; }
    .btn-subtle-info:hover { background-color: #0dcaf0; color: #fff !important; }
    .btn-subtle-warning:hover { background-color: #ffc107; color: #000 !important; }
    .btn-subtle-secondary:hover { background-color: #6c757d; color: #fff !important; }
    .btn-subtle-danger:hover { background-color: #dc3545; color: #fff !important; }
</style>

<?php include('footer.php'); ?>