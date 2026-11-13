<?php
// Session check aur token verification
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Config file include karein (isme checkSession function hona chahiye)
require_once('../includes/config.php'); 

// 1. Pehle basic session check karein
if (!isset($_SESSION['id']) || !isset($_SESSION['token'])) {
    header("Location: ../index.php");
    exit();
}

// 2. Token mismatch check (Database se compare karke)
$uid = mysqli_real_escape_string($DarkXen_conn, $_SESSION['id']);
$sql = "SELECT reset_token FROM users WHERE id = '$uid'";
$res = mysqli_query($DarkXen_conn, $sql);
$row = mysqli_fetch_assoc($res);

// Agar user delete ho gaya ho ya token match na kare
if (!$row || $row['reset_token'] !== $_SESSION['token']) {
    session_unset();
    session_destroy();
    header("Location: ../index.php?error=expired");
    exit();
}

// Agar sab sahi hai, to `checkSession()` call karein
checkSession();

// ✨ Role Based Logic Setup
$userRole = strtolower($udata['type'] ?? '');
$isAdmin = checkAdmin($udata['type'] ?? '');
// User Management sirf Admin, Distributor aur Super Distributor ko dikhega (Retailer ko nahi)
$canManageUsers = ($isAdmin || $userRole === 'distributor' || $userRole === 'super distributor');
?>

<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>
        <?php echo web('webname'); ?> | Dashboard
    </title>

    <link rel="apple-touch-icon" sizes="180x180" href="../assets/img/Screensho.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/Screensho.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/img/Screensho.png">
    <link rel="shortcut icon" type="image/x-icon" href="../assets/img/Screensho.png">
    <link rel="manifest" href="../assets/img/favicons/manifest.json">
    <meta name="msapplication-TileImage" content="../assets/img/Screensho.png">
    <meta name="theme-color" content="#ffffff">

    <script src="../assets/js/config.js"></script>
    <script src="../vendors/simplebar/simplebar.min.js"></script>
    <link href="../vendors/leaflet/leaflet.css" rel="stylesheet">
    <link href="../vendors/leaflet.markercluster/MarkerCluster.css" rel="stylesheet">
    <link href="../vendors/leaflet.markercluster/MarkerCluster.Default.css" rel="stylesheet">
    <link href="../vendors/flatpickr/flatpickr.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,500,600,700%7cPoppins:300,400,500,600,700,800,900&amp;display=swap" rel="stylesheet">
    <link href="../vendors/simplebar/simplebar.min.css" rel="stylesheet">
    <link href="../assets/css/theme-rtl.css" rel="stylesheet" id="style-rtl">
    <link href="../assets/css/theme.css" rel="stylesheet" id="style-default">
    <link href="../assets/css/user-rtl.css" rel="stylesheet" id="user-style-rtl">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Security Scripts -->
    <script>
        document.addEventListener('contextmenu', function (e) {
            e.preventDefault();
            window.close();
        });

        document.addEventListener('keydown', function (e) {
            if (e.ctrlKey && e.key === 'u' || // Ctrl+U
                (e.ctrlKey && e.shiftKey && e.key === 'I') || // Ctrl+Shift+I
                e.key === 'F12') { // F12
                window.close();
            }
        });

        function ctrlShiftKey(e, keyCode) {
            return e.ctrlKey && e.shiftKey && e.keyCode === keyCode.charCodeAt(0);
        }

        document.onkeydown = (e) => {
            if (
                event.keyCode === 123 ||
                ctrlShiftKey(e, 'I') ||
                ctrlShiftKey(e, 'J') ||
                ctrlShiftKey(e, 'C') ||
                (e.ctrlKey && e.keyCode === 'U'.charCodeAt(0))
            )
                return false;
        };
    </script>
    <script disable-devtool-auto src="https://cdn.jsdelivr.net/npm/disable-devtool@latest"></script>
    <script>
        var isRTL = JSON.parse(localStorage.getItem('isRTL'));
        if (isRTL) {
            var linkDefault = document.getElementById('style-default');
            var userLinkDefault = document.getElementById('user-style-default');
            linkDefault.setAttribute('disabled', true);
            userLinkDefault.setAttribute('disabled', true);
            document.querySelector('html').setAttribute('dir', 'rtl');
        } else {
            var linkRTL = document.getElementById('style-rtl');
            var userLinkRTL = document.getElementById('user-style-rtl');
            linkRTL.setAttribute('disabled', true);
            userLinkRTL.setAttribute('disabled', true);
        }
    </script>
</head>

