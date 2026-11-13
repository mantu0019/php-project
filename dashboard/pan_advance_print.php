<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('../includes/session.php');
include('../includes/config.php');

// टाइमज़ोन सेट करें
date_default_timezone_set("Asia/Kolkata"); 


// 1. डेटाबेस से PAN Advance Print (फ़ेच फ़ीस) निकालें
$fee_query1 = mysqli_query($DarkXen_conn, "SELECT `pan_advance` FROM `service_free` WHERE `id` = 1");
if ($fee_query1 && mysqli_num_rows($fee_query1) > 0) {
    $fee_row1 = mysqli_fetch_assoc($fee_query1);
    if ($fee_row1['pan_advance'] !== '' && $fee_row1['pan_advance'] !== null) {
        $fetch_fee = (float)$fee_row1['pan_advance'];
    }
}

// 2. डेटाबेस से PAN Details (सेव फ़ीस) निकालें
$fee_query2 = mysqli_query($DarkXen_conn, "SELECT `pan_details` FROM `service_free` WHERE `id` = 1");
if ($fee_query2 && mysqli_num_rows($fee_query2) > 0) {
    $fee_row2 = mysqli_fetch_assoc($fee_query2);
    if ($fee_row2['pan_details'] !== '' && $fee_row2['pan_details'] !== null) {
        $save_fee = (float)$fee_row2['pan_details'];
    }
}

$DarkXen_apikey = $webdata['api_key'] ?? ''; 
$api_domain = $DarkXen_url ?? 'darkxen.in';

// ===================================================
// 1. AJAX एक्शन: API द्वारा PAN डिटेल्स फ़ेच करना + ऑटो-फिल
// ===================================================
if (isset($_POST['action_type']) && $_POST['action_type'] === 'fetch_pan') {
    header('Content-Type: application/json');
    
    $pan_no = strtoupper(trim($_POST['panNumber'] ?? ''));
    $username = $udata['phone'];
    $wallet_amount = (float)($udata['balance'] ?? 0);

    if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/i', $pan_no)) {
        echo json_encode(['status' => 'error', 'msg' => 'Invalid PAN format.']);
        exit;
    }

    // फ़ेच करने के लिए बैलेंस चेक करें
    if ($wallet_amount <$fetch_fee) {
        echo json_encode(['status' => 'error', 'msg' => 'Insufficient balance for verification. Required: ₹' . $fetch_fee]);
        exit;
    }

    // NEW API URL & PARAMETERS
    $url = "https://" . $api_domain . "/api/v3/PAN/pan_verification.php?" . http_build_query([
        'apiKey' => $DarkXen_apikey,
        'panNumber' => $pan_no
    ]);

    // Fast cURL Configuration
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20, // Reduced timeout for faster failover
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ["cache-control: no-cache"],
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4 // Speeds up DNS resolution
    ]);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        echo json_encode(['status' => 'error', 'msg' => "Server Connection Failed: $err"]);
        exit;
    }

    $json = json_decode($response, true);

    // NEW API SUCCESS CHECK (status = true)
    if (isset($json['status']) &&$json['status'] === true) {
        
        mysqli_begin_transaction($DarkXen_conn);
        try {
            $u_phone = mysqli_real_escape_string($DarkXen_conn,$username);
            
            // वॉलेट बैलेंस घटाएं
            $debit = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fetch_fee WHERE phone='$u_phone' AND balance >=$fetch_fee");
            if (mysqli_affected_rows($DarkXen_conn) == 0) {
                throw new Exception("Fetch charge deduction failed due to low balance.");
            }

            // वॉलेट हिस्ट्री अपडेट करें
            $new_balance = $wallet_amount -$fetch_fee;
            $history_purpose = "PAN Fetch Fee: " . $pan_no;
            $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES (?, ?, ?, ?, '1', 'Debit')");
            mysqli_stmt_bind_param($stmt_wallet, "sdds", $username,$fetch_fee, $new_balance,$history_purpose);
            mysqli_stmt_execute($stmt_wallet);
            mysqli_stmt_close($stmt_wallet);

            mysqli_commit($DarkXen_conn);

            // DOB formatting (YYYY-MM-DD to DD/MM/YYYY)
            $raw_dob = $json['dob'] ?? '';$formatted_dob = !empty($raw_dob) ? date('d/m/Y', strtotime($raw_dob)) : '';

            // Gender formatting (Ensure it perfectly matches select options)
            $raw_gender = strtolower(trim($json['gender'] ?? 'male'));
            if(strpos($raw_gender, 'f') !== false)$final_gender = 'Female';
            elseif(strpos($raw_gender, 't') !== false)$final_gender = 'Transgender';
            else $final_gender = 'Male';

            echo json_encode([
                'status' => 'success',
                'msg' => 'Details fetched successfully! ₹' . $fetch_fee . ' debited.',
                'data' => [
                    'name' => $json['fullname'] ?? '',
                    'fatherName' => $json['fathername'] ?? '',
                    'dob' => $formatted_dob,
                    'gender' => $final_gender
                ]
            ]);
            exit;

        } catch (Exception $e) {
            mysqli_rollback($DarkXen_conn);
            echo json_encode(['status' => 'error', 'msg' => 'Transaction Failed: ' . $e->getMessage()]);
            exit;
        }

    } else {
        $msg =$json['message'] ?? 'Failed to fetch details from API. Invalid PAN.';
        echo json_encode(['status' => 'error', 'msg' => $msg]);
        exit;
    }
}

