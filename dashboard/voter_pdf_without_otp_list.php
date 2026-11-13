<?php
include('../includes/session.php');
include('../includes/config.php');

date_default_timezone_set('Asia/Kolkata');
$appliedby = mysqli_real_escape_string($DarkXen_conn,$udata['phone'] ?? '');

// ========================================================
// ⚡ STEALTH AJAX POLLING LOGIC (HIDDEN BACKEND PROCESS)
// ========================================================
if (isset($_GET['ajax_poll']) &&$_GET['ajax_poll'] == '1') {
    header('Content-Type: application/json');
    
    if (empty($appliedby)) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    $API_KEY = urlencode($webdata['api_key'] ?? '');
    $api_domain =$DarkXen_url;
    $updates = [];$latest_balance = null;

    // Get ONLY PENDING requests for this user
    $pending_query = mysqli_query($DarkXen_conn, "SELECT * FROM `voter_pdf_without_otp_history` WHERE `userid` = '$appliedby' AND `status` = 'Pending'");

    while ($row = mysqli_fetch_assoc($pending_query)) {
        $rowId =$row['id'];
        $epic =$row['epic_no'];
        $charge = (float)$row['charge'];

        // Backend API Hit
        $url = "https://" . $api_domain . "/api/v3/VOTER/VCMpdf_list.php?api_key=$API_KEY&epic=" . urlencode($epic);$ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $resdata = json_decode($response, true);
            $status = strtolower($resdata['status'] ?? '');

            // 🟢 IF SUCCESS & PDF RECEIVED
            if ($status === 'success' && !empty($resdata['pdf_base64'])) {$dir = "uploads/vot_pdfs";
                if (!is_dir($dir)) { mkdir($dir, 0777, true); }

                $safeEpic = preg_replace('/[^a-zA-Z0-9]/', '_', $epic);$fileName = $dir . "/" . $safeEpic . "_" . time() . ".pdf";
                
                // Decode & Save Base64 PDF
                file_put_contents($fileName, base64_decode($resdata['pdf_base64']));

                // Update Status to Success
                mysqli_query($DarkXen_conn, "UPDATE `voter_pdf_without_otp_history` SET `status` = 'Success', `pdf_path` = '$fileName' WHERE `id` = '$rowId'");

                $updates[] = ['id' => $rowId, 'status' => 'success', 'pdf_path' =>$fileName];
            } 
            // 🔴 IF FAILED / REJECTED -> REFUND AND DELETE
            elseif ($status === 'failed' || $status === 'rejected' && $status === 'error') {
                mysqli_begin_transaction($DarkXen_conn);
                try {
                    // Refund to Wallet
                    mysqli_query($DarkXen_conn, "UPDATE `users` SET `balance` = `balance` + $charge WHERE `phone` = '$appliedby'");
                    
                    // Fetch New Balance for Live UI Update
                    $bal_check = mysqli_query($DarkXen_conn, "SELECT balance FROM users WHERE phone='$appliedby'");
                    $latest_balance = mysqli_fetch_assoc($bal_check)['balance'];

                    // Wallet Credit Log
                    $remark = "Refund: Voter PDF Rejected ($epic)";
                    mysqli_query($DarkXen_conn, "INSERT INTO `wallethistory` (`userid`,`amount`,`balance`,`purpose`,`status`,`type`) 
                                                 VALUES ('$appliedby', '$charge', '$latest_balance', '$remark', '1', 'Credit')");

                    // Delete the rejected record permanently
                    mysqli_query($DarkXen_conn, "DELETE FROM `voter_pdf_without_otp_history` WHERE `id` = '$rowId'");

                    mysqli_commit($DarkXen_conn);$updates[] = ['id' => $rowId, 'status' => 'deleted', 'epic' =>$epic];
                } catch (Exception $e) {
                    mysqli_rollback($DarkXen_conn);
                }
            }
        }
    }

    $response_data = ['success' => true, 'updates' =>$updates];
    if ($latest_balance !== null) {
        $response_data['new_balance'] = number_format($latest_balance, 2);
    }
    
    echo json_encode($response_data);
    exit; // Stop further execution for AJAX calls
}

