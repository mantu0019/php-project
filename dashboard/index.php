<?php
// ==========================================
//    DARKXEN API - ULTRA PREMIUM CORE
// ==========================================
session_start();
include('header.php'); // Your header handles DB connection normally

// --- FETCH GLOBAL SETTINGS FOR NOTIFICATIONS & API ---
$settingsRes = mysqli_query($DarkXen_conn, "SELECT * FROM settings WHERE id=1");
$webdata = mysqli_fetch_assoc($settingsRes);
$adminNotice = $webdata['popap'] ?? $webdata['popupalert'] ?? 'Welcome to Premium DarkXen System Services. Experience the ultimate performance!';

// --- READ CURRENT RUNNING VERSION FROM current_version.txt ---
$versionFile = __DIR__ . '/../update/current_version.txt';
$currentVersion = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1.0.0';
$currentAppVersion = $currentVersion; 

// --- UPDATE API URL ---
$updateApiUrl ="https://update.darkxen.in/api/v1/update_info.json";

// --- FETCH UPDATE INFO ---
function fetchUpdateData($url){
    $ch = curl_init();
    curl_setopt_array($ch,[
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res ?: false;
}

$updateInfoJson = fetchUpdateData($updateApiUrl);
$nextUpdate = null;
if ($updateInfoJson !== false) {
    $updateData = json_decode($updateInfoJson, true);
    if (isset($updateData['updates']) && is_array($updateData['updates'])) {
        foreach ($updateData['updates'] as $update) {
            if (isset($update['version']) && version_compare($update['version'], $currentVersion, '>')) {
                $nextUpdate = $update;
                break;
            }
        }
    }
}
$updateAvailable = $nextUpdate !== null;

// --- DARKXEN ADMIN API BALANCE LOGIC ---
$buttonText = "Check Balance";
$btnClass = "dx-btn-dark-premium";
$iconClass = "fa-sync-alt";

if (isset($_POST['check_balance'])) {
    $DarkXen_url = base64_decode('ZGFya3hlbi5pbg=='); 
    $DarkXen_apikey = $webdata['api_key'] ?? ''; 

    $apiUrl = "https://" . $DarkXen_url . "/api/v3/Balance_info.php?apiKey=" . urlencode($DarkXen_apikey);
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => $apiUrl,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_SSL_VERIFYHOST => 0
    ));
    
    $responseApi = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if ($err) {
        $buttonText = "API Request Failed";
        $btnClass = "btn-danger";
        $iconClass = "fa-exclamation-triangle";
    } else {
        $response = json_decode($responseApi, true);
        if(isset($response['status']) && $response['status'] === "success" && isset($response['balance'])) {
            $buttonText = "✨ Api Wallet : ₹" . $response['balance'];
            $btnClass = "btn-success";
            $iconClass = "fa-check-circle";
        } else {
            $buttonText = "API Request Failed";
            $btnClass = "btn-danger";
            $iconClass = "fa-exclamation-triangle";
        }
    }
}

// --- WALLET HISTORY LOGIC ---
$today = date('Y-m-d');
$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';

if (!empty($from_date) && !empty($to_date)) {
    $query = "SELECT * FROM wallethistory WHERE DATE(date) BETWEEN '" . mysqli_real_escape_string($DarkXen_conn, $from_date) . "' 
              AND '" . mysqli_real_escape_string($DarkXen_conn, $to_date) . "'";
} else {
    $query = "SELECT * FROM wallethistory WHERE DATE(date) = '$today'";
}

if (isset($udata['type']) && $udata['type'] != 'admin') {
    $query .= " AND userid='" . mysqli_real_escape_string($DarkXen_conn, $udata['phone']) . "'";
}

$query .= " ORDER BY id DESC";
$res = mysqli_query($DarkXen_conn, $query);

$has_data = mysqli_num_rows($res) > 0;
$rows = [];
if ($has_data) {
    while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; }
}
?>
<script>
    if(!localStorage.getItem('dx_ultra_theme')) {
        localStorage.setItem('theme', 'light');
        localStorage.setItem('isFluid', 'true');
        localStorage.setItem('navbarStyle', 'card');
        localStorage.setItem('dx_ultra_theme', 'yes');
        window.location.reload(); 
    }
</script>
<?php

