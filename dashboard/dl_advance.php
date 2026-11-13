<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

$fee = 15;
$dl_info = null; 

// Auto-fill variables initialization
$api_dlNo = "";
$api_state = "";
$api_name = "";
$api_father = "";
$api_dob = "";
$api_address = "";
$api_issue = "";
$api_val_nt = "";
$api_val_tr = "NA";
$api_rto = "";
$api_cov = "";
$api_photo = "";
$api_sign = "";

if (isset($_POST['submit'])) {
    $dlNo = trim($_POST['dlNo']);
    $dob = trim($_POST['dob']);
    $appliedby = $udata['phone'];
    
    if ($udata['balance'] >= $fee) {
        // Target API URL with Token (Naye URL format ke hisaab se)
        $api_token = urlencode($DarkXen_apikey);
        $url = "https://" . htmlspecialchars($DarkXen_url) . "/api/v3/DL/dl_details_verification.php?apiKey=" . $api_token . "&dlno=" . urlencode($dlNo) . "&dob=" . urlencode($dob);
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        
        $resdata = json_decode($response, true);

        // Check for Status '200' or True
        if (isset($resdata['status']) && ($resdata['status'] === true || $resdata['sampleCode'] == "200")) {
            
            // Check if nested data exists
            if (isset($resdata['raw']['getDLLastEndRTODetForGivenDLNumber']['data']['Result'][0])) {
                $result = $resdata['raw']['getDLLastEndRTODetForGivenDLNumber']['data']['Result'][0];
                
                // Extracting details mapping to new JSON keys
                $api_dlNo   = upperCaseString(trim($result['dlno'] ?? $dlNo));
                
                // Date formatting from YYYY-MM-DD to DD-MM-YYYY
                $raw_dob = $result['applDateOfBirth'] ?? $dob;
                $api_dob = !empty($raw_dob) ? date('d-m-Y', strtotime($raw_dob)) : $dob;
                
                $api_name   = upperCaseString($result['applFullName'] ?? '');
                
                $f_name = trim($result['relFirstName'] ?? '');
                $m_name = trim($result['relMiddleName'] ?? '');
                $api_father = upperCaseString(trim("$f_name $m_name"));
                
                $api_state  = upperCaseString($result['lastEndState'] ?? substr($api_dlNo, 0, 2));
                $api_rto    = upperCaseString($result['lastEndRTO'] ?? '');
                
                // Address Merging
                $add1 = trim($result['apdTempAdd1'] ?? '');
                $add2 = trim($result['apdTempAdd2'] ?? '');
                $add3 = trim($result['apdTempAdd3'] ?? '');
                $pin  = trim($result['apdTempPin'] ?? '');
                $full_address = array_filter([$add1, $add2, $add3]);
                $api_address = implode(", ", $full_address) . ($pin ? " - $pin" : "");
                
                // Validity & Issue Dates formatting
                $raw_issue = $result['dlNtValdfrDt'] ?? '';
                $api_issue = !empty($raw_issue) ? date('d-m-Y', strtotime($raw_issue)) : '';

                $raw_val_nt = $result['dlNtValdtoDt'] ?? '';
                $api_val_nt = !empty($raw_val_nt) ? date('d-m-Y', strtotime($raw_val_nt)) : '';

                $raw_val_tr = $result['dlTrValdtoDt'] ?? '';
                $api_val_tr = !empty($raw_val_tr) ? date('d-m-Y', strtotime($raw_val_tr)) : 'NA';

                // Class of Vehicles (COV) extraction
                $cov_list_array = [];
                if (isset($result['DLCOVList']) && is_array($result['DLCOVList'])) {
                    foreach ($result['DLCOVList'] as $key => $catItem) {
                        if (isset($catItem['covabbrv'])) {
                            $cov_list_array[] = trim($catItem['covabbrv']);
                        }
                    }
                }
                $api_cov = implode(", ", $cov_list_array);

                // Fetch Base64 Photos & Signatures
                $raw_photo = $result['biPhotoBase64'] ?? '';
                if (!empty($raw_photo)) {
                    // Agar API image header (data:image/jpeg;base64,) nahi bhejti toh hum add karenge
                    if (strpos($raw_photo, 'data:image') === false) {
                        $api_photo = 'data:image/jpeg;base64,' . $raw_photo;
                    } else {
                        $api_photo = $raw_photo;
                    }
                }
                
                $raw_sign = $result['biSignatureBase64'] ?? '';
                if (!empty($raw_sign)) {
                    if (strpos($raw_sign, 'data:image') === false) {
                        $api_sign = 'data:image/jpeg;base64,' . $raw_sign;
                    } else {
                        $api_sign = $raw_sign;
                    }
                }

                echo "<script>Swal.fire('Success', 'Details Extracted Successfully! Check and Render.', 'success');</script>";
            } else {
                echo "<script>Swal.fire('Error', 'DL Record Not Found in RTO Database.', 'error');</script>";
            }
        } else {
            $msg = $resdata['message'] ?? 'Failed to fetch details from API';
            echo "<script>Swal.fire('Error', '" . htmlspecialchars($msg) . "', 'error');</script>";
        }
    } else {
        echo "<script>Swal.fire('Wallet Balance is Low!', 'Please Recharge Now!', 'error');</script>";
    }
}

