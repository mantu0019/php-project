<?php
       
 include('../../includes/config.php');      
                            
                           
$searchid = base64_decode($_GET['searchid']);
mysqli_set_charset($DarkXen_conn,"utf8");
$stmt = mysqli_prepare($DarkXen_conn, "SELECT * FROM farmer_verifications WHERE id=?");
mysqli_stmt_bind_param($stmt, "s", $searchid);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$b = mysqli_fetch_assoc($res);


$qrData =
    "FARMER:".$b['fid']."\n".
    "Name:".$b['farmer_name']."\n".
    "DIST:".$b['district']."\n".
    "VILLAGE:".$b['address']."\n";

?>


<html><script disable-devtool-auto src="https://cdn.jsdelivr.net/npm/disable-devtool@latest"></script>
<head><title>Farmer ID Card - Premium</title>
                <style>
                * { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Roboto, Arial, sans-serif; }
                body { background: white; margin:0; padding:0; }

                .id-card {
                    width: 85mm;
                    height: 54mm;
                    background: #ffffff;
                    border-radius: 8px;
                    padding: 3.5mm 4.5mm;
                    border: 1px solid #c8dcc8;
                    position: relative;
                    overflow: hidden;
                    page-break-inside: avoid;
                    break-inside: avoid;
                    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
                }
                .id-card::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    background: radial-gradient(ellipse at 15% 85%, rgba(46, 125, 50, 0.04) 0%, transparent 50%),
                        radial-gradient(ellipse at 85% 15%, rgba(255, 153, 51, 0.03) 0%, transparent 50%),
                        linear-gradient(180deg, #f7fcf7 0%, #ffffff 40%, #ffffff 70%, #f0f7f0 100%);
                    pointer-events: none;
                    z-index: 0;
                }
                .id-card .tricolor-bar {
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 3.8mm;
                    background: linear-gradient(90deg, #ff9933 0%, #ff9933 33.33%, #ffffff 33.33%, #ffffff 66.66%, #138808 66.66%, #138808 100%);
                    z-index: 5;
                    border-radius: 8px 8px 0 0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .id-card .tricolor-bar .chakra {
                    position: absolute;
                    width: 5mm;
                    height: 5mm;
                    opacity: 0.4;
                    color: #1a3c6e;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 4.2mm;
                    line-height: 1;
                    background: rgba(255, 255, 255, 0.3);
                    border-radius: 50%;
                    backdrop-filter: blur(2px);
                }
                .id-card .watermark-farmer {
                    position: absolute;
                    bottom: 2mm;
                    right: 3mm;
                    font-size: 32px;
                    opacity: 0.04;
                    z-index: 0;
                    pointer-events: none;
                    transform: rotate(-6deg);
                }
                .id-card .watermark-text {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%) rotate(-10deg);
                    font-size: 24px;
                    font-weight: 900;
                    color: #1a3c6e;
                    opacity: 0.025;
                    letter-spacing: 6px;
                    pointer-events: none;
                    z-index: 0;
                    white-space: nowrap;
                }
                .id-card .corner-leaf {
                    position: absolute;
                    font-size: 14px;
                    opacity: 0.07;
                    pointer-events: none;
                    z-index: 1;
                }
                .id-card .corner-leaf.tl { top: 4mm; left: 4mm; transform: rotate(-20deg); }
                .id-card .corner-leaf.br { bottom: 4mm; right: 4mm; transform: rotate(160deg); }
                .card-inner {
                    background: rgba(255,255,255,0.92);
                    height: 100%;
                    width: 100%;
                    border-radius: 4px;
                    padding: 3mm 4mm;
                    display: flex;
                    flex-direction: column;
                    position: relative;
                    z-index: 2;
                    backdrop-filter: blur(1px);
                    border: 0.5px solid rgba(255,255,255,0.3);
                }
                .card-body {
                    display: flex;
                    gap: 3.5mm;
                    flex: 1;
                    min-height: 0;
                }
                .inner-leaf-top {
                    position: absolute;
                    font-size: 16px;
                    opacity: 0.25;
                    pointer-events: none;
                    z-index: 3;
                    top: 2mm;
                }
                .inner-leaf-top.left { left: 2mm; }
                .inner-leaf-top.right { right: 2mm; }
                .farmer-id-footer {
                    margin-top: 0.4mm;
                    padding-top: 0.3mm;
                    font-size: 5px;
                    font-weight: 700;
                    color: #1a3c6e;
                    text-align: center;
                    border-top: 0.5px solid #dce8dc;
                    letter-spacing: 0.5px;
                    opacity: 0.7;
                    width: 100%;
                    flex-shrink: 0;
                }
                .farmer-id-footer.no-border {
                    border-top: none;
                    padding-top: 0.1mm;
                }
                .card-left { flex:1; display:flex; flex-direction:column; justify-content:center; gap:0.4mm; position:relative; z-index:1; min-width:0; }
                .card-header { display:flex; align-items:center; gap:2.5mm; margin-bottom:0.6mm; }
                .card-logo {
                    width: 8.5mm !important;
                    height: 8.5mm !important;
                    background: linear-gradient(145deg, #ff9933, #f57c00) !important;
                    border: 1.5px solid #ffffff !important;
                    box-shadow: 0 1px 6px rgba(255, 153, 51, 0.4) !important;
                    border-radius: 50% !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    flex-shrink: 0 !important;
                    position: relative !important;
                }
                .card-logo .logo-ring {
                    border: 1.5px solid rgba(255, 255, 255, 0.3) !important;
                    position: absolute !important;
                    inset: -2px !important;
                    border-radius: 50% !important;
                    pointer-events: none !important;
                }
                .card-logo .logo-inner {
                    position: relative !important;
                    width: 100% !important;
                    height: 100% !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    border-radius: 50% !important;
                    overflow: hidden !important;
                }
                .card-logo .logo-bg {
                    position: absolute !important;
                    inset: 0 !important;
                    border-radius: 50% !important;
                    background: radial-gradient(ellipse at 30% 30%, #ffcc80, #f57c00) !important;
                }
                .card-logo .logo-farmer {
                    position: relative !important;
                    z-index: 1 !important;
                    width: 70% !important;
                    height: 70% !important;
                }
                .card-logo .logo-farmer svg {
                    width: 100% !important;
                    height: 100% !important;
                    display: block !important;
                }
                .card-logo .logo-leaf {
                    position: absolute !important;
                    bottom: -1mm !important;
                    right: -1mm !important;
                    font-size: 3.2mm !important;
                    opacity: 0.9 !important;
                    filter: drop-shadow(0 1px 2px rgba(0,0,0,0.2)) !important;
                    z-index: 2 !important;
                    display: block !important;
                }
                .card-title-group .govt { font-size:4.8px; font-weight:600; color:#4a6a4f; letter-spacing:0.6px; text-transform:uppercase; line-height:1.2; }
                .card-title-group .title { font-size:9px; font-weight:800; color:#1a3c6e; letter-spacing:0.4px; line-height:1.1; }
                .card-title-group .title .highlight { color:#f7931e; }
                .card-title-group .title .green-text { color:#2e7d32; }
                .card-title-group .subtitle { font-size:4.8px; color:#5a7a5f; font-weight:500; letter-spacing:0.3px; }
                .divider-premium {
                    display: flex; align-items:center; gap:2px; margin:0.6mm 0 0.8mm 0;
                    border:none; height:1px;
                    background: linear-gradient(90deg, #dce8dc 0%, #a0c8a0 50%, #dce8dc 100%);
                    position:relative;
                }
                .divider-premium::after {
                    content: '🌱';
                    position: absolute; left:50%; transform:translateX(-50%) scale(0.8);
                    background: white; padding:0 3px; font-size:5px; top:-2.5px;
                }
                .detail-line { display:flex; align-items:center; font-size:6px; line-height:1.3; gap:2px; padding:0.2mm 0; }
                .detail-line .dl-icon { font-size:4.5px; opacity:0.5; min-width:3.5mm; text-align:center; flex-shrink:0; }
                .detail-line .dl-label { font-weight:600; color:#4a6a4f; min-width:16%; flex-shrink:0; font-size:5px; text-transform:uppercase; letter-spacing:0.2px; opacity:0.7; }
                .detail-line .dl-value { color:#1a202c; font-weight:600; word-break:break-word; font-size:6px; }
                .detail-line .dl-value.regional { color:#1a3c6e; font-weight:700; }
                .detail-line .dl-value.highlight-value { color:#f7931e; font-weight:700; }
                .card-right {
                    width: 16mm; display:flex; flex-direction:column; align-items:center; justify-content:center;
                    gap:1mm; flex-shrink:0; position:relative; z-index:1;
                }
                .photo-box {
                    width: 14mm; height:17mm; border:1.5px solid #b8d4b8; border-radius:5px;
                    background: linear-gradient(145deg, #f2faf2, #e8f5e9);
                    display:flex; align-items:center; justify-content:center; overflow:hidden;
                    flex-shrink:0; position:relative; box-shadow: inset 0 2px 8px rgba(46,125,50,0.06);
                }
                .photo-box .farmer-bg { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; opacity:0.12; pointer-events:none; }
                .photo-box .farmer-bg svg { width:100%; height:100%; }
                .photo-box img { width:100%; height:100%; object-fit:cover; position:relative; z-index:2; }
                .photo-box .photo-placeholder-text { display:none; }
                .card-right .uid-badge {
                    font-size:4.8px; font-weight:700; color:white; text-align:center;
                    background: linear-gradient(135deg, #1a3c6e, #2a5a9a);
                    padding:0.6mm 1.4mm; border-radius:4px; width:100%; line-height:1.2;
                    letter-spacing:0.2px; box-shadow: 0 1px 6px rgba(26,60,110,0.12);
                }
                .card-right .uid-badge .small { font-weight:400; opacity:0.7; font-size:3.8px; }
                .card-right .qr-wrap {
                    width:100%; display:flex; justify-content:center; align-items:center;
                    background:white; border-radius:4px; padding:0.5px 0; border:0.5px solid #dce8dc;
                }
                .card-right .qr-wrap img { display:block; width:12mm !important; height:12mm !important; max-width:12mm; max-height:12mm; border-radius:2px; background:white; }

                .card-back .card-left { justify-content:flex-start; padding-top:0.5mm; }
                .card-back .land-section { margin-top:0.6mm; border-top:0.5px solid #dce8dc; padding-top:0.6mm; }
                .card-back .land-section .land-title { font-size:5px; font-weight:700; color:#1a3c6e; margin-bottom:0.3mm; display:flex; align-items:center; gap:2px; }
                .card-back .land-table-mini { width:100%; border-collapse:collapse; font-size:5px; }
                .card-back .land-table-mini th { background:#eaf5ea; font-weight:700; color:#2e7d32; padding:0.2mm 0.5mm; border:0.5px solid #c8e0c8; text-align:left; font-size:4.6px; text-transform:uppercase; letter-spacing:0.2px; }
                .card-back .land-table-mini td { padding:0.2mm 0.5mm; border:0.5px solid #c8e0c8; color:#1a202c; font-size:4.8px; }
                .card-back .land-table-mini tr:nth-child(even) td { background:#f7fcf7; }
                .card-back .thumb-sign-area { display:flex; gap:2mm; margin-top:0.8mm; padding-top:0.6mm; border-top:0.5px solid #dce8dc; justify-content:space-between; }
                .card-back .thumb-box { text-align:center; flex:1; }
                .card-back .thumb-box .label { font-size:3.8px; font-weight:700; color:#4a6a4f; text-transform:uppercase; letter-spacing:0.2px; opacity:0.6; }
                .card-back .thumb-box .box { width:100%; height:6.5mm; border:1px dashed #b8d4b8; border-radius:4px; background:rgba(248,252,248,0.6); display:flex; align-items:center; justify-content:center; font-size:10px; color:#a0c8a0; margin-top:0.3mm; }
                .card-back .thumb-box .box .icon { font-size:14px; }
                .card-back .thumb-box .box .line { width:70%; border-bottom:1px solid #4a6a4f; margin-top:1px; }
                .card-back .thumb-box .box .line-text { font-size:3.2px; color:#7a9a7f; margin-top:0.5px; }
                .side-badge {
                    position: absolute; top:4mm; right:4.5mm;
                    font-size:4.5px; font-weight:700; color:#2e7d32;
                    background: rgba(255,255,255,0.85);
                    padding:0.3mm 2mm; border-radius:3px;
                    border:0.5px solid #81c784; z-index:6; opacity:0.5;
                    letter-spacing:0.5px;
                }

                .print-download-page {
                    display: flex !important;
                    flex-wrap: wrap !important;
                    justify-content: center !important;
                    align-items: center !important;
                    gap: 6mm !important;
                    padding: 8mm !important;
                    width: 100% !important;
                    background: white !important;
                }
                .print-download-page .id-card {
                    width: 85mm !important;
                    height: 54mm !important;
                    margin: 0 !important;
                    box-shadow: 0 4px 24px rgba(0,0,0,0.08) !important;
                    border: 1px solid #c8dcc8 !important;
                    transform: none !important;
                    border-radius: 8px !important;
                }
                @page { margin:0; padding:0; size: A4 portrait; }
                body { margin:0; padding:0; background:white !important; }

                /* ===== KEY FIX: exact color rendering for print ===== */
                * {
                    print-color-adjust: exact !important;
                    -webkit-print-color-adjust: exact !important;
                    color-adjust: exact !important;
                }
                .id-card,
                .id-card *,
                .card-logo,
                .card-logo *,
                .tricolor-bar,
                .tricolor-bar *,
                .card-inner,
                .card-inner *,
                .photo-box,
                .photo-box *,
                .qr-wrap,
                .qr-wrap *,
                .uid-badge,
                .uid-badge * {
                    print-color-adjust: exact !important;
                    -webkit-print-color-adjust: exact !important;
                    color-adjust: exact !important;
                }
            </style>
                <style>
                    .qr-wrap img { width: 12mm !important; height: 12mm !important; max-width:12mm; max-height:12mm; }
                    .id-card { page-break-inside: avoid; break-inside: avoid; }
                </style>
                </head>
                <body>
                    <div class="print-download-page">
                <div class="id-card ">
                    <div class="tricolor-bar">
                        <span class="chakra">🌱</span>
                    </div>
                    <div class="watermark-farmer">🌾</div>
                    <div class="watermark-text">FARMER</div>
                    <span class="corner-leaf tl">🌿</span>
                    <span class="corner-leaf br">🌱</span>
                    <div class="card-inner">
                        <span class="inner-leaf-top left">🌿</span>
                        <span class="inner-leaf-top right">🍃</span>
                        
                <div class="card-body">
                    <div class="card-left">
                        <div class="card-header">
                            <div class="card-logo">
                                <span class="logo-ring"></span>
                                <span class="logo-inner">
                                    <span class="logo-bg"></span>
                                    <span class="logo-farmer">
                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                    <!-- Sun rays -->
                    <g opacity="0.3">
                        <circle cx="72" cy="28" r="14" fill="#FFF8E1"></circle>
                        <circle cx="72" cy="28" r="10" fill="#FFE082"></circle>
                    </g>
                    <!-- Farmer body - dark navy for contrast -->
                    <!-- Head -->
                    <circle cx="48" cy="42" r="10" fill="#1a3c6e"></circle>
                    <!-- Hat -->
                    <ellipse cx="48" cy="35" rx="14" ry="4" fill="#1a3c6e"></ellipse>
                    <rect x="40" y="28" width="16" height="9" rx="2" fill="#1a3c6e" opacity="0.9"></rect>
                    <!-- Torso (Kurta) -->
                    <rect x="40" y="52" width="16" height="20" rx="3" fill="#1a3c6e"></rect>
                    <!-- Collar detail -->
                    <path d="M44 52 L48 56 L52 52" stroke="#2a5a9a" stroke-width="1.5" fill="none"></path>
                    <!-- Legs -->
                    <line x1="44" y1="72" x2="40" y2="88" stroke="#1a3c6e" stroke-width="5" stroke-linecap="round"></line>
                    <line x1="52" y1="72" x2="56" y2="88" stroke="#1a3c6e" stroke-width="5" stroke-linecap="round"></line>
                    <!-- Left arm holding sickle -->
                    <line x1="40" y1="56" x2="28" y2="66" stroke="#1a3c6e" stroke-width="4" stroke-linecap="round"></line>
                    <!-- Sickle -->
                    <path d="M24 60 L30 68 L26 72 Z" fill="#1a3c6e"></path>
                    <path d="M22 62 L28 70" stroke="#f7931e" stroke-width="1.5"></path>
                    <!-- Right arm holding wheat -->
                    <line x1="56" y1="56" x2="68" y2="64" stroke="#1a3c6e" stroke-width="4" stroke-linecap="round"></line>
                    <!-- Wheat stalks -->
                    <g stroke="#f7931e" stroke-width="2" fill="none">
                        <path d="M68 64 L74 56"></path>
                        <path d="M68 64 L72 54"></path>
                        <path d="M68 64 L76 58"></path>
                        <path d="M68 64 L78 62"></path>
                        <path d="M68 64 L76 66"></path>
                        <path d="M68 64 L72 70"></path>
                    </g>
                    <!-- Wheat grains -->
                    <g fill="#ff9933">
                        <circle cx="74" cy="55" r="2"></circle>
                        <circle cx="72" cy="53" r="2"></circle>
                        <circle cx="76" cy="57" r="2"></circle>
                        <circle cx="78" cy="61" r="2"></circle>
                        <circle cx="76" cy="65" r="2"></circle>
                        <circle cx="72" cy="69" r="2"></circle>
                    </g>
                    <!-- Small decorative dots -->
                    <circle cx="32" cy="78" r="3" fill="#ff9933" opacity="0.6"></circle>
                    <circle cx="68" cy="80" r="3" fill="#138808" opacity="0.6"></circle>
                    <!-- Ground line -->
                    <path d="M20 92 L80 92" stroke="#4a6a4f" stroke-width="1" opacity="0.2"></path>
                </svg>
            </span>
                                </span>
                                <span class="logo-leaf">🌿</span>
                            </div>
                            <div class="card-title-group">
                                <div class="govt">भारत सरकार · GOVERNMENT OF INDIA</div>
                                <div class="title">KISAN <span class="highlight">PEHCHAAN</span> <span class="green-text">PATRA</span></div>
                                <div class="subtitle">Farmer Identity Card · किसान पहचान पत्र</div>
                            </div>
                        </div>

                        <div class="divider-premium"></div>

                        <div class="detail-line">
                            <span class="dl-icon">👤</span>
                            <span class="dl-label">Name</span>
                            <span class="dl-value"><?php echo $b['farmer_name']; ?></span>
                        </div>
                        <div class="detail-line">
                            <span class="dl-icon">📝</span>
                            <span class="dl-label">नाव</span>
                            <span class="dl-value regional"><?php echo $b['farmer_name_local']; ?></span>
                        </div>
                        <div class="detail-line">
                            <span class="dl-icon">📅</span>
                            <span class="dl-label">DOB</span>
                            <span class="dl-value"><?php echo !empty($b['dob']) ? date("d/m/Y", strtotime($b['dob'])) : ''; ?></span>
                        </div>
                        <div class="detail-line">
                            <span class="dl-icon">⚤</span>
                            <span class="dl-label">Gender</span>
                            <span class="dl-value">Male / पुरुष</span>
                        </div>
                        <div class="detail-line">
                            <span class="dl-icon">🆔</span>
                            <span class="dl-label">UID</span>
                            <span class="dl-value highlight-value"><?php echo $b['aadhaar']; ?></span>
                        </div>
                        <div class="detail-line">
                            <span class="dl-icon">📱</span>
                            <span class="dl-label">Mobile</span>
                            <span class="dl-value"><?php echo $b['mobile']; ?></span>
                        </div>

                        <div class="divider-premium" style="margin:0.3mm 0 0.5mm;"></div>

                        <div class="detail-line">
                            <span class="dl-icon">📍</span>
                            <span class="dl-label">Address</span>
                            <span class="dl-value" style="font-size:5.6px;"><?php echo $b['address']; ?></span>
                        </div>
                        <div class="detail-line">
                            <span class="dl-icon">📍</span>
                            <span class="dl-label">पत्ता</span>
                            <span class="dl-value regional" style="font-size:5.6px;"><?php echo $b['address_local']; ?></span>
                        </div>
                    </div>
                    <div class="card-right">
                  
                        <div class="photo-box"><img src="data:image/png;base64,<?php echo $b['photo_base64']; ?>" alt="Photo"></div>
                        <div class="uid-badge">
                            <span class="small">ID</span><br>
                            <?php echo $b['farmer_id']; ?>
                        </div>
                        <div class="qr-wrap">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&amp;data=<?php echo $qrData; ?>&amp;bgcolor=ffffff&amp;color=1a3c6e&amp;margin=4" alt="QR" width="120" height="120">
                        </div>
                    </div>
                </div>
                <div class="farmer-id-footer"><strong>फार्मर आईडी</strong></div>
            
                    </div>
                    <div class="side-badge">FRONT</div>
                </div>
            
                <div class="id-card card-back">
                    <div class="tricolor-bar">
                        <span class="chakra">🌱</span>
                    </div>
                    <div class="watermark-farmer">🌾</div>
                    <div class="watermark-text">FARMER</div>
                    <span class="corner-leaf tl">🌿</span>
                    <span class="corner-leaf br">🌱</span>
                    <div class="card-inner">
                        <span class="inner-leaf-top left">🌿</span>
                        <span class="inner-leaf-top right">🍃</span>
                        
                <div class="card-body">
                    <div class="card-left" style="justify-content: flex-start; padding-top:0.5mm;">
                        <div class="card-header" style="margin-bottom:0.4mm;">
                            <div class="card-logo" style="width:6.5mm;height:6.5mm;">
                                <span class="logo-ring" style="inset:-1.5px;"></span>
                                <span class="logo-inner">
                                    <span class="logo-bg"></span>
                                    <span class="logo-farmer">
                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                    <!-- Sun rays -->
                    <g opacity="0.3">
                        <circle cx="72" cy="28" r="14" fill="#FFF8E1"></circle>
                        <circle cx="72" cy="28" r="10" fill="#FFE082"></circle>
                    </g>
                    <!-- Farmer body - dark navy for contrast -->
                    <!-- Head -->
                    <circle cx="48" cy="42" r="10" fill="#1a3c6e"></circle>
                    <!-- Hat -->
                    <ellipse cx="48" cy="35" rx="14" ry="4" fill="#1a3c6e"></ellipse>
                    <rect x="40" y="28" width="16" height="9" rx="2" fill="#1a3c6e" opacity="0.9"></rect>
                    <!-- Torso (Kurta) -->
                    <rect x="40" y="52" width="16" height="20" rx="3" fill="#1a3c6e"></rect>
                    <!-- Collar detail -->
                    <path d="M44 52 L48 56 L52 52" stroke="#2a5a9a" stroke-width="1.5" fill="none"></path>
                    <!-- Legs -->
                    <line x1="44" y1="72" x2="40" y2="88" stroke="#1a3c6e" stroke-width="5" stroke-linecap="round"></line>
                    <line x1="52" y1="72" x2="56" y2="88" stroke="#1a3c6e" stroke-width="5" stroke-linecap="round"></line>
                    <!-- Left arm holding sickle -->
                    <line x1="40" y1="56" x2="28" y2="66" stroke="#1a3c6e" stroke-width="4" stroke-linecap="round"></line>
                    <!-- Sickle -->
                    <path d="M24 60 L30 68 L26 72 Z" fill="#1a3c6e"></path>
                    <path d="M22 62 L28 70" stroke="#f7931e" stroke-width="1.5"></path>
                    <!-- Right arm holding wheat -->
                    <line x1="56" y1="56" x2="68" y2="64" stroke="#1a3c6e" stroke-width="4" stroke-linecap="round"></line>
                    <!-- Wheat stalks -->
                    <g stroke="#f7931e" stroke-width="2" fill="none">
                        <path d="M68 64 L74 56"></path>
                        <path d="M68 64 L72 54"></path>
                        <path d="M68 64 L76 58"></path>
                        <path d="M68 64 L78 62"></path>
                        <path d="M68 64 L76 66"></path>
                        <path d="M68 64 L72 70"></path>
                    </g>
                    <!-- Wheat grains -->
                    <g fill="#ff9933">
                        <circle cx="74" cy="55" r="2"></circle>
                        <circle cx="72" cy="53" r="2"></circle>
                        <circle cx="76" cy="57" r="2"></circle>
                        <circle cx="78" cy="61" r="2"></circle>
                        <circle cx="76" cy="65" r="2"></circle>
                        <circle cx="72" cy="69" r="2"></circle>
                    </g>
                    <!-- Small decorative dots -->
                    <circle cx="32" cy="78" r="3" fill="#ff9933" opacity="0.6"></circle>
                    <circle cx="68" cy="80" r="3" fill="#138808" opacity="0.6"></circle>
                    <!-- Ground line -->
                    <path d="M20 92 L80 92" stroke="#4a6a4f" stroke-width="1" opacity="0.2"></path>
                </svg>
            </span>
                                </span>
                                <span class="logo-leaf" style="font-size:2.8mm;bottom:-0.5mm;right:-0.5mm;">🌿</span>
                            </div>
                            <div class="card-title-group">
                                <div class="govt">भारत सरकार · GOVERNMENT OF INDIA</div>
                                <div class="title">KISAN <span class="highlight">PEHCHAAN</span> <span class="green-text">PATRA</span></div>
                                <div class="subtitle">Land Records · भूमि अभिलेख</div>
                            </div>
                        </div>

                        <div class="detail-line">
                            <span class="dl-icon">👤</span>
                            <span class="dl-label">Name</span>
                            <span class="dl-value"><?php echo $b['farmer_name']; ?></span>
                        </div>
                        <div class="detail-line">
                            <span class="dl-icon">🆔</span>
                            <span class="dl-label">ID</span>
                            <span class="dl-value highlight-value"><?php echo $b['farmer_id']; ?></span>
                        </div>

                        
                        <div class="land-section">
                            <div class="land-title">🌾 Land Holdings</div>
                            <table class="land-table-mini">
                                <thead><tr><th>District</th><th>Tehsil</th><th>Village</th><th>Survey No &amp; Area (ha)</th></tr></thead>
                                <tbody>
                                    <tr>
                                        <td><?php echo $b['district']; ?></td><td><?php echo $b['tehsil']; ?></td><td><?php echo $b['village']; ?></td><td><?php echo $b['survey_no']; ?></td></tr><tr>
                                        
                                        
                                </tbody>
                            </table>
                        </div>
                    

                        <div class="thumb-sign-area">
                            <div class="thumb-box">
                                <div class="label">Thumb</div>
                                <div class="box"><span class="icon">🖐️</span></div>
                            </div>
                            <div class="thumb-box">
                                <div class="label">Signature</div>
                                <div class="box" style="flex-direction:column;gap:1px;">
                                    <div class="line"></div>
                                    <div class="line-text">(Authorized)</div>
                                </div>
                            </div>
                            <div class="thumb-box" style="flex:0.6;">
                                <div class="label">QR</div>
                                <div class="box" style="border-style:solid;border-color:#dce8dc;background:white;padding:1px;">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&amp;data=<?php echo $qrData; ?>&amp;bgcolor=ffffff&amp;color=1a3c6e&amp;margin=4" alt="QR" style="width:6mm;height:6mm;border-radius:2px;">
                                </div>
                            </div>
                        </div>

                        <div style="margin-top:0.5mm; font-size:3.6px; color:#7a9a7f; border-top:0.5px solid #dce8dc; padding-top:0.4mm; text-align:center; letter-spacing:0.3px;">
                            📋 For information storage only · सूचना भंडारण हेतु
                        </div>
                    </div>
                    <div class="card-right" style="justify-content:center;gap:1mm;width:13mm;">
                        <div class="photo-box" style="width:12mm;height:14mm;border-style:dashed;border-color:#b8d4b8;background:#f2faf2;font-size:3.8px;flex-direction:column;">
                            <span style="font-size:12px;">🖐️</span>
                            Thumb
                        </div>
                        <div style="font-size:4.2px;font-weight:700;color:#1a3c6e;text-align:center;background:#eaf5ea;padding:0.3mm 0.8mm;border-radius:3px;width:100%;border:0.5px solid #c8e0c8;">
                           <?php echo $b['farmer_id']; ?>
                        </div>
                        <div class="qr-wrap" style="border:none;padding:0;">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&amp;data=<?php echo $qrData; ?>&amp;bgcolor=ffffff&amp;color=1a3c6e&amp;margin=4" alt="QR" width="120" height="120">
                        </div>
                    </div>
                </div>
               
            
                    </div>
                    <div class="side-badge">BACK</div>
                </div>
            </div>
                    <script>
                        window.onload = function() {
                            var imgs = document.querySelectorAll('.qr-wrap img, .photo-box img');
                            var total = imgs.length;
                            if (total === 0) { window.print(); return; }
                            var loaded = 0;
                            imgs.forEach(function(img) {
                                if (img.complete) { loaded++; }
                                else { img.onload = function() { loaded++; if (loaded === total) window.print(); };
                                        img.onerror = function() { loaded++; if (loaded === total) window.print(); }; }
                            });
                            if (loaded === total) window.print();
                            setTimeout(function() { window.print(); }, 3000);
                        };
                    </script>
                
                
            </body></html>