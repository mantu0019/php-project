<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set('Asia/Kolkata');

// Default Fee
$fee = 20;

// Fetch Fee
$fee_query = mysqli_query($DarkXen_conn, "SELECT `voter_advance` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if (!empty($fee_row['voter_advance'])) {
        $fee = (float)$fee_row['voter_advance'];
    }
}

$error_message = "";
$success_message = "";

function clean($conn,$v){
    return mysqli_real_escape_string($conn, trim($v ?? ''));
}

function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* DEFAULT VALUES */
$epic_no = $name =$namelocal = $gender =$age = "";
$fname = $fnamelocal =$relation = "";
$ac_no = $ac_name =$ac_name_local = "";
$part_no = $part_name =$part_name_local = "";
$district = $tehsil =$state = "";
$image_data = "";

/* ================= SAVE DATA ================= */
if(isset($_POST['savedata'])){

    if($udata['balance'] < $fee){$error_message = "Wallet Balance Low Please Recharge";
    } else {
        $name            = clean($DarkXen_conn, strtoupper($_POST['name']));
        $namelocal       = clean($DarkXen_conn, $_POST['namelocal']);$dob             = clean($DarkXen_conn,$_POST['dobadhar']);
        $gender          = clean($DarkXen_conn, $_POST['gender']);$genderlocal     = clean($DarkXen_conn,$_POST['genderlocal']);
        $relation        = clean($DarkXen_conn, $_POST['relation']);$fathername      = clean($DarkXen_conn, strtoupper($_POST['fathername']));
        $fathernamelocal = clean($DarkXen_conn, $_POST['fathernamelocal']);$epicno          = clean($DarkXen_conn,$_POST['epicno']);
        $tahshil         = clean($DarkXen_conn, $_POST['tahshil']);$district        = clean($DarkXen_conn,$_POST['district']);
        $ac              = clean($DarkXen_conn, $_POST['assemblyconnameno']);$aclocal         = clean($DarkXen_conn,$_POST['assemblyconnamenolocal']);
        $partno          = clean($DarkXen_conn, $_POST['partno']);$partname        = clean($DarkXen_conn,$_POST['partname']);
        $partlocal       = clean($DarkXen_conn, $_POST['partnamelocal']);$language        = clean($DarkXen_conn,$_POST['language']);
        $address         = clean($DarkXen_conn, $_POST['address']);$addresslocal    = clean($DarkXen_conn,$_POST['addresslocal']);
        $image           = clean($DarkXen_conn, $_POST['image_data']);$birthtithi      = clean($DarkXen_conn, strtoupper($_POST['birthtithi']));
        $sex             = clean($DarkXen_conn, strtoupper($_POST['sex']));$namehi          = clean($DarkXen_conn, strtoupper($_POST['namehi']));
        $fnamehi         = clean($DarkXen_conn, strtoupper($_POST['fnamehi']));$addrehi         = clean($DarkXen_conn, strtoupper($_POST['addrehi']));

        if($name == "" || $epicno == "" && $address == ""){
            $error_message = "Please fill required fields";
        } else {
            $r = mysqli_query($DarkXen_conn,"SELECT srno FROM voterauto ORDER BY srno DESC LIMIT 1");
            $d = mysqli_fetch_assoc($r);$srno = isset($d['srno']) ?$d['srno'] + 1 : 1;

            $time = date("Y-m-d H:i:s");
            $username = clean($DarkXen_conn,$udata['phone']);

            mysqli_begin_transaction($DarkXen_conn);
            mysqli_set_charset($DarkXen_conn,"utf8");
            
            $insert = mysqli_query($DarkXen_conn,"INSERT INTO voterauto
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
                '$partno','$partname','$partlocal',
                '$language','$address','$addresslocal',
                '$image','PENDING','$srno','$time','$username',
                '$birthtithi','$sex','$namehi','$fnamehi','$addrehi'
            )");

            if($insert){$phone = clean($DarkXen_conn,$udata['phone']);
                $wallet = mysqli_query($DarkXen_conn,"UPDATE users SET balance = balance - $fee WHERE phone='$phone' AND balance >=$fee");

                if($wallet && mysqli_affected_rows($DarkXen_conn) > 0){
                    mysqli_commit($DarkXen_conn);$success_message = "Data Saved Successfully";
                } else {
                    mysqli_rollback($DarkXen_conn);$error_message = "Wallet Debit Failed. Insufficient balance.";
                }
            } else {
                mysqli_rollback($DarkXen_conn);$error_message = "Insert Error: ".mysqli_error($DarkXen_conn);
            }
        }
    }
}