// -------------------------------------------------------------
// SECURE DB INSERTION & REDIRECT FLOW
// -------------------------------------------------------------
if (isset($_POST['action_route']) && $_POST['action_route'] == 'generate_canvas') {
    $appliedby = $udata['phone'];

    if ($udata['balance'] >= $fee) {
        
        $in_dl      = mysqli_real_escape_string($DarkXen_conn, upperCaseString($_POST['in_dl']));
        $in_state   = mysqli_real_escape_string($DarkXen_conn, upperCaseString($_POST['in_state']));
        $in_name    = mysqli_real_escape_string($DarkXen_conn, upperCaseString($_POST['in_name']));
        $in_father  = mysqli_real_escape_string($DarkXen_conn, upperCaseString($_POST['in_father']));
        $in_dob     = mysqli_real_escape_string($DarkXen_conn, $_POST['in_dob']);
        $in_bg      = mysqli_real_escape_string($DarkXen_conn, upperCaseString($_POST['in_bg']));
        $in_address = mysqli_real_escape_string($DarkXen_conn, $_POST['in_address']);
        $in_issue   = mysqli_real_escape_string($DarkXen_conn, $_POST['in_issue']);
        $in_val_nt  = mysqli_real_escape_string($DarkXen_conn, $_POST['in_val_nt']);
        $in_val_tr  = mysqli_real_escape_string($DarkXen_conn, $_POST['in_val_tr']);
        $in_rto     = mysqli_real_escape_string($DarkXen_conn, upperCaseString($_POST['in_rto']));
        $in_cov     = mysqli_real_escape_string($DarkXen_conn, $_POST['in_cov']);
        $in_card_bg = mysqli_real_escape_string($DarkXen_conn, $_POST['in_card_bg']);

        // Default Base64 from hidden fields (API response)
        $photo_base64 = $_POST['api_photo_data'] ?? '';
        $sign_base64  = $_POST['api_sign_data'] ?? '';

        // 1. Agar User ne New Photo upload ki ho to override karein
        if (isset($_FILES['photo_file']) && $_FILES['photo_file']['error'] == 0) {
            $photo_tmp = $_FILES['photo_file']['tmp_name'];
            $photo_type = $_FILES['photo_file']['type'];
            if (strpos($photo_type, 'image/') === 0) {
                $photo_data = file_get_contents($photo_tmp);
                $photo_base64 = 'data:' . $photo_type . ';base64,' . base64_encode($photo_data);
            }
        }

        // 2. Agar User ne New Signature upload kiya ho to override karein
        if (isset($_FILES['sign_file']) && $_FILES['sign_file']['error'] == 0) {
            $sign_tmp = $_FILES['sign_file']['tmp_name'];
            $sign_type = $_FILES['sign_file']['type'];
            if (strpos($sign_type, 'image/') === 0) {
                $sign_data = file_get_contents($sign_tmp);
                $sign_base64 = 'data:' . $sign_type . ';base64,' . base64_encode($sign_data);
            }
        }

        $photo_db_val = mysqli_real_escape_string($DarkXen_conn, $photo_base64);
        $sign_db_val  = mysqli_real_escape_string($DarkXen_conn, $sign_base64);

        // Wallet Balance Deduct
        $debit_fee = $udata['balance'] - $fee;
        $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
        
        if ($update_wallet) {
            mysqli_query($DarkXen_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('$appliedby','$fee','$debit_fee','DL Detail Print','1','Debit')");

            // Format COV as JSON array
            $cov_array = array_map('trim', explode(",", $in_cov));$cov_json_db = mysqli_real_escape_string($DarkXen_conn, json_encode($cov_array));

            // Database Insertion
            $insert_query = "INSERT INTO `dl_verify_records` (
                `userid`, `dl_number`, `dob`, `full_name`, `father_name`, 
                `address`, `dl_status`, `issued_date`, `expiry_date`, 
                `vehicle_categories`, `photo`, `signature`, `date_created`
            ) VALUES (
                '$appliedby', '$in_dl', '$in_dob', '$in_name', '$in_father', 
                '$in_address', 'ACTIVE', '$in_issue', '$in_val_nt', 
                '$cov_json_db', '$photo_db_val', '$sign_db_val', NOW()
            )";

            if (mysqli_query($DarkXen_conn,$insert_query)) {
                $last_inserted_id = mysqli_insert_id($DarkXen_conn);
                
                $_SESSION['print_payload_card_bg'] =$in_card_bg;
                $_SESSION['print_payload_val_tr']  =$in_val_tr;
                $_SESSION['print_payload_rto']     =$in_rto;
                $encoded_id = rtrim(base64_encode($last_inserted_id), '=');

                echo "<script>
                    Swal.fire({
                        title: 'Success!',
                        text: 'Record saved! Opening secure print canvas...',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.open('dacoment/print_canvas.php?searchid=$encoded_id', '_blank');
                    });
                </script>";
            } else {
                echo "<script>Swal.fire('Error', 'Database Error: " . mysqli_error($DarkXen_conn) . "', 'error');</script>";
            }
        }
    } else {
        echo "<script>Swal.fire('Wallet Balance is Low!', 'Please Recharge Now!', 'error');</script>";
    }
}

