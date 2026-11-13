<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php'); 

if(checkAdmin($udata['type']) == false){
    ?>
    <script>
        window.location='index.php';
    </script>
    <?php
    die();
}

date_default_timezone_set('Asia/Kolkata');

// यह सुनिश्चित करने के लिए कि केवल एडमिन ही इस पेज को खोल सके (यदि आपके पास ऐसा कोई वेरिएबल है)
// if($udata['role'] != 'admin') { echo "Access Denied!"; exit; }

$error_message = '';
$success_message = '';

// स्टेटस और रिमार्क अपडेट हैंडलर
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_complaint'])) {
    $ticket_id = mysqli_real_escape_string($DarkXen_conn, $_POST['ticket_id']);
    $status = mysqli_real_escape_string($DarkXen_conn, $_POST['status']);
    $remark = mysqli_real_escape_string($DarkXen_conn, $_POST['remark']);

    if (empty($ticket_id) || empty($status)) {
        $error_message = "Invalid Data Provided.";
    } else {
        // डेटाबेस में स्टेटस और एडमिन रिमार्क अपडेट करना
        $update_query = "UPDATE complaints SET status = '$status', remark = '$remark' WHERE id = '$ticket_id'";
        $update = mysqli_query($DarkXen_conn, $update_query);

        if ($update) {
            $success_message = "Ticket #$ticket_id Updated Successfully!";
        } else {
            $error_message = "Failed to update ticket: " . mysqli_error($DarkXen_conn);
        }
    }
}
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="avatar avatar-xl me-3 bg-200 rounded-circle text-center d-flex align-items-center justify-content-center">
                        <span class="fas fa-headset text-primary fs-2"></span>
                    </div>
                    <div>
                        <h4 class="text-primary fw-bold mb-0">Support Tickets Management</h4>
                        <p class="mb-0 fs-11 text-muted">Review, reply, and resolve user complaints in real-time.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-none border border-top border-4 border-primary mb-4">
    <div class="card-header bg-light py-3">
        <h5 class="mb-0 fw-bold text-900"><i class="fas fa-list me-2"></i>All User Complaints</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped fs-11 mb-0 overflow-hidden">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="white-space-nowrap py-3">Tkt ID</th>
                        <th>User Info</th>
                        <th>Category</th>
                        <th>User Message</th>
                        <th>UTR / Trans No.</th>
                        <th>Admin Remark</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // सभी शिकायतें रिवर्स ऑर्डर (नए पहले) में फ़ैच करें
                    $query = "SELECT * FROM complaints ORDER BY id DESC";
                    $result = mysqli_query($DarkXen_conn, $query);

                    if ($result && mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $t_id = $row['id'];
                            $t_uid = htmlspecialchars($row['user_id']);
                            $t_uphone = htmlspecialchars($row['username']); // आपकी टेबल स्ट्रक्चर में यहाँ फोन नंबर है
                            $t_type = htmlspecialchars($row['complaint_type']);
                            $t_msg = htmlspecialchars($row['message']);
                            $t_remark = htmlspecialchars($row['remark'] ?? '');
                            $t_status = strtoupper($row['status'] ?? 'OPEN');
                            $t_utr = htmlspecialchars($row['utrnumber'] ?? 'N/A');
                            $t_date = date('d M Y, h:i A', strtotime($row['date_time']));

                            // स्टेटस कलर स्कीम
                            $status_class = 'badge-subtle-warning text-warning';
                            if (in_array($t_status, ['SUCCESS', 'CLOSED', 'RESOLVED'])) {
                                $status_class = 'badge-subtle-success text-success';
                            } elseif (in_array($t_status, ['FAILED', 'REJECTED'])) {
                                $status_class = 'badge-subtle-danger text-danger';
                            }
                            ?>
                            <tr class="align-middle">
                                <td class="py-3 fw-bold text-primary">#<?= $t_id; ?></td>
                                <td>
                                    <div class="fw-bold text-900">ID: <?= $t_uid; ?></div>
                                    <div class="text-muted fs-12"><i class="fas fa-phone-alt me-1"></i><?= $t_uphone; ?></div>
                                    <div class="text-500 fs-12"><i class="far fa-clock me-1"></i><?= $t_date; ?></div>
                                </td>
                                <td><span class="badge badge-subtle-primary fs-12"><?= $t_type; ?></span></td>
                                <td style="max-width: 250px; white-space: normal;">
                                    <p class="mb-0 text-800 fs-11"><?= $t_msg; ?></p>
                                </td>
                                <td><span class="fw-bold text-danger"><?= $t_utr; ?></span></td>
                                <td style="max-width: 200px; white-space: normal;">
                                    <?php if(!empty($t_remark)): ?>
                                        <span class="text-success fw-medium"><i class="fas fa-reply me-1"></i><?= $t_remark; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted italic fs-12">No reply yet</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $status_class; ?> rounded-pill">
                                        <?= $t_status == 'OPEN' ? '<i class="fas fa-spinner fa-spin me-1"></i>Open' : $t_status; ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <!-- एक्शन बटन: जो मॉडलो/पॉपअप को ट्रिगर करेगा -->
                                    <button class="btn btn-sm btn-outline-primary px-3 shadow-none" 
                                            type="button" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#actionModal<?= $t_id; ?>">
                                        <i class="fas fa-edit me-1"></i>Solve
                                    </button>
                                </td>
                            </tr>

                            <!-- प्रत्येक टिकट के लिए कस्टमाइज्ड रिप्लाई पॉपअप (Modal) -->
                            <div class="modal fade" id="actionModal<?= $t_id; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border">
                                        <div class="modal-header bg-light">
                                            <h5 class="modal-title fw-bold text-primary">Update Ticket #<?= $t_id; ?></h5>
                                            <button class="btn-close shadow-none" type="button" data-bs-dynamic="modal" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="POST" autocomplete="off">
                                            <div class="modal-body p-4">
                                                <input type="hidden" name="ticket_id" value="<?= $t_id; ?>">
                                                
                                                <div class="mb-3 bg-light p-3 rounded fs-12 border">
                                                    <strong>User Query:</strong> <p class="text-800 mb-0 mt-1"><?= $t_msg; ?></p>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-bold text-900">Update Status <span class="text-danger">*</span></label>
                                                    <select class="form-select shadow-none" name="status" required>
                                                        <option value="Open" <?= $t_status == 'OPEN' ? 'selected' : ''; ?>>Open / Pending</option>
                                                        <option value="Resolved" <?= in_array($t_status, ['RESOLVED', 'SUCCESS', 'CLOSED']) ? 'selected' : ''; ?>>Resolved / Success</option>
                                                        <option value="Rejected" <?= in_array($t_status, ['REJECTED', 'FAILED']) ? 'selected' : ''; ?>>Rejected / Failed</option>
                                                    </select>
                                                </div>

                                                <div class="mb-0">
                                                    <label class="form-label fw-bold text-900">Admin Remark / Reply</label>
                                                    <textarea class="form-control shadow-none" name="remark" rows="4" placeholder="Type your response here (e.g., 'Wallet updated', 'Invalid UTR')..."><?= $t_remark; ?></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light">
                                                <button class="btn btn-secondary shadow-none px-4" type="button" data-bs-dismiss="modal">Close</button>
                                                <button class="btn btn-primary shadow-none px-4" type="submit" name="update_complaint">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <?php
                        }
                    } else {
                        echo '<tr><td colspan="8" class="text-center py-5 text-danger fw-bold fs-10 bg-white"><i class="fas fa-folder-open fs-2 d-block mb-2 text-400"></i>No Active Complaints Found!</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- SweetAlert2 नोटिफिकेशन्स -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    <?php if(!empty($error_message)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Action Failed',
            text: '<?= addslashes($error_message); ?>',
            confirmButtonText: 'OK'
        });
    <?php endif; ?>

    <?php if(!empty($success_message)): ?>
        Swal.fire({
            icon: 'success',
            title: '<?= addslashes($success_message); ?>',
            showConfirmButton: false,
            timer: 1800
        }).then(() => { 
            window.location.href = window.location.pathname; // रीफ्रेश होकर डेटा सिंक करेगा
        });
    <?php endif; ?>
</script>

<style>
    .form-select, .form-control { padding: 0.6rem 1rem; border-radius: 0.4rem; }
    .card { border-radius: 0.75rem; overflow: hidden; }
    .italic { font-style: italic; }
</style>

<?php include('footer.php'); ?>