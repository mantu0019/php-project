<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php'); // आपके प्रोजेक्ट स्ट्रक्चर के अनुसार इसे 'header.php' या 'header.php' रखें

date_default_timezone_set('Asia/Kolkata');

// यूज़र का सेशन डेटा सुरक्षित वेरिएबल्स में लें
$user_id = $udata['id'];
$username = $udata['name'];
$user_phone = $udata['phone'];

$error_message = '';
$success_message = '';

// फ़ॉर्म सबमिशन हैंडलर (HTML इनपुट नामों के साथ पूरी तरह फिक्स)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_ticket'])) {
    
    // नाम को HTML फॉर्म के 'subject' और 'message' के साथ मैप किया गया
    $complaint_type = mysqli_real_escape_string($DarkXen_conn, $_POST['subject'] ?? '');
    $message = mysqli_real_escape_string($DarkXen_conn, $_POST['message'] ?? '');
    $utrnumber = isset($_POST['utrnumber']) && trim($_POST['utrnumber']) !== '' ? mysqli_real_escape_string($DarkXen_conn, $_POST['utrnumber']) : null;

    if (empty($complaint_type) || empty(trim($message))) {
        $error_message = "Please select a category and enter your message.";
    } else {
        $site_id = 1;
        $remark = '';
        $status = 'Open';
        $date_time = date('Y-m-d H:i:s');

        // सही कनेक्शन ऑब्जेक्ट ($DarkXen_conn) और डेटाबेस सिंक के साथ इंसर्ट क्वेरी
        $insert_query = "INSERT INTO complaints (site_id, user_id, username, utrnumber, complaint_type, message, remark, status, date_time) 
                         VALUES ('$site_id', '$user_id', '$user_phone', " . ($utrnumber ? "'$utrnumber'" : "NULL") . ", '$complaint_type', '$message', '$remark', '$status', '$date_time')";
        
        $insert = mysqli_query($DarkXen_conn, $insert_query);

        if ($insert) {
            $success_message = "Complaint Submitted Successfully!";
        } else {
            $error_message = "Submission Failed: " . mysqli_error($DarkXen_conn);
        }
    }
}
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center">
                <div class="col-sm-auto d-flex align-items-center">
                    <img class="ms-n2" src="../assets/img/illustrations/crm-bar-chart.png" alt="" width="90" />
                    <div>
                        <h6 class="text-primary fs-11 mb-0">Welcome back, <?= htmlspecialchars($username); ?></h6>
                        <h4 class="text-primary fw-bold mb-0">Helpdesk & <span class="text-info fw-medium">Support Tickets</span></h4>
                    </div>
                    <img class="ms-n4 d-md-none d-lg-block" src="../assets/img/illustrations/crm-line-chart.png" alt="" width="150" />
                </div>
                <div class="col-md-auto p-3">
                    <div class="row align-items-center g-3">
                        <div class="col-auto"><h6 class="text-700 mb-0">Today: </h6></div>
                        <div class="col-md-auto">
                            <div class="form-control form-control-sm d-flex align-items-center bg-white border-200" style="min-width: 140px;">
                                <span class="fas fa-calendar-day text-primary me-2"></span>
                                <span class="fw-bold text-primary"><?= date('d M, Y'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card h-100 shadow-none border border-top border-4 border-primary">
            <div class="card-header bg-light border-bottom py-3">
                <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-plus-circle me-2"></i>Raise New Ticket</h5>
                <p class="mb-0 fs-11 text-muted">Describe your issue and our technical team will help you soon.</p>
            </div>
            <div class="card-body bg-white p-4">
                <form method="POST" class="row g-3" autocomplete="off">
                    <div class="col-12">
                        <label class="form-label fw-bold text-900">Category / Issue Type <span class="text-danger">*</span></label>
                        <select class="form-select shadow-none border-200" name="subject" required>
                            <option value="">Choose Category...</option>
                            <option value="Wallet Balance">Wallet Balance Issue</option>
                            <option value="API Integration">API Integration Support</option>
                            <option value="Service Down">Service / Portal Down</option>
                            <option value="Report Error">Incorrect Document Report</option>
                            <option value="General Query">General Inquiry</option>
                        </select>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label fw-bold text-900">UTR / Transaction Number <span class="text-muted">(Optional)</span></label>
                        <input type="text" class="form-control shadow-none border-200" name="utrnumber" placeholder="Enter UTR if related to payment issue">
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label fw-bold text-900">Detailed Message <span class="text-danger">*</span></label>
                        <textarea class="form-control shadow-none border-200" name="message" rows="4" placeholder="Briefly explain your problem with ID or vehicle numbers..." required></textarea>
                    </div>
                    
                    <div class="col-12 mt-4">
                        <button class="btn btn-primary w-100 py-2 fw-bold shadow-none" name="submit_ticket" type="submit">
                            <span class="fas fa-paper-plane me-2"></span>Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100 shadow-none border border-top border-4 border-info">
            <div class="card-header bg-light border-bottom py-3">
                <h5 class="mb-0 fw-bold text-info"><i class="fas fa-history me-2"></i>Support History Logs</h5>
            </div>
            <div class="card-body scrollbar bg-body-tertiary p-3" style="max-height: 480px; overflow-y: auto;">
                
                <?php
                // यूज़र आईडी के आधार पर रियल-टाइम फ़ैचिंग
                $history_query = "SELECT * FROM complaints WHERE user_id = '$user_id' ORDER BY id DESC";
                $history_result = mysqli_query($DarkXen_conn, $history_query);

                if (!$history_result) {
                    // यदि डेटाबेस में कोई स्ट्रक्चर त्रुटि होगी, तो यह एरर शो करेगा
                    echo '<div class="alert alert-danger fs-11 m-2"><strong>Database Error:</strong> ' . mysqli_error($DarkXen_conn) . '</div>';
                } elseif (mysqli_num_rows($history_result) > 0) {
                    while ($row = mysqli_fetch_assoc($history_result)) {
                        $c_type = htmlspecialchars($row['complaint_type'] ?? 'General Query');
                        $c_msg = htmlspecialchars($row['message'] ?? '');
                        $c_remark = htmlspecialchars($row['remark'] ?? '');
                        $c_status = strtoupper($row['status'] ?? 'OPEN');
                        $c_utr = htmlspecialchars($row['utrnumber'] ?? '');
                        
                        // डेट-टाइम फ़ॉर्मेटिंग सुरक्षा के साथ
                        $raw_date = $row['date_time'];
                        $c_date = (!empty($raw_date)) ? date('d M Y, h:i A', strtotime($raw_date)) : date('d M Y, h:i A');
                        
                        // स्टेटस के आधार पर बैज कलर्स
                        $status_badge = 'badge-subtle-warning text-warning';
                        if ($c_status === 'SUCCESS' || $c_status === 'CLOSED' || $c_status === 'RESOLVED') {
                            $status_badge = 'badge-subtle-success text-success';
                        } elseif ($c_status === 'FAILED' || $c_status === 'REJECTED') {
                            $status_badge = 'badge-subtle-danger text-danger';
                        }
                        ?>
                        
                        <div class="mb-3 p-3 border rounded bg-white shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge badge-subtle-primary fs-11"><?= $c_type; ?></span>
                                <span class="fs-11 text-600"><i class="far fa-clock me-1"></i><?= $c_date; ?></span>
                            </div>
                            
                            <?php if (!empty($c_utr)): ?>
                                <div class="mb-2 bg-light-subtle p-1 rounded"><small class="text-700 fs-11"><strong>UTR No:</strong> <span class="text-danger fw-bold"><?= $c_utr; ?></span></small></div>
                            <?php endif; ?>

                            <div class="bg-light p-2 rounded mb-2">
                                <p class="fs-11 text-800 mb-0">
                                    <strong><i class="fas fa-user me-1 text-primary"></i>You:</strong> <?= $c_msg; ?>
                                </p>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                <div class="fs-11 text-800">
                                    <?php if (!empty($c_remark)): ?>
                                        <strong><i class="fas fa-reply me-1 text-success"></i>Admin:</strong> <span class="text-success fw-bold"><?= $c_remark; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted italic"><i class="fas fa-stream me-1"></i> No reply yet</span>
                                    <?php endif; ?>
                                </div>
                                <span class="badge <?= $status_badge; ?> rounded-pill">
                                    <?php if($c_status == 'OPEN'): ?>
                                        <i class="fas fa-spinner fa-spin me-1"></i>Awaiting Response
                                    <?php else: ?>
                                        <i class="fas fa-check-circle me-1"></i><?= $c_status; ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <?php
                    }
                } else {
                    echo '<div class="text-center py-5 text-danger fw-bold fs-10 bg-white border rounded m-2"><i class="fas fa-folder-open fs-3 d-block mb-2 text-400"></i>No Support History Found!</div>';
                }
                ?>
                
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    <?php if(!empty($error_message)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Submission Failed',
            text: '<?= addslashes($error_message); ?>',
            confirmButtonText: 'OK'
        });
    <?php endif; ?>

    <?php if(!empty($success_message)): ?>
        Swal.fire({
            icon: 'success',
            title: '<?= addslashes($success_message); ?>',
            showConfirmButton: false,
            timer: 2000
        }).then(() => { 
            window.location.href = window.location.pathname; // पेज को सुरक्षित रूप से रीफ्रेश कर डेटा लोड करेगा
        });
    <?php endif; ?>
</script>

<style>
    .form-select, .form-control { padding: 0.75rem 1rem; border-radius: 0.5rem; }
    .card { border-radius: 0.75rem; overflow: hidden; }
    .italic { font-style: italic; }
</style>

<?php include('footer.php'); ?>