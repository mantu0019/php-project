<?php
session_start();
include('../../includes/config.php'); // Path verify karein agar folder structure dacoment/ me ho to '../../includes/config.php' ya '../includes/config.php'

if (!isset($_GET['searchid']) || empty($_GET['searchid'])) {
    die("<div style='text-align:center; margin-top:50px; font-family:sans-serif;'><h3>Error: Invalid or missing query ID!</h3><a href='../dl_advance_list.php'>Go Back to List</a></div>");
}

$id = base64_decode($_GET['searchid']);
$record_id = mysqli_real_escape_string($DarkXen_conn, $id);

// Database se record nikalna
$sql = "SELECT * FROM `dl_verify_records` WHERE `id` = '$record_id'";
$res = mysqli_query($DarkXen_conn, $sql);

if (!$res || mysqli_num_rows($res) == 0) {
    die("<div style='text-align:center; margin-top:50px; font-family:sans-serif;'><h3>Error: Record not found in database!</h3><a href='../dl_advance_list.php'>Go Back to List</a></div>");
}

$row = mysqli_fetch_assoc($res);

// Session and fallback values
$bgStyle   = $_SESSION['print_payload_card_bg'] ?? 'blue';
$valTr     = $_SESSION['print_payload_val_tr'] ?? 'NA';
$rtoOffice = $_SESSION['print_payload_rto'] ?? 'RTO AUTHORITY';

// Parse Vehicle Categories (COV)
$cov_raw = $row['vehicle_categories'];
$cov_arr = json_decode($cov_raw, true);
if (!is_array($cov_arr)) {
    $cov_arr = explode(",", str_replace(['[', ']', '"', "'"], '', $cov_raw));
}

// Background Image Path Selection
if ($bgStyle === 'white') {
    $bg_front_img = "assets/img/dlfrontsideimg.png";
    $bg_back_img  = "assets/img/dlbacksideimg.png";
} else {
    $bg_front_img = "assets/img/frontsides.png";
    $bg_back_img  = "assets/img/backside.png";
}

