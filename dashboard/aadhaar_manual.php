<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

// डिफ़ॉल्ट फीस सेट करें
$fee = 5; 

// डेटाबेस से लाइव फीस प्राप्त करें
$fee_query = mysqli_query($DarkXen_conn, "SELECT `aadhar_manual` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if ($fee_row['aadhar_manual'] !== '' && $fee_row['aadhar_manual'] !== null) {
        $fee = (float)$fee_row['aadhar_manual'];
    }
}

$msgno = "";

if (isset($_POST['savedataauto'])) {
    $appliedby = mysqli_real_escape_string($DarkXen_conn, $udata['phone']);

    if ($udata['balance'] >= $fee) {
        $debit_fee = $udata['balance'] - $fee;

        // इनपुट सुरक्षा के साथ वेरिएबल्स को सुरक्षित करें
        $aadharno        = mysqli_real_escape_string($DarkXen_conn, trim($_POST['aadharno']));
        $name            = mysqli_real_escape_string($DarkXen_conn, trim($_POST['name']));
        $fathername      = mysqli_real_escape_string($DarkXen_conn, trim($_POST['fathername']));
        $dobadhar        = mysqli_real_escape_string($DarkXen_conn, trim($_POST['dobadhar']));
        $birthtithilocal = mysqli_real_escape_string($DarkXen_conn, trim($_POST['birthtithilocal']));
        $gender          = mysqli_real_escape_string($DarkXen_conn, trim($_POST['gender']));
        $genderlocal     = mysqli_real_escape_string($DarkXen_conn, trim($_POST['genderlocal']));
        $address         = mysqli_real_escape_string($DarkXen_conn, trim($_POST['address']));
        $language        = mysqli_real_escape_string($DarkXen_conn, trim($_POST['language']));
        $namelocal       = mysqli_real_escape_string($DarkXen_conn, trim($_POST['namelocal']));
        $localaddress    = mysqli_real_escape_string($DarkXen_conn, trim($_POST['addresslocal']));
        $patalocal       = mysqli_real_escape_string($DarkXen_conn, trim($_POST['patalocal']));
        $houseno         = mysqli_real_escape_string($DarkXen_conn, trim($_POST['houseno']));
        $street          = mysqli_real_escape_string($DarkXen_conn, trim($_POST['street']));
        $pincode         = mysqli_real_escape_string($DarkXen_conn, trim($_POST['pincode']));
        $vtcandpost      = mysqli_real_escape_string($DarkXen_conn, trim($_POST['vtcandpost']));
        $dist            = mysqli_real_escape_string($DarkXen_conn, trim($_POST['dist']));
        $statename       = mysqli_real_escape_string($DarkXen_conn, trim($_POST['statename']));

        $target_file = '';
        if (!empty($_FILES['imagefile']['name'])) {
            $target_file = "uploads/" . md5(time() . $_FILES['imagefile']['name']) . ".jpg";
        }

        // वैलिडेशन रूल्स
        if (empty($aadharno)) {
            $msgno = "Please Enter Aadhaar Number";
        } elseif (strlen($aadharno) != 12 || !is_numeric($aadharno)) {
            $msgno = "Invalid Aadhaar Number (Must be 12 digits)";
        } elseif (empty($name)) {
            $msgno = "Please Enter Name";
        } elseif (empty($fathername)) {
            $msgno = "Please Enter Father Name";
        } elseif (empty($dobadhar)) {
            $msgno = "Please Enter Date Of Birth";
        } elseif (empty($address)) {
            $msgno = "Please Enter Address";
        } elseif (empty($target_file)) {
            $msgno = "Please Upload Applicant Photo";
        } else {
            // डुप्लीकेट रिकॉर्ड की जांच करें
            $check = mysqli_query($DarkXen_conn, "SELECT id FROM aadharmanual WHERE aadharno='$aadharno'");

            if (mysqli_num_rows($check) > 0) {
                $msgno = "This Aadhaar Number Already Exists in Database!";
            } else {
                // डिस्प्ले फॉर्मेट के लिए स्पेसिंग जोड़ें
                $adhrno = substr($aadharno, 0, 4) . " " . substr($aadharno, 4, 4) . " " . substr($aadharno, 8, 4);

                $resultm = mysqli_query($DarkXen_conn, "SELECT MAX(srno) AS srno FROM aadharmanual");
                $row = mysqli_fetch_assoc($resultm);
                $srno = (!empty($row['srno'])) ? ($row['srno'] + 1) : 1;

                mysqli_set_charset($DarkXen_conn, "utf8");
                
                $query = mysqli_query($DarkXen_conn, "
                    INSERT INTO aadharmanual 
                    (aadharno, originalaadharno, aadharname, fathername, dob, gender, sexinlocal, fulladdress, locallanguage, localname, localaddress, imagepathoriginal, dobinlocal, pata, houseno, street, vtcandpost, dist, statename, pincode, srno, userid, createdatetime) 
                    VALUES 
                    ('$aadharno', '$adhrno', '$name', '$fathername', '$dobadhar', '$gender', '$genderlocal', '$address', '$language', '$namelocal', '$localaddress', '$target_file', '$birthtithilocal', '$patalocal', '$houseno', '$street', '$vtcandpost', '$dist', '$statename', '$pincode', '$srno', '" . $_SESSION['phone'] . "', NOW())
                ");

                if ($query) {
                    if (!empty($_FILES['imagefile']['tmp_name'])) {
                        move_uploaded_file($_FILES['imagefile']['tmp_name'], $target_file);
                    }

                    // वॉलेट से पैसे काटें
                    mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - '$fee' WHERE phone='$appliedby'");

                    // वॉलेट हिस्ट्री में रिकॉर्ड दर्ज करें
                    mysqli_query($DarkXen_conn, "
                        INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) 
                        VALUES ('$appliedby', '$fee', '$debit_fee', 'AADHAR MANUAL " . substr($aadharno, 0, 4) . "XXXX" . substr($aadharno, -4) . "', '1', 'Debit')
                    ");
                    ?>
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Application Submitted Successfully',
                            showConfirmButton: false,
                            timer: 1500
                        });
                        setTimeout(function(){ window.location='aadharmanual_list.php'; }, 1500);
                    });
                    </script>
                    <?php
                } else {
                    $msgno = "Database Error: " . mysqli_error($DarkXen_conn);
                }
            }
        }
    } else {
        $msgno = "Wallet Balance Low! Required: ₹" . number_format($fee, 2);
    }
}

