<?php
include('header.php'); 

// --- WALLET HISTORY LOGIC ---
$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';

$conditions = [];

// Date Filter Condition
if (!empty($from_date) && !empty($to_date)) {
    $from_safe = mysqli_real_escape_string($DarkXen_conn, $from_date);
    $to_safe   = mysqli_real_escape_string($DarkXen_conn, $to_date);
    $conditions[] = "DATE(date) BETWEEN '$from_safe' AND '$to_safe'";
}

// User Filter (Admins see all users, regular users see only their own)
if (isset($udata['type']) && $udata['type'] != 'admin') {
    $user_phone = mysqli_real_escape_string($DarkXen_conn, $udata['phone']);
    $conditions[] = "userid='$user_phone'";
}

// Build Query
$query = "SELECT * FROM wallethistory";
if (count($conditions) > 0) {
    $query .= " WHERE " . implode(' AND ', $conditions);
}
$query .= " ORDER BY id DESC";

$res = mysqli_query($DarkXen_conn, $query);

$has_data = ($res && mysqli_num_rows($res) > 0);
$rows = [];
if ($has_data) {
    while ($r = mysqli_fetch_assoc($res)) { 
        $rows[] = $r; 
    }
}
?>

<!-- HEADER SECTION -->
<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-info-subtle shadow-none me-3">
                        <span class="fas fa-wallet text-info fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-11 mb-0">Welcome back, <?= htmlspecialchars($udata['name'] ?? 'User'); ?></h6>
                        <h4 class="text-primary fw-bold mb-0">Wallet History <span class="text-info fw-medium">All Records</span></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DATE FILTER SECTION -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius: 12px;">
            <div class="card-body p-3">
                <form method="GET" action="wallet_history.php" class="row g-2 align-items-center">
                    <div class="col-md-3 col-sm-6">
                        <label class="form-label fs-11 fw-bold text-muted mb-1">From Date</label>
                        <input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($from_date) ?>" required>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <label class="form-label fs-11 fw-bold text-muted mb-1">To Date</label>
                        <input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($to_date) ?>" required>
                    </div>
                    <div class="col-md-6 col-sm-12 d-flex align-items-end mt-sm-3 mt-md-4">
                        <button type="submit" class="btn btn-sm btn-primary me-2">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        <a href="wallet_history.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-sync-alt me-1"></i> All Time History
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- TRANSACTION HISTORY TABLE -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius: 20px; overflow: hidden;">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">
                    <i class="fas fa-history text-primary me-2"></i>
                    <?= (!empty($from_date) && !empty($to_date)) ? 'Filtered Transaction History' : 'All-Time Transaction History'; ?>
                </h6>
                <span class="badge bg-soft-primary text-primary px-3 py-2 rounded-pill">Total: <?= count($rows); ?> Records</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 border-0">No.</th>
                                <th class="border-0">Service Purpose</th>
                                <th class="border-0">Amount</th>
                                <th class="border-0">Prev. Balance</th>
                                <th class="border-0">Action</th>
                                <th class="pe-4 text-end border-0">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($has_data): 
                                $x = 0;
                                foreach ($rows as $data):
                                    $x++;
                                    $isDebit = strtoupper($data['type'] ?? '') === 'DEBIT';
                            ?>
                                    <tr>
                                        <td class="ps-4 text-muted fw-bold"><?= $x; ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($data['purpose'] ?? 'N/A'); ?></div>
                                            <div class="small text-muted fs-11">Trans ID: #WLT-<?= $data['id']; ?></div>
                                        </td>
                                        <td class="fw-bold <?= $isDebit ? 'text-danger' : 'text-success' ?>">
                                            <?= $isDebit ? '<i class="fas fa-arrow-down me-1"></i>-' : '<i class="fas fa-arrow-up me-1"></i>+' ?> ₹<?= number_format((float)($data['amount'] ?? 0), 2); ?>
                                        </td>
                                        <td class="text-muted fw-medium">₹<?= number_format((float)($data['balance'] ?? 0), 2); ?></td>
                                        <td>
                                            <span class="badge <?= $isDebit ? 'bg-soft-danger text-danger' : 'bg-soft-success text-success' ?> px-2 rounded-pill">
                                                <?= htmlspecialchars($data['type'] ?? ''); ?>
                                            </span>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <div class="fw-bold small"><?= date('h:i A', strtotime($data['date'])) ?></div>
                                            <div class="text-muted fs-11"><?= date('d-m-Y', strtotime($data['date'])) ?></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="text-muted fs-4 mb-2"><i class="fas fa-receipt opacity-50"></i></div>
                                        <span class="text-muted">No transactions found.</span>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- PAGE CUSTOM STYLES -->
<style>
    .bg-soft-primary { background: #eef2ff; color: #4361ee; }
    .bg-soft-success { background: #ecfdf5; color: #10b981; }
    .bg-soft-danger  { background: #fef2f2; color: #ef4444; }
    .fs-11 { font-size: 11px; }
</style>

<?php include('footer.php'); ?>