<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

// Fetch Fee
$fee = 0; // Default fee
$fee_query = mysqli_query($DarkXen_conn, "SELECT `electricBill_PDF` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if ($fee_row['electricBill_PDF'] !== '' && $fee_row['electricBill_PDF'] !== null) {
        $fee = $fee_row['electricBill_PDF'];
    }
}

$resdata = [];
$error_message = "";
$success_message = "";

if (isset($_POST['caNumber'])) {
    $caNumber = mysqli_real_escape_string($DarkXen_conn, trim($_POST['caNumber']));
    $appliedby = $udata['phone'];
    $debit_fee = $udata['balance'] - $fee;

    if ($udata['balance'] < $fee) {
        echo "<script>$(function(){ Swal.fire('Wallet Balance is Low!', 'Please Recharge Now!', 'error') }); setTimeout(() => { window.location='wallet.php'; }, 1200);</script>";
    } else {
        $api_key = urlencode($DarkXen_apikey);
        $url = "https://" . $DarkXen_url . "/api/NB_bijli_BILL_pdf.php?token=$api_key&consumer_no=$caNumber";
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ));

        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $error_message = curl_error($curl);
        }
        curl_close($curl);

        $resdata = json_decode($response, true) ?? [];

        $pdf_base64 = $resdata['pdf_base64'] ?? '';
        $provider = mysqli_real_escape_string($DarkXen_conn, $resdata['provider'] ?? 'N/A');
        $status = $resdata['status'] ?? false;
        $errore = $resdata['error'] ?? '';

        if (!empty($errore)) {
            echo "<script>$(function(){ Swal.fire('" . addslashes($errore) . "', 'Something went wrong.', 'error') }); setTimeout(() => { window.location=''; }, 5000);</script>";
        } elseif ($status === true && !empty($pdf_base64)) {
            
            // 1. Save PDF details directly to SQL Table
            $insert_bill = mysqli_query($DarkXen_conn, "INSERT INTO `electric_bill_history` (`userid`, `ca_number`, `provider`, `pdf_base64`, `fee_debited`) VALUES ('$appliedby', '$caNumber', '$provider', '$pdf_base64', '$fee')");
            
            if ($insert_bill) {
                // 2. Debit user balance
                mysqli_query($DarkXen_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
                
                // 3. Add to Wallet History log
                mysqli_query($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('$appliedby', '$fee', '$debit_fee', 'Electric Bill PDF', '1', 'Debit')");
                
                $success_message = "Bill PDF fetched and saved in database successfully!";
            } else {
                $error_message = "Database Error: Could not save receipt.";
            }
        } else {
            $error_message = "Unable to fetch PDF. Please check your Consumer Number.";
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
                        <span class="fas fa-bolt text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Utility Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Electricity Bill <span class="text-success fw-medium">PDF Generator</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="Bill_To_find_List.php" class="btn btn-sm btn-outline-success shadow-none fw-bold"><i class="fas fa-history me-1"></i> View Bill History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Alert Boxes -->
<?php if (!empty($success_message)) { ?>
    <div class="alert alert-success border-0 d-flex align-items-center mb-3" role="alert">
        <span class="fas fa-check-circle fs-3 me-2"></span>
        <p class="mb-0"><?php echo htmlspecialchars($success_message); ?></p>
    </div>
<?php } ?>

<?php if (!empty($error_message)) { ?>
    <div class="alert alert-danger border-0 d-flex align-items-center mb-3" role="alert">
        <span class="fas fa-exclamation-circle fs-3 me-2"></span>
        <p class="mb-0"><?php echo htmlspecialchars($error_message); ?></p>
    </div>
<?php } ?>

<!-- Content Grid -->
<div class="row justify-content-center g-4 mb-4">
    <!-- Left Column: Search Form -->
    <div class="col-lg-5">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-primary">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-search me-2"></i>Fetch Bill Document</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-primary border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-info-circle fs-3 me-3 text-primary"></i>
                    <p class="mb-0 fs-11">Service Charge: <strong>₹<?php echo number_format($fee, 2); ?></strong> will be deducted upon successful PDF generation.</p>
                </div>
                               
                <form action="" method="POST" class="row g-3">
                    <div class="col-12 mb-3">
                        <label for="caNumber" class="form-label font-weight-500 text-dark">Consumer ID / CA Number</label>
                        <input name="caNumber" type="text" id="caNumber" value="<?php echo isset($_POST['caNumber']) ? htmlspecialchars($_POST['caNumber']) : ''; ?>" placeholder="Enter CA Number" class="form-control" required>
                    </div>
                    
                    <div class="col-12">
                        <hr class="mt-0 mb-4">
                        <div class="row align-items-center">
                            <div class="col-6">
                                <input class="form-control font-weight-600 text-primary bg-light border-0" value="Fee: ₹ <?php echo htmlspecialchars($fee); ?>" readonly style="box-shadow: none;">
                            </div>
                            <div class="col-6 text-end">
                                <button type="submit" class="btn btn-primary w-100 fw-bold" name="submit" id="submit">
                                    <i class="fas fa-cloud-download-alt me-1"></i> Get Bill PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Instant Action / Download Preview -->
    <div class="col-lg-7">
        <?php if (isset($resdata['status']) && $resdata['status'] === true && !empty($pdf_base64)) { ?>
            <div class="card shadow-sm border-0 rounded-3 h-100 text-center p-5 d-flex flex-column align-items-center justify-content-center">
                <div class="mb-4">
                    <span class="far fa-file-pdf text-danger" style="font-size: 5rem;"></span>
                </div>
                <h4 class="text-success fw-bold mb-2">Bill Generated Successfully!</h4>
                <p class="text-muted mb-4">Consumer Number: <strong><?= htmlspecialchars($caNumber) ?></strong><br>Provider: <?= htmlspecialchars($provider) ?></p>
                
                <div class="d-flex gap-3">
                    <!-- Base64 Direct Download Trigger -->
                    <a href="data:application/pdf;base64,<?= $pdf_base64 ?>" download="Electric_Bill_<?= htmlspecialchars($caNumber) ?>.pdf" class="btn btn-lg btn-danger shadow-sm">
                        <i class="fas fa-file-download me-2"></i>Download PDF
                    </a>
                    <!-- Direct Print Base64 via Iframe -->
                    <button onclick="printPdf('<?= $pdf_base64 ?>')" class="btn btn-lg btn-outline-secondary">
                        <i class="fas fa-print me-2"></i>Print Bill
                    </button>
                </div>
            </div>
        <?php } else { ?>
            <div class="card shadow-sm border-0 rounded-3 text-center p-5 h-100" style="display: flex; align-items: center; justify-content: center;">
                <div class="text-muted">
                    <i class="fas fa-bolt display-4 mb-3 text-secondary"></i>
                    <p class="mb-0 fs-5">Submit CA details to generate the official Electricity Bill PDF.</p>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<script>
// Print helper for Base64 PDF
function printPdf(base64Str) {
    const blob = base64ToBlob(base64Str, 'application/pdf');
    const blobUrl = URL.createObjectURL(blob);
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = blobUrl;
    document.body.appendChild(iframe);
    iframe.contentWindow.focus();
    iframe.contentWindow.print();
}

function base64ToBlob(base64, type) {
    const binStr = atob(base64);
    const len = binStr.length;
    const arr = new Uint8Array(len);
    for (let i = 0; i < len; i++) {
        arr[i] = binStr.charCodeAt(i);
    }
    return new Blob([arr], { type: type });
}
</script>

<?php include('footer.php'); ?>