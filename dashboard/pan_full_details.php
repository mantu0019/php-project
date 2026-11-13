<?php

include('../includes/session.php');
include('../includes/config.php');
include('header.php');

// Set timezone once
date_default_timezone_set("Asia/Kolkata");

// Initialize variables
$resdata = null;
$error_message = '';$fee = 20; // Default fee if query fails

// Fetch Fee
$fee_query = mysqli_query($DarkXen_conn, "SELECT `pan_details_verify` FROM `service_free` WHERE `id` = 1");
if ($fee_query && mysqli_num_rows($fee_query) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_query);
    if ($fee_row['pan_details_verify'] !== '' &&$fee_row['pan_details_verify'] !== null) {
        $fee = (float)$fee_row['pan_details_verify'];
    }
}

$DarkXen_apikey =$webdata['api_key'] ?? ''; 
$api_domain =$DarkXen_url ?? 'darkxen.in';

// Check if PAN is submitted
if (isset($_POST['verify_pan'])) {
    
    $pan_no = strtoupper(trim($_POST['pan_number']));

    // Basic PAN validation (length and pattern)
    if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/i', $pan_no)) {$error_message = "Invalid PAN format. Must be 10 characters (e.g. ABCDE1234F).";
    } else {
        $username =$udata['phone'];
        $wallet_amount = (float)($udata['balance'] ?? 0);

        // Check wallet balance
        if ($wallet_amount <$fee) {
            ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    Swal.fire({
                        icon: 'error',
                        title: 'Insufficient Balance',
                        text: 'Your wallet balance is too low to process this request',
                    }).then(() => {
                        window.location.href = 'wallet.php';
                    });
                });
            </script>
            <?php
            exit;
        }

        // Call NEW API
        $url = "https://" . $api_domain . "/api/v3/PAN/pan_verification.php?" . http_build_query([
            'apiKey' => $DarkXen_apikey,
            'panNumber' => $pan_no
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => ["cache-control: no-cache"],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {$error_message = "Server Connection Error: $err";
        } else {
            $json = json_decode($response, true);

            // Check for success status from new API (status = true)
            if (isset($json['status']) &&$json['status'] === true) {
                
                // Prepare data
                $name =$json['fullname'] ?? 'N/A';
                $fathername =$json['fathername'] ?? 'N/A';
                $gender =$json['gender'] ?? 'N/A';
                
                // Format DOB (YYYY-MM-DD to DD/MM/YYYY)
                $raw_dob =$json['dob'] ?? '';
                $dob = !empty($raw_dob) ? date('d/m/Y', strtotime($raw_dob)) : 'N/A';$aadhaarLinked = (isset($json['dob_verified']) &&$json['dob_verified'] == true) ? 'Yes' : 'No';
                $aadhar = 'N/A'; // Since new API doesn't return Aadhaar$timestamp = date("Y-m-d H:i:s");

                // Start transaction
                mysqli_begin_transaction($DarkXen_conn);

                try {
                    // 1. Deduct fee from the wallet securely
                    $clean_username = mysqli_real_escape_string($DarkXen_conn,$username);
                    $debit = mysqli_query($DarkXen_conn, "UPDATE users SET balance = balance - $fee WHERE phone='$clean_username' AND balance >=$fee");
                    if (mysqli_affected_rows($DarkXen_conn) == 0) {
                        throw new Exception("Wallet deduction failed due to low balance.");
                    }

                    // 2. Update wallet history using standard query (FIXED Prepared Statement Issue)
                    $new_balance = $wallet_amount -$fee;
                    $history_query = "INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) 
                                      VALUES ('$clean_username', '$fee', '$new_balance', 'PAN Details Verify', '1', 'Debit')";
                    
                    if (!mysqli_query($DarkXen_conn,$history_query)) {
                        throw new Exception("Wallet history setup failed: " . mysqli_error($DarkXen_conn));
                    }

                    // 3. Save PAN Data using standard secure query (FIXED NULL created_at error)
                    $clean_pan_no = mysqli_real_escape_string($DarkXen_conn,$pan_no);
                    $clean_name = mysqli_real_escape_string($DarkXen_conn, $name);$clean_fathername = mysqli_real_escape_string($DarkXen_conn,$fathername);
                    $clean_dob = mysqli_real_escape_string($DarkXen_conn, $dob);$clean_gender = mysqli_real_escape_string($DarkXen_conn,$gender);
                    $clean_aadhaarLinked = mysqli_real_escape_string($DarkXen_conn, $aadhaarLinked);$clean_aadhar = mysqli_real_escape_string($DarkXen_conn,$aadhar);

                    $insert_sql = "INSERT INTO pan_fetch_history (userid, pan_no, name, fathername, dob, gender, aadhaar_linked, aadhar_ref, created_at) 
                                   VALUES ('$clean_username', '$clean_pan_no', '$clean_name', '$clean_fathername', '$clean_dob', '$clean_gender', '$clean_aadhaarLinked', '$clean_aadhar', '$timestamp')";
                    
                    if (!mysqli_query($DarkXen_conn,$insert_sql)) {
                        throw new Exception("PAN history setup failed: " . mysqli_error($DarkXen_conn));
                    }

                    // Commit transaction
                    mysqli_commit($DarkXen_conn);

                    // Set response data for display
                    $resdata = [
                        'status' => true,
                        'pan_no' => $pan_no,
                        'name' => $name,
                        'fathername' => $fathername,
                        'gender' => $gender,
                        'dob' => $dob,
                        'fetch_time' => date('d/m/Y g:i:s A')
                    ];

                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);$error_message = "Database error: " . $e->getMessage();
                }
            } else {
                $error_message =$json['message'] ?? "Failed to verify PAN. Invalid details.";
            }
        }
    }
}
?>

