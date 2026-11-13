<?php


error_reporting(0);
session_start(); // ✅ Add this to keep session alive


require('lib/Config_RechPay.php'); 
require('lib/RechPayChecksum.php');


// ✅ SAFE INPUT
$gateway_type = $_POST['gateway_type'] ?? '';
$cust_Mobile  = $_POST['cust_Mobile'] ?? '';
$cust_Email   = $_POST['cust_Email'] ?? '';
$txnAmount    = $_POST['txnAmount'] ?? '';
$txnNote      = $_POST['txnNote'] ?? '';
$orderId      = $_POST['ORDER_ID'] ?? '';

// ✅ BLOCK DIRECT ACCESS
if(empty($txnAmount)){
    die("Invalid Access");
}


$callback_url = "{$websiteUrl}/payment/txnResult.php";






if($gateway_type == "Robotics"){
    $RECHPAY_TXN_URL = $url;

   
    $paramList["cust_Mobile"] = $cust_Mobile;
    $paramList["cust_Email"]  = $cust_Email;
}

else {
    die("Invalid Gateway Type");
}

// Prepare Params
$paramList["upiuid"]       = $upiuid;
$paramList["token"]        = $token;
$paramList["orderId"]      = $orderId;
$paramList["txnAmount"]    = $txnAmount;
$paramList["txnNote"]      = $txnNote;
$paramList["callback_url"] = $callback_url;

// Generate Checksum
$checkSum = RechPayChecksum::generateSignature($paramList, $secret);


?>

<html>
<head>
<title>Gateway Check Out Page</title>
</head>
<body>
	<center><h1>Please do not refresh this page...</h1></center>
		<form method="post" action="<?php echo $RECHPAY_TXN_URL ?>" name="f1">
		<table border="1">
			<tbody>
			<?php
			foreach($paramList as $name => $value) {
				echo '<input type="hidden" name="' . $name .'" value="' . $value . '">';
			}
			?>
			<input type="hidden" name="checksum" value="<?php echo $checkSum ?>">
			</tbody>
		</table>
		<script type="text/javascript">
			document.f1.submit();
		</script>
	</form>
</body>
</html>