// ========================================================
// 🌐 FRONTEND UI HTML CODE (NORMAL PAGE LOAD)
// ========================================================
include('header.php');
$history_query = mysqli_query($DarkXen_conn, "SELECT * FROM `voter_pdf_without_otp_history` WHERE `userid` = '$appliedby' ORDER BY `id` DESC");
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-info-subtle shadow-none me-3">
                        <span class="fas fa-file-pdf text-info fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-info fs-10 mb-0">Election Commission</h6>
                        <h4 class="text-primary fw-bold mb-0">Voter PDF Without OTP <span class="text-info fw-medium">History</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-control form-control-sm bg-white border-200 shadow-sm px-3">
                            <span class="fas fa-wallet text-success me-2"></span>
                            <span id="userBalanceDisplay" class="fw-bold text-success">Balance: ₹<?php echo number_format((float)($udata['balance'] ?? 0), 2); ?></span>
                        </div>
                        <a href="voter_pdf_without_otp.php" class="btn btn-sm btn-outline-info shadow-none fw-bold">
                            <i class="fas fa-plus me-1"></i> Request New PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <div class="card border-0 shadow-sm border-top border-4 border-info">
            <div class="card-body p-3 bg-light">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-secondary"></i></span>
                            <input type="text" id="historySearch" class="form-control border-start-0 shadow-none fw-bold text-dark" placeholder="Search EPIC or Ref ID...">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info-subtle border-bottom py-3">
                <h5 class="mb-0 text-info fw-bold"><i class="fas fa-history me-2"></i>PDF Requests Ledger</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive scrollbar">
                    <table class="table table-striped table-hover align-middle mb-0 fs-11" id="historyTable">
                        <thead class="bg-200 text-900 font-weight-600">
                            <tr>
                                <th class="p-3 text-center">S.No.</th>
                                <th class="p-3">EPIC Number</th>
                                <th class="p-3">Ref ID</th>
                                <th class="p-3">Charge</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-center">Date & Time</th>
                                <th class="p-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (mysqli_num_rows($history_query) > 0) {$sn = 1;
                                while ($row = mysqli_fetch_assoc($history_query)) {
                                    $status = ucfirst(strtolower($row['status']));
                                    $rowId =$row['id'];
                                    $epic = htmlspecialchars($row['epic_no']);
                                    $refId = htmlspecialchars($row['ref_id']);
                                    $charge = number_format($row['charge'], 2);
                                    $dateStr = date('d M Y, h:i A', strtotime($row['created_at']));
                                    $pdfPath =$row['pdf_path'];

                                    // HTML Elements
                                    $status_badge = '<span class="badge badge-subtle-warning fs-11 border border-warning-subtle pending-badge" id="badge_'.$rowId.'"><i class="fas fa-clock me-1"></i>Pending</span>';
                                    
                                    if ($status === 'Success') {$status_badge = '<span class="badge badge-subtle-success fs-11 border border-success-subtle" id="badge_'.$rowId.'"><i class="fas fa-check-circle me-1"></i>Success</span>';
                                    }

                                    echo "<tr id='row_{$rowId}' style='transition: background-color 0.5s ease;'>
                                        <td class='p-3 fw-bold text-center text-secondary'>{$sn}</td>
                                        <td class='p-3 text-primary font-family-monospace text-uppercase tracking-wide'><strong>{$epic}</strong></td>
                                        <td class='p-3'><code>{$refId}</code></td>
                                        <td class='p-3 text-danger fw-bold'>₹{$charge}</td>
                                        <td class='p-3'>{$status_badge}</td>
                                        <td class='p-3 text-center text-secondary fw-medium'>{$dateStr}</td>
                                        <td class='p-3 text-center' id='action_{$rowId}'>";
                                        
                                    if ($status === 'Success' && !empty($pdfPath)) {
                                        echo "<a href='{$pdfPath}' target='_blank' class='btn btn-sm btn-primary shadow-none px-3 py-1 fw-bold'>
                                                <i class='fas fa-file-download me-1'></i> Download PDF
                                              </a>";
                                    } else {
                                        echo "<div class='spinner-border spinner-border-sm text-info' role='status'><span class='visually-hidden'>Loading...</span></div> <span class='text-muted fw-bold ms-1'>Processing...</span>";
                                    }
                                        
                                    echo "</td></tr>";
                                    $sn++;
                                }
                            } else {
                                echo "<tr id='emptyRow'>
                                    <td colspan='7' class='text-center p-5 text-secondary bg-white'>
                                        <i class='fas fa-inbox fs-3 mb-3 d-block text-300'></i>
                                        <p class='fw-bold fs-10 mb-0'>No voter PDF requests found.</p>
                                    </td>
                                </tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Instant Search Filter
document.getElementById('historySearch').addEventListener('keyup', function() {
    var value = this.value.toLowerCase();
    var rows = document.querySelectorAll('#historyTable tbody tr:not(#emptyRow)');
    
    rows.forEach(function(row) {
        var epic = row.cells[1].textContent.toLowerCase();
        var refId = row.cells[2].textContent.toLowerCase();
        if (epic.indexOf(value) > -1 || refId.indexOf(value) > -1) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
});

// ========================================================
// ⚡ STEALTH AUTO-POLLING AJAX (EVERY 5 SEC)
// ========================================================
let isChecking = false;

function checkPendingRequests() {
    // 🚀 SMART OPTIMIZATION: Only hit the server if there is a 'Pending' badge on screen!
    if ($('.pending-badge').length === 0) return; 

    // Prevent overlapping requests
    if (isChecking) return; 
    isChecking = true;

    $.ajax({
        url: '?ajax_poll=1', 
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response && response.success) {
                
                // 💎 LIVE WALLET UPDATE (If refund happens)
                if (response.new_balance !== undefined) {
                    $('#userBalanceDisplay').fadeOut(200, function() {
                        $(this).text('Balance: ₹' + response.new_balance).fadeIn(200);
                    });
                }

                if (response.updates) {
                    response.updates.forEach(function(item) {
                        if (item.status === 'success') {
                            
                            // Remove pending class so it stops polling for this row
                            $('#badge_' + item.id).replaceWith('<span class="badge badge-subtle-success fs-11 border border-success-subtle"><i class="fas fa-check-circle me-1"></i>Success</span>');
                            $('#action_' + item.id).html('<a href="' + item.pdf_path + '" target="_blank" class="btn btn-sm btn-primary shadow-none px-3 py-1 fw-bold"><i class="fas fa-file-download me-1"></i> Download PDF</a>').hide().fadeIn(500);
                            
                            // ✨ UNIQUE TOUCH: Flash the row green briefly
                            $('#row_' + item.id).css('background-color', '#d1e7dd');
                            setTimeout(() => { $('#row_' + item.id).css('background-color', ''); }, 2000);

                        } else if (item.status === 'deleted') {
                            
                            // Smoothly remove rejected row
                            $('#row_' + item.id).fadeOut(600, function() {
                                $(this).remove();
                                
                                // ✨ UNIQUE TOUCH: Silent Toast Notification for Refund
                                Swal.fire({
                                    toast: true,
                                    position: 'top-end',
                                    icon: 'info',
                                    title: 'Refund processed for rejected EPIC: ' + item.epic,
                                    showConfirmButton: false,
                                    timer: 5000,
                                    timerProgressBar: true
                                });
                            });
                        }
                    });
                }
            }
        },
        complete: function() {
            isChecking = false; // Release the lock
        }
    });
}

// Start Background Loop (5000 ms = 5 seconds)
setInterval(checkPendingRequests, 5000);
</script>

<?php include('footer.php'); ?>