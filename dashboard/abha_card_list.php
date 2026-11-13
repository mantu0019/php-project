<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set('Asia/Kolkata');

$user = mysqli_real_escape_string($DarkXen_conn,$udata['phone'] ?? '');

// User ki ABHA Card history fetch karne ki query
$history_query = mysqli_query($DarkXen_conn, "SELECT * FROM `abha_card_history` WHERE `userid` = '$user' ORDER BY `id` DESC");
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-id-card-alt text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Health Services</h6>
                        <h4 class="text-primary fw-bold mb-0">ABHA Card <span class="text-info fw-medium">History</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="abha_card_verification.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold">
                        <i class="fas fa-plus me-1"></i> Generate New Card
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <div class="card border-0 shadow-sm border-top border-4 border-primary">
            <div class="card-body p-3 bg-light">
                <!-- Quick Search Input -->
                <div class="row align-items-center">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-secondary"></i></span>
                            <input type="text" id="historySearch" class="form-control border-start-0 shadow-none fw-bold text-dark" placeholder="Search ABHA No, Txn ID...">
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
            <div class="card-header bg-primary-subtle border-bottom py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-history me-2"></i>Generated Cards Ledger</h5>
                <span class="badge bg-primary text-white border px-2 py-1 fs-11">Total Records: <?php echo mysqli_num_rows($history_query); ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive scrollbar">
                    <table class="table table-striped table-hover align-middle mb-0 fs-11" id="historyTable">
                        <thead class="bg-200 text-900 font-weight-600">
                            <tr>
                                <th class="p-3 text-center">S.No.</th>
                                <th class="p-3">ABHA Number</th>
                                <th class="p-3">Txn ID / Details</th>
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
                                    $abha_num = htmlspecialchars($row['abha_number'] ?? 'N/A');
                                    $txn_id = htmlspecialchars($row['txn_id'] ?? 'N/A');
                                    $charge = number_format($row['charge'], 2);
                                    $dateStr = date('d M Y, h:i A', strtotime($row['created_at']));
                                    $pdfPath =$row['pdf_path'] ?? '';
                                    $msg = htmlspecialchars($row['message'] ?? 'Processing Completed');

                                    // Status Badge styling
                                    $status_badge = '<span class="badge badge-subtle-warning fs-11 border border-warning-subtle"><i class="fas fa-clock me-1"></i>Pending</span>';
                                    if ($status === 'Success') {$status_badge = '<span class="badge badge-subtle-success fs-11 border border-success-subtle"><i class="fas fa-check-circle me-1"></i>Success</span>';
                                    } elseif ($status === 'Failed') {$status_badge = '<span class="badge badge-subtle-danger fs-11 border border-danger-subtle"><i class="fas fa-times-circle me-1"></i>Failed</span>';
                                    }

                                    echo "<tr>
                                        <td class='p-3 fw-bold text-center text-secondary'>{$sn}</td>
                                        <td class='p-3 text-primary font-family-monospace tracking-wide fs-6'><strong>{$abha_num}</strong></td>
                                        <td class='p-3'>
                                            <div class='fw-bold text-dark' style='font-size:10px;'>Txn: {$txn_id}</div>
                                            <div class='text-muted fs-10'>{$msg}</div>
                                        </td>
                                        <td class='p-3 text-danger fw-bold'>₹{$charge}</td>
                                        <td class='p-3'>{$status_badge}</td>
                                        <td class='p-3 text-center text-secondary fw-medium'>{$dateStr}</td>
                                        <td class='p-3 text-center'>";
                                        
                                    // Action Buttons (Download & Print)
                                    if ($status === 'Success' && !empty($pdfPath)) {
                                        echo "
                                            <div class='btn-group' role='group'>
                                                <a href='{$pdfPath}' target='_blank' class='btn btn-sm btn-primary shadow-none px-2 py-1' title='Download PDF'>
                                                    <i class='fas fa-file-pdf'></i>
                                                </a>
                                                <button class='btn btn-sm btn-outline-primary shadow-none px-2 py-1' onclick=\"printReceipt('print_{$row['id']}')\" title='Print Receipt'>
                                                    <i class='fas fa-print'></i>
                                                </button>
                                            </div>
                                              
                                            <!-- Hidden Print Layout -->
                                            <div id='print_{$row['id']}' class='d-none'>
                                                <div style='max-width: 400px; margin: 0 auto; border: 1px dashed #ccc; padding: 20px; font-family: Arial, sans-serif;'>
                                                    <div style='text-align: center; border-bottom: 1px solid #ddd; padding-bottom: 15px; margin-bottom: 20px;'>
                                                        <h3 style='margin: 0; color: #333;'>Ayushman Bharat Health Account</h3>
                                                        <p style='margin: 5px 0 0; color: #666; font-size: 12px;'>ABHA Card Generation Receipt</p>
                                                    </div>
                                                    <table style='width: 100%; font-size: 14px;'>
                                                        <tr><td style='padding: 5px 0; color: #666;'>Transaction ID:</td><td style='text-align: right; font-weight: bold; font-size:11px;'>{$txn_id}</td></tr>
                                                        <tr><td colspan='2'><hr style='border: none; border-top: 1px solid #eee; margin: 10px 0;'></td></tr>
                                                        <tr><td style='padding: 5px 0; color: #666;'>ABHA Number:</td><td style='text-align: right; font-weight: bold; font-family: monospace; font-size: 16px;'>{$abha_num}</td></tr>
                                                        <tr><td style='padding: 5px 0; color: #666;'>Status:</td><td style='text-align: right; font-weight: bold; color: green;'>Successful</td></tr>
                                                        <tr><td colspan='2'><hr style='border: none; border-top: 1px solid #eee; margin: 10px 0;'></td></tr>
                                                        <tr><td style='padding: 5px 0; color: #666;'>Amount Deducted:</td><td style='text-align: right; font-weight: bold;'>Rs. {$charge}</td></tr>
                                                        <tr><td style='padding: 5px 0; color: #666;'>Date & Time:</td><td style='text-align: right; font-size: 12px; color: #666;'>{$dateStr}</td></tr>
                                                    </table>
                                                    <div style='text-align: center; margin-top: 20px; font-size: 11px; color: #999;'>
                                                        This is a computer-generated receipt and does not require a physical signature.
                                                    </div>
                                                </div>
                                            </div>";
                                    } else {
                                        echo "<span class='text-muted'>-</span>";
                                    }
                                        
                                    echo "</td></tr>";
                                    $sn++;
                                }
                            } else {
                                echo "<tr id='emptyRow'>
                                    <td colspan='7' class='text-center p-5 text-secondary bg-white'>
                                        <i class='fas fa-inbox fs-3 mb-3 d-block text-300'></i>
                                        <p class='fw-bold fs-10 mb-0'>No ABHA card generation records found.</p>
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
<script>
// Instant Search Filter without page reload
document.getElementById('historySearch').addEventListener('keyup', function() {
    var value = this.value.toLowerCase();
    var rows = document.querySelectorAll('#historyTable tbody tr:not(#emptyRow)');
    
    rows.forEach(function(row) {
        // Cells: 1 is ABHA No, 2 is Txn ID
        var abha = row.cells[1].textContent.toLowerCase();
        var txn = row.cells[2].textContent.toLowerCase();
        
        if (abha.indexOf(value) > -1 || txn.indexOf(value) > -1) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
});

// Print Receipt Function
function printReceipt(divId) {
    var printContent = document.getElementById(divId).innerHTML;
    var printWindow = window.open('', '', 'height=500,width=600');
    
    printWindow.document.write('<html><head><title>Print Receipt</title></head><body style="margin:0; padding:20px; display:flex; justify-content:center;">');
    printWindow.document.write(printContent);
    printWindow.document.write('</body></html>');
    
    printWindow.document.close();
    printWindow.focus();
    
    // Give browser time to load styles before triggering print prompt
    setTimeout(function() {
        printWindow.print();
        printWindow.close();
    }, 250);
}
</script>

<?php include('footer.php'); ?>