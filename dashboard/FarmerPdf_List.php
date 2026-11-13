<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

$appliedby = $udata['phone'];

// Fetch user history from database
$history_query = mysqli_query($DarkXen_conn, "SELECT * FROM `farmer_pdf_history` WHERE `userid` = '$appliedby' ORDER BY `id` DESC");
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-list text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Agri History Logs</h6>
                        <h4 class="text-primary fw-bold mb-0">Farmer Card <span class="text-success fw-medium">History Logs</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="FarmerPdf.php" class="btn btn-sm btn-outline-success shadow-none fw-bold"><i class="fas fa-plus me-1"></i> Generate New Card</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0 text-dark fw-bold"><i class="fas fa-history me-2 text-success"></i>Generated Cards Records</h5>
            </div>

    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-striped table-hover align-middle mb-0 fs-11">
                <thead class="bg-200 text-900 font-weight-600">
                            <thead class="table-light">
                                <th class="p-3">S.No.</th>
                                <th class="p-3">Application No</th>
                                <th class="p-3">Farmer Name</th>
                                <th class="p-3">Aadhaar Number</th>
                                <th class="p-3">State Name</th>
                                <th class="p-3">Charge</th>
                                <th class="p-3">Date & Time</th>
                                <th class="p-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (mysqli_num_rows($history_query) > 0) {
                                $sn = 1;
                                while ($row = mysqli_fetch_assoc($history_query)) {
                                    // Base64 string ko clean tarike se variable me store kar rahe hain
                                    $pdf_data = $row['pdf_url'];
                                    
                                    echo "<tr>
                                        <td class='p-3 fw-bold'>{$sn}</td>
                                        <td class='p-3 text-primary'><strong>".htmlspecialchars($row['application_no'])."</strong></td>
                                        <td class='p-3 fw-semibold'>".htmlspecialchars($row['farmer_name'])."</td>
                                        <td class='p-3'><code>".htmlspecialchars($row['aadhaar_number'])."</code></td>
                                        <td class='p-3'>".htmlspecialchars($row['state'])."</td>
                                        <td class='p-3 text-success fw-bold'>₹".number_format($row['charge'], 2)."</td>
                                        <td class='p-3 text-secondary'>".date('d M Y, h:i A', strtotime($row['created_at']))."</td>
                                        <td class='p-3'>
                                            <button type='button' class='btn btn-sm btn-success shadow-none fw-bold view-pdf-btn' data-pdf='".htmlspecialchars($pdf_data, ENT_QUOTES, 'UTF-8')."'>
                                                <i class='fas fa-eye me-1'></i> View PDF
                                            </button>
                                        </td>
                                    </tr>";
                                    $sn++;
                                }
                            } else {
                                echo "<tr>
                                    <td colspan='8' class='text-center p-5 text-secondary'>
                                        <i class='fas fa-folder-open fs-3 mb-2 d-block text-gray-300'></i>
                                        No farmer cards have been generated yet.
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

<!-- SweetAlert2 Library for nice warnings if popup is blocked -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sabhi buttons par click event listener set karein
    var buttons = document.querySelectorAll('.view-pdf-btn');
    
    buttons.forEach(function(button) {
        button.addEventListener('click', function() {
            var base64Data = this.getAttribute('data-pdf');
            if (base64Data) {
                viewPdf(base64Data);
            } else {
                Swal.fire('Error', 'PDF data not found for this record.', 'error');
            }
        });
    });
});

function viewPdf(base64Data) {
    try {
        // 1. Check karein ki string me prefix hai ya nahi
        var base64WithoutHeader = base64Data;
        if (base64Data.includes(';base64,')) {
            base64WithoutHeader = base64Data.split(';base64,')[1];
        }

        // 2. Base64 ko binary string me decode karein
        var byteCharacters = atob(base64WithoutHeader.trim());
        var byteNumbers = new Array(byteCharacters.length);
        
        for (var i = 0; i < byteCharacters.length; i++) {
            byteNumbers[i] = byteCharacters.charCodeAt(i);
        }
        
        var byteArray = new Uint8Array(byteNumbers);
        
        // 3. Blob data create karein
        var blob = new Blob([byteArray], { type: 'application/pdf' });

        // 4. Temporary Browser URL banayein
        var blobUrl = URL.createObjectURL(blob);

        // 5. New tab me secure tarike se display karein
        var pdfWindow = window.open();
        if (pdfWindow) {
            pdfWindow.document.write(
                '<html><head><title>View Farmer Card PDF</title><style>body,html{margin:0;padding:0;height:100%;overflow:hidden;}iframe{width:100%;height:100%;border:none;}</style></head>' +
                '<body><iframe src="' + blobUrl + '"></iframe></body></html>'
            );
            pdfWindow.document.close();
        } else {
            Swal.fire('Popup Blocked', 'Kripya apne browser mein popups allow karein taaki PDF dikh sake.', 'warning');
        }
    } catch (error) {
        console.error("PDF generation failed:", error);
        Swal.fire('Oops!', 'PDF render karne mein koi issue aaya hai. Kripya check karein.', 'error');
    }
}
</script>

<?php include('footer.php'); ?>