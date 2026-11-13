<?php
include('../includes/session.php');
include('../includes/config.php');

$userRole = strtolower($udata['type'] ?? '');$isAdmin = checkAdmin($udata['type'] ?? '');$myPhone = mysqli_real_escape_string($DarkXen_conn,$udata['phone']);

// 🛡️ ROLE-BASED ACCESS CONTROL (Retailer पैसे ट्रांसफर नहीं कर सकता)
if (!$isAdmin && $userRole !== 'distributor' &&$userRole !== 'super distributor') {
    echo "<script>window.location='index.php';</script>";
    die();
}

include('header.php');  

date_default_timezone_set('Asia/Kolkata');

$sender_id =$udata['id'];
$sender_phone =$udata['phone'];
$sender_balance = floatval($udata['balance']);

$error_message = '';$success_message = '';

// अगर users.php से Fund बटन दबाकर आये हैं, तो Receiver का नाम Auto-fill करें
$prefill_receiver = '';
if (isset($_POST['username']) && !isset($_POST['transfer_btn'])) {
    $prefill_receiver = htmlspecialchars($_POST['username']);
}

// --- WALLET TRANSFER LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['transfer_btn'])) {
    
    // सुरक्षित इनपुट लें
    $receiver_identity = mysqli_real_escape_string($DarkXen_conn, trim($_POST['receiver_username'] ?? ''));$amount = floatval($_POST['amount'] ?? 0);$remarks = mysqli_real_escape_string($DarkXen_conn, trim($_POST['remarks'] ?? 'Wallet Transfer'));

    // 1. बेसिक वैलिडेशन चेक
    if (empty($receiver_identity) || $amount <= 0) {$error_message = "Please enter a valid receiver and amount greater than 0.";
    } 
    // खुद को ट्रांसफर करने से रोकना
    elseif ($receiver_identity === $sender_phone && $receiver_identity === $udata['email']) {$error_message = "You cannot transfer funds to your own account!";
    } 
    // पर्याप्त बैलेंस चेक (अगर 50 है और 60 डाल रहा है तो एरर देगा)
    elseif ($sender_balance < $amount) {$error_message = "Insufficient Wallet Balance! You only have ₹" . number_format($sender_balance, 2);
    } else {
        
        // 2. रिसीवर (पैसे पाने वाले) को ढूंढें
        $receiver_sql = "SELECT * FROM users WHERE phone='$receiver_identity' OR email='$receiver_identity' LIMIT 1";
        $receiver_result = mysqli_query($DarkXen_conn,$receiver_sql);

        if ($receiver_result && mysqli_num_rows($receiver_result) == 1) {
            $receiver_data = mysqli_fetch_assoc($receiver_result);
            $receiver_phone =$receiver_data['phone'];
            $receiver_parent =$receiver_data['parent'];
            $receiver_current_bal = floatval($receiver_data['balance']);

            // 🛡️ HIERARCHY SECURITY CHECK: एडमिन के अलावा बाकी सब सिर्फ अपने बनाए यूज़र को ही भेज सकते हैं
            if (!$isAdmin &&$receiver_parent !== $sender_phone) {$error_message = "Access Denied: You can only transfer funds to users created by you (in your network).";
            } else {
                // 3. डेटाबेस ट्रांजैक्शन शुरू करें (ताकि पैसे बीच में न फंसें)
                mysqli_begin_transaction($DarkXen_conn);

                try {
                    // नए बैलेंस की गणना
                    $sender_new_bal = $sender_balance -$amount;
                    $receiver_new_bal = $receiver_current_bal +$amount;

                    // A. भेजने वाले के वॉलेट से पैसे काटें (Minus / -)
                    $deduct_sql = "UPDATE users SET balance = balance - $amount WHERE id = '$sender_id'";
                    if (!mysqli_query($DarkXen_conn,$deduct_sql)) {
                        throw new Exception("Failed to deduct amount from your wallet.");
                    }

                    // B. पाने वाले के वॉलेट में पैसे जोड़ें (Plus / +)
                    $add_sql = "UPDATE users SET balance = balance + $amount WHERE id = '".$receiver_data['id']."'";
                    if (!mysqli_query($DarkXen_conn,$add_sql)) {
                        throw new Exception("Failed to credit amount to receiver wallet.");
                    }

                    // C. भेजने वाले की वॉलेट हिस्ट्री लॉग (Debit)
                    $sender_remark = "Transferred to " . $receiver_phone . " (" . $remarks . ")";
                    $log_sender = "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) 
                                   VALUES ('$sender_phone', '$amount', '$sender_new_bal', '$sender_remark', '1', 'debit')";
                    if (!mysqli_query($DarkXen_conn,$log_sender)) {
                        throw new Exception("Sender history logging failed.");
                    }

                    // D. पाने वाले की वॉलेट हिस्ट्री लॉग (Credit)
                    $receiver_remark = "Received from " . $sender_phone . " (" . $remarks . ")";
                    $log_receiver = "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) 
                                     VALUES ('$receiver_phone', '$amount', '$receiver_new_bal', '$receiver_remark', '1', 'credit')";
                    if (!mysqli_query($DarkXen_conn,$log_receiver)) {
                        throw new Exception("Receiver history logging failed.");
                    }

                    // सब कुछ सही होने पर कमिट करें
                    mysqli_commit($DarkXen_conn);
                    $success_message = "₹" . number_format($amount, 2) . " Transferred Successfully to " . $receiver_phone;

                    // अपडेटेड बैलेंस सेशन में सेट करें
                    $sender_balance =$sender_new_bal;

                } catch (Exception $e) {
                    // कोई भी एरर आने पर ट्रांजैक्शन रोलबैक (कैंसिल) करें
                    mysqli_rollback($DarkXen_conn);$error_message = "Transfer Failed: " . $e->getMessage();
                }
            }

        } else {
            $error_message = "Receiver Account Not Found! Please check Phone/Email.";
        }
    }
}
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="avatar avatar-xl me-3 bg-200 rounded-circle text-center d-flex align-items-center justify-content-center">
                        <span class="fas fa-paper-plane text-primary fs-2"></span>
                    </div>
                    <div>
                        <h4 class="text-primary fw-bold mb-0">Peer-to-Peer Wallet Transfer</h4>
                        <p class="mb-0 fs-11 text-muted">
                            <?= $isAdmin ? "Transfer funds to any user securely." : "Instantly transfer funds to your network users (retailers/distributors)."; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 justify-content-center mb-4">
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm border border-top border-4 border-primary rounded-3">
            <div class="card-header bg-light border-bottom py-3">
                <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-share-square me-2"></i>Send Money Instantly</h5>
            </div>
            <div class="card-body bg-white p-4">
                
                <div class="alert alert-primary border-0 d-flex align-items-center mb-4 rounded-3">
                    <i class="fas fa-coins fs-3 me-3 text-primary"></i>
                    <p class="mb-0 fs-11">Your Current Balance: <strong class="fs-10">₹<?= number_format($sender_balance, 2); ?></strong></p>
                </div>

                <form method="POST" autocomplete="off" id="transferForm">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-900">Receiver Phone Number or Email <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-200 text-700 border-200"><i class="fas fa-user-plus"></i></span>
                            <input type="text" class="form-control shadow-none border-200 fw-semi-bold" name="receiver_username" placeholder="Enter recipient phone or email" value="<?= $prefill_receiver; ?>" required>
                        </div>
                        <?php if(!$isAdmin): ?>
                        <div class="form-text text-muted fs-12 mt-1"><i class="fas fa-info-circle"></i> You can only send funds to users created by you.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-900">Amount to Transfer (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-200 text-success fw-bold border-200"><i class="fas fa-rupee-sign"></i></span>
                            <input type="number" step="0.01" class="form-control shadow-none border-200 fw-bold text-success fs-10" name="amount" placeholder="0.00" min="1" max="<?= floor($sender_balance); ?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Remarks / Description</label>
                        <input type="text" class="form-control shadow-none border-200" name="remarks" placeholder="e.g. Wallet Topup, Commission, Support">
                    </div>

                    <button class="btn btn-primary w-100 py-3 fw-bold shadow-sm rounded-pill" type="submit" name="transfer_btn" style="font-size: 1.1rem;">
                        <span class="fas fa-paper-plane me-2"></span>Transfer Funds Now
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.getElementById('transferForm').addEventListener('submit', function() {
        Swal.fire({
            title: 'Processing Transaction...',
            html: 'Securing ledger logs and moving balances, please hold...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
    });

    <?php if(!empty($error_message)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Transfer Failed',
            text: '<?= addslashes($error_message); ?>',
            confirmButtonText: 'Try Again',
            confirmButtonColor: '#d33'
        });
    <?php endif; ?>

    <?php if(!empty($success_message)): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?= addslashes($success_message); ?>',
            showConfirmButton: true,
            confirmButtonColor: '#198754'
        }).then(() => { 
            window.location.href = window.location.pathname; 
        });
    <?php endif; ?>
</script>

<style>
    .form-control, .input-group-text { padding: 0.7rem 1rem; border-radius: 0.5rem; }
    .input-group > .input-group-text { border-top-right-radius: 0; border-bottom-right-radius: 0; }
    .input-group > .form-control { border-top-left-radius: 0; border-bottom-left-radius: 0; }
    .card { border-radius: 1rem; overflow: hidden; }
    .form-control:focus { border-color: #0d6efd; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.1); }
</style>

<?php include('footer.php'); ?>