/* ================= EPIC SEARCH (NEW API) ================= */
if(isset($_POST['submit'])){
    $epic = strtoupper(trim($_POST['epicno'] ?? ''));
    $image_data =$_POST['image_data'] ?? '';

    if($epic == ""){
        $error_message = "Enter Valid EPIC Number";
    } else {
        
        $DarkXen_apikey_local = $webdata['api_key'] ?? $DarkXen_apikey;
        $api_domain =$DarkXen_url;

        // NEW API Configuration
        $url = "https://" . $api_domain . "/api/v3/VOTER/voter_verification.php?apiKey=" . urlencode($DarkXen_apikey_local) . "&epicNumber=" . urlencode($epic);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if($err){$error_message = "API Error: ".$err;
        } else {
            $json = json_decode($response, true);

            // New API Success check ("success": true and "status": "100")
            if(empty($json) || !isset($json['success']) && $json['success'] !== true){
                $error_message =$json['message'] ?? "Voter Record Not Found";
            } else {
                // New API data object is named 'result'
                $a =$json['result'] ?? [];

                if(empty($a)){$error_message = "Voter Record Details Not Found";
                } else {
                    $epic_no = $a['epicNumber'] ?? $epic;
                    $name =$a['fullName'] ?? '';
                    $namelocal =$a['fullNameL1'] ?? '';
                    
                    $sex =$a['gender'] ?? '';
                    $gender = ($sex == "M") ? "Male" : "Female";

                    $age =$a['age'] ?? '';
                    $fname =$a['relativeFullName'] ?? '';
                    $fnamelocal =$a['relativeFullNameL1'] ?? '';

                    $relation_type = $a['relationType'] ?? '';$relation = ($relation_type == "FTHR" &&  $relation_type == "F") ? "Father" : "Husband";

                    $ac_no =$a['acNumber'] ?? '';
                    $ac_name =$a['asmblyName'] ?? '';
                    $ac_name_local =$a['asmblyNameL1'] ?? '';

                    $part_no =$a['partNumber'] ?? '';
                    $part_name =$a['partName'] ?? '';
                    $part_name_local =$a['partNameL1'] ?? '';

                    $district =$a['districtValue'] ?? '';
                    $tehsil =$a['asmblyName'] ?? '';
                    $state =$a['stateName'] ?? '';
                }
            }
        }
    }
}
?>

<?php if(!empty($error_message)): ?>
    <div class="alert alert-danger"><?php echo h($error_message); ?></div>
<?php endif; ?>
<?php if(!empty($success_message)): ?>
    <div class="alert alert-success"><?php echo h($success_message); ?></div>