function upperCaseString($str) {
    return mb_strtoupper(trim($str ?? ''), 'UTF-8');
}
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-danger-subtle shadow-none me-3">
                        <span class="fas fa-car text-danger fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-danger fs-10 mb-0">Transport Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Advance DL Designer <span class="text-danger fw-medium">(DL) Details</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="dl_advance_list.php" class="btn btn-sm btn-outline-danger shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center mb-4">
    <div class="card h-100 border-0 shadow-sm border-top border-4 border-danger">
        <!-- SEARCH API BLOCK -->
        <form method="POST" action="">
            <div class="row g-3 p-3">
                <div class="col-md-5">
                    <input type="text" name="dlNo" class="form-control text-dark fw-bold" placeholder="DL Number (e.g., MH0320080022135)" value="<?php echo htmlspecialchars($api_dlNo); ?>" required style="text-transform: uppercase;">
                </div>
                <div class="col-md-4">
                    <input type="text" id="api_dob_mask" name="dob" class="form-control text-dark fw-bold" placeholder="DD-MM-YYYY" maxlength="10" value="<?php echo htmlspecialchars($api_dob); ?>" required>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-danger w-100 fw-bold py-2 shadow-none" type="submit" name="submit">
                        <i class="bi bi-download me-2"></i> Get DL Details
                    </button>
                </div>
            </div>
        </form>

        <!-- MANUAL / EDIT FORM -->
        <div class="main-box p-3">
            <p class="text-muted mb-4">Edit or verify fields before clicking "Render & Save To Database" to generate the card.</p>
            
            <form id="dlManualForm" method="POST" enctype="multipart/form-data" action="">
                <input type="hidden" name="action_route" value="generate_canvas">
                
                <!-- Hidden inputs to hold API base64 images -->
                <input type="hidden" name="api_photo_data" value="<?php echo htmlspecialchars($api_photo); ?>">
                <input type="hidden" name="api_sign_data" value="<?php echo htmlspecialchars($api_sign); ?>">

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">DL Number</label>
                        <input type="text" name="in_dl" id="in_dl" class="form-control" required style="text-transform: uppercase;" value="<?php echo htmlspecialchars($api_dlNo); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">State Name</label>
                        <input type="text" name="in_state" id="in_state" class="form-control" required style="text-transform: uppercase;" value="<?php echo htmlspecialchars($api_state); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Full Name</label>
                        <input type="text" name="in_name" id="in_name" class="form-control" required value="<?php echo htmlspecialchars($api_name); ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Father / Husband Name</label>
                        <input type="text" name="in_father" id="in_father" class="form-control" required value="<?php echo htmlspecialchars($api_father); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Date of Birth</label>
                        <input type="text" name="in_dob" id="in_dob" class="form-control" maxlength="10" required value="<?php echo htmlspecialchars($api_dob); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Blood Group</label>
                        <input type="text" name="in_bg" id="in_bg" class="form-control" value="U">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-bold">Permanent Address</label>
                        <textarea name="in_address" id="in_address" class="form-control" rows="2" required><?php echo htmlspecialchars($api_address); ?></textarea>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Issue Date</label>
                        <input type="text" name="in_issue" id="in_issue" class="form-control" required value="<?php echo htmlspecialchars($api_issue); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Validity (NT)</label>
                        <input type="text" name="in_val_nt" id="in_val_nt" class="form-control" required value="<?php echo htmlspecialchars($api_val_nt); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Validity (TR)</label>
                        <input type="text" name="in_val_tr" id="in_val_tr" class="form-control" value="<?php echo htmlspecialchars($api_val_tr); ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">RTO Authority Office</label>
                        <input type="text" name="in_rto" id="in_rto" class="form-control" required value="<?php echo htmlspecialchars($api_rto); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Vehicle Classes (COV)</label>
                        <input type="text" name="in_cov" id="in_cov" class="form-control" required value="<?php echo htmlspecialchars($api_cov); ?>">
                    </div>
                </div>

                <div class="row align-items-center">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Holder Photo (Upload to Change)</label>
                        <input type="file" name="photo_file" class="form-control" accept="image/*">
                        <?php if (!empty($api_photo)): ?>
                            <div class="mt-2">
                                <small class="text-success fw-bold"><i class="fas fa-check-circle"></i> Photo fetched from API</small><br>
                                <img src="<?php echo $api_photo; ?>" alt="Photo Preview" style="height: 60px; border-radius: 4px; border: 1px solid #ccc;">
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Signature (Upload to Change)</label>
                        <input type="file" name="sign_file" class="form-control" accept="image/*">
                        <?php if (!empty($api_sign)): ?>
                            <div class="mt-2">
                                <small class="text-success fw-bold"><i class="fas fa-check-circle"></i> Signature fetched from API</small><br>
                                <img src="<?php echo $api_sign; ?>" alt="Signature Preview" style="height: 40px; border-radius: 4px; border: 1px solid #ccc; background: #fdfdfd;">
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Card Color Style</label>
                        <select name="in_card_bg" id="in_card_bg" class="form-select">
                            <option value="blue">Light Blue Background</option>
                            <option value="white">Standard White Background</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-danger w-100 fw-bold py-2 shadow-none mt-3">
                    <i class="bi bi-cpu-fill me-2"></i>Render & Save To Database
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    window.onload = function() {
        const fields = ['api_dob_mask', 'in_dob', 'in_issue', 'in_val_nt'];
        fields.forEach(id => {
            const el = document.getElementById(id);
            if(el) {
                el.addEventListener('input', function (e) {
                    var c = e.target.value.replace(/\D/g, '');
                    var o = "";
                    if (c.length > 0) { o = c.substring(0, 2); if (c.length > 2) o += '-' + c.substring(2, 4); if (c.length > 4) o += '-' + c.substring(4, 8); }
                    e.target.value = o;
                });
            }
        });
    };
</script>

<?php include('footer.php'); ?>