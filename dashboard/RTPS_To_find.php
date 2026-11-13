<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

// Fetch Fee
$fee = 10; // Default fee
$fee_query = mysqli_query($DarkXen_conn, "SELECT `aadhaar_2_pan_mask` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if ($fee_row['aadhaar_2_pan_mask'] !== '' && $fee_row['aadhaar_2_pan_mask'] !== null) {
        $fee = $fee_row['aadhaar_2_pan_mask'];
    }
}

$resdata = [];
$error_message = "";
$success_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicationNumber = mysqli_real_escape_string($DarkXen_conn, trim($_POST['applicationNumber'] ?? ''));
    $name = mysqli_real_escape_string($DarkXen_conn, trim($_POST['name'] ?? ''));
    $appliedby = $udata['phone'];
    $debit_fee = $udata['balance'] - $fee;

    if ($udata['balance'] < $fee) {
        echo "<script>
                $(function(){ Swal.fire('Wallet Balance is Low!', 'Please Recharge Now!', 'error') });
                setTimeout(() => { window.location='wallet.php'; }, 1200);
              </script>";
    } else {
        $api_key = urlencode($DarkXen_apikey); 
        $api_url = htmlspecialchars($DarkXen_url);

        $url = "https://$api_url/api/rtps_pdf.php?token=$api_key&appl_ref_no=" . urlencode($_POST['applicationNumber']) . "&applicant_name_en=" . urlencode($_POST['name']);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
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

        $status = $resdata['status'] ?? '';
        $statusMessage = $resdata['statusMessage'] ?? '';
        $pdf_base64 = $resdata['pdf'] ?? '';
        $error = $resdata['error'] ?? '';

        if ($error) {
            echo "<script>
                    $(function(){ Swal.fire('Error', '".htmlspecialchars($error)."', 'error') });
                    setTimeout(() => { window.location=''; }, 5000);
                  </script>";
        } elseif ($status === "success" && !empty($pdf_base64)) {
            
            // 1. Save data to SQL Database
            $insert_query = "INSERT INTO `rtps_pdf_history` (`userid`, `application_number`, `applicant_name`, `pdf_base64`, `fee_debited`) VALUES ('$appliedby', '$applicationNumber', '$name', '$pdf_base64', '$fee')";
            
            if (mysqli_query($DarkXen_conn, $insert_query)) {
                // 2. Debit user wallet
                mysqli_query($DarkXen_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
                
                // 3. Insert into wallet history
                mysqli_query($DarkXen_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('$appliedby','$fee','$debit_fee','Bihar RTPS PDF','1','Debit')");
                
                $success_message = "RTPS Certificate PDF fetched and saved successfully!";
            } else {
                $error_message = "Database Error: Could not save verification record.";
            }
        } else {
            $msg = !empty($statusMessage) ? $statusMessage : "Unable to fetch data. Check details.";
            echo "<script>
                    $(function(){ Swal.fire('Error', '".htmlspecialchars($msg)."', 'error') });
                    setTimeout(() => { window.location=''; }, 5000);
                  </script>";
        }
    }
}
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-success-subtle shadow-none me-3">
                        <span class="fas fa-file-alt text-success fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-success fs-10 mb-0">Government Certificate Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Bihar RTPS <span class="text-success fw-medium">PDF Downloader</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="RTPS_To_find_List.php" class="btn btn-sm btn-outline-success shadow-none fw-bold"><i class="fas fa-history me-1"></i> View Download History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Error Box -->
<?php if (!empty($error_message)) { ?>
    <div class="alert alert-danger border-0 d-flex align-items-center mb-3" role="alert">
        <span class="fas fa-exclamation-circle fs-3 me-2"></span>
        <p class="mb-0"><?php echo htmlspecialchars($error_message); ?></p>
    </div>
<?php } ?>

<!-- Content Layout -->
<div class="row justify-content-center g-4 mb-4">
    <!-- Left Form Panel -->
    <div class="col-lg-5">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-success">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-search-plus me-2 text-success"></i>Fetch RTPS Document</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-success border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-info-circle fs-3 me-3 text-success"></i>
                    <p class="mb-0 fs-11">Service Cost: <strong>₹<?php echo number_format($fee, 2); ?></strong> will be deducted on successful retrieval.</p>
                </div>
                               
                <form action="" method="POST" class="row g-3">
                    <div class="col-12 mb-3">
                        <label for="applicationNumber" class="form-label font-weight-500 text-dark">Application Reference Number (Appl Ref No)</label>
                        <input name="applicationNumber" type="text" id="applicationNumber" value="<?php echo isset($_POST['applicationNumber']) ? htmlspecialchars($_POST['applicationNumber']) : ''; ?>" placeholder="e.g. BRCCCO/2026/XXXXX" class="form-control" required>
                    </div>
                    
                    <div class="col-12 mb-4">
                        <label for="name" class="form-label font-weight-500 text-dark">Applicant Name (In English)</label>
                        <input name="name" type="text" id="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" placeholder="Enter Full Name" class="form-control" required>
                    </div>
                    
                    <div class="col-12">
                        <hr class="mt-0 mb-4">
                        <div class="row align-items-center">
                            <div class="col-6">
                                <input class="form-control font-weight-600 text-success bg-light border-0" value="Fee: ₹ <?php echo htmlspecialchars($fee); ?>" readonly style="box-shadow: none;">
                            </div>
                            <div class="col-6 text-end">
                                <button type="submit" class="btn btn-success w-100 fw-bold" name="submit" id="submit">
                                    <i class="fas fa-download me-1"></i> Download PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Result Actions -->
    <div class="col-lg-7">
        <?php if (isset($status) && $status === "success" && !empty($pdf_base64)) { ?>
            <div class="card shadow-sm border-0 rounded-3 h-100 text-center p-5 d-flex flex-column align-items-center justify-content-center">
                <div class="mb-4">
                    <span class="fas fa-file-pdf text-danger animate__animated animate__bounceIn" style="font-size: 5.5rem;"></span>
                </div>
                <h4 class="text-success fw-bold mb-2">Document Found & Saved!</h4>
                <p class="text-muted mb-4">
                    <strong>Applicant:</strong> <?= htmlspecialchars($_POST['name']) ?><br>
                    <strong>Ref No:</strong> <?= htmlspecialchars($_POST['applicationNumber']) ?>
                </p>
                
                <div class="d-flex gap-3">
                    <a href="<?= $pdf_base64 ?>" download="RTPS_<?= htmlspecialchars($_POST['applicationNumber']) ?>.pdf" class="btn btn-lg btn-danger shadow-sm fw-bold">
                        <i class="fas fa-file-download me-2"></i>Download PDF File
                    </a>
                   
                </div>
            </div>
        <?php } else { ?>
            <div class="card shadow-sm border-0 rounded-3 text-center p-5 h-100" style="display: flex; align-items: center; justify-content: center;">
                <div class="text-muted">
                    <i class="fas fa-file-invoice display-4 mb-3 text-secondary"></i>
                    <p class="mb-0 fs-5">Fill out the reference details to fetch your official RTPS certificate.</p>
                </div>
            </div>
        <?php } ?>
    </div>
</div>


<?php include('footer.php'); ?>