<?php endif; ?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <img class="ms-n2" src="../assets/img/illustrations/crm-bar-chart.png" alt="" width="90" />
                    <div>
                         <h6 class="text-primary fs-11 mb-0">Welcome back, <?php echo htmlspecialchars($udata['name']); ?></h6>
                        <h4 class="text-primary fw-bold mb-0">Voter Advance <span class="text-info fw-medium">Application</span></h4>
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
        <div class="badge badge-subtle-danger fs-10 p-2 fw-bold"><i class="fas fa-rupee-sign me-1"></i> Fee: ₹<?php echo h($fee); ?></div>
    </div>
    
    <div class="card-body p-4 bg-body-tertiary">
        <div class="voter-header mb-3">
            <h3>Voter Advance</h3>
            <p class="mb-0 text-muted">One Page Verify + Save</p>
        </div>

        <form method="post" autocomplete="off">
            <div class="row mb-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label fw-bold">EPIC Number</label>
                    <input type="text" name="epicno" class="form-control text-uppercase" value="<?= h($epic_no) ?>" required autofocus>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">Photo</label>
                    <div class="photo-box border rounded bg-white text-center p-2" style="cursor:pointer; height:45px; display:flex; align-items:center; justify-content:center;" onclick="openPhotoPopup()">
                        <img id="photoPreview" src="<?= h($image_data) ?>" style="max-height:100%; display:none;">
                        <span id="photoText">Click Photo</span>
                    </div>
                    <input type="hidden" name="image_data" id="image_data" value="<?= h($image_data) ?>">
                </div>

                <div class="col-md-3">
                    <button type="submit" name="submit" class="btn btn-primary w-100 fw-bold">Submit Verify</button>
                </div>
            </div>
        </form>

        <?php if($epic_no != ""){ ?>
        <hr>

        <form method="post" autocomplete="off">
            <input type="hidden" name="image_data" id="image_data2" value="<?= h($image_data) ?>">

            <div class="row">
                <div class="col-md-4 mt-3">
                    <label class="form-label">EPIC No</label>
                    <input class="form-control" name="epicno" value="<?= h($epic_no) ?>" required>
                </div>

                <div class="col-md-4 mt-3">
                    <label class="form-label">Name</label>
                    <input class="form-control" name="name" id="name" value="<?= h($name) ?>" required>
                </div>

                <div class="col-md-4 mt-3">
                    <label class="form-label">Name Local</label>
                    <input class="form-control" name="namelocal" id="namelocal" value="<?= h($namelocal) ?>">
                </div>

                <div class="col-md-4 mt-3">
                    <label class="form-label">Age</label>
                    <input class="form-control" name="dobadhar" value="<?= h($age) ?>" required>

                    <input id="birthtithi" name="birthtithi" type="hidden" value="Birth Tithi / Age">
                    <input id="sex" name="sex" type="hidden" value="Gender">
                    <input id="namehi" name="namehi" type="hidden" value="Name">
                    <input id="fnamehi" name="fnamehi" type="hidden" value="<?= h($relation) ?>">
                    <input id="addrehi" name="addrehi" type="hidden" value="Address">
                </div>

                <div class="col-md-4 mt-3">
                    <label class="form-label">Gender</label>
                    <input class="form-control" name="gender" id="gender" value="<?= h($gender) ?>" required>
                </div>

                <div class="col-md-4 mt-3">
                    <label class="form-label">Father/Husband</label>
                    <input class="form-control" name="relation" value="<?= h($relation) ?>" required>
                </div>

                <div class="col-md-6 mt-3">
                    <label class="form-label">Father/Husband Name</label>
                    <input class="form-control" name="fathername" id="fathername" value="<?= h($fname) ?>" required>
                </div>

                <div class="col-md-6 mt-3">
                    <label class="form-label">Father/Husband Local</label>
                    <input class="form-control" name="fathernamelocal" id="fathernamelocal" value="<?= h($fnamelocal) ?>">
                </div>

                <div class="col-md-4 mt-3">
                    <label class="form-label">Tahshil</label>
                    <input class="form-control" name="tahshil" id="tehsil" value="<?= h($tehsil) ?>" required>
                </div>

                <div class="col-md-4 mt-3">
                    <label class="form-label">District</label>
                    <input class="form-control" name="district" id="district" value="<?= h($district) ?>">
                </div>

                <div class="col-md-4 mt-3">
                    <label class="form-label">State</label>
                    <input class="form-control" id="state" value="<?= h($state) ?>" readonly>
                </div>

                <div class="col-md-6 mt-3">
                    <label class="form-label">Assembly Constituency</label>
                    <input class="form-control" name="assemblyconnameno" value="<?= h($ac_no.' - '.$ac_name) ?>" required>
                </div>

                <div class="col-md-6 mt-3">
                    <label class="form-label">Assembly Local</label>
                    <input class="form-control" name="assemblyconnamenolocal" value="<?= h($ac_no.' - '.$ac_name_local) ?>">
                </div>

                <div class="col-md-4 mt-3">
                    <label class="form-label">Part No</label>
                    <input class="form-control" name="partno" value="<?= h($part_no) ?>" required>
                </div>

                <div class="col-md-4 mt-3">
                    <label class="form-label">Part Name</label>
                    <input class="form-control" name="partname" id="partname" value="<?= h($part_name) ?>" required>
                </div>

                <div class="col-md-4 mt-3">
                    <label class="form-label">Part Local</label>
                    <input class="form-control" name="partnamelocal" id="partnamelocal" value="<?= h($part_name_local) ?>">
                </div>

                <div class="col-md-4 mt-3">
                    <label class="form-label">Language</label>
                    <select class="form-control" name="language" id="lang1" onchange="changelang()" required>
                        <option value="">SELECT</option>
                        <option value="HI">Hindi</option>
                        <option value="PA">Punjabi</option>
                        <option value="GU">Gujrati</option>
                        <option value="MR">Marathi</option>
                        <option value="TA">Tamil</option>
                        <option value="KN">Kannada</option>
                        <option value="BN">Bengali</option>
                        <option value="TE">Telugu</option>
                        <option value="OR">Oriya</option>
                    </select>
                </div>

                <div class="col-md-8 mt-3">
                    <label class="form-label">House No</label>
                    <input class="form-control" id="houseNo" oninput="setHouseNo()">
                </div>

                <div class="col-md-6 mt-3">
                    <label class="form-label">Address</label>
                    <textarea class="form-control" style="height:75px" name="address" id="txtSource" required></textarea>
                </div>

                <div class="col-md-6 mt-3">
                    <label class="form-label">Address Local</label>
                    <textarea class="form-control" style="height:75px" name="addresslocal" id="txtTarget"></textarea>
                </div>

                <input type="hidden" name="genderlocal" id="genderlocal">

                <div class="col-md-6 mt-4">
                    <input class="form-control text-center text-success fw-bold" value="Fee ₹ <?= number_format($fee, 2) ?>" readonly>
                </div>

                <div class="col-md-6 mt-4">
                    <button type="submit" name="savedata" class="btn btn-success w-100 fw-bold">Final Submit</button>
                </div>
            </div>
        </form>
        <?php } ?>
    </div>