<body>
    <main class="main" id="top">
        <div class="container" data-layout="container">
            <script>
                var isFluid = JSON.parse(localStorage.getItem('isFluid'));
                if (isFluid) {
                    var container = document.querySelector('[data-layout]');
                    container.classList.remove('container');
                    container.classList.add('container-fluid');
                }
            </script>

            <!-- Sidebar -->
            <nav class="navbar navbar-light navbar-vertical navbar-expand-xl">
                <script>
                    var navbarStyle = localStorage.getItem("navbarStyle");
                    if (navbarStyle && navbarStyle !== 'transparent') {
                        document.querySelector('.navbar-vertical').classList.add(`navbar-${navbarStyle}`);
                    }
                </script>
                <div class="d-flex align-items-center">
                    <div class="toggle-icon-wrapper">
                        <button class="btn navbar-toggler-humburger-icon navbar-vertical-toggle" data-bs-toggle="tooltip" data-bs-placement="left" title="Toggle Navigation">
                            <span class="navbar-toggle-icon"><span class="toggle-line"></span></span>
                        </button>
                    </div>
                    <a class="navbar-brand" href="index.php">
                        <div class="d-flex align-items-center py-3">
                            <span class="font-sans-serif text-primary fw-bold">
                                <?php echo web('webname'); ?>
                            </span>
                        </div>
                    </a>
                </div>

                <div class="collapse navbar-collapse" id="navbarVerticalCollapse">
                    <div class="navbar-vertical-content scrollbar">
                        <ul class="navbar-nav flex-column mb-3" id="navbarVerticalNav">

                            <li class="nav-item">
                                <a class="nav-link" href="index.php" role="button">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-home text-primary"></span></span>
                                        <span class="nav-link-text ps-1">Dashboard</span>
                                    </div>
                                </a>
                            </li>

                            <!-- 👥 NETWORK & USER MANAGEMENT (Admin, Distributor, Super Distributor) -->
                            <?php if ($canManageUsers) { ?>
                            <li class="nav-item">
                                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                    <div class="col-auto navbar-vertical-label">👥 Network Management</div>
                                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                                </div>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#User" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="User">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-users text-info"></span></span>
                                        <span class="nav-link-text ps-1">User Management</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="User">
                                    <li class="nav-item"><a class="nav-link" href="adduser.php">➕ User Entry</a></li>
                                    <li class="nav-item"><a class="nav-link" href="users.php">📋 User List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#Transfer" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="Transfer">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-exchange-alt text-success"></span></span>
                                        <span class="nav-link-text ps-1">Balance Transfer</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="Transfer">
                                    <li class="nav-item"><a class="nav-link" href="wallet_transfer.php">💸 Wallet Transfer</a></li>
                                </ul>
                            </li>
                            <?php } ?>

                            <!-- 🛡️ ADMIN ONLY SETTINGS -->
                            <?php if ($isAdmin) { ?>
                            <li class="nav-item">
                                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                    <div class="col-auto navbar-vertical-label">🛡️ Admin Management</div>
                                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                                </div>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#WEB" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="WEB">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-cogs text-danger"></span></span>
                                        <span class="nav-link-text ps-1">Website Settings</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="WEB">
                                    <li class="nav-item"><a class="nav-link" href="notification.php">💬 Popup SMS</a></li>
                                    <li class="nav-item"><a class="nav-link" href="complaint_request.php">⚠️ Complaints</a></li>
                                    <li class="nav-item"><a class="nav-link" href="pricing.php">💲 Set Pricing</a></li>
                                    <li class="nav-item"><a class="nav-link" href="settings.php">🔧 Website Details</a></li>
                                </ul>
                            </li>
                            <?php } ?>

                            <!-- 🆔 AADHAAR SERVICES -->
                            <li class="nav-item">
                                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                    <div class="col-auto navbar-vertical-label text-warning">🆔 AADHAAR SERVICES</div>
                                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                                </div>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#aadharotp" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="aadharotp">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-file-pdf text-warning"></span></span>
                                        <span class="nav-link-text ps-1">Aadhaar PDF</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="aadharotp">
                                    <li class="nav-item"><a class="nav-link" href="aadhaar_pdf.php">➕ Apply Entry</a></li>
                                    <li class="nav-item"><a class="nav-link" href="aadhaar_pdf_list.php">📋 PDF List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#panaadhaarFull" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="panaadhaarFull">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-info-circle text-info"></span></span>
                                        <span class="nav-link-text ps-1">Aadhaar Details</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="panaadhaarFull">
                                    <li class="nav-item"><a class="nav-link" href="aadhaar_details.php">👁️ Get Details</a></li>
                                    <li class="nav-item"><a class="nav-link" href="aadhaar_details_list.php">📊 Details List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#Abhacard" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="Abhacard">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-notes-medical text-danger"></span></span>
                                        <span class="nav-link-text ps-1">Abha Card </span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="Abhacard">
                                    <li class="nav-item"><a class="nav-link" href="abha_card_verification.php">👁️ Get PDF</a></li>
                                    <li class="nav-item"><a class="nav-link" href="abha_card_list.php">📊 PDF List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#aadharMan" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="aadharMan">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-edit text-primary"></span></span>
                                        <span class="nav-link-text ps-1">Aadhaar Manual</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="aadharMan">
                                    <li class="nav-item"><a class="nav-link" href="aadhaar_manual.php">➕ Manual Entry</a></li>
                                    <li class="nav-item"><a class="nav-link" href="aadharmanual_list.php">📋 Manual List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#npci" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="npci">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-university text-success"></span></span>
                                        <span class="nav-link-text ps-1">Aadhaar NPCI Status</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="npci">
                                    <li class="nav-item"><a class="nav-link" href="npci_status.php">🔍 Check NPCI</a></li>
                                    <li class="nav-item"><a class="nav-link" href="npci_status_list.php">📊 NPCI List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#aadharVer" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="aadharVer">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-check-circle text-success"></span></span>
                                        <span class="nav-link-text ps-1">Aadhaar Verify</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="aadharVer">
                                    <li class="nav-item"><a class="nav-link" href="aadhaar_verify.php">🔍 Verify Aadhaar</a></li>
                                    <li class="nav-item"><a class="nav-link" href="aadhaar_verify_list.php">📊 Verify List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#eidStatusMenu" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="eidStatusMenu">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-thumbtack text-warning"></span></span>
                                        <span class="nav-link-text ps-1">Enrollment Status</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="eidStatusMenu">
                                    <li class="nav-item"><a class="nav-link" href="aadhaar_eid_status.php">🔎 Check EID Status</a></li>
                                    <li class="nav-item"><a class="nav-link" href="aadhaar_eid_status_list.php">📑 EID Status List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#aadharPanLink" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="aadharPanLink">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-link text-info"></span></span>
                                        <span class="nav-link-text ps-1">Aadhaar-PAN Link</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="aadharPanLink">
                                    <li class="nav-item"><a class="nav-link" href="aadhaar_pan_link_status.php">🔍 Check Link Status</a></li>
                                    <li class="nav-item"><a class="nav-link" href="aadhaar_pan_link_list.php">📑 Link Status List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#aadharMsk" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="aadharMsk">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-user-secret text-danger"></span></span>
                                        <span class="nav-link-text ps-1">Aadhaar Mask PAN</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="aadharMsk">
                                    <li class="nav-item"><a class="nav-link" href="aadhaar_mask.php">🔒 PAN Mask</a></li>
                                    <li class="nav-item"><a class="nav-link" href="aadhaar_mask_list.php">📂 Mask List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#Panaadhaar" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="Panaadhaar">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-id-badge text-primary"></span></span>
                                        <span class="nav-link-text ps-1">Aadhaar to PAN</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="Panaadhaar">
                                    <li class="nav-item"><a class="nav-link" href="aadhar_to_pan.php">🔎 Find PAN</a></li>
                                    <li class="nav-item"><a class="nav-link" href="aadhar_to_pan_list.php">📑 PAN List</a></li>
                                </ul>
                            </li>

                            <!-- 💳 PAN SERVICES -->
                            <li class="nav-item">
                                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                    <div class="col-auto navbar-vertical-label text-primary">💳 PAN SERVICES</div>
                                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                                </div>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#panAdv" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="panAdv">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-bolt text-warning"></span></span>
                                        <span class="nav-link-text ps-1">PAN Advance</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="panAdv">
                                    <li class="nav-item"><a class="nav-link" href="pan_advance_print.php">🖨️ Advance PDF</a></li>
                                    <li class="nav-item"><a class="nav-link" href="pan_manual_list.php">📋 PDF List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#panMan" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="panMan">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-keyboard text-success"></span></span>
                                        <span class="nav-link-text ps-1">PAN Manual</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="panMan">
                                    <li class="nav-item"><a class="nav-link" href="pan_manual.php">✍️ Manual Apply</a></li>
                                    <li class="nav-item"><a class="nav-link" href="pan_manual_list.php">📂 Manual List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#aadhaarPan" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="aadhaarPan">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-search text-danger"></span></span>
                                        <span class="nav-link-text ps-1">PAN to Aadhaar</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="aadhaarPan">
                                    <li class="nav-item"><a class="nav-link" href="pan_to_aadhar.php">🔎 Find ID</a></li>
                                    <li class="nav-item"><a class="nav-link" href="pan_to_aadhar_list.php">📑 ID List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#moPan" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="moPan">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-mobile-alt text-info"></span></span>
                                        <span class="nav-link-text ps-1">Mobile to PAN</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="moPan">
                                    <li class="nav-item"><a class="nav-link" href="mobile_to_pan.php">🔎 Find PAN</a></li>
                                    <li class="nav-item"><a class="nav-link" href="mobile_to_pan_list.php">📑 PAN List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#oPan" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="oPan">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-sms text-primary"></span></span>
                                        <span class="nav-link-text ps-1">Pan To Aadhar Mask</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="oPan">
                                    <li class="nav-item"><a class="nav-link" href="pan_to_aadharmask.php">🔎 Find ID</a></li>
                                    <li class="nav-item"><a class="nav-link" href="pan_to_aadharmask_list.php">📑 PAN List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#panFull" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="panFull">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-info-circle text-success"></span></span>
                                        <span class="nav-link-text ps-1">PAN Full Details</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="panFull">
                                    <li class="nav-item"><a class="nav-link" href="pan_full_details.php">👁️ Get Details</a></li>
                                    <li class="nav-item"><a class="nav-link" href="pan_full_details_list.php">📊 Details List</a></li>
                                </ul>
                            </li>

                            <!-- 🏛️ GST SERVICE -->
                            <li class="nav-item">
                                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                    <div class="col-auto navbar-vertical-label text-success">🏛️ GST SERVICES</div>
                                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                                </div>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#PanGST" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="PanGST">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-university text-primary"></span></span>
                                        <span class="nav-link-text ps-1">Pan To GST</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="PanGST">
                                    <li class="nav-item"><a class="nav-link" href="gst_info.php">🔎 Find GST</a></li>
                                </ul>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#gstPan" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="gstPan">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-university text-success"></span></span>
                                        <span class="nav-link-text ps-1">GST To Pan</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="gstPan">
                                    <li class="nav-item"><a class="nav-link" href="gst_info.php">🔎 Find GST</a></li>
                                </ul>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#gstInfo" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="gstInfo">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-university text-danger"></span></span>
                                        <span class="nav-link-text ps-1">GST Information</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="gstInfo">
                                    <li class="nav-item"><a class="nav-link" href="gst_info.php">🔎 Find GST</a></li>
                                </ul>
                            </li>

                            <!-- 🛠️ E SHRAM SERVICE -->
                            <li class="nav-item">
                                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                    <div class="col-auto navbar-vertical-label text-danger">🛠️ E-SHRAM SERVICES</div>
                                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                                </div>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#eshrampdf" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="eshrampdf">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-id-card text-success"></span></span>
                                        <span class="nav-link-text ps-1">E-Shram Card Download</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="eshrampdf">
                                    <li class="nav-item"><a class="nav-link" href="eshram_pdf.php">🖨️ Apply</a></li>
                                    <li class="nav-item"><a class="nav-link" href="eshram_pdf_list.php">📑 List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#eshramlink" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="eshramlink">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-mobile-alt text-warning"></span></span>
                                        <span class="nav-link-text ps-1">Mobile Number Update</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="eshramlink">
                                    <li class="nav-item"><a class="nav-link" href="eshram_mobile.php">🖨 Apply PDF </a></li>
                                    <li class="nav-item"><a class="nav-link" href="eshram_mobile_list.php">📑 PDF List</a></li>
                                </ul>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#eshramfind" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="eshramfind">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-search-plus text-info"></span></span>
                                        <span class="nav-link-text ps-1">E-Shram Details Find</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="eshramfind">
                                    <li class="nav-item"><a class="nav-link" href="eshram_details.php">🖨 Apply PDF </a></li>
                                    <li class="nav-item"><a class="nav-link" href="eshram_details_list.php">📑 PDF List</a></li>
                                </ul>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#eshramUAN" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="eshramUAN">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-id-badge text-danger"></span></span>
                                        <span class="nav-link-text ps-1">Find UAN By Aadhaar</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="eshramUAN">
                                    <li class="nav-item"><a class="nav-link" href="eshram_uan_find.php">🖨 Find Now </a></li>
                                    <li class="nav-item"><a class="nav-link" href="eshram_uan_list.php">📑 UAN List</a></li>
                                </ul>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#mobileUAN" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="mobileUAN">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-phone-square text-success"></span></span>
                                        <span class="nav-link-text ps-1">Find UAN By Mobile No</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="mobileUAN">
                                    <li class="nav-item"><a class="nav-link" href="mobile_uan_find.php">🖨 Find UAN </a></li>
                                    <li class="nav-item"><a class="nav-link" href="mobile_uan_list.php">📑 UAN List</a></li>
                                </ul>
                            </li>

                            <!-- 🏭 UDHYAM SERVICES -->
                            <li class="nav-item">
                                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                    <div class="col-auto navbar-vertical-label text-info">🏭 UDHYAM SERVICES</div>
                                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                                </div>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#panudhyam" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="panudhyam">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-industry text-primary"></span></span>
                                        <span class="nav-link-text ps-1">Pan To Udhyam Find</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="panudhyam">
                                    <li class="nav-item"><a class="nav-link" href="pan_to_udyam.php">🖨️ Udhyam Number Find </a></li>
                                    <li class="nav-item"><a class="nav-link" href="pan_to_udyam_list.php">📋 Udhyam List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#udhyanfind" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="udhyanfind">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-edit text-success"></span></span>
                                        <span class="nav-link-text ps-1">Udhyam Details Find</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="udhyanfind">
                                    <li class="nav-item"><a class="nav-link" href="udyam_to_details.php">📝 Find Details</a></li>
                                    <li class="nav-item"><a class="nav-link" href="udyam_details_list.php">📂 Details List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#udhyampdf" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="udhyampdf">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-file-pdf text-danger"></span></span>
                                        <span class="nav-link-text ps-1">Udhyam PDF Download</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="udhyampdf">
                                    <li class="nav-item"><a class="nav-link" href="udyam_pdf.php">📥 Fetch PDF</a></li>
                                    <li class="nav-item"><a class="nav-link" href="udyam_pdf_list.php">📑 PDF List</a></li>
                                </ul>
                            </li>

                            <!-- 🗳️ VOTER SERVICES -->
                            <li class="nav-item">
                                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                    <div class="col-auto navbar-vertical-label text-primary">🗳️ VOTER SERVICES</div>
                                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                                </div>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#voterAdv" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="voterAdv">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-bolt text-warning"></span></span>
                                        <span class="nav-link-text ps-1">Voter Advance</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="voterAdv">
                                    <li class="nav-item"><a class="nav-link" href="voter_advance.php">🖨️ Advance PDF</a></li>
                                    <li class="nav-item"><a class="nav-link" href="voter_advance_list.php">📋 Advance List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#voterMan" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="voterMan">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-edit text-success"></span></span>
                                        <span class="nav-link-text ps-1">Voter Manual</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="voterMan">
                                    <li class="nav-item"><a class="nav-link" href="voter_manual.php">📝 Apply Manual</a></li>
                                    <li class="nav-item"><a class="nav-link" href="voter_manual_list.php">📂 Manual List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#voterPdfWithoutotp" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="voterPdfWithoutotp">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-file-pdf text-info"></span></span>
                                        <span class="nav-link-text ps-1">Voter PDF Without OTP</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="voterPdfWithoutotp">
                                    <li class="nav-item"><a class="nav-link" href="voter_pdf_without_otp.php">📥 Fetch PDF</a></li>
                                    <li class="nav-item"><a class="nav-link" href="voter_pdf_without_otp_list.php">📑 PDF List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#voterPdf" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="voterPdf">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-file-pdf text-danger"></span></span>
                                        <span class="nav-link-text ps-1">Voter PDF</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="voterPdf">
                                    <li class="nav-item"><a class="nav-link" href="voter_pdf.php">📥 Fetch PDF</a></li>
                                    <li class="nav-item"><a class="nav-link" href="voter_pdf_list.php">📑 PDF List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#voterLink" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="voterLink">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-link text-warning"></span></span>
                                        <span class="nav-link-text ps-1">Voter Mobile Link</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="voterLink">
                                    <li class="nav-item"><a class="nav-link" href="voter_mobile_link.php">📱 Link Mobile Number</a></li>
                                    <li class="nav-item"><a class="nav-link" href="voter_mobile_link_list.php">📊 Link List</a></li>
                                </ul>
                            </li>

                            <!-- 🚗 DL & RC SERVICES -->
                            <li class="nav-item">
                                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                    <div class="col-auto navbar-vertical-label text-dark">🚗 DL & RC SERVICES</div>
                                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                                </div>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#dlPrint" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="dlPrint">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-id-card text-success"></span></span>
                                        <span class="nav-link-text ps-1">DL Advance</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="dlPrint">
                                    <li class="nav-item"><a class="nav-link" href="dl_advance.php">🖨️ Apply</a></li>
                                    <li class="nav-item"><a class="nav-link" href="dl_advance_list.php">📑 List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#dlPDF" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="dlPDF">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-id-card text-warning"></span></span>
                                        <span class="nav-link-text ps-1">DL Licence PDF</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="dlPDF">
                                    <li class="nav-item"><a class="nav-link" href="dl_pdf.php">🖨 Apply PDF </a></li>
                                    <li class="nav-item"><a class="nav-link" href="dl_pdf_list.php">📑 PDF List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#llPDF" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="llPDF">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-id-card text-primary"></span></span>
                                        <span class="nav-link-text ps-1">Learning Licence PDF</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="llPDF">
                                    <li class="nav-item"><a class="nav-link" href="ll_pdf.php">🖨 Apply PDF </a></li>
                                    <li class="nav-item"><a class="nav-link" href="ll_pdf_list.php">📑 PDF List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#llexam" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="llexam">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-check-circle text-danger"></span></span>
                                        <span class="nav-link-text ps-1">Learning Exam Pass</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="llexam">
                                    <li class="nav-item"><a class="nav-link" href="ll_exam.php">🖨 Apply Exam </a></li>
                                    <li class="nav-item"><a class="nav-link" href="ll_exam_list.php">📑 Check Exam List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#rcPrint" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="rcPrint">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-car text-info"></span></span>
                                        <span class="nav-link-text ps-1">Vehicle RC PDF</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="rcPrint">
                                    <li class="nav-item"><a class="nav-link" href="rc_pro.php">🖨️ Apply PDF </a></li>
                                    <li class="nav-item"><a class="nav-link" href="rc_pro2.php">🖨️ Apply PDF (Father's Name)</a></li>
                                    <li class="nav-item"><a class="nav-link" href="rc_pro_list.php">📑 PDF List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#rcfind" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="rcfind">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-car text-success"></span></span>
                                        <span class="nav-link-text ps-1">Vehicle RC Details</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="rcfind">
                                    <li class="nav-item"><a class="nav-link" href="rc_deatils.php">🖨️ Find Details </a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#rcmob" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="rcmob">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-car text-warning"></span></span>
                                        <span class="nav-link-text ps-1">Vehicle To Mobile No </span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="rcmob">
                                    <li class="nav-item"><a class="nav-link" href="rc_to_mobile.php">🖨️ Find Details </a></li>
                                    <li class="nav-item"><a class="nav-link" href="rc_mobile_list.php">📑 Find List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#mobrc" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="mobrc">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-car text-primary"></span></span>
                                        <span class="nav-link-text ps-1">Mobile No To Vehicle</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="mobrc">
                                    <li class="nav-item"><a class="nav-link" href="mobile_to_rc.php">🖨️ Find Details </a></li>
                                    <li class="nav-item"><a class="nav-link" href="mobile_to_rc_list.php">📑 Find List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#mobrcdeatils" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="mobrcdeatils">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-car text-danger"></span></span>
                                        <span class="nav-link-text ps-1">Mobile No Vehicle Details</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="mobrcdeatils">
                                    <li class="nav-item"><a class="nav-link" href="mobile_to_rc_details.php">🖨️ Find Details </a></li>
                                    <li class="nav-item"><a class="nav-link" href="mobile_rc_details_list.php">📑 Find List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#challan" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="challan">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-balance-scale text-success"></span></span>
                                        <span class="nav-link-text ps-1">Vehicle Challan </span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="challan">
                                    <li class="nav-item"><a class="nav-link" href="challan_check.php">🔍 Check Challan</a></li>
                                    <li class="nav-item"><a class="nav-link" href="vehicle_challan_list.php">📋 Challan List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#RTO" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="RTO">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-building text-info"></span></span>
                                        <span class="nav-link-text ps-1">Vehicle RTO </span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="RTO">
                                    <li class="nav-item"><a class="nav-link" href="rto_check.php">🔍 Check RTO</a></li>
                                    <li class="nav-item"><a class="nav-link" href="rto_list.php">📂 RTO List</a></li>
                                </ul>
                            </li>

                            <!-- 🌾 RATION SERVICE -->
                            <li class="nav-item">
                                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                    <div class="col-auto navbar-vertical-label text-warning">🌾 RATION SERVICES</div>
                                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                                </div>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#ratCard" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="ratCard">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-shopping-basket text-primary"></span></span>
                                        <span class="nav-link-text ps-1">Ration Card PDF</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="ratCard">
                                    <li class="nav-item"><a class="nav-link" href="ration_card.php">📑 Ration PDF </a></li>
                                    <li class="nav-item"><a class="nav-link" href="ration_pdf_list.php">📑 Ration List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#upRat" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="upRat">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-id-card text-success"></span></span>
                                        <span class="nav-link-text ps-1">UP Ration to Aadhaar</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="upRat">
                                    <li class="nav-item"><a class="nav-link" href="up_ration_aadhar.php">📑 Find Aadhaar</a></li>
                                    <li class="nav-item"><a class="nav-link" href="up_ration_aadhar_list.php">📑 Aadhaar List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#allstate" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="allstate">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-map-marked-alt text-danger"></span></span>
                                        <span class="nav-link-text ps-1">All State Ration to Aadhaar</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="allstate">
                                    <li class="nav-item"><a class="nav-link" href="all_state_ration_aadhar.php">📑 Find Aadhaar </a></li>
                                    <li class="nav-item"><a class="nav-link" href="all_state_ration_aadhar_list.php">📑 Aadhaar List</a></li>
                                </ul>
                            </li>

                            <!-- 🏛️ GOVT & BANK SERVICE -->
                            <li class="nav-item">
                                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                    <div class="col-auto navbar-vertical-label text-success">🏛️ GOVT & BANK SERVICES</div>
                                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                                </div>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#farmeStatus" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="farmeStatus">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-tractor text-success"></span></span>
                                        <span class="nav-link-text ps-1">Farmer Status</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="farmeStatus">
                                    <li class="nav-item"><a class="nav-link" href="FarmerStatus.php">📊 Status Check</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#farmerPdf" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="farmerPdf">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-file-pdf text-danger"></span></span>
                                        <span class="nav-link-text ps-1">Farmer PDF</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="farmerPdf">
                                    <li class="nav-item"><a class="nav-link" href="FarmerPdf.php">📥 Generate PDF</a></li>
                                    <li class="nav-item"><a class="nav-link" href="FarmerPdf_List.php">📑 PDF List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#bill" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="bill">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-bolt text-warning"></span></span>
                                        <span class="nav-link-text ps-1">Electricity Bill</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="bill">
                                    <li class="nav-item"><a class="nav-link" href="Bill_To_find.php">🔍 Find Bill</a></li>
                                    <li class="nav-item"><a class="nav-link" href="Bill_To_find_List.php">📊 Bill List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#RTPS" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="RTPS">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-flag text-primary"></span></span>
                                        <span class="nav-link-text ps-1">RTPS Bihar</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="RTPS">
                                    <li class="nav-item"><a class="nav-link" href="RTPS_To_find.php">🔍 Find RTPS</a></li>
                                    <li class="nav-item"><a class="nav-link" href="RTPS_To_find_List.php">📂 RTPS List</a></li>
                                </ul>
                            </li>

                            <!-- 🎁 OTHER SERVICES -->
                            <li class="nav-item">
                                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                    <div class="col-auto navbar-vertical-label text-info">🎁 OTHER SERVICES</div>
                                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                                </div>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#ifscInfo" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="ifscInfo">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-university text-primary"></span></span>
                                        <span class="nav-link-text ps-1">IFSC Info</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="ifscInfo">
                                    <li class="nav-item"><a class="nav-link" href="ifsc-info.php">🔎 Find IFSC</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#numberInfo" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="numberInfo">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-phone-alt text-success"></span></span>
                                        <span class="nav-link-text ps-1">Number Info</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="numberInfo">
                                    <li class="nav-item"><a class="nav-link" href="number-info.php">📋 Number Detail</a></li>
                                    <li class="nav-item"><a class="nav-link" href="number_info_list.php">📋 Detail List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link dropdown-indicator" href="#pincodeInfo" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="pincodeInfo">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-map-pin text-danger"></span></span>
                                        <span class="nav-link-text ps-1">Pincode Info</span>
                                    </div>
                                </a>
                                <ul class="nav collapse" id="pincodeInfo">
                                    <li class="nav-item"><a class="nav-link" href="pinecode-info.php">🗺️ Pincode Detail</a></li>
                                    <li class="nav-item"><a class="nav-link" href="pincode_history_list.php">🗺️ Detail List</a></li>
                                </ul>
                            </li>

                            <li class="nav-item">
                                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                                    <div class="col-auto navbar-vertical-label text-danger">HELP & SUPPORT</div>
                                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                                </div>
                                <a class="nav-link" href="helpdesk.php" role="button">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-icon"><span class="fas fa-headset text-danger"></span></span>
                                        <span class="nav-link-text ps-1">Customer Support</span>
                                    </div>
                                </a>
                            </li>
                        </ul>

                        <div class="settings my-3">
                            <div class="card shadow-none">
                                <div class="card-body alert mb-0" role="alert">
                                    <div class="btn-close-falcon-container">
                                        <button class="btn btn-link btn-close-falcon p-0" aria-label="Close" data-bs-dismiss="alert"></button>
                                    </div>
                                    <div class="text-center">
                                        <p class="fs-11 mt-2">Welcome to <strong class="text-primary"><?php echo web('webname'); ?></strong></p>
                                        <div class="d-grid"><a class="btn btn-sm btn-primary" href="helpdesk.php">🎫 Open Ticket</a></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="content">
                <nav class="navbar navbar-light navbar-glass navbar-top navbar-expand">
                    <button class="btn navbar-toggler-humburger-icon navbar-toggler me-1 me-sm-3" type="button" data-bs-toggle="collapse" data-bs-target="#navbarVerticalCollapse" aria-controls="navbarVerticalCollapse" aria-expanded="false" aria-label="Toggle Navigation">
                        <span class="navbar-toggle-icon"><span class="toggle-line"></span></span>
                    </button>
                    <a class="navbar-brand me-1 me-sm-3" href="index.php">
                        <div class="d-flex align-items-center">
                            <img class="me-2" src="../assets/img/724fl6724fl6724f.png" alt="" width="40" style="object-fit: contain;" />
                            <span class="font-sans-serif text-primary fw-bold">
                                <?php echo web('webname'); ?>
                            </span>
                        </div>
                    </a>

                    <ul class="navbar-nav align-items-center d-none d-lg-block">
                        <li class="nav-item">
                            <div class="search-box">
                                <form class="position-relative">
                                    <input class="form-control search-input" type="search" placeholder="🔍 Search..." aria-label="Search">
                                </form>
                            </div>
                        </li>
                    </ul>

                    <ul class="navbar-nav navbar-nav-icons ms-auto flex-row align-items-center">

                        <!-- Wallet Button -->
                        <li class="nav-item px-2">
                            <a class="btn btn-primary btn-sm fw-bold d-flex align-items-center shadow-sm" href="addwallet.php" style="background-color: #0d6efd; border: none; border-radius: 6px; padding: 0.35rem 0.6rem; font-size: 14px;">
                                <span class="fas fa-wallet" style="margin-top: 1px;"></span><span class="ms-1">: ₹<?= round($udata['balance'], 2) ?></span>
                            </a>
                        </li>

                        <!-- Theme Switcher -->
                        <li class="nav-item ps-2 pe-0">
                            <div class="dropdown theme-control-dropdown">
                                <a class="nav-link d-flex align-items-center dropdown-toggle fs-9 pe-1 py-0" href="#" role="button" id="themeSwitchDropdown" data-bs-toggle="dropdown">
                                    <span class="fas fa-sun fs-7" data-theme-dropdown-toggle-icon="light"></span>
                                    <span class="fas fa-moon fs-7" data-theme-dropdown-toggle-icon="dark"></span>
                                    <span class="fas fa-adjust fs-7" data-theme-dropdown-toggle-icon="auto"></span>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end border py-0 mt-3">
                                    <div class="bg-white dark__bg-1000 rounded-2 py-2">
                                        <button class="dropdown-item d-flex align-items-center gap-2" type="button" data-theme-control="theme" value="light"><span class="fas fa-sun"></span> Light</button>
                                        <button class="dropdown-item d-flex align-items-center gap-2" type="button" data-theme-control="theme" value="dark"><span class="fas fa-moon"></span> Dark</button>
                                        <button class="dropdown-item d-flex align-items-center gap-2" type="button" data-theme-control="theme" value="auto"><span class="fas fa-adjust"></span> Auto</button>
                                    </div>
                                </div>
                            </div>
                        </li>

                        <!-- Profile Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link pe-0 ps-2" id="navbarDropdownUser" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <div class="avatar avatar-xl">
                                    <img class="rounded-circle" src="../assets/img/3-thumb.png" alt="User" />
                                </div>
                            </a>
                            <div class="dropdown-menu dropdown-caret dropdown-menu-end py-0" aria-labelledby="navbarDropdownUser">
                                <div class="bg-white dark__bg-1000 rounded-2 py-2">
                                    <a class="dropdown-item fw-bold text-warning" href="#!"><span class="fas fa-crown me-1"></span><span>Go Pro</span></a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="profile.php">👤 Profile & Account</a>
                                    <a class="dropdown-item" href="helpdesk.php">🎫 HelpDesk</a>
                                    <a class="dropdown-item text-success fw-medium" href="https://whatsapp.com/channel/<?php echo web('channel'); ?>" target="_blank">
                                        <span class="fab fa-whatsapp me-1"></span>Follow WhatsApp Channel
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger fw-bold" href="logout.php">🚪 Logout</a>
                                </div>
                            </div>
                        </li>
                    </ul>
                </nav>