if (!empty($msgno)) {
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({ icon: 'error', title: 'Action Denied', text: '<?= addslashes($msgno) ?>' });
});
</script>
<?php } ?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center">
                <div class="col-sm-auto d-flex align-items-center">
                    <img class="ms-n2" src="../assets/img/illustrations/crm-bar-chart.png" alt="" width="90" />
                    <div>
                        <h6 class="text-primary fs-11 mb-0">Welcome back, <?php echo htmlspecialchars($udata['name']); ?></h6>
                        <h4 class="text-primary fw-bold mb-0"><?php echo web('webname'); ?> <span class="text-info fw-medium">Aadhaar Manual</span></h4>
                    </div>
                </div>
                <div class="col-md-auto p-3">
                    <div class="form-control form-control-sm d-flex align-items-center bg-white border-200 shadow-sm" style="min-width: 140px;">
                        <span class="fas fa-wallet text-success me-2"></span>
                        <span class="fw-bold text-success">Wallet: ₹<?= number_format($udata['balance'], 2) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="card shadow-sm border-0 border-top border-4 border-primary">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-1000 fw-bold"><i class="bx bxs-id-card me-2 text-primary"></i>Enter Aadhaar Details Manually</h5>
                <a href="https://www.google.com/intl/sa/inputtools/try/" target="_blank" class="btn btn-falcon-default btn-sm">
                    <i class="fas fa-language me-1 text-info"></i> Google Input Tools
                </a>
            </div>
            <div class="card-body p-4 bg-body-tertiary">
                <form method="post" autocomplete="off" enctype="multipart/form-data" onsubmit="return validation();">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Aadhaar Card No. <span class="text-danger">*</span></label>
                            <input class="form-control bg-white" id="aadharno" name="aadharno" type="text" maxlength="12" placeholder="12 Digit Aadhaar Number" required>
                            <span id="erroraadharno" class="text-danger fs-11"></span>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Full Name <span class="text-danger">*</span></label>
                            <input class="form-control bg-white" id="name" name="name" type="text" placeholder="Applicant Name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Father/Husband Name <span class="text-danger">*</span></label>
                            <input class="form-control bg-white" id="fathername" name="fathername" type="text" placeholder="Guardian Name" oninput="setaddress()" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">House No</label>
                            <input class="form-control bg-white" id="houseno" name="houseno" type="text" oninput="setaddress()" placeholder="House/Flat No">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Gali, Locality</label>
                            <input class="form-control bg-white" id="streetlocality" name="street" type="text" oninput="setaddress()" placeholder="Street, Area, Locality">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Post Office</label>
                            <input class="form-control bg-white" id="vtcandpost" name="vtcandpost" type="text" oninput="setaddress()" placeholder="Post Office">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">City / District <span class="text-danger">*</span></label>
                            <input class="form-control bg-white" id="city" name="dist" type="text" oninput="setaddress()" placeholder="District Name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">State <span class="text-danger">*</span></label>
                            <input class="form-control bg-white" id="state" name="statename" type="text" oninput="setaddress()" placeholder="State Name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Pin code <span class="text-danger">*</span></label>
                            <input class="form-control bg-white" id="pincode" name="pincode" type="text" maxlength="6" oninput="setaddress()" placeholder="6 Digit Pincode" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Date Of Birth <span class="text-danger">*</span></label>
                            <input class="form-control bg-white" name="dobadhar" type="date" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Date Of Birth (Local)</label>
                            <input class="form-control bg-white" id="birthtithilocal" name="birthtithilocal" type="text" placeholder="Auto Fill via Translation">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fs-10 text-900">Gender <span class="text-danger">*</span></label>
                            <select class="form-select bg-white" name="gender" id="gender" required>
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fs-10 text-900">Gender (Local)</label>
                            <input class="form-control bg-white" id="genderlocal" name="genderlocal" type="text" placeholder="Auto Fill">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-9">
                            <label class="form-label fs-10 text-900">Full Address (English) <span class="text-danger">*</span></label>
                            <textarea class="form-control bg-white" id="txtSource" name="address" rows="3" placeholder="Address will auto-generate here..." required></textarea>
                            <span id="errortxtSource" class="text-danger fs-11"></span>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-10 text-900 text-danger fw-bold"><i class="fas fa-image me-1"></i>Applicant Photo <span class="text-danger">*</span></label>
                            <input type="file" name="imagefile" class="form-control bg-white" id="imgInp" accept="image/jpeg, image/png" required />
                            <div class="mt-2 text-center">
                                <img src="" id="blah" class="img-thumbnail" style="display: none; max-height: 100px;">
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-200 rounded mb-4 border">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label fs-10 text-900 fw-bold">1. Select Local Language</label>
                                <select class="form-select" onchange="changelang()" name="language" id="language" required>
                                    <option value="">SELECT</option>
                                    <option value="hi">Hindi</option>
                                    <option value="pa">Punjabi</option>
                                    <option value="gu">Gujarati</option>
                                    <option value="mr">Marathi</option>
                                    <option value="kn">Kannada</option>
                                    <option value="bn">Bengali</option>
                                    <option value="te">Telugu</option>
                                    <option value="or">Oriya</option>
                                    <option value="sd">Sindhi</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fs-10 text-900">Name (Local) <span class="text-danger">*</span></label>
                                <input class="form-control bg-white" id="name_regional" name="namelocal" type="text" placeholder="Auto Translate" readonly required>
                                <span id="errorname_regional" class="text-danger fs-11"></span>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fs-10 text-900">Address (Local) <span class="text-danger">*</span></label>
                                <textarea class="form-control bg-white" id="txtTarget" name="addresslocal" rows="1" placeholder="Auto Translate" readonly required></textarea>
                                <span id="errortxtTarget" class="text-danger fs-11"></span>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" id="pata" value="address">
                    <input type="hidden" id="patalocal" name="patalocal" value="">

                    <div class="text-end border-top pt-3">
                        <h6 class="text-danger d-inline-block me-4">Application Fee: ₹<?= number_format($fee, 2) ?></h6>
                        <button type="submit" name="savedataauto" class="btn btn-falcon-success px-5 fw-semi-bold shadow-none">
                            <i class="fas fa-check-circle me-2"></i>Save & Deduct ₹<?= number_format($fee, 2) ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Image Preview
    function readURL(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function (e) {
                $('#blah').attr('src', e.target.result).show();
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    $("#imgInp").change(function(){ readURL(this); });

    // Address Builder
    function setaddress(){
        var fathername = document.getElementById('fathername').value;
        var houseno = document.getElementById('houseno').value;
        var streetlocality = document.getElementById('streetlocality').value;
        var vtcandpost = document.getElementById('vtcandpost').value;
        var state = document.getElementById('state').value;
        var city = document.getElementById('city').value;
        var pincode = document.getElementById('pincode').value;
        
        var addressParts = [];
        if(fathername) addressParts.push("S/O : " + fathername);
        if(houseno) addressParts.push(houseno);
        if(streetlocality) addressParts.push(streetlocality);
        if(vtcandpost) addressParts.push(vtcandpost);
        if(city) addressParts.push(city);
        if(state) addressParts.push(state);
        if(pincode) addressParts.push("- " + pincode);

        var fullAddress = addressParts.join(", ");
        document.getElementById('txtSource').value = fullAddress;
        
        if(document.getElementById('language').value != "") {
            changelang();
        }
    }

    // Form Validation
    function validation() {
        var txtSource = document.getElementById('txtSource').value;
        if (txtSource.trim() == "") {
            document.getElementById('errortxtSource').innerHTML = " **Please Enter Address !!!";
            document.getElementById('txtSource').style.border = "1px solid red";
            return false;
        }
        var name_regional = document.getElementById('name_regional').value;
        if (name_regional.trim() == "") {
            document.getElementById('errorname_regional').innerHTML = " **Please Select Language to Translate !!!";
            document.getElementById('name_regional').style.border = "1px solid red";
            return false;
        }
        var txtTarget = document.getElementById('txtTarget').value;
        if (txtTarget.trim() == "") {
            document.getElementById('errortxtTarget').innerHTML = " **Please Select Language to Translate !!!";
            document.getElementById('txtTarget').style.border = "1px solid red";
            return false;
        }
        
        var aadharno = document.getElementById('aadharno').value;
        if(aadharno.length != 12 || isNaN(aadharno)) {
            document.getElementById('erroraadharno').innerHTML = " **Please enter valid 12 digit Aadhaar number !!!";
            return false;
        }
        
        return true;
    }

    // Google Translate API Logic
    function changelang() {
        var lang = document.getElementById("language").value;
        if(lang == "") return;

        function translateField(sourceText, targetId, callback = null) {
            if(sourceText == "") return;
            var url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&tl=" + lang + "&dt=t&q=" + encodeURI(sourceText);
            
            $.get(url, function (data) {
                var result = '';
                for(var i=0; i<data[0].length; i++) {
                    result += data[0][i][0];
                }
                $("#" + targetId).val(result);
                if(callback) callback(result);
            }).fail(function() {
                console.log("Translation failed for ID: " + targetId);
            });
        }

        // Translate Core Fields
        translateField($("#txtSource").val(), "txtTarget");
        translateField($("#name").val(), "name_regional");
        
        // Translate Birth Tithi
        translateField("Date of Birth", "birthtithilocal");
        
        // Translate Gender
        var genderText = $("#gender").val();
        if(genderText) {
            translateField(genderText, "genderlocal", function(result) {
                if(lang == 'hi' && (result == "नर" || result == "पुरुष")) $('#genderlocal').val("पुरुष");
                if(lang == 'pa') $('#genderlocal').val(genderText == "Male" ? "ਮਰਦ" : "ਔਰਤ");
            });
        }
    }
</script>

<?php include('footer.php'); ?>