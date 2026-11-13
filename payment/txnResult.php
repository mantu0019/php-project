<?php
error_reporting(E_ALL);
session_start();

require_once('lib/Config_RechPay.php');
require_once('lib/RechPayChecksum.php');

$secret = $secret;

// VALIDATION
if (!isset($_POST['status']) || !isset($_POST['hash'])) {
    exit("Invalid Access");
}

if ($_POST['status'] != 'SUCCESS') {
    header("Location: ".$websiteUrl."/dashboard/addwallet.php?txn_status=FAILED");
    exit;
}

// DECRYPT
$hash    = $_POST['hash'];
$encoded = hash_decrypt($hash, $secret);
$pdata   = json_decode($encoded, true);

if (!$pdata) {
    exit("Invalid Data");
}

// STATUS CHECK
if ($pdata['txnStatus'] != "TXN_SUCCESS" || empty($pdata['payee_vpa'])) {
    header("Location: ".$websiteUrl."/dashboard/addwallet.php?txn_status=TXN_FAILED");
    exit;
}

// DATA
$txnAmount = (float)$pdata['txnAmount'];
$utr       = $pdata['utr'] ?? '';
$order_id  = $pdata['orderId'] ?? '';
$userid    = $pdata['sender_note'] ?? '';

if (empty($order_id) || empty($utr)) {
    exit("Missing txn data");
}

// DB CONNECTION
mysqli_begin_transaction($DarkXen_conn);

// FETCH TXN
$result = mysqli_query(
    $DarkXen_conn,
    "SELECT * FROM wallet WHERE txn_id='".mysqli_real_escape_string($DarkXen_conn,$order_id)."' LIMIT 1"
);

$txn_data = mysqli_fetch_assoc($result);

if (!$txn_data) {
    mysqli_rollback($DarkXen_conn);
    exit("Transaction not found");
}

// ALREADY SUCCESS
if ($txn_data['status'] == "success") {
    mysqli_rollback($DarkXen_conn);

    header("Location: ".$websiteUrl."/dashboard/addwallet.php?txn_status=ALREADY_DONE");
    exit;
}

// DUPLICATE UTR CHECK
$utr_safe = mysqli_real_escape_string($DarkXen_conn,$utr);

$utr_check = mysqli_num_rows(
    mysqli_query(
        $DarkXen_conn,
        "SELECT id FROM wallet WHERE BANKTXNID='$utr_safe'"
    )
);

if ($utr_check > 0) {
    mysqli_rollback($DarkXen_conn);
    exit("Duplicate UTR detected");
}

// AMOUNT CHECK
if ((float)$txn_data['amount'] != $txnAmount) {
    mysqli_rollback($DarkXen_conn);
    exit("Amount mismatch");
}

// UPDATE WALLET
$update_wallet = mysqli_query(
    $DarkXen_conn,
    "UPDATE wallet SET
        status='success',
        BANKTXNID='$utr_safe',
        PAYMENTMODE='UPI',
        RESPMSG='SUCCESS'
     WHERE txn_id='".mysqli_real_escape_string($DarkXen_conn,$order_id)."'"
);

// UPDATE USER BALANCE
$update_user = mysqli_query(
    $DarkXen_conn,
    "UPDATE users
     SET balance = balance + $txnAmount
     WHERE phone='".mysqli_real_escape_string($DarkXen_conn,$userid)."'"
);

if ($update_wallet && $update_user) {

    mysqli_commit($DarkXen_conn);

    // Login session restore (optional)
    $_SESSION['user'] = $userid;
    $_SESSION['phone'] = $userid;

    header("Location: ".$websiteUrl."/dashboard/addwallet.php?txn_status=SUCCESS&txn_id=".$order_id);
    exit;

} else {

    mysqli_rollback($DarkXen_conn);

    header("Location: ".$websiteUrl."/dashboard/addwallet.php?txn_status=DB_FAILED");
    exit;
}
?>