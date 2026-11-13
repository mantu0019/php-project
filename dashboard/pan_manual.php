<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

$msgno = ""; // एरर मैसेज के लिए वेरिएबल

if (isset($_POST['savedata'])) {    
    $aadharno = trim($_POST['pannumber']);
    $name = trim($_POST['name']);
    $fathername = trim($_POST['fathername']);
    $dobadhar = trim($_POST['dobadhar']);
    $gender = trim($_POST['gender']);

    $file = $_FILES['imagefile']['name'];
    $target_dir = "uploads/";
    $target_file = $target_dir . basename($_FILES["imagefile"]["name"]);

    $signfile = $_FILES['signfile']['name'];
    $sign_dir = "uploads/";
    $sign_file = $sign_dir . basename($_FILES["signfile"]["name"]);

    if ($aadharno == "") {
        $msgno = 'Please Enter Pan Card No .... ';
    } elseif ($name == "") {
        $msgno = 'Please Enter Name .... ';
    } elseif ($fathername == "") {
        $msgno = 'Please Enter Father Name .... ';
    } elseif ($dobadhar == "") {
        $msgno = 'Please Enter Date of Birth .... ';
    } elseif ($gender == "") {
        $msgno = 'Please Enter Gender .... ';
    } elseif (empty($_FILES['imagefile']['tmp_name']) || empty($_FILES['signfile']['tmp_name'])) {
        $msgno = 'Please upload both Photo and Signature .... ';
    } else { 
        $clean_panno = mysqli_real_escape_string($DarkXen_conn, $aadharno);
        $a = mysqli_query($DarkXen_conn, "SELECT panno FROM panauto WHERE panno ='$clean_panno'");
        $b = mysqli_fetch_array($a);

        if (isset($b['panno']) && $b['panno'] == $aadharno) {
            $msgno = 'This Pan Card No Already Exist .... ';
        } else {
            $word = "image";
            $sign_image_type = $_FILES["signfile"]["type"];
            $image_type = $_FILES["imagefile"]["type"];
            
            if ((strpos($sign_image_type, $word) !== false) && (strpos($image_type, $word) !== false)) {
                
                date_default_timezone_set('Asia/Kolkata');
                $timestamp = date("Y-m-d H:i:s");

                // SQL सुरक्षित करने के लिए सभी वैल्यूज को एस्केप किया
                $u_phone = mysqli_real_escape_string($DarkXen_conn, $_SESSION['phone']);
                $u_name = mysqli_real_escape_string($DarkXen_conn, $name);
                $u_father = mysqli_real_escape_string($DarkXen_conn, $fathername);
                $u_dob = mysqli_real_escape_string($DarkXen_conn, $dobadhar);
                $u_gender = mysqli_real_escape_string($DarkXen_conn, $gender);
                $u_img = mysqli_real_escape_string($DarkXen_conn, $target_file);
                $u_sign = mysqli_real_escape_string($DarkXen_conn, $sign_file);

                $query = "INSERT INTO panauto(`userid`,`panno`,`name`,`fathername`,`dob`,`gender`,`image`,`signimage`,`create_time`) 
                          VALUES('$u_phone','$clean_panno','$u_name','$u_father','$u_dob','$u_gender','$u_img','$u_sign','$timestamp')";

                $result = mysqli_query($DarkXen_conn, $query);

                if ($result) {
                    move_uploaded_file($_FILES["imagefile"]["tmp_name"], $target_file);
                    move_uploaded_file($_FILES["signfile"]["tmp_name"], $sign_file);

                    $_SESSION["IMGPATH"] = '';
                    $_SESSION["Panno"] = trim($aadharno);

                    // Fetch Fee
                    $fee = 10; 
                    $fee_query = mysqli_query($DarkXen_conn, "SELECT `pan_manual` FROM `service_free` WHERE `id` = 1");
                    if ($fee_query && mysqli_num_rows($fee_query) > 0) {
                        $fee_row = mysqli_fetch_assoc($fee_query);
                        if ($fee_row['pan_manual'] !== '' && $fee_row['pan_manual'] !== null) {
                            $fee = $fee_row['pan_manual'];
                        }
                    }
                    
                    $appliedby = $udata['phone'];
                    $debit_fee = $udata['balance'] - $fee;

                    $debit = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - '$fee' WHERE phone='$appliedby'");

                    if ($debit) {
                        mysqli_query($DarkXen_conn, "INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) 
                                                  VALUES ('$appliedby', '$fee', '$debit_fee', 'PAN MANUAL $aadharno', '1', 'Debit')");


                        $msgno = "Please Wait Pan Preview just a second...";
                        echo "<script>window.location.href = 'pan_manual_list.php';</script>";
                        exit();
                    } else {
                        die("Wallet Deduction Failed : ");
                    }
                } else {
                    // यहाँ असली डेटाबेस एरर दिखेगा अगर कोई गड़बड़ होगी
                    $msgno = "Database Error: ";
                }
            } else {
                $msgno = "Please select PNG and JPEG image only.";
            }
        }
    }
}
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
                        <h6 class="text-primary fs-11 mb-0">Welcome back, <?php echo htmlspecialchars($udata['name']); ?></h6>
                        <h4 class="text-primary fw-bold mb-0">PAN Manual <span class="text-info fw-medium">Application</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <div class="form-control form-control-sm d-flex align-items-center bg-white border-200 shadow-sm">
                        <span class="fas fa-wallet text-success me-2"></span>
                        <span class="fw-bold text-success">Wallet: ₹<?= round($udata['balance'], 2) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="panForm" autocomplete="off" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="savedata" value="1">
    
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 border-top border-4 border-primary h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-1000 fw-bold"><i class="fas fa-edit me-2 text-primary"></i>Enter PAN Details</h5>
                    <div class="badge badge-subtle-danger fs-10 p-2 fw-bold"><i class="fas fa-rupee-sign me-1"></i> Print Fee: ₹10</div>
                </div>
                <div class="card-body p-4 bg-body-tertiary">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fs-10 text-900 fw-bold">PAN Number <span class="text-danger">*</span></label>
                            <input class="form-control shadow-sm text-uppercase" name="pannumber" id="panNumber" type="text" placeholder="ABCDE1234F" maxlength="10" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-10 text-900 fw-bold">Full Name <span class="text-danger">*</span></label>
                            <input class="form-control shadow-sm text-uppercase" name="name" type="text" placeholder="ENTER NAME" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fs-10 text-900 fw-bold">Father's Name <span class="text-danger">*</span></label>
                            <input class="form-control shadow-sm text-uppercase" name="fathername" type="text" placeholder="FATHER NAME" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-10 text-900 fw-bold">Gender <span class="text-danger">*</span></label>
                            <select class="form-select shadow-sm" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Transgender">Transgender</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-10 text-900 fw-bold">Date of Birth <span class="text-danger">*</span></label>
                            <input class="form-control shadow-sm" name="dobadhar" type="text" placeholder="DD/MM/YYYY" required>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="row g-3">
                <div class="col-12">
                    <div class="card shadow-sm border-0 border-top border-4 border-info">
                        <div class="card-header bg-light py-2 text-center">
                            <h6 class="mb-0 fw-bold fs-10">Applicant Photo (Under 100KB)</h6>
                        </div>
                        <div class="card-body text-center bg-body-tertiary">
                            <div class="border rounded bg-white d-flex align-items-center justify-content-center mx-auto mb-3" style="height: 140px; width:120px; overflow: hidden;">
                                <img id="imgPreview" src="https://via.placeholder.com/120x140?text=Photo" style="max-height: 100%;">
                            </div>
                            <input type="file" id="img_up" name="imagefile" class="form-control form-control-sm" accept="image/png, image/jpeg" onchange="readImg(this);" required>
                        </div>
                    </div>
                </div>
                
                <div class="col-12">
                    <div class="card shadow-sm border-0 border-top border-4 border-warning">
                        <div class="card-header bg-light py-2 text-center">
                            <h6 class="mb-0 fw-bold fs-10">Applicant Signature (Under 100KB)</h6>
                        </div>
                        <div class="card-body text-center bg-body-tertiary">
                            <div class="border rounded bg-white d-flex align-items-center justify-content-center mx-auto mb-3" style="height: 60px; width:150px; overflow: hidden;">
                                <img id="signPreview" src="https://via.placeholder.com/150x60?text=Sign" style="max-height: 100%;">
                            </div>
                            <input type="file" id="sign_up" name="signfile" class="form-control form-control-sm" accept="image/png, image/jpeg" onchange="readSign(this);" required>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <button type="button" class="btn btn-success w-100 shadow-sm fw-bold py-3" onclick="submitAjaxForm()">
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
function readImg(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function (e) {
            $('#imgPreview').attr('src', e.target.result);
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Signature preview function
function readSign(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function (e) {
            $('#signPreview').attr('src', e.target.result);
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function submitAjaxForm() {
    var form = document.getElementById('panForm');
    if(form.checkValidity()) {
        form.submit();
    } else {
        form.reportValidity();
    }
}
</script>

<?php
// केवल एरर दिखाने के लिए SweetAlert
if (!empty($msgno)) {
    echo '<script>
        $(document).ready(function() {
            Swal.fire("Error", "' . addslashes($msgno) . '", "error");
        });
    </script>';
}
?>

<?php include('footer.php'); ?>