// --- HEAVY COLOR GRADED PREMIUM SERVICE CARD ---
function renderService($title, $icon, $img, $badge, $applyLink, $listLink = '', $color = 'primary') {
    // Brand Color Palettes for Heavy Grading
    $themes = [
        'primary' => ['border'=>'#4f46e5', 'bg1'=>'#eef2ff', 'bg2'=>'#c7d2fe', 'text'=>'#312e81', 'glow'=>'rgba(79,70,229,0.5)', 'btn'=>'linear-gradient(135deg, #4f46e5, #3730a3)'],
        'success' => ['border'=>'#10b981', 'bg1'=>'#ecfdf5', 'bg2'=>'#a7f3d0', 'text'=>'#064e3b', 'glow'=>'rgba(16,185,129,0.5)', 'btn'=>'linear-gradient(135deg, #10b981, #047857)'],
        'danger'  => ['border'=>'#f43f5e', 'bg1'=>'#fff1f2', 'bg2'=>'#fecdd3', 'text'=>'#881337', 'glow'=>'rgba(244,63,94,0.5)', 'btn'=>'linear-gradient(135deg, #f43f5e, #be123c)'],
        'warning' => ['border'=>'#f59e0b', 'bg1'=>'#fffbeb', 'bg2'=>'#fde68a', 'text'=>'#78350f', 'glow'=>'rgba(245,158,11,0.5)', 'btn'=>'linear-gradient(135deg, #f59e0b, #b45309)'],
        'info'    => ['border'=>'#0ea5e9', 'bg1'=>'#f0f9ff', 'bg2'=>'#bae6fd', 'text'=>'#0c4a6e', 'glow'=>'rgba(14,165,233,0.5)', 'btn'=>'linear-gradient(135deg, #0ea5e9, #0369a1)'],
        'dark'    => ['border'=>'#475569', 'bg1'=>'#f8fafc', 'bg2'=>'#cbd5e1', 'text'=>'#0f172a', 'glow'=>'rgba(71,85,105,0.5)', 'btn'=>'linear-gradient(135deg, #475569, #1e293b)']
    ];
    $t = $themes[$color] ?? $themes['primary'];
    ?>
    <div class="col-6 col-sm-4 col-md-3 col-xl-2 mb-4 px-2">
        <div class="card h-100 dx-heavy-card" style="
            --dx-border: <?= $t['border'] ?>; 
            --dx-bg1: <?= $t['bg1'] ?>; 
            --dx-bg2: <?= $t['bg2'] ?>; 
            --dx-text: <?= $t['text'] ?>; 
            --dx-glow: <?= $t['glow'] ?>;
            --dx-btn: <?= $t['btn'] ?>;
            border: 2px dashed <?= $t['border'] ?> !important;">
            
            <div class="card-body p-3 d-flex flex-column justify-content-between position-relative z-index-1">
                <!-- Top Badge & Icon -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="badge rounded-pill dx-badge shadow-sm" style="background: rgba(255,255,255,0.7); color: <?= $t['border'] ?>; border: 1px solid <?= $t['bg2'] ?>;"><?= $badge; ?></span>
                    <div class="dx-icon-wrapper shadow-sm" style="background: rgba(255,255,255,0.9);">
                        <i class="fas <?= $icon; ?>" style="color: <?= $t['border'] ?>; font-size: 13px;"></i>
                    </div>
                </div>
                
                <!-- Image -->
                <div class="text-center py-2 position-relative">
                    <div class="dx-img-glow" style="background: <?= $t['border'] ?>;"></div>
                    <img src="../assets/img/<?= $img; ?>" alt="<?= $title; ?>" onerror="this.src='../assets/img/default.png'" class="dx-img-heavy">
                </div>
                
                <!-- Title -->
                <div class="text-center mt-3">
                    <h6 class="fw-black mb-0 dx-heavy-title" style="color: <?= $t['text'] ?>;" title="<?= $title; ?>"><?= $title; ?></h6>
                </div>
                
                <!-- Buttons -->
                <div class="d-flex gap-2 mt-3 action-buttons">
                    <a href="<?= $applyLink; ?>" class="btn btn-sm flex-grow-1 fw-bold rounded-3 shadow-lg dx-apply-btn text-white" style="background: <?= $t['btn'] ?>;">
                        <i class="fas fa-bolt me-1 text-warning"></i> Apply
                    </a>
                    <?php if (!empty($listLink) && $listLink !== '#') { ?>
                        <a href="<?= $listLink; ?>" class="btn btn-light btn-sm px-2 rounded-3 border shadow-sm dx-list-btn" style="color: <?= $t['border'] ?>;">
                            <i class="fas fa-list-ul"></i>
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>

<!-- ========================================================================= -->
<!-- ULTRA HEAVY BRANDED CSS STYLE -->
<!-- ========================================================================= -->
<style>
    :root { 
        --dx-transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1); 
    }
    
    body { background-color: #f4f7fb; }

    /* --- HEAVY COLOR GRADED CARDS --- */
    .dx-heavy-card { 
        background: linear-gradient(145deg, var(--dx-bg1), var(--dx-bg2)); 
        border-radius: 22px !important; 
        box-shadow: inset 0 2px 10px rgba(255,255,255,0.6), 0 8px 20px rgba(0, 0, 0, 0.04) !important; 
        transition: var(--dx-transition); 
        position: relative;
        overflow: hidden;
    }
    [data-bs-theme="dark"] .dx-heavy-card { background: linear-gradient(145deg, #1e293b, #0f172a); }

    /* --- HOVER EFFECT: DIL KHUSH HO JAYEGA --- */
    .dx-heavy-card:hover { 
        transform: translateY(-10px) scale(1.03); 
        border-style: solid !important; /* Converts dashed to solid heavy border */
        box-shadow: inset 0 2px 10px rgba(255,255,255,0.8), 0 25px 40px -10px var(--dx-glow) !important; 
        background: linear-gradient(145deg, #ffffff, var(--dx-bg1));
    }

    /* Floating Image Effects */
    .dx-img-heavy { 
        height: 55px; width: auto; object-fit: contain; position: relative; z-index: 2;
        filter: drop-shadow(0 12px 15px rgba(0,0,0,0.15)); transition: var(--dx-transition); 
    }
    .dx-heavy-card:hover .dx-img-heavy { transform: scale(1.2) rotate(-5deg) translateY(-5px); filter: drop-shadow(0 15px 20px var(--dx-glow));}
    
    /* Internal Image Glow */
    .dx-img-glow {
        position: absolute; width: 60px; height: 60px; top: 50%; left: 50%; transform: translate(-50%, -50%);
        border-radius: 50%; filter: blur(35px); opacity: 0; transition: var(--dx-transition); z-index: 1;
    }
    .dx-heavy-card:hover .dx-img-glow { opacity: 0.3; }

    /* Typography & Buttons */
    .dx-heavy-title { font-size: 14.5px; letter-spacing: -0.3px; line-height: 1.3; height: 38px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .dx-badge { font-size: 9px !important; padding: 6px 12px !important; font-weight: 900; letter-spacing: 1px; }
    .dx-icon-wrapper { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: var(--dx-transition);}
    
    .dx-apply-btn { border: none; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; transition: var(--dx-transition); }
    .dx-apply-btn:hover { box-shadow: 0 10px 25px var(--dx-glow) !important; transform: translateY(-3px); }
    .dx-list-btn { transition: var(--dx-transition); }
    .dx-list-btn:hover { background: var(--dx-border) !important; color: white !important; transform: translateY(-3px); border-color: var(--dx-border) !important;}
    
    /* --- PREMIUM SECTION HEADERS --- */
    .dx-section-header { 
        font-size: 17px; font-weight: 900; text-transform: uppercase; letter-spacing: 1.5px; 
        display: flex; align-items: center; margin: 2.5rem 0 1.5rem 0; padding: 1rem 1.5rem; 
        background: linear-gradient(135deg, rgba(255,255,255,1) 0%, rgba(248,250,252,0.8) 100%); 
        border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); border: 1px solid rgba(226, 232, 240, 0.8);
    }
    .dx-section-bar { width: 6px; height: 24px; border-radius: 6px; margin-right: 15px; }

    /* --- COMPACT BRANDED DASHBOARD STAT CARDS --- */
    .dx-stat-card { border-radius: 20px !important; transition: var(--dx-transition); border: none !important;}
    .dx-stat-card:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.1) !important; }
    
    .dx-btn-dark-premium {
        background: linear-gradient(135deg, #0f172a, #334155); color: white; border: none;
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.3); transition: var(--dx-transition);
    }
    .dx-btn-dark-premium:hover { box-shadow: 0 15px 25px rgba(15, 23, 42, 0.5); transform: translateY(-3px); color: #fff;}

    /* --- ANIMATED UPDATE BAR --- */
    .dx-update-bar {
        background: linear-gradient(270deg, #1e1b4b, #4338ca, #1e1b4b);
        background-size: 400% 400%;
        animation: glowPulse 6s ease infinite;
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(67, 56, 202, 0.4);
        border: 2px solid rgba(255,255,255,0.1);
    }
    @keyframes glowPulse { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
</style>

<!-- ========================================================================= -->
<!-- NEW SYSTEM UPDATE BAR (ONLY VISIBLE TO ADMIN IF UPDATE IS AVAILABLE) -->
<!-- ========================================================================= -->
<?php if (isset($udata['type']) && checkAdmin($udata['type']) == true && $updateAvailable): ?>
<div class="dx-update-bar d-flex justify-content-between align-items-center py-3 px-4 px-md-5 mb-5 position-relative overflow-hidden">
    <!-- Overlay fixed with pointer-events: none so button is clickable -->
    <div class="position-absolute end-0 top-0 h-100" style="width: 300px; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15)); transform: skewX(-30deg); pointer-events: none;"></div>
    
    <div class="d-flex align-items-center position-relative" style="z-index: 10;">
        <div class="bg-white rounded-circle p-2 me-4 shadow-lg d-flex justify-content-center align-items-center" style="width:50px; height:50px;">
            <i class="fas fa-rocket fs-4" style="color: #4338ca; animation: bounce 2s infinite;"></i>
        </div>
        <div>
            <h5 class="mb-1 fw-black text-white" style="letter-spacing: 1px; text-shadow: 0 2px 10px rgba(0,0,0,0.3);">SYSTEM UPGRADE READY: v<?= $nextUpdate['version'] ?></h5>
            <p class="mb-0 text-white fw-bold fs-13" style="opacity: 0.85;">Current Built: v<?= $currentVersion ?> • Premium Features Available</p>
        </div>
    </div>
    
    <!-- Install button with high Z-Index for smooth clicks -->
    <button onclick="showUpdatePopup()" class="btn bg-white fw-black rounded-pill px-4 py-2 shadow-lg hover-lift text-uppercase position-relative" style="color: #4338ca; letter-spacing: 1px; z-index: 50;">
        Update Now <i class="fas fa-download ms-2"></i>
    </button>
</div>
<?php endif; ?>
<!-- ========================================================================= -->

<!-- WELCOME BAR : DARKXEN PREMIUM -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card dx-stat-card border-0 shadow-sm" style="background: radial-gradient(circle at top right, #ffffff, #eff6ff, #ffffff);">
            <div class="card-body p-4 d-flex align-items-center justify-content-between flex-wrap gap-4">
                <div class="d-flex align-items-center">
                    <div class="avatar avatar-2xl me-4 d-none d-sm-block position-relative">
                        <img class="rounded-circle border border-4 border-white shadow-lg" src="../assets/img/3-thumb.png" width="70" alt="User">
                        <span class="position-absolute bottom-0 end-0 p-2 bg-success border border-white rounded-circle shadow"></span>
                    </div>
                    <div>
                        <h6 class="fw-bolder mb-1 fs-12 text-uppercase tracking-wide" style="color: #4f46e5; letter-spacing: 2px;">WELCOME BACK, <?php echo strtoupper($udata['name'] ?? 'USER'); ?> ✨</h6>
                        <h3 class="fw-black text-dark mb-0" style="letter-spacing: -0.5px;"><?php echo web('webname') ?? 'DarkXen'; ?> 
                            <span class="badge bg-dark text-white fs-11 ms-2 fw-bold align-middle rounded-pill shadow-sm py-1 px-3">🏬 <?= htmlspecialchars($udata['shop'] ?? 'Branch') ?></span>
                        </h3>
                    </div>
                </div>
                
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white px-3 py-2 rounded-pill d-flex align-items-center shadow-sm border border-light">
                        <i class="fas fa-code-branch text-primary me-2"></i>
                        <span class="fw-black text-primary fs-13">v<?= $currentAppVersion; ?></span>
                    </div>
                    <div class="bg-white px-3 py-2 rounded-pill d-flex align-items-center shadow-sm border border-light">
                        <div class="pulse-dot-premium me-2"></div>
                        <span class="fw-black text-dark fs-13"><?= date("d M Y | h:i A") ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- NOTICE BAR : DARKXEN ALERTS (Now fully fixed) -->
<div class="alert border-0 shadow-sm mb-4 d-flex align-items-center py-2 px-4 overflow-hidden position-relative" style="background: linear-gradient(90deg, #fff1f2 0%, #ffffff 100%); border-radius: 14px; border-left: 5px solid #e11d48 !important;">
    <div class="position-absolute end-0 top-0 h-100 w-25" style="background: linear-gradient(90deg, transparent, #ffffff);"></div>
    <span class="badge bg-danger me-4 px-3 py-1 rounded-pill shadow-sm fw-black"><i class="fas fa-bullhorn me-1"></i> NOTICE</span>
    <marquee behavior="scroll" direction="left" class="text-danger fw-bolder mb-0 fs-14">
        ⚡ <?= htmlspecialchars($adminNotice) ?>
    </marquee>
</div>


        
        <div>
            <div class="text-center fw-bolder mt-3 mb-4 text-primary fw-bold ">
                <p><font size="5" face="arial" color=#ff0000 >🏬 Learning Exam Pass Live Now <a href="https://pdfdekho.net/dashboard/ll_exam.php">Click Here</a> Whatsapp Follow <a href="https://whatsapp.com/channel/0029Vb9mwDHAu3aLRFlf7J3P">Click Here</a></font></p>
            </div>
        </div>

<!-- COMPACT TOP STATS : DARKXEN DASHBOARD -->
<div class="row g-4 mb-5">
    <!-- Heavy Wallet Card -->
    <div class="col-12 col-md-4">
        <div class="card dx-stat-card h-100 overflow-hidden text-white" style="background: linear-gradient(135deg, #0f172a 0%, #312e81 100%); box-shadow: 0 10px 25px rgba(15,23,42,0.15) !important;">
            <div class="card-body position-relative p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <p class="fs-12 fw-bold text-indigo-200 mb-0 text-uppercase" style="letter-spacing: 1px;">Available Balance</p>
                    <div class="bg-white bg-opacity-25 rounded-circle p-2 backdrop-blur shadow-sm">
                        <i class="fas fa-wallet text-white fs-5"></i>
                    </div>
                </div>
                <h2 class="fw-black mb-3 text-white" style="text-shadow: 0 4px 10px rgba(0,0,0,0.3);">₹<?= number_format((float)($udata['balance'] ?? 0), 2) ?></h2>
                <a href="addwallet.php" class="btn btn-sm btn-light fw-black text-primary px-4 py-2 rounded-pill shadow-sm text-uppercase" style="letter-spacing: 0.5px;">
                    <i class="fas fa-plus-circle me-1"></i> Add Funds
                </a>
                <i class="fas fa-coins position-absolute end-0 bottom-0 mb-n3 me-n2" style="font-size: 90px; opacity:0.05; transform: rotate(-15deg);"></i>
            </div>
        </div>
    </div>

    <?php if (isset($udata['type']) && checkAdmin($udata['type']) == true): ?>
        <!-- Heavy Network Card -->
        <div class="col-6 col-md-4">
            <div class="card dx-stat-card h-100 text-white overflow-hidden" style="background: linear-gradient(135deg, #059669 0%, #064e3b 100%); box-shadow: 0 10px 25px rgba(5,150,105,0.15) !important;">
                <div class="card-body p-4 position-relative">
                    <p class="fs-12 fw-bold text-emerald-200 mb-2 text-uppercase" style="letter-spacing: 1px;">Total Network</p>
                    <h2 class="fw-black mb-3 text-white" style="text-shadow: 0 4px 10px rgba(0,0,0,0.3);"><?php echo $allusersforadmin ?? 0; ?></h2>
                    <a href="users.php" class="btn btn-sm bg-white fw-black text-success px-4 py-2 rounded-pill shadow-sm text-uppercase" style="letter-spacing: 0.5px;">
                        Manage Users
                    </a>
                    <i class="fas fa-users position-absolute end-0 bottom-0 mb-n2 me-n2" style="font-size: 85px; opacity:0.1;"></i>
                </div>
            </div>
        </div>
        
        <!-- API Check Card -->
        <div class="col-6 col-md-4">
            <div class="card dx-stat-card h-100 bg-white" style="border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(0,0,0,0.03) !important;">
                <div class="card-body d-flex flex-column justify-content-center align-items-center p-4 text-center">
                    <div class="bg-soft-primary p-3 rounded-circle mb-3 shadow-sm">
                        <i class="fas fa-server text-primary fs-3"></i>
                    </div>
                    <p class="fs-12 fw-black text-muted mb-3 text-uppercase" style="letter-spacing: 1px;">Master API Balance</p>
                    <form method="POST" class="w-100">
                        <button type="submit" name="check_balance" class="btn btn-sm <?= $btnClass; ?> fw-black w-100 py-2 rounded-pill text-uppercase" style="letter-spacing: 0.5px;">
                            <i class="fas <?= $iconClass; ?> me-1"></i> <?= $buttonText; ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        
    <?php else: ?>
        <!-- Helpdesk Card for Retailers -->
        <div class="col-12 col-md-8">
            <div class="card dx-stat-card h-100 bg-white" style="border: 1px solid #e2e8f0; background: url('../assets/img/support-bg.png') right bottom no-repeat; background-size: contain; box-shadow: 0 10px 25px rgba(0,0,0,0.03) !important;">
                <div class="card-body d-flex align-items-center p-4">
                    <div class="bg-soft-danger p-3 rounded-circle me-4 shadow-sm">
                        <i class="fas fa-headset text-danger fs-1"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h4 class="fw-black mb-1 text-dark">Technical Support Desk</h4>
                        <p class="text-muted fw-bold mb-3 fs-14">Encountering issues with API or services? Create a priority ticket.</p>
                        <a href="helpdesk.php" class="btn btn-sm btn-danger fw-black text-uppercase rounded-pill px-4 py-2 shadow-sm" style="background: linear-gradient(135deg, #e11d48, #9f1239); border:none;">
                            <i class="fas fa-ticket-alt me-1"></i> Open Helpdesk
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ======================================================== -->
<!-- HEAVY COLOR GRADED SERVICE GRIDS -->
<!-- ======================================================== -->

<!-- 1. AADHAAR SERVICES -->
<div class="dx-section-header text-primary">
    <div class="dx-section-bar bg-primary shadow-sm"></div>
    <i class="fas fa-fingerprint me-3 fs-5 text-primary"></i> Aadhaar Smart Solutions
</div>
<div class="row g-2 g-sm-3 mb-3">
    <?php
    renderService("Aadhaar PDF", "fa-file-pdf", "aadhaar-card.png", "PDF", "aadhaar_pdf.php", "aadhaar_pdf_list.php", "primary");
    renderService("Aadhaar Details", "fa-info-circle", "aadhaar-card.png", "DETAILS", "aadhaar_details.php", "aadhaar_details_list.php", "success");
    renderService("Abha Card", "fa-notes-medical", "aadhaar-card.png", "HEALTH", "abha_card_verification.php", "abha_card_list.php", "danger");
    renderService("Aadhaar Manual", "fa-edit", "aadhaar-card.png", "MANUAL", "aadhaar_manual.php", "aadharmanual_list.php", "info");
    renderService("Aadhaar NPCI Status", "fa-university", "NPCI-Logo-removebg-preview.png", "BANK", "npci_status.php", "npci_status_list.php", "success");
    renderService("Aadhaar Verify", "fa-check-circle", "aadhaar-card.png", "VERIFY", "aadhaar_verify.php", "aadhaar_verify_list.php", "primary");
    renderService("Enrollment Status", "fa-thumbtack", "aadhaar-card.png", "EID", "aadhaar_eid_status.php", "aadhaar_eid_status_list.php", "warning");
    renderService("Aadhaar-PAN Link", "fa-link", "pan-card (1).png", "LINK", "aadhaar_pan_link_status.php", "aadhaar_pan_link_list.php", "info");
    renderService("Aadhaar Mask PAN", "fa-user-secret", "pan-card (1).png", "MASK", "aadhaar_mask.php", "aadhaar_mask_list.php", "danger");
    renderService("Aadhaar to PAN", "fa-search", "02-07-WedSub-Images-2-1024x577-removebg-preview.png", "FIND", "aadhar_to_pan.php", "aadhar_to_pan_list.php", "warning");
    ?>
</div>

<!-- 2. PAN SERVICES -->
<div class="dx-section-header text-danger">
    <div class="dx-section-bar bg-danger shadow-sm"></div>
    <i class="fas fa-id-card-alt me-3 fs-5 text-danger"></i> PAN Card Hub
</div>
<div class="row g-2 g-sm-3 mb-3">
    <?php
    renderService("PAN Advance", "fa-bolt", "pan-card (1).png", "ADVANCE", "pan_advance_print.php", "pan_manual_list.php", "warning");
    renderService("PAN Manual Apply", "fa-keyboard", "pan-card (1).png", "MANUAL", "pan_manual.php", "pan_manual_list.php", "success");
    renderService("PAN to Aadhaar", "fa-search", "aadhaar-card.png", "FIND ID", "pan_to_aadhar.php", "pan_to_aadhar_list.php", "primary");
    renderService("Mobile to PAN", "fa-mobile-alt", "pan-card (1).png", "MOBILE", "mobile_to_pan.php", "mobile_to_pan_list.php", "info");
    renderService("Pan To Aadhar Mask", "fa-sms", "pan-card (1).png", "MASK", "pan_to_aadharmask.php", "pan_to_aadharmask_list.php", "danger");
    renderService("PAN Full Details", "fa-info-circle", "pan-card (1).png", "DETAILS", "pan_full_details.php", "pan_full_details_list.php", "success");
    ?>
</div>

<!-- 3. GST SERVICES -->
<div class="dx-section-header text-success">
    <div class="dx-section-bar bg-success shadow-sm"></div>
    <i class="fas fa-university me-3 fs-5 text-success"></i> GST Services
</div>
<div class="row g-2 g-sm-3 mb-3">
    <?php
    renderService("Pan To GST", "fa-university", "pan-card (1).png", "GST FIND", "gst_info.php", "", "primary");
    renderService("GST To Pan", "fa-university", "pan-card (1).png", "PAN FIND", "gst_info.php", "", "info");
    renderService("GST Information", "fa-info-circle", "pan-card (1).png", "DETAILS", "gst_info.php", "", "danger");
    ?>
</div>

<!-- 4. E-SHRAM SERVICES -->
<div class="dx-section-header text-danger" style="color: #ea580c !important;">
    <div class="dx-section-bar shadow-sm" style="background: #ea580c;"></div>
    <i class="fas fa-tools me-3 fs-5" style="color: #ea580c;"></i> E-Shram Portal
</div>
<div class="row g-2 g-sm-3 mb-3">
    <?php
    renderService("E-Shram Card", "fa-id-card", "eshram.png", "DOWNLOAD", "eshram_pdf.php", "eshram_pdf_list.php", "success");
    renderService("Mobile Update", "fa-mobile-alt", "eshram.png", "UPDATE", "eshram_mobile.php", "eshram_mobile_list.php", "warning");
    renderService("E-Shram Details", "fa-search-plus", "eshram.png", "DETAILS", "eshram_details.php", "eshram_details_list.php", "info");
    renderService("UAN By Aadhaar", "fa-id-badge", "eshram.png", "FIND UAN", "eshram_uan_find.php", "eshram_uan_list.php", "danger");
    renderService("UAN By Mobile", "fa-phone-square", "eshram.png", "FIND UAN", "mobile_uan_find.php", "mobile_uan_list.php", "primary");
    ?>
</div>

<!-- 5. UDHYAM SERVICES -->
<div class="dx-section-header text-info">
    <div class="dx-section-bar bg-info shadow-sm"></div>
    <i class="fas fa-industry me-3 fs-5 text-info"></i> Udhyam Registration
</div>
<div class="row g-2 g-sm-3 mb-3">
    <?php
    renderService("Pan To Udhyam", "fa-industry", "udhyam.png", "FIND", "pan_to_udyam.php", "pan_to_udyam_list.php", "primary");
    renderService("Udhyam Details", "fa-edit", "udhyam.png", "DETAILS", "udyam_to_details.php", "udyam_details_list.php", "success");
    renderService("Udhyam PDF", "fa-file-pdf", "udhyam.png", "DOWNLOAD", "udyam_pdf.php", "udyam_pdf_list.php", "danger");
    ?>
</div>

<!-- 6. VOTER SERVICES -->
<div class="dx-section-header text-primary" style="color: #3b82f6 !important;">
    <div class="dx-section-bar shadow-sm" style="background: #3b82f6;"></div>
    <i class="fas fa-vote-yea me-3 fs-5" style="color: #3b82f6;"></i> Voter ID Services
</div>
<div class="row g-2 g-sm-3 mb-3">
    <?php
    renderService("Voter Advance", "fa-bolt", "What-is-Voter-ID-Card_Inside-removebg-preview.png", "ADVANCE", "voter_advance.php", "voter_advance_list.php", "warning");
    renderService("Voter Manual", "fa-edit", "What-is-Voter-ID-Card_Inside-removebg-preview.png", "MANUAL", "voter_manual.php", "voter_manual_list.php", "success");
    renderService("Voter PDF (No OTP)", "fa-file-pdf", "What-is-Voter-ID-Card_Inside-removebg-preview.png", "PDF FAST", "voter_pdf_without_otp.php", "voter_pdf_without_otp_list.php", "info");
    renderService("Voter PDF", "fa-file-pdf", "What-is-Voter-ID-Card_Inside-removebg-preview.png", "DOWNLOAD", "voter_pdf.php", "voter_pdf_list.php", "danger");
    renderService("Voter Mobile Link", "fa-link", "What-is-Voter-ID-Card_Inside-removebg-preview.png", "LINK", "voter_mobile_link.php", "voter_mobile_link_list.php", "warning");
    ?>
</div>

<!-- 7. DL & RC (RTO) SERVICES -->
<div class="dx-section-header text-dark">
    <div class="dx-section-bar bg-dark shadow-sm"></div>
    <i class="fas fa-car me-3 fs-5 text-dark"></i> DL & Vehicle Registry
</div>
<div class="row g-2 g-sm-3 mb-3">
    <?php
    renderService("DL Advance", "fa-id-card", "WhatsApp-Image-2022-11-12-at-5.02.59-PM-3-removebg-preview.png", "APPLY", "dl_advance.php", "dl_advance_list.php", "success");
    renderService("DL Licence PDF", "fa-id-card", "WhatsApp-Image-2022-11-12-at-5.02.59-PM-3-removebg-preview.png", "PDF", "dl_pdf.php", "dl_pdf_list.php", "warning");
    renderService("Learning PDF", "fa-id-card", "WhatsApp-Image-2022-11-12-at-5.02.59-PM-3-removebg-preview.png", "LEARNING", "ll_pdf.php", "ll_pdf_list.php", "primary");
    renderService("Learning Exam Pass", "fa-check-circle", "WhatsApp-Image-2022-11-12-at-5.02.59-PM-3-removebg-preview.png", "EXAM", "ll_exam.php", "ll_exam_list.php", "danger");
    renderService("Vehicle RC PDF", "fa-car", "61ZpM4H0C8L._AC_UF1000_1000_QL80_-removebg-preview.png", "RC PDF", "rc_pro.php", "rc_pro_list.php", "info");
    renderService("Vehicle RC Details", "fa-car", "61ZpM4H0C8L._AC_UF1000_1000_QL80_-removebg-preview.png", "DETAILS", "rc_deatils.php", "", "success");
    renderService("RC To Mobile", "fa-car", "61ZpM4H0C8L._AC_UF1000_1000_QL80_-removebg-preview.png", "FIND", "rc_to_mobile.php", "rc_mobile_list.php", "warning");
    renderService("Mobile To RC", "fa-mobile-alt", "61ZpM4H0C8L._AC_UF1000_1000_QL80_-removebg-preview.png", "FIND", "mobile_to_rc.php", "mobile_to_rc_list.php", "primary");
    renderService("Mobile RC Details", "fa-info-circle", "61ZpM4H0C8L._AC_UF1000_1000_QL80_-removebg-preview.png", "DETAILS", "mobile_to_rc_details.php", "mobile_rc_details_list.php", "danger");
    renderService("Vehicle Challan", "fa-balance-scale", "E_Challan.png", "FINE", "challan_check.php", "vehicle_challan_list.php", "success");
    renderService("Vehicle RTO", "fa-building", "61ZpM4H0C8L._AC_UF1000_1000_QL80_-removebg-preview.png", "RTO", "rto_check.php", "rto_list.php", "info");
    ?>
</div>

<!-- 8. RATION SERVICES -->
<div class="dx-section-header text-warning">
    <div class="dx-section-bar bg-warning shadow-sm"></div>
    <i class="fas fa-shopping-basket me-3 fs-5 text-warning"></i> Ration Card Services
</div>
<div class="row g-2 g-sm-3 mb-3">
    <?php
    renderService("Ration Card PDF", "fa-shopping-basket", "100.0.z5d9f6duly--removebg-preview.png", "RATION", "ration_card.php", "ration_pdf_list.php", "primary");
    renderService("UP Ration to UID", "fa-id-card", "100.0.z5d9f6duly--removebg-preview.png", "UP", "up_ration_aadhar.php", "up_ration_aadhar_list.php", "success");
    renderService("All State to UID", "fa-map-marked-alt", "100.0.z5d9f6duly--removebg-preview.png", "ALL STATE", "all_state_ration_aadhar.php", "all_state_ration_aadhar_list.php", "danger");
    ?>
</div>

<!-- 9. GOVT & BANK SERVICES -->
<div class="dx-section-header text-success" style="color: #15803d !important;">
    <div class="dx-section-bar shadow-sm" style="background: #15803d;"></div>
    <i class="fas fa-landmark me-3 fs-5" style="color: #15803d;"></i> Govt & Schemes
</div>
<div class="row g-2 g-sm-3 mb-3">
    <?php
    renderService("Farmer Status", "fa-tractor", "images__1_-removebg-preview.png", "STATUS", "FarmerStatus.php", "", "success");
    renderService("Farmer PDF", "fa-file-pdf", "images__1_-removebg-preview.png", "DOWNLOAD", "FarmerPdf.php", "FarmerPdf_List.php", "danger");
    renderService("Electricity Bill", "fa-bolt", "unnamed-removebg-preview.png", "BILL", "Bill_To_find.php", "Bill_To_find_List.php", "warning");
    renderService("RTPS Bihar", "fa-flag", "unnamed-removebg-preview.png", "RTPS", "RTPS_To_find.php", "RTPS_To_find_List.php", "primary");
    ?>
</div>

<!-- 10. OTHER UTILITY SERVICES -->
<div class="dx-section-header text-info" style="color: #0284c7 !important;">
    <div class="dx-section-bar shadow-sm" style="background: #0284c7;"></div>
    <i class="fas fa-gift me-3 fs-5" style="color: #0284c7;"></i> Free Utility Tools
</div>
<div class="row g-2 g-sm-3 mb-5">
    <?php
    renderService("IFSC Info", "fa-university", "NPCI-Logo-removebg-preview.png", "FREE", "ifsc-info.php", "", "primary");
    renderService("Number Info", "fa-phone-alt", "unnamed-removebg-preview.png", "FREE", "number-info.php", "number_info_list.php", "success");
    renderService("Pincode Info", "fa-map-pin", "unnamed-removebg-preview.png", "FREE", "pinecode-info.php", "pincode_history_list.php", "danger");
    renderService("Customer Support", "fa-headset", "unnamed-removebg-preview.png", "HELP", "helpdesk.php", "", "warning");
    ?>
</div>

<!-- ======================================================== -->
<!-- DARKXEN: PREMIUM TRANSACTION LOG -->
<!-- ======================================================== -->
<div class="row mb-5 mt-5">
    <div class="col-12">
        <div class="card dx-stat-card bg-white" style="box-shadow: 0 10px 30px rgba(0,0,0,0.03) !important;">
            <div class="card-header bg-white py-4 px-4 border-bottom d-flex justify-content-between align-items-center rounded-top-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-soft-primary p-3 rounded-circle shadow-sm" style="background: linear-gradient(135deg, #eef2ff, #c7d2fe) !important;">
                        <i class="fas fa-file-invoice-dollar text-primary fs-4"></i>
                    </div>
                    <div>
                        <h5 class="mb-1 fw-black text-dark" style="letter-spacing: -0.5px;">Real-Time Transactions</h5>
                        <p class="mb-0 text-muted fw-bold" style="font-size: 13px;">Live daily operations tracking log</p>
                    </div>
                </div>
                <a href="wallet_history.php" class="btn btn-sm btn-primary fw-black rounded-pill px-4 py-2 shadow-sm hover-lift text-uppercase" style="background: linear-gradient(135deg, #4f46e5, #3730a3); border:none; letter-spacing: 0.5px;">View History <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="table-responsive">
                    <table class="table table-borderless align-middle mb-0">
                        <thead class="text-uppercase text-muted fw-black tracking-wide" style="font-size: 0.75rem; background: #f8fafc;">
                            <tr>
                                <th class="ps-4 py-3 rounded-start-3">Txn No.</th>
                                <th class="py-3">Service Detail</th>
                                <th class="py-3 text-center">Amount</th>
                                <th class="py-3">Closing Balance</th>
                                <th class="py-3 text-center">Type</th>
                                <th class="pe-4 py-3 text-end rounded-end-3">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($has_data):$x = 0;
                                foreach ($rows as $data) {$x++;
                                    $isDebit = strtoupper($data['type']) == 'DEBIT';
                                    ?>
                                    <tr class="dx-table-row border-bottom" style="transition: all 0.3s ease;">
                                        <td class="ps-4 text-muted fw-black fs-14">#<?= str_pad($x, 2, '0', STR_PAD_LEFT); ?></td>
                                        <td class="py-3">
                                            <div class="fw-black text-dark mb-1 fs-14"><?= htmlspecialchars($data['purpose']); ?></div>
                                            <div class="text-muted fw-bold" style="font-size: 0.7rem; font-family: monospace;">TXN-ID: WLT-<?= $data['id']; ?></div>
                                        </td>
                                        <td class="text-center py-3">
                                            <span class="badge <?= $isDebit ? 'bg-soft-danger text-danger' : 'bg-soft-success text-success' ?> rounded-pill px-3 py-2 fw-black shadow-sm" style="font-size: 13px;">
                                                <?= $isDebit ? '-' : '+' ?> ₹<?= number_format((float)$data['amount'], 2) ?>
                                            </span>
                                        </td>
                                        <td class="fw-black text-dark py-3 fs-15">₹<?= number_format((float)$data['balance'], 2) ?></td>
                                        <td class="text-center py-3">
                                            <div class="bg-soft-<?= $isDebit ? 'danger':'success' ?> d-inline-block rounded-circle p-2 shadow-sm" style="font-size: 12px;">
                                                <i class="fas <?= $isDebit ? 'fa-arrow-down text-danger' : 'fa-arrow-up text-success' ?>"></i>
                                            </div>
                                        </td>
                                        <td class="pe-4 text-end py-3">
                                            <div class="fw-black text-dark mb-1 fs-14"><?= date('h:i A', strtotime($data['date'])) ?></div>
                                            <div class="text-muted fw-bold" style="font-size: 0.75rem;"><?= date('d M Y', strtotime($data['date'])) ?></div>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="text-muted mb-3"><i class="fas fa-inbox opacity-25" style="font-size: 60px;"></i></div>
                                        <h5 class="fw-black text-dark">No records found today</h5>
                                        <p class="text-muted mb-0 fw-bold fs-14">System is idle. Awaiting transactions.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Table hover animation fix */
    .dx-table-row:hover { background: #f8fafc; transform: scale(1.01); box-shadow: 0 5px 15px rgba(0,0,0,0.03); border-radius: 12px; position: relative; z-index: 10; }
</style>

<!-- ======================================================== -->
<!-- DARKXEN: SWEETALERT2 UPDATE SCRIPT INTEGRATION -->
<!-- ======================================================== -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
<?php if(isset($udata['type']) && checkAdmin($udata['type']) == true && $updateAvailable): ?>
function showUpdatePopup() {
    Swal.fire({
        title: '<i class="fas fa-rocket text-primary me-2"></i> System Update',
        html: `
        <div style="text-align:left; font-size:16px; font-family:'Segoe UI', sans-serif;">
            <div style="display: flex; justify-content: space-between; align-items: center; background: linear-gradient(145deg, #f8fafc, #f1f5f9); padding: 30px; border-radius: 24px; border: 2px dashed #cbd5e1; margin-bottom: 25px; box-shadow: inset 0 4px 10px rgba(0,0,0,0.02);">
                <div style="text-align:center; width:40%;">
                    <span style="font-size: 11px; color: #64748b; text-transform:uppercase; font-weight:900; letter-spacing:1.5px;">Current Version</span><br>
                    <b style="font-size: 32px; color: #334155; text-shadow: 0 2px 4px rgba(0,0,0,0.05);"><?= htmlspecialchars($currentVersion) ?></b>
                </div>
                <div style="width:20%; text-align:center;">
                    <div style="background:#e2e8f0; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto; box-shadow: inset 0 2px 5px rgba(0,0,0,0.1);">
                        <i class="fas fa-arrow-right text-slate-500"></i>
                    </div>
                </div>
                <div style="text-align:center; width:40%;">
                    <span style="font-size: 11px; color: #64748b; text-transform:uppercase; font-weight:900; letter-spacing:1.5px;">New Update</span><br>
                    <b style="font-size: 32px; color: #4f46e5; text-shadow: 0 2px 10px rgba(79,70,229,0.2);"><?= htmlspecialchars($nextUpdate['version']) ?></b>
                </div>
            </div>
            <h6 style="font-weight: 900; color: #0f172a; margin-bottom: 15px; font-size:16px; text-transform:uppercase; letter-spacing:1px;"><i class="fas fa-list-ul me-2 text-primary"></i> Release Notes:</h6>
            <div style="background: #ffffff; padding: 25px; border-radius: 20px; border: 1px solid #e2e8f0; font-size: 14.5px; color:#475569; max-height: 250px; overflow-y: auto; line-height: 1.8; font-weight:600; box-shadow: 0 4px 15px rgba(0,0,0,0.02);">
                <?= nl2br(htmlspecialchars($nextUpdate['changelog'] ?? 'System improvements and bug fixes.')) ?>
            </div>
        </div>`,
        width: '600px',
        padding: '2.5em',
        buttonsStyling: false,
        showCancelButton: true,
        confirmButtonText: '⚡ INSTALL UPDATE',
        cancelButtonText: 'REMIND LATER',
        customClass: {
            popup: 'dx-swal-popup',
            title: 'dx-swal-title',
            confirmButton: 'btn btn-primary rounded-pill px-5 py-3 mx-2 shadow-lg fw-black text-uppercase letter-spacing-1',
            cancelButton: 'btn btn-light rounded-pill px-5 py-3 mx-2 border shadow-sm fw-black text-uppercase letter-spacing-1 text-muted'
        },
        backdrop: `rgba(15, 23, 42, 0.85)`
    }).then((result) => {
        if(result.isConfirmed) { applyUpdate(); }
    });
}

function applyUpdate() {
    Swal.fire({
        title: 'Downloading & Installing...',
        html: `
            <div style="margin-top:40px; margin-bottom: 20px;">
                <div style="height:22px; background: #e2e8f0; border-radius:50px; overflow:hidden; box-shadow:inset 0 3px 6px rgba(0,0,0,0.1); padding:3px;">
                    <div id="progressBar" style="height:100%; border-radius:50px; width:0%; background:linear-gradient(90deg, #4f46e5, #818cf8); transition:width 0.4s ease-out; box-shadow: 0 0 20px rgba(79,70,229,0.5);"></div>
                </div>
                <p style="margin-top:30px; font-size:16px; color:#475569; font-weight:800; letter-spacing:1px; text-transform:uppercase;">Securing Files... Please Wait <i class="fas fa-spinner fa-spin ms-2"></i></p>
            </div>
        `,
        allowOutsideClick: false,
        showConfirmButton: false,
        customClass: { popup: 'dx-swal-popup', title: 'dx-swal-title' },
        didOpen: () => {
            let progress = 0;
            let bar = document.getElementById("progressBar");
            let interval = setInterval(() => {
                progress += Math.floor(Math.random() * 15) + 5;
                if(progress > 90) progress = 90;
                bar.style.width = progress + "%";
            }, 600);

            fetch('../update/update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    update_url: "<?= $nextUpdate['update_url'] ?>",
                    version: "<?= $nextUpdate['version'] ?>",
                    extractPath: "<?= $nextUpdate['extractPath'] ?? '../' ?>"
                })
            }).then(res => res.json()).then(data => {
                clearInterval(interval);
                bar.style.width = "100%";
                setTimeout(() => {
                    if(data.success){
                        Swal.fire({ icon:'success', title:'Update Successful!', text:'System successfully upgraded to v<?= $nextUpdate['version'] ?>.', timer: 2500, showConfirmButton:false, customClass:{popup:'dx-swal-popup'} });
                        setTimeout(()=>{ location.reload(); },2500);
                    } else {
                        Swal.fire({icon:'error', title:'Update Failed', text: data.message, customClass:{popup:'dx-swal-popup'}});
                    }
                }, 900);
            }).catch(()=>{
                clearInterval(interval);
                Swal.fire('Error','Server connectivity lost.','error');
            });
        }
    });
}
<?php endif; ?>
</script>

<style>
    /* SweetAlert Ultra Premium Fixes */
    .dx-swal-popup { border-radius: 30px !important; box-shadow: 0 40px 100px rgba(0,0,0,0.3) !important; padding: 2em !important; border: 1px solid rgba(255,255,255,0.2) !important;}
    .dx-swal-title { font-weight: 900 !important; font-size: 28px !important; color: #0f172a !important; letter-spacing: -0.5px !important;}
</style>

<?php include('footer.php'); ?>