<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

date_default_timezone_set("Asia/Kolkata"); 
$appliedby = $udata['phone'];
$user_role = $udata['role'] ?? 'User';

if ($user_role === 'Admin') {
    $query = "SELECT * FROM `udyam_detail_history` ORDER BY id DESC";
} else {
    $query = "SELECT * FROM `udyam_detail_history` WHERE `username` = '$appliedby' ORDER BY id DESC";
}

$result = mysqli_query($DarkXen_conn,$query);
?>

<style>
    /* SINGLE SLIP FULL DATA PRINT CSS */
    @media print {
        body * { visibility: hidden; }
        #printModalArea, #printModalArea * { visibility: visible; }
        #printModalArea { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
        .modal { position: absolute; left: 0; top: 0; margin: 0; padding: 0; overflow: visible!important; }
        .modal-dialog { max-width: 100%; margin: 0; }
        .modal-content { border: none; box-shadow: none; }
    }
</style>

<div class="row mb-3 no-print">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3" style="width: 50px; height: 50px;">
                        <span class="fas fa-history text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">MSME Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Udyam Full Details <span class="text-info fw-medium">History</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="udyam_to_details.php" class="btn btn-sm btn-primary shadow-none fw-bold">
                        <i class="fas fa-search me-1"></i> New Full Data Extraction
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4 no-print">
    <div class="col-12">
        <div class="card shadow-sm border border-top border-4 border-info">
            <div class="card-header bg-light py-3 border-bottom">
                <h5 class="mb-0 text-dark fw-bold"><i class="fas fa-list me-2 text-info"></i> Processed MSME Profiles</h5>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive scrollbar">
                    <table class="table table-sm table-striped table-bordered align-middle fs-10 mb-0">
                        <thead class="bg-200">
                            <tr>
                                <th class="text-center py-3">Sl No.</th>
                                <th class="py-3">App Reference</th>
                                <?php if($user_role === 'Admin'): ?><th class="py-3">User</th><?php endif; ?>
                                <th class="py-3">Udyam No</th>
                                <th class="py-3">Enterprise Name</th>
                                <th class="py-3">Org Type</th>
                                <th class="text-center py-3">Date & Time</th>
                                <th class="text-center py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($result && mysqli_num_rows($result) > 0) {$sl = 1;
                                while ($row = mysqli_fetch_assoc($result)) {
                                    // Safe JSON string conversion
                                    $row_json = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr>
                                <td class="text-center fw-bold text-dark"><?= $sl++; ?></td>
                                <td class="fw-bold text-primary font-monospace fs-11"><?= htmlspecialchars($row['application_no']); ?></td>
                                <?php if($user_role === 'Admin'): ?>
                                    <td class="fw-medium text-dark"><?= htmlspecialchars($row['username']); ?></td>
                                <?php endif; ?>
                                <td class="fw-bold text-success font-monospace"><?= htmlspecialchars($row['udyam_no']); ?></td>
                                <td class="fw-bold text-dark text-uppercase"><?= htmlspecialchars($row['company_name']); ?></td>
                                <td class="text-muted fw-bold"><?= htmlspecialchars($row['org_type']); ?></td>
                                <td class="text-center text-muted fw-medium"><?= date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
                                <td class="text-center">
                                    <!-- Print Data Button -->
                                    <button type="button" class="btn btn-info btn-sm fw-bold shadow-none view-btn" data-info='<?= $row_json ?>'>
                                        <i class="fas fa-print me-1"></i> Print All Data
                                    </button>
                                </td>
                            </tr>
                            <?php 
                                }
                            } else {
                                $colspan = ($user_role === 'Admin') ? 8 : 7;
                            ?>
                            <tr>
                                <td colspan="<?= $colspan ?>" class="text-center py-5 text-muted fw-bold">
                                    <i class="fas fa-folder-open fs-2 mb-2 d-block"></i> No Udyam extraction history found!
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light py-2 border-top">
                <p class="mb-0 fs-11 text-600 text-center">Click 'Print All Data' to view and print Complete Details including NIC Codes & Branch Units.</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Viewing & Printing Full Dynamic Data -->
<div class="modal fade" id="infoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-light border-bottom-0 no-print">
        <h5 class="modal-title fw-bold text-primary"><i class="fas fa-file-invoice me-2"></i> Comprehensive MSME Report</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <!-- Print Area -->
      <div class="modal-body p-0" id="printModalArea">
        <div class="card shadow-none border-0 rounded-0">
            <div class="card-body p-4">
                
                <div class="text-center mb-4 pb-3 border-bottom border-dark">
                    <h4 class="fw-bold text-dark text-uppercase mb-1" id="modal_cname"></h4>
                    <h6 class="mb-0 text-dark fw-bold font-monospace fs-11 border border-dark d-inline-block px-3 py-1">UDYAM ID: <span id="modal_udyam"></span></h6>
                </div>
                
                <h6 class="fw-bold text-decoration-underline mb-2 text-dark">A. Basic Organization Details</h6>
                <table class="table table-sm table-bordered mb-4 fs-10 align-middle border-dark text-dark">
                    <tr>
                        <th class="w-25">Enterprise Type:</th>
                        <td class="fw-bold" id="modal_etype"></td>
                        <th class="w-25">Organization Type:</th>
                        <td class="fw-bold" id="modal_otype"></td>
                    </tr>
                    <tr>
                        <th>Date of Incorporation:</th>
                        <td class="fw-bold" id="modal_inc"></td>
                        <th>Mobile / Email:</th>
                        <td><span id="modal_mob" class="fw-bold"></span> <br/> <span id="modal_mail"></span></td>
                    </tr>
                    <tr>
                        <th>Major Activity:</th>
                        <td class="fw-bold" id="modal_activity"></td>
                        <th>Social Category:</th>
                        <td class="fw-bold" id="modal_cat"></td>
                    </tr>
                    <tr>
                        <th>DIC / MSME DI:</th>
                        <td colspan="3" class="fw-bold text-uppercase"><span id="modal_dic"></span> / <span id="modal_msmedi"></span></td>
                    </tr>
                    <tr>
                        <th>Full Address:</th>
                        <td colspan="3" class="fw-bold text-uppercase" id="modal_addr"></td>
                    </tr>
                </table>

                <!-- Dynamic NIC Codes Table -->
                <div id="nic_section" style="display: none;">
                    <h6 class="fw-bold text-decoration-underline mb-2 text-dark">B. Registered NIC Activities</h6>
                    <table class="table table-sm table-bordered mb-4 fs-10 align-middle border-dark text-dark">
                        <thead class="bg-light">
                            <tr>
                                <th>Activity Type</th>
                                <th>NIC 2 Digit</th>
                                <th>NIC 4 Digit</th>
                                <th>NIC 5 Digit</th>
                            </tr>
                        </thead>
                        <tbody id="nic_body">
                            <!-- Filled dynamically via JS -->
                        </tbody>
                    </table>
                </div>

                <!-- Dynamic Units Table -->
                <div id="units_section" style="display: none;">
                    <h6 class="fw-bold text-decoration-underline mb-2 text-dark">C. Plant / Enterprise Units</h6>
                    <table class="table table-sm table-bordered mb-4 fs-10 align-middle border-dark text-dark">
                        <thead class="bg-light">
                            <tr>
                                <th width="30%">Unit Name</th>
                                <th>Complete Address</th>
                            </tr>
                        </thead>
                        <tbody id="units_body">
                            <!-- Filled dynamically via JS -->
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4 pt-2 border-top border-dark text-center">
                    <p class="mb-0 fs-11 fw-bold text-dark">Data generated from Central Government Registry on <span id="modal_date"></span></p>
                </div>
            </div>
        </div>
      </div>
      
      <div class="modal-footer bg-light py-2 border-top-0 d-flex justify-content-between no-print">
        <button type="button" class="btn btn-success btn-sm fw-bold shadow-none" onclick="printSingleData()"><i class="fas fa-print me-1"></i> Print Complete Report</button>
        <button type="button" class="btn btn-secondary btn-sm fw-bold shadow-none" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {$('.view-btn').on('click', function() {
        let rowData = $(this).data('info');                  // 1. Basic SQL Row mapping$('#modal_cname').text(rowData.company_name);
        $('#modal_udyam').text(rowData.udyam_no);
        $('#modal_etype').text(rowData.enterprise_type);
        $('#modal_otype').text(rowData.org_type);
        $('#modal_mob').text(rowData.mobile);
        $('#modal_mail').text(rowData.email);
        $('#modal_inc').text(rowData.inc_date);
        $('#modal_date').text(rowData.created_at);

        // 2. Parse Full Detailed JSON from API
        if(rowData.full_response && rowData.full_response !== '') {
            let apiData = JSON.parse(rowData.full_response);
            
            if(apiData.enterprise_data) {
                $('#modal_activity').text(apiData.enterprise_data.major_activity || 'N/A');
                $('#modal_cat').text(apiData.enterprise_data.social_category || 'N/A');
                $('#modal_dic').text(apiData.enterprise_data.dic || 'N/A');
                $('#modal_msmedi').text(apiData.enterprise_data.msme_di || 'N/A');
                $('#modal_addr').text(apiData.enterprise_data.address || 'N/A');
            }
            
            // Render Dynamic NIC Data
            if(apiData.nic_data && apiData.nic_data.length > 0) {
                let nicHtml = '';
                apiData.nic_data.forEach(function(item) {
                    nicHtml += `<tr>
                                    <td><b>${item.activity_type || ''}</b></td>
                                    <td>${item.nic_2_digit || ''}</td>
                                    <td>${item.nic_4_digit || ''}</td>
                                    <td><b>${item.nic_5_digit || ''}</b></td>
                                </tr>`;
                });
                $('#nic_body').html(nicHtml);
                $('#nic_section').show();
            } else {
                $('#nic_section').hide();
            }
            
            // Render Dynamic Units Data
            if(apiData.enterprise_units && apiData.enterprise_units.length > 0) {
                let unitHtml = '';
                apiData.enterprise_units.forEach(function(unit) {
                    unitHtml += `<tr>
                                    <td><b>${unit.name || ''}</b></td>
                                    <td>${unit.address || ''}</td>
                                </tr>`;
                });
                $('#units_body').html(unitHtml);
                $('#units_section').show();
            } else {
                $('#units_section').hide();
            }
        }
        
        var myModal = new bootstrap.Modal(document.getElementById('infoModal'));
        myModal.show();
    });
});

// SINGLE PRINT
function printSingleData() {
    window.print();
}
</script>

<?php include('footer.php'); ?>