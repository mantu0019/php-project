<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php'); // Dashboard layout header file

$user = $udata['phone'] ?? '';

// Pagination logic (Ek page par 10 records)
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search Filter Logic
$search_query = "";
$search_keyword = "";
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_keyword = mysqli_real_escape_string($DarkXen_conn, trim($_GET['search']));
    $search_query = " AND (`rc_number` LIKE '%$search_keyword%' OR `make` LIKE '%$search_keyword%' OR `model` LIKE '%$search_keyword%') ";
}

// Total records count (for pagination)
$count_query = mysqli_query($DarkXen_conn, "SELECT COUNT(*) as total FROM `rto_cheak` WHERE `username` = '$user' $search_query");
$total_rows = mysqli_fetch_assoc($count_query)['total'] ?? 0;
$total_pages = ceil($total_rows / $limit);

// Main Query to fetch data
$query = "SELECT * FROM `rto_cheak` WHERE `username` = '$user' $search_query ORDER BY `id` DESC LIMIT $offset, $limit";
$result = mysqli_query($DarkXen_conn, $query);
?>

<!-- ================= HTML FRONT-END SECTION ================= -->
<div class="container-fluid py-4">
    
    <!-- Title Banner Area -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-100 shadow-none border">
                <div class="row gx-0 flex-between-center p-3">
                    <div class="col-sm-auto d-flex align-items-center">
                        <div class="avatar avatar-xl me-3 bg-200 rounded-circle text-center d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                            <span class="fas fa-list-alt text-primary fs-2"></span>
                        </div>
                        <div>
                            <h4 class="text-primary fw-bold mb-0">My RTO Check History</h4>
                            <p class="mb-0 fs-11 text-muted font-sans-serif">Aapke dwara search kiye gaye sabhi vehicle registration/RC details ki history report.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Bar & Table Card -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none border">
                <div class="card-header bg-light border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3 py-3">
                    <h5 class="mb-0 fw-bold text-700"><i class="fas fa-history me-2"></i>History Log Records</h5>
                    
                    <!-- Search Form -->
                    <form method="GET" class="d-flex align-items-center gap-2" style="max-width: 300px; width: 100%;">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control form-control-sm shadow-none" placeholder="Search RC, Brand or Model..." value="<?= htmlspecialchars($search_keyword); ?>">
                            <button class="btn btn-primary btn-sm shadow-none" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if (!empty($search_keyword)): ?>
                                <a href="rto_list.php" class="btn btn-secondary btn-sm shadow-none" title="Clear Filter">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                

<div class="card shadow-sm border mb-4">
    <div class="card-header bg-light border-bottom py-3">
        <h5 class="mb-0 text-900 fw-bold"><i class="fas fa-history me-2 text-warning"></i> Your Generated Documents</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped fs-10 mb-0 text-center table-hover">
                <thead class="bg-200 text-900">
                    <tr>
                                    <th class="ps-3" style="width: 5%;">#</th>
                                    <th>RC Number</th>
                                    <th>Brand / Make</th>
                                    <th>Model & Variant</th>
                                    <th>Fuel</th>
                                    <th>RTO Registered</th>
                                    <th>Reg Date</th>
                                    <th>Charge</th>
                                    <th>Status</th>
                                    <th class="pe-3">Search Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($result) > 0): ?>
                                    <?php 
                                    $serial = $offset + 1;
                                    while ($row = mysqli_fetch_assoc($result)): 
                                    ?>
                                        <tr>
                                            <td class="ps-3 text-muted"><?= $serial++; ?></td>
                                            <td class="fw-bold text-primary text-monospace text-uppercase"><?= htmlspecialchars($row['rc_number']); ?></td>
                                            <td class="fw-bold text-uppercase"><?= htmlspecialchars($row['make'] ?: 'N/A'); ?></td>
                                            <td>
                                                <div class="fw-semibold text-800 text-uppercase"><?= htmlspecialchars($row['model'] ?: 'N/A'); ?></div>
                                                <div class="small text-muted font-monospace text-uppercase" style="font-size: 11px;"><?= htmlspecialchars($row['variant'] ?: 'N/A'); ?></div>
                                            </td>
                                            <td>
                                                <?php if (!empty($row['fuel_type'])): ?>
                                                    <span class="badge bg-info-subtle text-info border border-info px-2 py-1 fs-11 text-uppercase"><?= htmlspecialchars($row['fuel_type']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-muted text-uppercase text-truncate" style="max-width: 180px;" title="<?= htmlspecialchars($row['rto']); ?>">
                                                <?= htmlspecialchars($row['rto'] ?: 'N/A'); ?>
                                            </td>
                                            <td class="text-monospace small text-dark"><?= htmlspecialchars($row['registration_date'] ?: 'N/A'); ?></td>
                                            <td class="fw-bold text-warning-emphasis text-monospace"><i class="fas fa-coins me-1"></i><?= (float)$row['charge']; ?></td>
                                            <td>
                                                <?php if ((int)$row['status'] === 1): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success px-2 py-1 fs-11 fw-bold"><i class="fas fa-check-circle me-1"></i>Success</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1 fs-11 fw-bold"><i class="fas fa-times-circle me-1"></i>Failed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="pe-3 text-monospace small text-muted"><?= date('d-m-Y H:i A', strtotime($row['date'])); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="text-center py-5 text-muted">
                                            <i class="fas fa-folder-open fa-3x mb-3 text-200"></i>
                                            <h6 class="fw-bold text-700 mb-0">No Search Records Found</h6>
                                            <p class="small text-muted mb-0">Aapki history table khali hai ya search query se match nahi kar rhi.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination Card Footer -->
                <?php if ($total_pages > 1): ?>
                    <div class="card-footer bg-light border-top d-flex align-items-center justify-content-between py-3">
                        <span class="small text-muted">Showing <?= $offset + 1; ?> to <?= min($offset + $limit, $total_rows); ?> of <?= $total_rows; ?> entries</span>
                        
                        <nav aria-label="Page navigation">
                            <ul class="pagination pagination-sm mb-0">
                                <!-- Previous Button -->
                                <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link shadow-none" href="?page=<?= $page - 1; ?><?= !empty($search_keyword) ? '&search=' . urlencode($search_keyword) : ''; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                
                                <!-- Page Numbers -->
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= ($page === $i) ? 'active' : ''; ?>">
                                        <a class="page-link shadow-none" href="?page=<?= $i; ?><?= !empty($search_keyword) ? '&search=' . urlencode($search_keyword) : ''; ?>"><?= $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <!-- Next Button -->
                                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link shadow-none" href="?page=<?= $page + 1; ?><?= !empty($search_keyword) ? '&search=' . urlencode($search_keyword) : ''; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .table th { font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .table td { white-space: nowrap; }
    .text-monospace { font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", Courier, monospace; }
    .fs-11 { font-size: 11px !important; }
    .bg-info-subtle { background-color: #cff4fc !important; }
    .bg-success-subtle { background-color: #d1e7dd !important; }
    .bg-danger-subtle { background-color: #f8d7da !important; }
    .pagination .page-link { color: #5e6e82; }
    .pagination .page-item.active .page-link { background-color: #2c7be5; border-color: #2c7be5; color: #fff; }
</style>

<?php include('footer.php'); ?>