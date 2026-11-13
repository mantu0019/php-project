<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set('Asia/Kolkata');

// ================= INIT & FEE CONFIG =================
$fee = 20; // Default Fee$pdf_url = "";
$dl_number_res = "";
$txn_id_res = "";

function upperCaseString($str) {
    return mb_strtoupper(trim($str ?? ''), 'UTF-8');
}

if (isset($_POST['submit'])) {
    $dlNo = upperCaseString($_POST['dlNo']);
    $dob = trim($_POST['dob']);
    $dltype = trim($_POST['dltype']); // Getting the DL Type from form
    $appliedby =$udata['phone'];
    
    // Check Wallet Balance
    if ($udata['balance'] >=$fee) {
        
        // NEW API Setup
        $api_token = urlencode($webdata['api_key'] ?? ''); // Use your actual API Key variable
        $api_domain =$DarkXen_url ?? 'darkxen.in';
        
        $url = "https://" . $api_domain . "/api/v3/DL/dl_pdf_verification.php?apiKey=" . $api_token . "&dl_number=" . urlencode($dlNo) . "&dob=" . urlencode($dob) . "&dltype=" . urlencode($dltype);$curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => ["cache-control: no-cache"]
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        
        $resdata = json_decode($response, true);

        // Check for Status 'success' (New API uses "success")
        if (isset($resdata['status']) && strtolower($resdata['status']) == "success") {
            
            $dl_number_res = $resdata['dl_number'] ?? $dlNo;
            $txn_id_res    = 'TXN' . time() . rand(1000, 9999); // API response didn't show txn_id, so generating one
            
            // New API returns base64 in 'Driving_Pdf'
            $pdf_base64    =$resdata['Driving_Pdf'] ?? ''; 

            if (!empty($pdf_base64)) {
                // PDF Directory create if not exists
                $target_dir = "uploads/dl_pdfs/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }

                // --- PDF CORRUPTION FIX ---
                // Remove data URI scheme (e.g. data:application/pdf;base64,)
                if (strpos($pdf_base64, ',') !== false) {
                    $pdf_parts = explode(',',$pdf_base64);
                    $pdf_base64 = end($pdf_parts);
                }
                
                // Clean and Decode
                $pdf_base64 = str_replace([' ', "\r", "\n"], ['+', '', ''], $pdf_base64);
                $pdf_clean_data = base64_decode($pdf_base64);

                if ($pdf_clean_data === false && strpos(substr($pdf_clean_data, 0, 10), '%PDF') === false) {
                     echo "<script>Swal.fire('Error', 'Received corrupted PDF data from the API.', 'error');</script>";
                } else {
                    // File name generate safely
                    $file_name = 'DL_' . preg_replace('/[^A-Za-z0-9]/', '', $dl_number_res) . '_' . time() . '.pdf';
                    $file_path = $target_dir .$file_name;
                    
                    // Save PDF to server
                    if (file_put_contents($file_path,$pdf_clean_data)) {
                        $pdf_url =$file_path;

                        // Deduct Wallet Balance (Safe transaction)
                        mysqli_begin_transaction($DarkXen_conn);
                        try {
                            $update_wallet = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone='$appliedby' AND balance >=$fee");
                            
                            if (mysqli_affected_rows($DarkXen_conn) > 0) {
                                // Fetch new balance for log
                                $bal_check = mysqli_query($DarkXen_conn, "SELECT balance FROM users WHERE phone='$appliedby'");
                                $debit_fee = mysqli_fetch_assoc($bal_check)['balance'];

                                // Insert Wallet History
                                mysqli_query($DarkXen_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('$appliedby','$fee','$debit_fee','DL PDF Download ($dl_number_res)','1','Debit')");

                                // Save DL Record in DB
                                $safe_dl  = mysqli_real_escape_string($DarkXen_conn, $dl_number_res);$safe_dob = mysqli_real_escape_string($DarkXen_conn,$dob);
                                $safe_pdf = mysqli_real_escape_string($DarkXen_conn, $file_path); // Save full path$safe_txn = mysqli_real_escape_string($DarkXen_conn,$txn_id_res);

                                mysqli_query($DarkXen_conn, "INSERT INTO `dl_pdf_records` (`userid`, `dl_number`, `dob`, `txn_id`, `pdf_file`, `status`, `created_at`) VALUES ('$appliedby', '$safe_dl', '$safe_dob', '$safe_txn', '$safe_pdf', 'SUCCESS', NOW())");

                                mysqli_commit($DarkXen_conn);

                                echo "<script>
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'PDF Generated Successfully!',
                                        text: 'DL Number: " . $safe_dl . "',
                                        showConfirmButton: true
                                    });
                                </script>";
                            } else {
                                throw new Exception("Insufficient Balance during transaction.");
                            }
                        } catch (Exception $e) {
                            mysqli_rollback($DarkXen_conn);
                            echo "<script>Swal.fire('Error', '" . addslashes($e->getMessage()) . "', 'error');</script>";
                        }
                    } else {
                        echo "<script>Swal.fire('Error', 'Unable to write PDF file to server storage.', 'error');</script>";
                    }
                }
            } else {
                echo "<script>Swal.fire('Error', 'Base64 PDF content not received from API.', 'error');</script>";
            }
        } else {
            $msg =$resdata['message'] ?? 'Failed to fetch DL PDF from API. Check DL No and DOB.';
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
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-control form-control-sm bg-white border-200 shadow-sm px-3">
                            <span class="fas fa-wallet text-success me-2"></span>
                            <span class="fw-bold text-success">Balance: ₹<?php echo number_format((float)($udata['balance'] ?? 0), 2); ?></span>
                        </div>
                        <span class="badge bg-danger p-2 fs-11">Charge: ₹<?php echo number_format($fee, 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center mb-4">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm border-top border-4 border-danger">
            <div class="card-body p-4">
                
                <form method="POST" action="" onsubmit="showLoader('Fetching DL PDF, Please wait...')">
                    <div class="row g-3">
                        
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Select DL Format Type <span class="text-danger">*</span></label>
                            <select name="dltype" class="form-select form-select-lg fw-bold" required>
                                <option value="" disabled selected>--- Choose Format ---</option>
                                <option value="1">Non-Chip (Old Format)</option>
                                <option value="2">With Chip (Smart Card Format)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Driving Licence Number <span class="text-danger">*</span></label>
                            <input type="text" name="dlNo" class="form-control form-control-lg fw-bold" placeholder="e.g. HR8720250003789" value="<?php echo isset($_POST['dlNo']) ? htmlspecialchars($_POST['dlNo']) : ''; ?>" required style="text-transform: uppercase;">
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
                                <p class="text-muted mb-0 small">DL Number: <b><?php echo htmlspecialchars($dl_number_res); ?></b></p>
                            </div>
                            <div>
                                <a href="<?php echo htmlspecialchars($pdf_url); ?>" download class="btn btn-success fw-bold me-2 shadow-none">
                                    <i class="fas fa-file-download me-1"></i> Download PDF
                                </a>
                                <a href="<?php echo htmlspecialchars($pdf_url); ?>" target="_blank" class="btn btn-outline-primary fw-bold shadow-none">
                                    <i class="fas fa-external-link-alt me-1"></i> Open in New Tab
                                </a>
                            </div>
                        </div>
                        
                        <!-- Inline Preview -->
                        <div class="ratio ratio-16x9" style="height: 500px;">
                            <iframe src="<?php echo htmlspecialchars($pdf_url); ?>" class="rounded border" allowfullscreen></iframe>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Sweetalert Loader
    function showLoader(txt) {
        Swal.fire({
            title: txt,
            html: 'Connecting to Transport Servers...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
    }

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