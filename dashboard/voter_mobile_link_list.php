<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

$appliedby = mysqli_real_escape_string($DarkXen_conn,$udata['phone']);

// Table se user ki history fetch karne ki query
$history_query = mysqli_query($DarkXen_conn, "SELECT * FROM `voter_mobile_link_history` WHERE `userid` = '$appliedby' ORDER BY `id` DESC");
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-success-subtle shadow-none me-3">
                        <span class="fas fa-list text-success fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-success fs-10 mb-0">Election Commission</h6>
                        <h4 class="text-primary fw-bold mb-0">Voter Mobile <span class="text-success fw-medium">Link History</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="voter_mobile_link.php" class="btn btn-sm btn-outline-success shadow-none fw-bold">
                        <i class="fas fa-plus me-1"></i> Link New Mobile
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <div class="card border-0 shadow-sm border-top border-4 border-success">
            <div class="card-body p-3 bg-light">
                <!-- Quick Search Input -->
                <div class="row align-items-center">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-secondary"></i></span>
                            <input type="text" id="historySearch" class="form-control border-start-0 shadow-none fw-bold text-dark" placeholder="Search EPIC, Mobile or Ref ID...">
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
            <div class="card-header bg-success-subtle border-bottom py-3">
                <h5 class="mb-0 text-success fw-bold"><i class="fas fa-history me-2"></i>Linking Records Ledger</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive scrollbar">
                    <table class="table table-striped table-hover align-middle mb-0 fs-11" id="historyTable">
                        <thead class="bg-200 text-900 font-weight-600">
                            <tr>
                                <th class="p-3 text-center">S.No.</th>
                                <th class="p-3">EPIC Number</th>
                                <th class="p-3">Linked Mobile</th>
                                <th class="p-3">Ref ID / App No</th>
                                <th class="p-3">Charge</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-center">Date & Time</th>
                                <th class="p-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (mysqli_num_rows($history_query) > 0) {$sn = 1;
                                while ($row = mysqli_fetch_assoc($history_query)) {$status = $row['status'];$status_badge = '<span class="badge badge-subtle-success fs-11 border border-success-subtle"><i class="fas fa-check-circle me-1"></i>Success</span>';
                                    
                                    if (strtolower($status) !== 'success') {$status_badge = '<span class="badge badge-subtle-danger fs-11 border border-danger-subtle"><i class="fas fa-times-circle me-1"></i>Failed</span>';
                                    }

                                    $epic = htmlspecialchars($row['epic_no']);
                                    $mobile = htmlspecialchars($row['mobile_no']);
                                    $refId = htmlspecialchars($row['ref_id']);
                                    $appNo = htmlspecialchars($row['application_no']);
                                    $charge = number_format($row['charge'], 2);
                                    $dateStr = date('d M Y, h:i A', strtotime($row['created_at']));
                                    $msg = htmlspecialchars($row['message']);
                                    
                                    echo "<tr>
                                        <td class='p-3 fw-bold text-center text-secondary'>{$sn}</td>
                                        <td class='p-3 text-primary font-family-monospace text-uppercase tracking-wide'><strong>{$epic}</strong></td>
                                        <td class='p-3 fw-bold font-family-monospace text-dark'>{$mobile}</td>
                                        <td class='p-3'>
                                            <div class='fw-bold text-dark'>{$refId}</div>
                                            <div class='text-muted fs-10'>{$appNo}</div>
                                        </td>
                                        <td class='p-3 text-danger fw-bold'>₹{$charge}</td>
                                        <td class='p-3'>{$status_badge}</td>
                                        <td class='p-3 text-center text-secondary fw-medium'>{$dateStr}</td>
                                        <td class='p-3 text-center'>";
                                        
                                    // Print Receipt Action
                                    if(strtolower($status) === 'success') {
                                        echo "<button class='btn btn-sm btn-outline-primary shadow-none px-2 py-1' onclick=\"printReceipt('print_{$row['id']}')\" title='Print Receipt'>
                                                <i class='fas fa-print'></i>
                                              </button>
                                              
                                              <!-- Hidden Print Layout -->
                                              <div id='print_{$row['id']}' class='d-none'>
                                                  <div style='max-width: 400px; margin: 0 auto; border: 1px dashed #ccc; padding: 20px; font-family: Arial, sans-serif;'>
                                                      <div style='text-align: center; border-bottom: 1px solid #ddd; padding-bottom: 15px; margin-bottom: 20px;'>
                                                          <h3 style='margin: 0; color: #333;'>Voter Mobile Linking</h3>
                                                          <p style='margin: 5px 0 0; color: #666; font-size: 12px;'>Authorized Service Center Receipt</p>
                                                      </div>
                                                      <table style='width: 100%; font-size: 14px;'>
                                                          <tr><td style='padding: 5px 0; color: #666;'>Transaction ID:</td><td style='text-align: right; font-weight: bold;'>{$refId}</td></tr>
                                                          <tr><td style='padding: 5px 0; color: #666;'>Application No:</td><td style='text-align: right; font-weight: bold;'>{$appNo}</td></tr>
                                                          <tr><td colspan='2'><hr style='border: none; border-top: 1px solid #eee; margin: 10px 0;'></td></tr>
                                                          <tr><td style='padding: 5px 0; color: #666;'>EPIC Number:</td><td style='text-align: right; font-weight: bold; font-family: monospace; font-size: 16px;'>{$epic}</td></tr>
                                                          <tr><td style='padding: 5px 0; color: #666;'>Linked Mobile:</td><td style='text-align: right; font-weight: bold; font-family: monospace; font-size: 16px;'>{$mobile}</td></tr>
                                                          <tr><td style='padding: 5px 0; color: #666;'>Status:</td><td style='text-align: right; font-weight: bold; color: green;'>{$msg}</td></tr>
                                                          <tr><td colspan='2'><hr style='border: none; border-top: 1px solid #eee; margin: 10px 0;'></td></tr>
                                                          <tr><td style='padding: 5px 0; color: #666;'>Amount:</td><td style='text-align: right; font-weight: bold;'>Rs. {$charge}</td></tr>
                                                          <tr><td style='padding: 5px 0; color: #666;'>Date & Time:</td><td style='text-align: right; font-size: 12px; color: #666;'>{$dateStr}</td></tr>
                                                      </table>
                                                  </div>
                                              </div>";
                                    } else {
                                        echo "<span class='text-muted'>N/A</span>";
                                    }
                                        
                                    echo "</td></tr>";
                                    $sn++;
                                }
                            } else {
                                echo "<tr>
                                    <td colspan='8' class='text-center p-5 text-secondary bg-white'>
                                        <i class='fas fa-inbox fs-3 mb-3 d-block text-300'></i>
                                        <p class='fw-bold fs-10 mb-0'>No voter linking records found mapped to your ID.</p>
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

<script>
// Search function to instantly filter records without page reload
document.getElementById('historySearch').addEventListener('keyup', function() {
    var value = this.value.toLowerCase();
    var rows = document.querySelectorAll('#historyTable tbody tr');
    
    rows.forEach(function(row) {
        // Agar table empty message wala row hai toh skip karein
        if (row.cells.length === 1) return;
        
        var epic = row.cells[1].textContent.toLowerCase();
        var mobile = row.cells[2].textContent.toLowerCase();
        var refId = row.cells[3].textContent.toLowerCase();
        
        if (epic.indexOf(value) > -1 || mobile.indexOf(value) > -1 || refId.indexOf(value) > -1) {
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
    
    setTimeout(function() {
        printWindow.print();
        printWindow.close();
    }, 200);
}
</script>

<?php include('footer.php'); ?>