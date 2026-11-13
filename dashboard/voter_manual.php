<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set('Asia/Kolkata');

// Helper function definitions safely wrap karne ke liye (agar clean function defined na ho)
if (!function_exists('clean')) {
    function clean($conn, $data) {
        return mysqli_real_escape_string($conn, trim($data));
    }
}

// Fetch Fee
$fee = 10; 
$fee_query = mysqli_query($DarkXen_conn, "SELECT `voter_manual` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if ($fee_row['voter_manual'] !== '' && $fee_row['voter_manual'] !== null) {
        $fee = $fee_row['voter_manual'];
    }
}

$error_message = "";
$status = "";

// 1. PHP फॉर्म सबमिशन हैंडलर
if (isset($_POST['savedata'])) {
    
    if ($udata['balance'] < $fee) {
        $error_message = "Wallet Balance Low Please Recharge";
    } else {
        // फॉर्म डेटा सुरक्षित रूप से प्राप्त करें (Cleaned with Connection)
        $epicno          = clean($DarkXen_conn, $_POST['epicno']);
        $name            = clean($DarkXen_conn, strtoupper($_POST['name']));
        $namelocal       = clean($DarkXen_conn, $_POST['namelocal']);
        $relation        = clean($DarkXen_conn, $_POST['father_husband']);
        $fathername      = clean($DarkXen_conn, strtoupper($_POST['fathername']));
        $fathernamelocal = clean($DarkXen_conn, $_POST['fathernamelocal']);
        $dob             = clean($DarkXen_conn, $_POST['dobadhar']);
        $gender          = clean($DarkXen_conn, $_POST['gender']);
        $policestation   = clean($DarkXen_conn, $_POST['policestation']);
        $tahshil         = clean($DarkXen_conn, $_POST['tahshil']);
        $district        = clean($DarkXen_conn, $_POST['district']);
        $pincode         = clean($DarkXen_conn, $_POST['pincode']);
        $statename       = clean($DarkXen_conn, $_POST['statename']);
        $ac              = clean($DarkXen_conn, $_POST['assemblyconnameno']);
        $partinfo        = clean($DarkXen_conn, $_POST['partnoandname']);
        $language        = clean($DarkXen_conn, $_POST['language']);
        $addresslocal    = clean($DarkXen_conn, $_POST['addresslocal']);
        $address         = clean($DarkXen_conn, $_POST['address']);
        $image           = clean($DarkXen_conn, $_POST['image_data']); // Base64 इमेज स्ट्रिंग

        // लोकल वेरिएबल्स (अनुवादित हिडन डेटा)
        $genderlocal     = clean($DarkXen_conn, $_POST['genderlocal']);
        $birthtithi      = clean($DarkXen_conn, $_POST['birthtithilocal']);
        $patalocal       = clean($DarkXen_conn, $_POST['patalocal']);
        $namehi          = clean($DarkXen_conn, $_POST['kanamelocal']);
        $sex             = clean($DarkXen_conn, $_POST['sexlocal']);
        $signlocal       = clean($DarkXen_conn, $_POST['signlocal']);
        $aclocal         = clean($DarkXen_conn, $_POST['assemblyconnamenolocal']);
        $partlocal       = clean($DarkXen_conn, $_POST['partnoandnamelocal']);
        
        // Split part info variables just in case database demands distinct part metrics
        $partno          = clean($DarkXen_conn, $_POST['partno'] ?? '');
        $partname        = clean($DarkXen_conn, $_POST['partname'] ?? '');

        if ($name == "" || $epicno == "" || $address == "") {
            $error_message = "Please fill required fields";
        } else {
            // Get Serial Number
            $r = mysqli_query($DarkXen_conn, "SELECT srno FROM voterauto ORDER BY srno DESC LIMIT 1");
            $d = mysqli_fetch_assoc($r);
            $srno = isset($d['srno']) ? $d['srno'] + 1 : 1;

            $time = date("Y-m-d H:i:s");
            $username = clean($DarkXen_conn, $udata['phone']);

            mysqli_begin_transaction($DarkXen_conn);
            mysqli_set_charset($DarkXen_conn, "utf8");
            
            $insert = mysqli_query($DarkXen_conn, "INSERT INTO voterauto
            (
                votername, namelocal, dob, gender, genderlocal,
                spousename, fathername, fathernamelocal,
                epicno, tahshil, assconnonm, assconnonmlocal,
                partno, partname, partnamelocal,
                locallanguage, fulladdress, localaddress,
                imagepathoriginal, status, srno, createdatetime, username, dobinlocal, sexlocal, kaname, spousenamelocal, pata
            )
            VALUES
            (
                '$name','$namelocal','$dob','$gender','$genderlocal',
                '$relation','$fathername','$fathernamelocal',
                '$epicno','$tahshil','$ac','$aclocal',
                '$partno','$partinfo','$partlocal',
                '$language','$address','$addresslocal',
                '$image','PENDING','$srno','$time','$username',
                '$birthtithi','$sex','$namehi','$fathernamelocal','$addresslocal'
            )");

            if ($insert) {
                $phone = clean($DarkXen_conn, $udata['phone']);
                $wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone='$phone' AND balance >= $fee");

                if ($wallet && mysqli_affected_rows($DarkXen_conn) > 0) {
                    mysqli_commit($DarkXen_conn);
                    $status = "success"; 
                } else {
                    mysqli_rollback($DarkXen_conn);
                    $error_message = "Wallet Debit Failed";
                }
            } else {
                mysqli_rollback($DarkXen_conn);
                $error_message = "Insert Error: " . mysqli_error($DarkXen_conn);
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
                    <img class="ms-n2" src="../assets/img/illustrations/crm-bar-chart.png" alt="" width="90" />
                    <div>
                        <h6 class="text-primary fs-11 mb-0">Welcome back, <?php echo $udata['name']; ?></h6>

                        <h4 class="text-primary fw-bold mb-0">Voter Manual <span class="text-info fw-medium">Application</span></h4>
                    </div>
                </div>
                <div class="col-md-auto">
                    <div class="form-control form-control-sm d-flex align-items-center bg-white border-200 shadow-sm">
                        <span class="fas fa-wallet text-success me-2"></span>
                        <span class="fw-bold text-success">Wallet: ₹<?php echo number_format((float)($udata['balance'] ?? 0), 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 border-top border-4 border-primary">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-1000 fw-bold"><i class="bx bxs-id-card me-2 text-primary"></i>Enter Voter Details</h5>
        <a href="https://www.google.com/intl/sa/inputtools/try/" target="_blank" class="btn btn-falcon-default btn-sm">
            <i class="fas fa-language me-1 text-info"></i> Google Input Tools
        </a>
    </div>
    <div class="card-body p-4 bg-body-tertiary">
        <form method="post" autocomplete="off" onsubmit="return validation();">
            
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label fs-10 text-900 fw-bold">Applicant Photo</label>
                    <div class="image-preview border rounded bg-white d-flex align-items-center justify-content-center" style="height: 180px; overflow: hidden; position: relative;">
                        <img id="blah" src="../assets/img/illustrations/default-avatar.png" style="max-height: 100%; width: auto;">
                    </div>
                    <input type="file" id="file_up" class="form-control mt-2 form-control-sm" accept="image/*" onchange="readURL(this);">
                    <input type="hidden" name="image_data" id="image_data">
                </div>

                <div class="col-md-9">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">EPIC Number (Voter ID)</label>
                            <input class="form-control" name="epicno" id="epicno" type="text" placeholder="ABC1234567" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Voter Name (English)</label>
                            <input class="form-control" name="name" id="name" type="text" placeholder="Full Name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Voter Name (Local)</label>
                            <input class="form-control" name="namelocal" id="name_regional" type="text" placeholder="Auto Translate" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Relation Type</label>
                            <select class="form-select" name="father_husband" id="spouse" required>
                                <option value="Father">Father</option>
                                <option value="Husband">Husband</option>
                                <option value="Mother">Mother</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Relation Name (English)</label>
                            <input class="form-control" name="fathername" id="fathername" type="text" placeholder="Guardian Name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Relation Name (Local)</label>
                            <input class="form-control" name="fathernamelocal" id="fathernamelocal" type="text" placeholder="Auto Translate" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label fs-10 text-900">Age / DOB</label>
                    <input class="form-control" name="dobadhar" id="dob" type="text" placeholder="e.g. 25" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-10 text-900">Gender</label>
                    <select class="form-select" name="gender" id="gender" required>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Third Gender">Third Gender</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-10 text-900">Police Station</label>
                    <input class="form-control" name="policestation" id="policestation" oninput="setaddress()" type="text" placeholder="Thana" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-10 text-900">Tahshil / Block</label>
                    <input class="form-control" name="tahshil" id="tahshil" oninput="setaddress()" type="text" placeholder="Tehsil" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-10 text-900">District</label>
                    <input class="form-control" name="district" id="dists" oninput="setaddress()" type="text" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-10 text-900">Pincode</label>
                    <input class="form-control" name="pincode" id="pincodes" oninput="setaddress()" maxlength="6" type="text" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-10 text-900">State</label>
                    <input class="form-control" name="statename" id="statename" oninput="setaddress()" type="text" value="Bihar" required>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fs-10 text-900">Assembly Const. (No & Name)</label>
                    <input class="form-control" name="assemblyconnameno" id="assconnameno_input" type="text" placeholder="e.g. 154 - Patna Sahib" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fs-10 text-900">Part (No & Name)</label>
                    <input class="form-control" name="partnoandname" id="partnoandname_input" type="text" placeholder="e.g. 45 - Govt School" required>
                </div>
            </div>

            <div class="p-3 bg-200 rounded mb-4 border shadow-sm">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fs-10 text-900 fw-bold">Select Local Language</label>
                        <select class="form-select" onchange="changelang()" name="language" id="lang1" required>
                            <option value="">SELECT</option>
                            <option value="HI">Hindi</option>
                            <option value="PA">Punjabi</option>
                            <option value="GU">Gujarati</option>
                            <option value="MR">Marathi</option>
                            <option value="TA">Tamil</option>
                            <option value="KN">Kannada</option>
                            <option value="BN">Bengali</option>
                            <option value="TE">Telugu</option>
                            <option value="OR">Oriya</option>
                        </select>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label fs-10 text-900">Full Address (Local Language)</label>
                        <textarea class="form-control bg-white" name="addresslocal" id="txtTarget" rows="2" placeholder="Auto-translated address..."></textarea>
                    </div>
                </div>
            </div>

            <input type="hidden" name="address" id="txtSource">
            <input type="hidden" name="genderlocal" id="genderlocal">
            <input type="hidden" name="birthtithilocal" id="birthtithilocal">
            <input type="hidden" name="patalocal" id="patalocal">
            <input type="hidden" name="kanamelocal" id="kanamelocal">
            <input type="hidden" name="sexlocal" id="sexlocal">
            <input type="hidden" name="signlocal" id="signlocal">
            <input type="hidden" name="assemblyconnamenolocal" id="assconnamenolocal">
            <input type="hidden" name="partnoandnamelocal" id="partnoandnamelocal">
            <input type="hidden" name="partno" id="partno_hidden">
            <input type="hidden" name="partname" id="partname_hidden">
            <input type="hidden" name="partnamelocal" id="partnamelocal">

            <input type="hidden" id="pata" value="Address">
            <input type="hidden" id="kaname" value="Voter ID">
            <input type="hidden" id="sex" value="Gender">
            <input type="hidden" id="sign" value="Electoral Registration Officer">
            <input type="hidden" id="birthtithi" value="Age">
            <input type="hidden" id="assconnameno" value="Assembly Constituency Number and Name">
            <input type="hidden" id="partnoandname" value="Part Number and Name">

            <div class="text-end border-top pt-3">
                <h6 class="text-danger d-inline-block me-4">Application Fee: ₹<?= number_format((float)$fee, 2); ?></h6>
                <button type="submit" name="savedata" class="btn btn-falcon-success px-5 fw-semi-bold">
                    <i class="fas fa-check-circle me-2"></i>Submit & Save
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    function readURL(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function (e) {
                $('#blah').attr('src', e.target.result);
                $('#image_data').val(e.target.result);
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function setaddress(){
        var ps = $('#policestation').val();
        var block = $('#tahshil').val();
        var dist = $('#dists').val();
        var pin = $('#pincodes').val();
        var state = $('#statename').val();
        var fullAdd = "P.S.- " + ps + ", Block- " + block + ", Dist- " + dist + ", State- " + state + ", Pin- " + pin;
        $('#txtSource').val(fullAdd);
    }

    function changelang() {
        var lang = $("#lang1").val();
        if(!lang) return;

        function translateField(sourceId, targetId) {
            var text = $("#" + sourceId).val();
            if(!text) return;
            var url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=EN&tl=" + lang + "&dt=t&q=" + encodeURI(text);
            $.get(url, function (data) {
                var res = "";
                for(var i=0; i<data[0].length; i++) { res += data[0][i][0]; }
                $("#" + targetId).val(res);
            });
        }

        translateField("name", "name_regional");
        translateField("fathername", "fathernamelocal");
        translateField("txtSource", "txtTarget");
        translateField("assconnameno_input", "assconnamenolocal");
        translateField("partnoandname_input", "partnoandnamelocal");

        translateField("gender", "genderlocal");
        translateField("birthtithi", "birthtithilocal");
        translateField("pata", "patalocal");
        translateField("kaname", "kanamelocal");
        translateField("sex", "sexlocal");
        translateField("sign", "signlocal");
    }

    function validation() {
        if (!$('#image_data').val()) { 
            Swal.fire('Error', 'Please upload applicant photo!', 'error');
            return false; 
        }
        return true;
    }
</script>

<?php 
// Modern SweetAlert POPUP for backend response handlers
if (!empty($status) && $status === "success") {
    echo "<script>
        setTimeout(function() {
            Swal.fire({
                title: 'Success!',
                text: 'Data Saved Successfully',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'voter_manual_list.php';
                }
            });
        }, 100);
    </script>";
}

if (!empty($error_message)) {
    echo "<script>
        setTimeout(function() {
            Swal.fire({
                title: 'Failed!',
                text: '" . $error_message . "',
                icon: 'error',
                confirmButtonText: 'Try Again'
            });
        }, 100);
    </script>";
}

include('footer.php'); 
?>