// State Header calculation
$state_code = strtoupper(substr(trim($row['dl_number']), 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HD DL Print Canvas - <?php echo htmlspecialchars($row['dl_number']); ?></title>
    
    <!-- Bootstrap 5 & Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Rendering Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>

    <style>
        body { 
            background: #eef2f6; 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            user-select: none;
        }
        .main-wrapper { max-width: 1100px; margin: 25px auto; }
        .preview-container {
            display: flex;
            gap: 30px;
            justify-content: center;
            flex-wrap: wrap;
            padding: 10px;
        }
        .card-box {
            position: relative;
            width: 486px;
            height: 307px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 12px 28px rgba(0,0,0,0.22);
            background: #fff;
        }
        .bg-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 1;
        }
        .content-layer {
            position: absolute;
            width: 100%;
            height: 100%;
            z-index: 2;
            top: 0;
            left: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
        }
        .abs { position: absolute; }
        .abs-wrap { position: absolute; }
        .cov-table th, .cov-table td {
            border: 1px solid #000 !important;
            padding: 2px 3px !important;
            line-height: 1.1;
        }
    </style>
</head>
<body>

<div class="container main-wrapper">
    <!-- Action Controls Header -->
    <div class="card shadow-sm border-0 mb-4" style="border-radius: 15px;">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center bg-white py-3 px-4" style="border-radius: 15px;">
            <div>
                <h5 class="fw-bold m-0 text-success"><i class="bi bi-check-circle-fill me-2"></i>HD Driving Licence Preview</h5>
                <small class="text-muted">DL: <b><?php echo htmlspecialchars($row['dl_number']); ?></b> | Name: <b><?php echo htmlspecialchars($row['full_name']); ?></b></small>
            </div>
            <div class="d-flex gap-2 mt-2 mt-md-0">
                <button onclick="downloadPNG('card-front-hd', 'DL_Front')" class="btn btn-sm px-3 fw-bold text-white shadow-sm" style="background: #15803d; border-radius: 8px;">
                    <i class="bi bi-image me-1"></i> Front PNG
                </button>
                <button onclick="downloadPNG('card-back-hd', 'DL_Back')" class="btn btn-sm px-3 fw-bold text-white shadow-sm" style="background: #15803d; border-radius: 8px;">
                    <i class="bi bi-image me-1"></i> Back PNG
                </button>
                <button onclick="downloadPDF()" class="btn btn-sm px-3 fw-bold text-white shadow-sm" style="background: #16a34a; border-radius: 8px;">
                    <i class="bi bi-file-earmark-pdf-fill me-1"></i> Download PDF
                </button>
                <button onclick="window.close()" class="btn btn-sm btn-outline-secondary px-3 fw-bold shadow-none" style="border-radius: 8px;">Close</button>
            </div>
        </div>
    </div>

    <!-- CANVAS CARDS CONTAINER -->
    <div class="preview-container">
        
        <!-- FRONT CARD -->
        <div class="card-box" id="card-front-hd">
            <img id="bg_front" src="<?php echo $bg_front_img; ?>" class="bg-img" alt="Front BG">
            <div class="content-layer">
                <div class="abs" style="top: 8px; left: 90px; font-size: 20px; font-weight: 500; color: #111; letter-spacing: 0.5px;">INDIAN UNION DRIVING LICENCE</div>
                <div class="abs" style="top: 35px; left: 110px; font-size: 15px; font-weight: 400; color: #111;" id="c_state_header">ISSUED BY GOVERNMENT OF INDIA</div>
                <div class="abs" style="top: 5px; right: 15px; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; color: #000; z-index: 10;">DL</div>
                
                <div class="abs" style="top: 75px; left: 90px; font-size: 18px; font-weight: 700;" id="disp_dl"><?php echo htmlspecialchars($row['dl_number']); ?></div>
                
                <div class="abs" style="top: 105px; left: 90px; font-size: 12px; font-weight: 500;">Issue Date</div>
                <div class="abs" style="top: 105px; left: 180px; font-size: 12px; font-weight: 500;">Validity ( NT )</div>
                <div class="abs" style="top: 105px; left: 270px; font-size: 12px; font-weight: 500;">Validity ( TR )</div>
                
                <div class="abs" style="top: 125px; left: 90px; font-size: 14px; font-weight: 700;" id="c_issue_date"><?php echo htmlspecialchars($row['issued_date']); ?></div>
                <div class="abs" style="top: 125px; left: 180px; font-size: 14px; font-weight: 700;" id="c_val_nt"><?php echo htmlspecialchars($row['expiry_date']); ?></div>
                <div class="abs" style="top: 125px; left: 270px; font-size: 14px; font-weight: 700;" id="c_val_tr"><?php echo htmlspecialchars($row['expiry_date']); ?></div>
                
                <div class="abs-wrap" style="top: 173px; left: 20px; width: 365px; font-size: 13px; line-height: 1.4; color: #000;">
                    <div style="margin-bottom: 2px;">Name : <span style="font-weight: 700;" id="c_name"><?php echo htmlspecialchars($row['full_name']); ?></span></div>
                    <div style="margin-bottom: 2px;">Date Of Birth : <span style="font-weight: 700;" id="disp_dob"><?php echo htmlspecialchars($row['dob']); ?></span> <span style="margin-left: 8px; font-size: 12px;">Blood Group: <b style="font-weight: 600;">U</b></span></div>
                    <div style="margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Son/Daughter/Wife of : <span style="font-weight: 700;" id="c_father"><?php echo htmlspecialchars($row['father_name']); ?></span></div>
                    <div style="display: flex; align-items: flex-start; margin-top: 1px;">
                        <span style="white-space: nowrap;">Address :&nbsp;</span>
                        <span style="font-size: 11px; font-weight: 700; line-height: 1.3;" id="c_address"><?php echo htmlspecialchars($row['address']); ?></span>
                    </div>
                </div>

                <!-- Holder Photo & Signature -->
                <img id="c_photo" src="<?php echo $row['photo']; ?>" class="abs" style="top: 75px; right: 25px; width: 75px; height: 100px; object-fit: cover; border: 1px solid #ccc; border-radius: 4px;">
                <img id="c_sign" src="<?php echo $row['signature']; ?>" class="abs" style="top: 180px; right: 25px; width: 75px; height: 30px; object-fit: contain; mix-blend-mode: multiply;">
                
                <div class="abs" style="top: 215px; right: 25px; width: 75px; text-align: center; font-size: 10px;">Holder's Signature</div>
                <div class="abs" style="top: 165px; right: -65px; font-size: 10px; transform: rotate(-90deg); width: 150px; text-align: center;" id="c_side_date">Date of First Issue <?php echo htmlspecialchars($row['issued_date']); ?></div>
            </div>
        </div>

        <!-- BACK CARD -->
        <div class="card-box" id="card-back-hd">
            <img id="bg_back" src="<?php echo $bg_back_img; ?>" class="bg-img" alt="Back BG">
            <div class="content-layer">
                <div class="abs" style="top: 6px; left: 20px; font-size: 18px; font-weight: 700;" id="disp_dl_back">DL No. <?php echo htmlspecialchars($row['dl_number']); ?></div>
                <img id="dl_qr_code_img" class="abs" style="top: 48px; left: 20px; width: 80px; height: 80px;" src="">
                <div class="abs" style="top: 50px; left: 120px; font-size: 13.5px;">ADPVEH No.(Regn.Numbers)</div>
                <div class="abs" style="top: 85px; left: 120px; font-size: 13.5px;">Hazardous validity</div>
                <div class="abs" style="top: 85px; left: 280px; font-size: 13.5px;">Hill Validity</div>
                
                <!-- COV Detailed 8 Column Table -->
                <div class="abs" style="top: 135px; left: 20px; width: 440px; height: 95px;">
                    <table class="cov-table" style="width: 100%; border-collapse: collapse; text-align: center;">
                        <thead>
                            <tr>
                                <th style="background: #9bd3f0; font-size: 9.5px;">Class of<br>Vehicle</th>
                                <th style="background: #9bd3f0; font-size: 9.5px;">Code</th>
                                <th style="background: #9bd3f0; font-size: 9.5px;">Issued<br>by</th>
                                <th style="background: #9bd3f0; font-size: 9.5px;">Date of<br>Issue</th>
                                <th style="background: #9bd3f0; font-size: 9.5px;">Vehicle<br>Category</th>
                                <th style="background: #9bd3f0; font-size: 9.5px;">Badge<br>Number</th>
                                <th style="background: #9bd3f0; font-size: 9.5px;">Badge<br>Issued Date</th>
                                <th style="background: #9bd3f0; font-size: 9.5px;">Badge<br>Issued by</th>
                            </tr>
                        </thead>
                        <tbody id="c_cov_body">
                            <?php 
                            if (!empty($cov_arr)) {
                                foreach ($cov_arr as $v_cov) {
                                    $v_cov = strtoupper(trim($v_cov));
                                    if (empty($v_cov)) continue;
                                    
                                    $icon = (strpos($v_cov, 'LMV') !== false || strpos($v_cov, 'MCW') !== false || strpos($v_cov, '2WN') !== false) ? '🚗' : '🚚';
                                    $type = (strpos($v_cov, 'TRANS') !== false) ? 'TR' : 'NT';
                                    $authCode = substr($row['dl_number'], 0, 4);
                            ?>
                                <tr>
                                    <td style="font-size: 12px; padding: 1px;"><?php echo $icon; ?></td>
                                    <td style="font-size: 10px; font-weight: bold;"><?php echo $v_cov; ?></td>
                                    <td style="font-size: 10px; font-weight: bold;"><?php echo $authCode; ?></td>
                                    <td style="font-size: 10px; font-weight: bold;"><?php echo htmlspecialchars($row['issued_date']); ?></td>
                                    <td style="font-size: 10px; font-weight: bold;"><?php echo $type; ?></td>
                                    <td style="font-size: 10px;"></td>
                                    <td style="font-size: 10px;"></td>
                                    <td style="font-size: 10px;"></td>
                                </tr>
                            <?php
                                }
                            }
                            ?>
                            <tr><td colspan="8" style="height: 12px;"></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="abs" style="bottom: 25px; left: 20px; font-size: 14px;">Mobile Number: Not Provided</div>
                <div class="abs" style="bottom: 13px; right: 20px; text-align: right;">
                    <div style="font-size: 15px; font-weight: 500;">Licencing Authority</div>
                    <div style="font-size: 12.5px;" id="c_auth"><?php echo htmlspecialchars($rtoOffice); ?></div>
                </div>
                <div class="abs" style="bottom: 3px; left: 0; width: 100%; text-align: center; font-size: 8px; color: #444;">
                    This duplicate card, generated from publicly available data on sarathi.parivahan.gov.in, is for reference/personal use only.
                </div>
                <div class="abs" style="top: 160px; right: -42px; font-size: 10px; transform: rotate(-90deg); width: 100px; text-align: center;">Form 7 Rule 16 (2)</div>
            </div>
        </div>

    </div>
</div>

<script>
    var currentDlNumber = "<?php echo htmlspecialchars($row['dl_number']); ?>";

    window.onload = function() {
        // High Quality QR Code Generator
        var qrText = "DL:<?php echo $row['dl_number']; ?>|DOB:<?php echo $row['dob']; ?>|Name:<?php echo addslashes($row['full_name']); ?>";
        var qr = qrcode(0, 'M'); 
        qr.addData(qrText); 
        qr.make();
        document.getElementById('dl_qr_code_img').src = qr.createDataURL(4, 0);
    };

    // Download PNG Function
    function downloadPNG(divId, fileName) {
        Swal.fire({ 
            title: 'Generating HD Image...', 
            html: 'Rendering crystal clear 4K PNG canvas...',
            allowOutsideClick: false, 
            didOpen: () => { Swal.showLoading(); } 
        });

        html2canvas(document.getElementById(divId), { scale: 4, useCORS: true, allowTaint: false }).then(canvas => {
            Swal.close();
            const link = document.createElement('a');
            link.download = currentDlNumber + '_' + fileName + '.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        }).catch(err => {
            Swal.close();
            Swal.fire('Error', 'Failed to generate PNG image: ' + err, 'error');
        });
    }

    // Download Standard Side-by-Side PDF
    function downloadPDF() {
        window.jsPDF = window.jspdf.jsPDF;
        Swal.fire({ 
            title: 'Generating PDF...', 
            text: 'Rendering HD Print Document. Please wait...', 
            allowOutsideClick: false, 
            didOpen: () => { Swal.showLoading(); } 
        });

        const frontEl = document.getElementById('card-front-hd');
        const backEl = document.getElementById('card-back-hd');

        const renderOptions = { scale: 4, useCORS: true, allowTaint: false };

        html2canvas(frontEl, renderOptions).then(canvasFront => {
            html2canvas(backEl, renderOptions).then(canvasBack => {
                const pdf = new jsPDF('p', 'mm', 'a4'); 
                
                // CR80 Credit card dimensions: 85.6mm x 54mm
                pdf.addImage(canvasFront.toDataURL('image/jpeg', 1.0), 'JPEG', 15, 20, 85.6, 54);
                pdf.addImage(canvasBack.toDataURL('image/jpeg', 1.0), 'JPEG', 110, 20, 85.6, 54);
                
                pdf.save(currentDlNumber + '_Driving_Licence.pdf');
                Swal.close();
            });
        }).catch(err => {
            Swal.close();
            Swal.fire('Error', 'Failed to generate PDF: ' + err, 'error');
        });
    }
</script>
<script disable-devtool-auto src="https://cdn.jsdelivr.net/npm/disable-devtool@latest"></script>
</body>
</html>