<?php
include('../includes/session.php');
include('../includes/config.php');

// एडमिन चेक
if(checkAdmin($udata['type']) == false){
    ?>
    <script>
        window.location='index.php';
    </script>
    <?php
    die();
}

include('header.php');

$data = [];
$id = "";

// गेट रिक्वेस्ट से यूजर डेटा निकालना
if(isset($_GET['id']) && $_GET['id'] != NULL){
    // यदि getSafe कस्टम फ़ंक्शन है, तो उसके अनुसार डिकोड करें
    $id = base64_decode(getSafe($_GET['id']));
    $data = fetchRow("SELECT * FROM users WHERE id='$id'");
}

// पोस्ट रिक्वेस्ट से डेटा अपडेट करना
if(isset($_POST['name']) && isset($_POST['phone'])){
    $id = getSafe($_POST['id']);
    $name = getSafe($_POST['name']);
    $phone = getSafe($_POST['phone']);
    $email = getSafe($_POST['email']);
    
    if(Query("UPDATE users SET name='$name', phone='$phone', email='$email' WHERE id='$id'")){
        showAlert('User Data Saved!');
        Redirect('', 1200);
    } else {
        showAlert('User Not Updated!', '', 'error');
    }
}
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-danger-subtle shadow-none me-3">
                        <span class="fas fa-list text-danger fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-danger fs-10 mb-0">Edit Profile</h6>
                        <h4 class="text-primary fw-bold mb-0">Edit User Profile</h4>
                    </div>
                </div>
               
            </div>
        </div>
    </div>
</div>

<!-- मुख्य कंटेंट कंटेनर -->
<div class="container-fluid py-4">
    <div class="card shadow-none border">
        <div class="card-header bg-light">
            <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-user-edit me-2"></i>Edit User Profile</h5>
        </div>
        <div class="card-body">
            <!-- यूजर एडिटिंग फॉर्म -->
            <form action="" method="POST" class="row g-3">
                <!-- हिडन आईडी इनपुट -->
                <input name="id" type="hidden" value="<?php echo isset($data['id']) ? htmlspecialchars($data['id']) : htmlspecialchars($id); ?>">
                
                <!-- नाम इनपुट -->
                <div class="col-md-4">
                    <label for="inputName" class="form-label fw-medium text-secondary">Full Name</label>
                    <input name="name" type="text" value="<?php echo isset($data['name']) ? htmlspecialchars($data['name']) : ''; ?>" placeholder="Enter Full Name" class="form-control shadow-none" id="inputName" required>
                </div>
                
                <!-- फोन इनपुट (यूजरनेम) -->
                <div class="col-md-4">
                    <label for="inputPhone" class="form-label fw-medium text-secondary">Phone (Username)</label>
                    <input name="phone" type="text" value="<?php echo isset($data['phone']) ? htmlspecialchars($data['phone']) : ''; ?>" placeholder="Enter Phone Number" class="form-control shadow-none" id="inputPhone" required>
                </div>
                
                <!-- ईमेल इनपुट -->
                <div class="col-md-4">
                    <label for="inputEmail" class="form-label fw-medium text-secondary">Email Address</label>
                    <input name="email" type="email" value="<?php echo isset($data['email']) ? htmlspecialchars($data['email']) : ''; ?>" placeholder="Enter Email" class="form-control shadow-none" id="inputEmail">
                </div>
            
                <!-- सबमिट बटन पैनल -->
                <div class="col-12 mt-4 pt-2 border-top text-right">
                    <button type="submit" class="btn btn-primary px-5 shadow-none fw-bold">
                        <i class="fas fa-save me-1"></i> Save User Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
include('footer.php');
?>