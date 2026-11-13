<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

// लॉगिन यूज़र का फ़ोन नंबर या यूज़र आईडी (जो डेटाबेस में स्टोर हो रहा है)
$current_userid = mysqli_real_escape_string($DarkXen_conn, $udata['phone']);
// रिकॉर्ड डिलीट करने का लॉजिक
if (isset($_GET['delete_id'])) {
    $delete_id = mysqli_real_escape_string($DarkXen_conn, $_GET['delete_id']);
    
    // सुरक्षा के लिए सुनिश्चित करें कि यूज़र केवल अपना ही रिकॉर्ड डिलीट कर सके
    $delete_query = mysqli_query($DarkXen_conn, "DELETE FROM aadharmanual WHERE id = '$delete_id' AND userid = '$current_userid'");
    
    if ($delete_query) {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({ icon: 'success', title: 'Deleted!', text: 'Record deleted successfully.', showConfirmButton: false, timer: 1500 });
            setTimeout(function(){ window.location='aadharmanual_list.php'; }, 1500);
        });
        </script>
        <?php
    } else {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to delete record.' });
        });
        </script>
        <?php
    }
}

// डेटाबेस से केवल इस यूज़र के रिकॉर्ड फ़ेच करें
mysqli_set_charset($DarkXen_conn, "utf8");
$query_string = "SELECT * FROM aadharmanual WHERE userid = '$current_userid' ORDER BY id DESC";
$records_result = mysqli_query($DarkXen_conn, $query_string);
$total_records = mysqli_num_rows($records_result);
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center">
                <div class="col-sm-auto d-flex align-items-center">
                    <img class="ms-n2" src="../assets/img/illustrations/crm-bar-chart.png" alt="" width="90" />
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Welcome back, <?php echo htmlspecialchars($udata['name']); ?></h6>
                        <h4 class="text-primary fw-bold mb-0"><?php echo web('webname'); ?> <span class="text-info fw-medium">Aadhaar History</span></h4>
                    </div>
                </div>
                <div class="col-md-auto p-3">
                    <div class="d-flex gap-2">
                        <a href="aadhaar_manual.php" class="btn btn-sm btn-primary shadow-none fw-semi-bold">
                            <i class="fas fa-plus-circle me-1"></i> New Application
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-1000 fw-bold"><i class="fas fa-list-alt me-2 text-primary"></i>Aadhaar Manual Records</h5>
        <span class="badge bg-info">
            My Records Only - <?= htmlspecialchars($udata['role'] ?? 'RETAILER') ?> (Total: <?= $total_records ?>)
        </span>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped table-hover fs-10 mb-0 align-middle">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="ps-3 text-center" style="width: 5%;">#</th>
                        <th>Name</th>
                        <th class="text-center">Aadhaar Number</th>
                        <th class="text-center">Date & Time</th>
                        <th class="text-end pe-3" style="width: 20%;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($total_records > 0) {
                        $count = 1;
                        while ($row = mysqli_fetch_assoc($records_result)) {
                            // आधार नंबर को सुरक्षा के लिए मास्क करें (सिर्फ आखिरी 4 अंक दिखेंगे)
                            $raw_aadhaar = str_replace(' ', '', $row['originalaadharno'] ?? $row['aadharno']);
                            $masked_aadhaar = "XXXX XXXX " . substr($raw_aadhaar, -4);
                            
                            $formatted_date = !empty($row['createdatetime']) ? date('d-m-Y h:i A', strtotime($row['createdatetime'])) : '-';
                    ?>
                            <tr>
                                <td class="ps-3 text-center fw-semi-bold"><?= $count++ ?></td>
                                <td class="fw-bold text-900">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-xl me-2 rounded-circle bg-soft-primary text-primary d-flex align-items-center justify-content-center">
                                            <span class="fw-bold"><?= strtoupper(substr($row['aadharname'], 0, 1)) ?></span>
                                        </div>
                                        <div>
                                            <span class="d-block"><?= htmlspecialchars($row['aadharname']) ?></span>
                                            <small class="text-muted fs-11">S/O: <?= htmlspecialchars($row['fathername']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center fw-semi-bold text-danger"><?= $masked_aadhaar ?></td>
                                <td class="text-center text-600"><?= $formatted_date ?></td>
                                <td class="text-end pe-3">
                                    <div class="btn-group">
                                        <!-- प्रिंट पीडीएफ बटन -->

                                        <a href="dacoment/aadharmanual.php?searchid=<?php echo rtrim(base64_encode($row['id']), '='); ?>9EI%3D" target="_blank" class="btn btn-sm btn-falcon-success me-1" title="Print PDF">
                                            <i class="fas fa-print me-1"></i> Print
                                        </a>
                                        <!-- डिलीट बटन स्विटअलर्ट कन्फर्मेशन के साथ -->
                                        <button onclick="confirmDelete(<?= $row['id'] ?>)" class="btn btn-sm btn-falcon-danger" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                    <?php
                        }
                    } else {
                    ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-500 fs-11 fst-italic">
                                <img src="../assets/img/illustrations/empty.png" alt="No Data" width="80" class="mb-2 opacity-50"><br>
                                No Aadhaar manual records found.<br>
                                <small>Showing only records created by you (ID: <?= htmlspecialchars($current_userid) ?>)</small>
                            </td>
                        </tr>
                    <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .btn-falcon-success {
        color: #00864e;
        background-color: #e2f6ed;
        border: 1px solid #a3e1c4;
    }
    .btn-falcon-success:hover {
        color: #fff;
        background-color: #00864e;
    }
    .btn-falcon-danger {
        color: #ca2026;
        background-color: #fde8e9;
        border: 1px solid #f9bec1;
    }
    .btn-falcon-danger:hover {
        color: #fff;
        background-color: #ca2026;
    }
    .avatar-xl {
        width: 2.2rem;
        height: 2.2rem;
        font-size: 0.9rem;
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function confirmDelete(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this record!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }). presidential (function (result) {
        if (result.isConfirmed) {
            window.location.href = 'aadharmanual_list.php?delete_id=' + id;
        }
    });
}
</script>

<?php include('footer.php'); ?>