<style>
    .btn-print-blue {
        background-color: #0d6efd;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        font-weight: bold;
        cursor: pointer;
        transition: background 0.2s;
    }
    .btn-print-blue:hover {
        background-color: #0b5ed7;
    }
    @media print {
        body * {
            visibility: hidden;
        }
        #printSection, #printSection * {
            visibility: visible;
        }
        #printSection {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }
        .no-print {
            display: none !important;
        }
    }
</style>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-id-card text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Identity Services</h6>
                        <h4 class="text-primary fw-bold mb-0">PAN To <span class="fw-medium">Full Details</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="pan_full_details_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View Details History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 justify-content-center">
    <!-- LEFT SIDE: INPUT FORM -->
    <div class="col-lg-5 no-print">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-primary">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-search me-2 text-primary"></i>Fetch PAN Details</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-primary border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-primary"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?= number_format($fee, 2); ?></strong> will be deducted upon successful fetch.</p>
                </div>
                
                <form method="POST" autocomplete="off" id="panForm">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">PAN Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-keyboard"></i></span>
                            <input type="text" class="form-control shadow-none text-uppercase fw-bold text-center tracking-wide" name="pan_number" id="pan_number" placeholder="Enter 10-digit PAN" maxlength="10" required autofocus>
                        </div>
                    </div>
                    
                    <button class="btn btn-primary w-100 fw-bold shadow-none py-2" type="button" onclick="confirmFetch()">
                        <i class="fas fa-cloud-download-alt me-2"></i> Get PAN Details
                    </button>
                    <button type="submit" name="verify_pan" id="realSubmit" class="d-none"></button>
                </form>
            </div>
        </div>
    </div>

    <!-- RIGHT SIDE: SUCCESS RESPONSE -->
    <div class="col-lg-7">
        <?php if (!empty($resdata) &&$resdata['status'] === true): ?>
            <div class="card h-100 border-0 shadow-sm border-top border-4 border-success" id="printSection">
                <div class="card-header bg-success-subtle border-bottom d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 text-success fw-bold"><i class="fas fa-check-circle me-2"></i> Fetched PAN Account Details</h5>
                    <small class="text-muted fw-semi-bold"><?= $resdata['fetch_time']; ?></small>
                </div>
                <div class="card-body p-4 bg-body-tertiary">
                    <div class="table-responsive border rounded shadow-sm">
                        <table class="table table-bordered table-striped mb-0 bg-white">
                            <tbody>
                                <tr>
                                    <th width="35%" class="text-secondary fw-bold bg-light">PAN Number</th>
                                    <td class="fw-bold text-danger font-family-monospace text-uppercase fs-5 tracking-wide"><?= htmlspecialchars($resdata['pan_no']); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-secondary fw-bold bg-light">Full Name</th>
                                    <td class="fw-bold text-primary text-uppercase"><?= htmlspecialchars($resdata['name']); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-secondary fw-bold bg-light">Father Name</th>
                                    <td class="text-dark fw-bold text-uppercase"><?= htmlspecialchars($resdata['fathername']); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-secondary fw-bold bg-light">Date of Birth</th>
                                    <td class="text-dark fw-bold"><?= htmlspecialchars($resdata['dob']); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-secondary fw-bold bg-light">Gender</th>
                                    <td class="text-dark fw-bold text-uppercase"><?= htmlspecialchars($resdata['gender']); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-flex-end mt-4 no-print" style="justify-content: flex-end;">
                        <button class="btn-print-blue d-flex align-items-center gap-2 shadow-sm" onclick="printBill()">
                            <i class="fas fa-print"></i> Print Status Receipt
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Format input to uppercase automatically
document.getElementById('pan_number').addEventListener('input', function () {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
});

function confirmFetch() {
    var panInput = document.getElementById('pan_number').value.trim();
    var panPattern = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/i;
    
    if(!panPattern.test(panInput)) {
        Swal.fire("Invalid Format", "Please enter a valid 10-digit PAN number (e.g., ABCDE1234F).", "warning");
        return false;
    }

    Swal.fire({
        title: 'Confirm Query?',
        text: "₹" + parseFloat(<?= json_encode($fee); ?>).toFixed(2) + " will be deducted from your wallet.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, Fetch Details!'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Fetching PAN Details...',
                html: 'Communicating with central servers, please wait...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            document.getElementById('realSubmit').click();
        }
    });
}

function printBill() {
    window.print();
}
</script>

<?php
if (!empty($error_message)) {
    echo '<script>
        $(document).ready(function() {
            Swal.fire({
                icon: "error",
                title: "Query Failed",
                text: "' . addslashes($error_message) . '",
                confirmButtonText: "OK"
            });
        });
    </script>';
}
?>

<?php include('footer.php'); ?>