<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

$fee = 25; // Aap apni requirement ke hisab se fee set kar sakte hain
$pdf_url = "";
$dl_number_res = "";
$txn_id_res = "";

function upperCaseString($str) {
    return mb_strtoupper(trim($str ?? ''), 'UTF-8');
}

if (isset($_POST['submit'])) {
    $dlNo = upperCaseString($_POST['dlNo']);
    $dob = trim($_POST['dob']);
    $appliedby = $udata['phone'];
    
    // Check Wallet Balance
    if ($udata['balance'] >= $fee) {
        
        // Dynamic Token Setup
        $api_token = urlencode($DarkXen_apikey);
        $url = "https://" . htmlspecialchars($DarkXen_url) . "/api/dl_pdf.php?token=" . $api_token . "&dl_no=" . urlencode($dlNo) . "&dob=" . urlencode($dob);
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        
        $resdata = json_decode($response, true);

        // Check for Status '100'
        if (isset($resdata['status']) && ($resdata['status'] == "100" || $resdata['status'] == 100 || $resdata['status'] === true)) {
            
            $dl_number_res = $resdata['dl_number'] ?? $dlNo;
            $txn_id_res    = $resdata['txn_id'] ?? ('TXN' . rand(10000000, 99999999));
            $pdf_base64    = $resdata['pdf_base64'] ?? '';

            if (!empty($pdf_base64)) {
                // PDF Directory create if not exists
                $target_dir = "uploads/dl_pdfs/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }

                // Clean base64 string
                if (strpos($pdf_base64, ',') !== false) {
                    $pdf_parts = explode(',', $pdf_base64);
                    $pdf_clean_data = base64_decode($pdf_parts[1]);
                } else {
                    $pdf_clean_data = base64_decode($pdf_base64);
                }

                // File name generate
                $file_name = 'DL_' . preg_replace('/[^A-Za-z0-9]/', '', $dl_number_res) . '_' . time() . '.pdf';
                $file_path = $target_dir . $file_name;
                
                // Save PDF to server
                if (file_put_contents($file_path, $pdf_clean_data)) {
                    $pdf_url = $file_path;

                    // Deduct Wallet Balance
                    $debit_fee = $udata['balance'] - $fee;
                    $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
                    
                    if ($update_wallet) {
                        // Insert Wallet History
                        mysqli_query($DarkXen_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('$appliedby','$fee','$debit_fee','DL PDF Download ($dl_number_res)','1','Debit')");

                        // Save DL Record in DB
                        $safe_dl  = mysqli_real_escape_string($DarkXen_conn, $dl_number_res);
                        $safe_dob = mysqli_real_escape_string($DarkXen_conn, $dob);
                        $safe_pdf = mysqli_real_escape_string($DarkXen_conn, $file_name);
                        $safe_txn = mysqli_real_escape_string($DarkXen_conn, $txn_id_res);

                        mysqli_query($DarkXen_conn, "INSERT INTO `dl_pdf_records` (`userid`, `dl_number`, `dob`, `txn_id`, `pdf_file`, `status`, `created_at`) VALUES ('$appliedby', '$safe_dl', '$safe_dob', '$safe_txn', '$safe_pdf', 'SUCCESS', NOW())");

                        echo "<script>
                            Swal.fire({
                                icon: 'success',
                                title: 'PDF Generated Successfully!',
                                text: 'DL Number: " . $safe_dl . "',
                                showConfirmButton: true
                            });
                        </script>";
                    }
                } else {
                    echo "<script>Swal.fire('Error', 'Unable to write PDF file to server storage.', 'error');</script>";
                }
            } else {
                echo "<script>Swal.fire('Error', 'Base64 PDF content not received from API.', 'error');</script>";
            }
        } else {
            $msg = $resdata['message'] ?? 'Failed to fetch DL PDF from API.';
            echo "<script>Swal.fire('Error', '" . htmlspecialchars($msg) . "', 'error');</script>";
        }
    } else {
        echo "<script>Swal.fire('Low Balance', 'Insufficient wallet balance! Please recharge to continue.', 'warning');</script>";
    }
}
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-danger-subtle shadow-none me-3">
                        <span class="fas fa-file-pdf text-danger fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-danger fs-10 mb-0">Transport Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Original Driving Licence <span class="text-danger fw-medium">(DL) PDF Download</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <span class="badge bg-danger p-2 fs-11">Charge: ₹<?php echo $fee; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center mb-4">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm border-top border-4 border-danger">
            <div class="card-body p-4">
                
                <form method="POST" action="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Driving Licence Number <span class="text-danger">*</span></label>
                            <input type="text" name="dlNo" class="form-control form-control-lg fw-bold" placeholder="e.g. MH0320080022135" value="<?php echo isset($_POST['dlNo']) ? htmlspecialchars($_POST['dlNo']) : ''; ?>" required style="text-transform: uppercase;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Date of Birth <span class="text-danger">*</span></label>
                            <input type="text" id="dob_mask" name="dob" class="form-control form-control-lg fw-bold" placeholder="DD-MM-YYYY" maxlength="10" value="<?php echo isset($_POST['dob']) ? htmlspecialchars($_POST['dob']) : ''; ?>" required>
                        </div>
                        <div class="col-12 mt-4">
                            <button class="btn btn-danger btn-lg w-100 fw-bold shadow-none py-2" type="submit" name="submit">
                                <i class="fas fa-download me-2"></i> Fetch & Generate DL PDF
                            </button>
                        </div>
                    </div>
                </form>

                <!-- PDF DISPLAY SECTION -->
                <?php if (!empty($pdf_url)): ?>
                    <div class="mt-4 p-3 bg-light rounded border border-success">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h5 class="text-success fw-bold mb-1"><i class="fas fa-check-circle me-1"></i> DL PDF Ready</h5>
                                <p class="text-muted mb-0 small">DL Number: <b><?php echo htmlspecialchars($dl_number_res); ?></b> | Txn ID: <b><?php echo htmlspecialchars($txn_id_res); ?></b></p>
                            </div>
                            <div>
                                <a href="<?php echo $pdf_url; ?>" download class="btn btn-success fw-bold me-2">
                                    <i class="fas fa-file-download me-1"></i> Download PDF
                                </a>
                                <a href="<?php echo $pdf_url; ?>" target="_blank" class="btn btn-outline-primary fw-bold">
                                    <i class="fas fa-external-link-alt me-1"></i> Open in New Tab
                                </a>
                            </div>
                        </div>
                        
                        <!-- Inline Preview -->
                        <div class="ratio ratio-16x9" style="height: 500px;">
                            <iframe src="<?php echo $pdf_url; ?>" class="rounded border" allowfullscreen></iframe>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script>
    // Auto Date Formatter: DD-MM-YYYY
    document.addEventListener("DOMContentLoaded", function () {
        const dobField = document.getElementById('dob_mask');
        if (dobField) {
            dobField.addEventListener('input', function (e) {
                var c = e.target.value.replace(/\D/g, '');
                var o = "";
                if (c.length > 0) { 
                    o = c.substring(0, 2); 
                    if (c.length > 2) o += '-' + c.substring(2, 4); 
                    if (c.length > 4) o += '-' + c.substring(4, 8); 
                }
                e.target.value = o;
            });
        }
    });
</script>

<?php include('footer.php'); ?>