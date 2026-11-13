<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('../../includes/config.php');

// URL से ID निकालें और चेक करें
$encoded_id = $_GET['searchid'] ?? '';
$searchid = base64_decode($encoded_id);

if (empty($searchid)) {
    die("<h2 style='text-align:center; margin-top:50px; color:red;'>Invalid Request! No ID provided.</h2>");
}

// FIX: Secure standard query to prevent Fatal Errors
$safe_searchid = mysqli_real_escape_string($DarkXen_conn, $searchid);
$query = "SELECT * FROM panauto WHERE id='$safe_searchid'";
$res = mysqli_query($DarkXen_conn, $query);

if (!$res) {
    die("<h2 style='text-align:center; margin-top:50px; color:red;'>Database Query Failed!</h2>");
}

$b = mysqli_fetch_assoc($res);

// अगर डेटाबेस में रिकॉर्ड नहीं मिलता है, तो Blank पेज की जगह Error दिखायेगा
if (!$b) {
    die("<h2 style='text-align:center; margin-top:50px; color:red;'>Record Not Found! <br><small>This PAN record does not exist in the database.</small></h2>");
}

$a1 = strtoupper($b['name'] ?? '');
$a2 = strtoupper($b['fathername'] ?? '');
$a3 = strtoupper($b['panno'] ?? '');
$a4 = $b['gender'] ?? '';
$dob_raw = $b['dob'] ?? '';
$a5 = !empty($dob_raw) ? date('d/m/Y', strtotime($dob_raw)) : '';

// इमेज पाथ 
$img_path = "../" . ($b['image'] ?? '');
$sign_path = "../" . ($b['signimage'] ?? '');

// ==========================================
// QR CODE DENSITY FIX (LIKE ORIGINAL PAN)
// ==========================================
// असली PAN कार्ड के QR कोड को बारीक (dense) बनाने के लिए उसमें एक लंबा डिजिटल सिग्नेचर होता है।
// हम यहाँ एक Dummy Long String जोड़ रहे हैं ताकि QR Code बिल्कुल असली जैसा घना (High Density) बने।
$dummy_signature = str_repeat(md5($a3 . $a1 . "NSDL_SECURE_QR_DATA"), 15); 

// XML Data with padded signature
$xml_data = '<?xml version="1.0" encoding="UTF-8"?><PrintLetterBarcodeData uid="'.$a3.'" name="'.$a1.'" dob="'.$dob_raw.'" co="'.$a2.'" sig="'.$dummy_signature.'"/>';

// Requesting a larger size (500x500) and High Error Correction (ecc=H) from API. 
// margin=0 रखा है ताकि API से कोई एक्स्ट्रा स्पेस ना आये, और CSS से हम परफेक्ट 3px का सफ़ेद बॉर्डर देंगे।
$qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&ecc=H&margin=0&data=' . urlencode($xml_data);