</div>

<input type="file" id="photoInput" accept="image/*" style="display:none">

<script>
function openPhotoPopup(){
    document.getElementById("photoInput").click();
}

document.getElementById("photoInput").addEventListener("change", function(){
    let file = this.files[0];
    if(!file) return;

    let reader = new FileReader();
    reader.onload = function(e){
        let preview = document.getElementById("photoPreview");
        let text = document.getElementById("photoText");
        if(preview) {
            preview.style.display = "block";
            preview.src = e.target.result;
        }
        if(text) text.style.display = "none";
        
        let img1 = document.getElementById("image_data");
        if(img1) img1.value = e.target.result;

        let second = document.getElementById("image_data2");
        if(second){
            second.value = e.target.result;
        }
    };
    reader.readAsDataURL(file);
});

function setHouseNo(){
    let house = document.getElementById("houseNo").value || "";
    let part = document.getElementById("partname").value || "";
    let tehsil = document.getElementById("tehsil").value || "";
    let district = document.getElementById("district").value || "";
    let state = document.getElementById("state").value || "";

    document.getElementById("txtSource").value =
        house + ", " + part + ", " + tehsil + ", " + district + ", " + state;
}

function changelang(){
    let lang = document.getElementById("lang1").value;
    if(lang == "") return;

    translateText("txtSource","txtTarget",lang);
    translateText("name","namelocal",lang);
    translateText("fathername","fathernamelocal",lang);
    translateText("partname","partnamelocal",lang);
    translateText("gender","genderlocal",lang);
    
    translateText("birthtithi","birthtithi",lang);
    translateText("sex","sex",lang);
    translateText("namehi","namehi",lang);
    translateText("fnamehi","fnamehi",lang); 
    translateText("addrehi","addrehi",lang); 
}

function translateText(from,to,lang){
    let element = document.getElementById(from);
    if(!element) return;
    let text = element.value;
    if(text.trim() == "") return;

    fetch("https://translate.googleapis.com/translate_a/single?client=gtx&sl=EN&tl="+lang+"&dt=t&q="+encodeURIComponent(text))
    .then(r=>r.json())
    .then(data=>{
        let out = "";
        data[0].forEach(x=>out += x[0]);
        let targetEl = document.getElementById(to);
        if(targetEl) targetEl.value = out;
    });
}
</script>

<?php include('footer.php'); ?>