// ===================================================
// 2. AJAX एक्शन: फ़ॉर्म डेटा सेव करना + वॉलेट डिडक्ट करना
// ===================================================
if (isset($_POST['action_type']) &&$_POST['action_type'] === 'save_pan') {
    header('Content-Type: application/json');

    $pan_no = strtoupper(trim($_POST['panNumber'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $fathername = trim($_POST['fathername'] ?? '');
    $dobadhar = trim($_POST['dobadhar'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $username =$udata['phone'];
    
    // ताज़ा बैलेंस चेक करने के लिए दोबारा डेटाबेस से रीड करना
    $user_q = mysqli_query($DarkXen_conn, "SELECT balance FROM users WHERE phone='".mysqli_real_escape_string($DarkXen_conn,$username)."'");
    $user_d = mysqli_fetch_assoc($user_q);
    $wallet_amount = (float)($user_d['balance'] ?? 0);

    // वैलिडेशन्स
    if (empty($pan_no) || empty($name) || empty($fathername) || empty($dobadhar) || empty($gender)) {
        echo json_encode(['status' => 'error', 'msg' => 'All fields are mandatory.']);
        exit;
    }

    if ($wallet_amount <$save_fee) {
        echo json_encode(['status' => 'error', 'msg' => 'Insufficient balance to save this entry. Required: ₹' . $save_fee]);
        exit;
    }

    // डुप्लीकेट चेक
    $clean_panno = mysqli_real_escape_string($DarkXen_conn,$pan_no);
    $check_query = mysqli_query($DarkXen_conn, "SELECT panno FROM panauto WHERE panno = '$clean_panno'");
    if (mysqli_num_rows($check_query) > 0) {
        echo json_encode(['status' => 'error', 'msg' => 'This PAN Card number already exists in history.']);
        exit;
    }

    // इमेजेस हैंडलिंग
    $target_dir = "uploads/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $photo_data =$_POST['image_data'] ?? '';
    $sign_data =$_POST['sign_data'] ?? '';

    if (empty($photo_data) || empty($sign_data)) {
        echo json_encode(['status' => 'error', 'msg' => 'Please upload both Photo and Signature.']);
        exit;
    }

    // फोटो सेव करें
    define('UPLOAD_DIR', 'uploads/');
    $img_parts = explode(";base64,", $photo_data);$img_base64 = base64_decode($img_parts[1]);$photo_filename = UPLOAD_DIR . 'photo_' . time() . '_' . rand(1000, 9999) . '.jpg';
    file_put_contents($photo_filename,$img_base64);

    // सिग्नेचर सेव करें
    $sign_parts = explode(";base64,", $sign_data);$sign_base64 = base64_decode($sign_parts[1]);$sign_filename = UPLOAD_DIR . 'sign_' . time() . '_' . rand(1000, 9999) . '.jpg';
    file_put_contents($sign_filename,$sign_base64);

    // डेटाबेस ट्रांजैक्शन शुरू करें
    mysqli_begin_transaction($DarkXen_conn);

    try {
        $timestamp = date("Y-m-d H:i:s");
        $u_phone = mysqli_real_escape_string($DarkXen_conn,$username);
        $u_name = mysqli_real_escape_string($DarkXen_conn, $name);$u_father = mysqli_real_escape_string($DarkXen_conn,$fathername);
        $u_dob = mysqli_real_escape_string($DarkXen_conn, $dobadhar);$u_gender = mysqli_real_escape_string($DarkXen_conn,$gender);
        $u_img = mysqli_real_escape_string($DarkXen_conn, $photo_filename);$u_sign = mysqli_real_escape_string($DarkXen_conn,$sign_filename);

        // 1. Insert into panauto
        $query = "INSERT INTO panauto(`userid`,`panno`,`name`,`fathername`,`dob`,`gender`,`image`,`signimage`,`create_time`) 
                  VALUES('$u_phone','$clean_panno','$u_name','$u_father','$u_dob','$u_gender','$u_img','$u_sign','$timestamp')";
        if (!mysqli_query($DarkXen_conn,$query)) {
            throw new Exception("Failed to insert PAN record.");
        }

        // 2. Wallet Deduct
        $debit = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $save_fee WHERE phone='$u_phone' AND balance >=$save_fee");
        if (mysqli_affected_rows($DarkXen_conn) == 0) {
            throw new Exception("Wallet deduction failed.");
        }

        // 3. Wallet History Update
        $new_balance = $wallet_amount -$save_fee;
        $history_purpose = "PAN Advance Save: " . $pan_no;
        $stmt_wallet = mysqli_prepare($DarkXen_conn, "INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES (?, ?, ?, ?, '1', 'Debit')");
        $stmt_wallet->bind_param("sdds", $username,$save_fee, $new_balance,$history_purpose);
        $stmt_wallet->execute();$stmt_wallet->close();

        mysqli_commit($DarkXen_conn);
        
        $_SESSION["Panno"] = $pan_no;

        echo json_encode(['status' => 'success', 'msg' => 'Data saved successfully and wallet debited ₹' . $save_fee]);
        exit;

    } catch (Exception $e) {
        mysqli_rollback($DarkXen_conn);
        if (file_exists($photo_filename)) unlink($photo_filename);
        if (file_exists($sign_filename)) unlink($sign_filename);
        
        echo json_encode(['status' => 'error', 'msg' => 'Database Transaction Error: ' . $e->getMessage()]);
        exit;
    }
}

// होम लेआउट इंक्लूड करें
include('header.php');
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-id-card text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-11 mb-0">Welcome, <?php echo htmlspecialchars($udata['name'] ?? 'User'); ?></h6>
                        <h4 class="text-primary fw-bold mb-0">PAN Advance <span class="text-info fw-medium">Application</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <div class="form-control form-control-sm d-flex align-items-center bg-white border-200 shadow-sm px-3 py-2">
                        <span class="fas fa-wallet text-success me-2"></span>
                        <span class="fw-bold text-success fs-10">Wallet: ₹<?= number_format((float)($udata['balance'] ?? 0), 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="panForm" autocomplete="off">
    <input type="hidden" name="action_type" id="action_type" value="save_pan">
    
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 border-top border-4 border-primary h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 text-1000 fw-bold"><i class="fas fa-edit me-2 text-primary"></i>Enter PAN Details</h5>
                    <div class="badge badge-subtle-danger fs-10 p-2 fw-bold border border-danger-subtle"><i class="fas fa-rupee-sign me-1"></i> Save/Print Fee: ₹<?= number_format($save_fee, 2); ?></div>
                </div>
                <div class="card-body p-4 bg-body-tertiary">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fs-10 text-900 fw-bold">PAN Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fas fa-id-card text-secondary"></i></span>
                                <input class="form-control shadow-none font-family-monospace text-uppercase tracking-wide" name="panNumber" id="panNumber" type="text" placeholder="ABCDE1234F" maxlength="10" required>
                                <button class="btn btn-primary shadow-none px-4 fw-bold" type="button" onclick="autoFetchPan()">
                                    <i class="fas fa-bolt me-1"></i> Quick Fetch
                                </button>
                            </div>
                            <small class="text-500 mt-1 d-block"><i class="fas fa-info-circle me-1"></i> Fetching data will auto-fill the form and deduct ₹<?= number_format($fetch_fee, 2); ?> from your wallet instantly.</small>
                        </div>
                        
                        <!-- These fields will be AUTO FILLED magically via JS -->
                        <div class="col-md-6 mt-4">
                            <label class="form-label fs-10 text-900 fw-bold">Full Name <span class="text-danger">*</span></label>
                            <input class="form-control shadow-none text-uppercase bg-white fw-bold" id="name" name="name" type="text" placeholder="ENTER NAME" required>
                        </div>
                        <div class="col-md-6 mt-4">
                            <label class="form-label fs-10 text-900 fw-bold">Father's Name <span class="text-danger">*</span></label>
                            <input class="form-control shadow-none text-uppercase bg-white fw-bold" id="fathername" name="fathername" type="text" placeholder="FATHER NAME" required>
                        </div>
                        <div class="col-md-6 mt-3">
                            <label class="form-label fs-10 text-900 fw-bold">Gender <span class="text-danger">*</span></label>
                            <select class="form-select shadow-none bg-white fw-bold" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Transgender">Transgender</option>
                            </select>
                        </div>
                        <div class="col-md-6 mt-3">
                            <label class="form-label fs-10 text-900 fw-bold">Date of Birth <span class="text-danger">*</span></label>
                            <input class="form-control shadow-none bg-white fw-bold" id="dobadhar" name="dobadhar" type="text" placeholder="DD/MM/YYYY" required>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="row g-3">
                <div class="col-12">
                    <div class="card shadow-sm border-0 border-top border-4 border-info">
                        <div class="card-header bg-light py-2 text-center border-bottom">
                            <h6 class="mb-0 fw-bold fs-10 text-info">Applicant Photo (Under 100KB)</h6>
                        </div>
                        <div class="card-body text-center bg-white">
                            <div class="border rounded bg-light d-flex align-items-center justify-content-center mx-auto mb-3 shadow-sm" style="height: 140px; width:120px; overflow: hidden;">
                                <img id="imgPreview" src="https://via.placeholder.com/120x140?text=Photo" style="height: 100%; width: 100%; object-fit: cover;">
                            </div>
                            <input type="file" id="img_up" class="form-control form-control-sm shadow-none" accept="image/png, image/jpeg" onchange="readImg(this);" required>
                            <input type="hidden" name="image_data" id="image_data">
                        </div>
                    </div>
                </div>
                
                <div class="col-12">
                    <div class="card shadow-sm border-0 border-top border-4 border-warning">
                        <div class="card-header bg-light py-2 text-center border-bottom">
                            <h6 class="mb-0 fw-bold fs-10 text-warning">Applicant Signature (Under 100KB)</h6>
                        </div>
                        <div class="card-body text-center bg-white">
                            <div class="border rounded bg-light d-flex align-items-center justify-content-center mx-auto mb-3 shadow-sm" style="height: 60px; width:150px; overflow: hidden;">
                                <img id="signPreview" src="https://via.placeholder.com/150x60?text=Sign" style="height: 100%; width: 100%; object-fit: contain;">
                            </div>
                            <input type="file" id="sign_up" class="form-control form-control-sm shadow-none" accept="image/png, image/jpeg" onchange="readSign(this);" required>
                            <input type="hidden" name="sign_data" id="sign_data">
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <button type="button" class="btn btn-success w-100 shadow-sm fw-bold py-3 mt-2" onclick="submitAjaxForm()">
                        <i class="fas fa-save me-2"></i>Save & Generate Print
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // Auto-uppercase and filter PAN input
    document.getElementById('panNumber').addEventListener('input', function () {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    });

    // 🚀 Fast Fetch Logic + Auto Fill
    function autoFetchPan() {
        let pan = $('#panNumber').val().trim();
        
        if (pan.length !== 10) {
            Swal.fire('Warning', 'Please enter a valid 10-digit PAN number.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Fetch Details?',
            text: '₹<?= number_format($fetch_fee, 2); ?> will be deducted for this API search. Proceed?',
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, Fetch!'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Fetching Data...',
                    text: 'Connecting to centralized server, please wait...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                let fetchFormData = new FormData();
                fetchFormData.append('action_type', 'fetch_pan');
                fetchFormData.append('panNumber', pan);

                fetch(window.location.href, {
                    method: 'POST',
                    body: fetchFormData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // ⚡ Instant Auto-Fill form fields
                        $('#name').val(data.data.name);
                        $('#fathername').val(data.data.fatherName);
                        $('#dobadhar').val(data.data.dob);
                        $('#gender').val(data.data.gender);
                        
                        Swal.fire('Success!', 'Data Auto-filled successfully!', 'success');
                    } else {
                        Swal.fire('Error', data.msg, 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'Server connection failed while fetching.', 'error');
                });
            }
        });
    }

    function readImg(input) {
        if (input.files && input.files[0]) {
            if(input.files[0].size > 102400) { 
                Swal.fire('Error','Photo size must be under 100KB','error'); 
                input.value=''; 
                return; 
            }
            var reader = new FileReader();
            reader.onload = function (e) {
                $('#imgPreview').attr('src', e.target.result);
                $('#image_data').val(e.target.result);
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    function readSign(input) {
        if (input.files && input.files[0]) {
            if(input.files[0].size > 102400) { 
                Swal.fire('Error','Signature size must be under 100KB','error'); 
                input.value=''; 
                return; 
            }
            var reader = new FileReader();
            reader.onload = function (e) {
                $('#signPreview').attr('src', e.target.result);
                $('#sign_data').val(e.target.result);
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function submitAjaxForm() {
        let form = document.getElementById('panForm');
        
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        // FIXED: || operator corrected
        if (!$('#image_data').val() && !$('#sign_data').val()) {
            Swal.fire('Warning', 'Please upload both Applicant Photo and Signature.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Confirm Save?',
            text: '₹' + parseFloat(<?= json_encode($save_fee); ?>).toFixed(2) + ' will be debited from your wallet to generate print details.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, Save & Pay!'
        }).then((result) => {
            if (result.isConfirmed) {
                
                Swal.fire({
                    title: 'Processing...',
                    text: 'Please wait while we update your data and wallet.',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                $('#action_type').val('save_pan');
                let formData = new FormData(form);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Saved Successfully!',
                            text: data.msg,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = 'pan_manual_list.php'; 
                        });
                    } else {
                        Swal.fire('Error', data.msg, 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Server Error', 'Failed to connect to the server while saving.', 'error');
                });
            }
        });
    }
</script>

<?php include('footer.php'); ?>