// Signature Date
$current_time = date('Y.m.d H:i:s \I\S\T');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Pan Card Preview - <?php echo $a3; ?></title>
<link href="https://fonts.googleapis.com/css?family=Hind" rel="stylesheet">
<script disable-devtool-auto src="https://cdn.jsdelivr.net/npm/disable-devtool@latest"></script>
<link href="pan.css" rel="stylesheet">
<style>
    body { margin: 0; padding: 20px; background-color: #f4f4f4; display: flex; justify-content: center; }
    .bg { background: url('pan.jpg') no-repeat; width: 800px; height: 1200px; overflow: visible; display: block; background-size: 100% auto; position: relative; background-color: #fff; box-shadow: 0 0 10px rgba(0,0,0,0.5); }
    
    /* QR Code Styling - Added 3px white padding to match the image */
    img.mrmins.mrninbigs { 
        top: 924px; 
        position: absolute; 
        left: 302px; 
        width: 91px; 
        background-color: #fff; 
        padding: 3px; 
        box-sizing: border-box; 
    }
    img.mrminss.mrninbigss { 
        position: absolute; 
        top: 505px; 
        width: 159px; 
        left: 459px; 
        background-color: #fff; 
        padding: 4px; /* Slightly larger padding for the top QR code due to its bigger size */
        box-sizing: border-box; 
    }
    
    .cimg { top: 923px; position: absolute; left: 102px; object-fit: cover; }
    .mainno { top: 935px; position: absolute; left: 194px; font-weight: 700; font-size: 14px; color: #000; }
    .name { top: 982px; position: absolute; left: 105px; font-weight: 600; font-size: 9px; color: #000; }
    .mainno2 { position: absolute; top: 181px; left: 347px; font-size: 19px; font-weight: 600; color: #000; }
    .signs { position: absolute; top: 556px; left: 250px; object-fit: contain; }
    .names { position: absolute; top: 249px; left: 253px; font-size: 15px; font-weight: 500; color: #000; }
    .fathername { top: 1009px; position: absolute; left: 106px; font-weight: 600; font-size: 9px; color: #000; }
    .cimgs { position: absolute; top: 525px; left: 66px; object-fit: cover; }
    .fathernames { position: absolute; top: 314px; left: 253px; font-size: 15px; font-weight: 500; color: #000; }
    .gender { position: absolute; top: 443px; left: 253px; font-size: 15px; font-weight: 500; color: #000; }
    .bod { top: 1043px; position: absolute; left: 105px; font-weight: 600; font-size: 9px; color: #000; }
    .bods { position: absolute; top: 379px; left: 253px; font-size: 15px; font-weight: 500; color: #000; }
    .sign { top: 1030px; position: absolute; left: 189px; object-fit: contain; }
    .settext { position: absolute; font-size: 9px; top: 568px; left: 631px; font-weight: bold; color: #000; line-height: 1.2; }
    
    @media print {
        body { padding: 0; background-color: #fff; }
        .bg { box-shadow: none; margin: 0; }
        @page { margin: 0; }
    }
</style>
</head>
<body>

<main class="bg"> 
    <!-- QR Codes (Now Dense, Crisp, and with White Border) -->
    <img class="mrmins mrninbigs" src="<?php echo $qr_url; ?>" alt="QR Code Bottom">
    <img class="mrminss mrninbigss" src="<?php echo $qr_url; ?>" alt="QR Code Top">
    
    <!-- Photos -->
    <img class="cimg" src="<?php echo htmlspecialchars($img_path); ?>" height="56px" width="54px" alt="Applicant Photo"/>
    <img class="cimgs" src="<?php echo htmlspecialchars($img_path); ?>" width="90px" height="105px" alt="Applicant Photo Large"/>
    
    <!-- Texts -->
    <p class="mainno2"><?php echo htmlspecialchars($a3); ?></p>
    <p class="mainno"><?php echo htmlspecialchars($a3); ?></p>
    <p class="name"><?php echo htmlspecialchars($a1); ?></p>
    <p class="names"><?php echo htmlspecialchars($a1); ?></p>
    <p class="fathername"><?php echo htmlspecialchars($a2); ?></p>
    <p class="fathernames"><?php echo htmlspecialchars($a2); ?></p>
    <p class="gender"><?php echo htmlspecialchars($a4); ?></p>
    <p class="bod"><?php echo htmlspecialchars($a5); ?></p>
    <p class="bods"><?php echo htmlspecialchars($a5); ?></p>
    
    <!-- Signatures -->
    <img class="sign" src="<?php echo htmlspecialchars($sign_path); ?>" height="20px" width="90px" alt="Signature Bottom"/>
    <img class="signs" src="<?php echo htmlspecialchars($sign_path); ?>" width="190px" height="50px" alt="Signature Top"/>
    
    <!-- Date & Location -->
    <p class="settext">
        Date: <?php echo $current_time; ?> <br>
        Reason : NSDL ePAN Sign <br>
        Location : Mumbai
    </p>
</main>

<script>
    // ऑटोमैटिक प्रिंट करने के लिए (Optional - uncomment if needed)
    // window.onload = function() { window.print(); }
</script>
</body>
</html>