<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

$user_phone = $udata['phone'];
$user_role = $udata['role']; 

$query = ($user_role == 'Admin') ? "SELECT * FROM `gst_history` ORDER BY id DESC" : "SELECT * FROM `gst_history` WHERE `username` = '$user_phone' ORDER BY id DESC";
$result = mysqli_query($DarkXen_conn, $query);
?>

<div class="card mb-3 shadow-none border">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0">GST Search History</h5>
        <a href="gst_info.php" class="btn btn-sm btn-primary">New Search</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped fs-10 mb-0">
                <thead class="bg-200">
                    <tr>
                        <th class="py-3">App No</th>
                        <th class="py-3">GSTIN</th>
                        <th class="py-3">Legal Name</th>
                        <th class="py-3">Trade Name</th>
                        <th class="py-3">Status</th>
                        <th class="py-3 text-center">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($result) > 0): while($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td class="py-3 text-primary fw-semi-bold"><?= $row['application_no'] ?></td>
                        <td class="py-3 fw-bold"><?= $row['gstin'] ?></td>
                        <td class="py-3"><?= $row['legal_name'] ?></td>
                        <td class="py-3"><?= $row['trade_name'] ?></td>
                        <td class="py-3">
                            <span class="badge badge-subtle-<?= $row['gst_status'] == 'Active' ? 'success' : 'danger' ?>">
                                <?= $row['gst_status'] ?>
                            </span>
                        </td>
                        <td class="py-3 text-center"><?= date('d-m-Y', strtotime($row['date'])) ?></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="6" class="text-center py-4 text-danger fw-bold">No records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>