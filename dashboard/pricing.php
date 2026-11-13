<?php
include('../includes/session.php');
include('../includes/config.php');

// Admin Check
if(checkAdmin($udata['type']) == false){
    ?>
    <script>
        window.location='index.php';
    </script>
    <?php
    die();
}

include('header.php'); 

// 1. DYNAMICALLY FETCH ALL COLUMNS FROM DATABASE
$services = [];
$col_res = mysqli_query($DarkXen_conn, "SHOW COLUMNS FROM `service_free`");
if ($col_res) {
    while ($col = mysqli_fetch_assoc($col_res)) {
        $col_name =$col['Field'];
        // 'id' column ko chhodkar baaki sabhi columns ko list me add karein
        if (strtolower($col_name) !== 'id') {
            $services[] =$col_name;
        }
    }
}

$success_message = '';$error_message = '';

// 2. FORM SUBMIT LOGIC (Dynamic Update/Insert)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_pricing'])) {$update_parts = [];
    
    foreach ($services as$service) {
        // Handle input names that might have spaces/dots converted to underscores by PHP
        $post_key = str_replace([' ', '.'], '_', $service);$val = isset($_POST[$post_key]) ? mysqli_real_escape_string($DarkXen_conn, trim($_POST[$post_key])) : '0';$update_parts[] = "`$service` = '$val'";
    }
    
    // Check if ID = 1 exists
    $check_exist = mysqli_query($DarkXen_conn, "SELECT `id` FROM `service_free` WHERE `id` = 1");
    
    if (mysqli_num_rows($check_exist) > 0) {$query = "UPDATE `service_free` SET " . implode(', ', $update_parts) . " WHERE `id` = 1";
    } else {
        $columns_sql = array_map(function($col) { return "`$col`"; }, $services);$values_sql = array_map(function($service) use ($DarkXen_conn) {
            $post_key = str_replace([' ', '.'], '_',$service);
            $val = isset($_POST[$post_key]) ? mysqli_real_escape_string($DarkXen_conn, trim($_POST[$post_key])) : '0';
            return "'$val'";
        }, $services);$query = "INSERT INTO `service_free` (`id`, " . implode(', ', $columns_sql) . ") VALUES (1, " . implode(', ', $values_sql) . ")";
    }
    
    if (mysqli_query($DarkXen_conn, $query)) {$success_message = "All service configurations updated successfully!";
    } else {
        $error_message = "Database Error: " . mysqli_error($DarkXen_conn);
    }
}

// 3. FETCH EXISTING DATA (Dynamic Fetch)
$current_values = [];
// Default 0 set karein
foreach ($services as$service) {
    $current_values[$service] = '0';
}

$fetch_query = mysqli_query($DarkXen_conn, "SELECT * FROM `service_free` WHERE `id` = 1 LIMIT 1");
if ($fetch_query && mysqli_num_rows($fetch_query) > 0) {
    $db_row = mysqli_fetch_assoc($fetch_query);
    
    foreach ($services as$service) {
        if (isset($db_row[$service]) && $db_row[$service] !== null && $db_row[$service] !== '') {
            $current_values[$service] = $db_row[$service];
        }
    }
}

// Service naam ko thoda saaf dikhane ke liye function
function formatServiceLabel($string) {
    return ucwords(str_replace(['_', '1', '2', '3'], [' ', '', '', ''], $string));
}
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="avatar avatar-xl me-3 bg-200 rounded-circle text-center d-flex align-items-center justify-content-center">
                        <span class="fas fa-hand-holding-usd text-primary fs-2"></span>
                    </div>
                    <div>
                        <h4 class="text-primary fw-bold mb-0">Service Control & Pricing Manager</h4>
                        <p class="mb-0 fs-11 text-muted">Manage points/charges or active status for all <?= count($services); ?> dynamically loaded services.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<form method="POST" autocomplete="off">
    <div class="row g-3">
        <div class="col-12">
            <div class="card shadow-none border border-top border-4 border-primary">
                <div class="card-header bg-light d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 fw-bold text-900"><i class="fas fa-sliders-h me-2"></i>Service List & Rates (<?= count($services); ?> Services)</h5>
                    <button class="btn btn-primary btn-sm px-4 fw-bold shadow-none" type="submit" name="update_pricing">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
                <div class="card-body bg-white p-4">
                    <div class="row g-3">
                        <?php if(count($services) > 0): ?>
                            <?php foreach ($services as$service): ?>
                                <div class="col-md-4 col-sm-6">
                                    <div class="p-3 border rounded bg-light-subtle shadow-none row align-items-center">
                                        <div class="col-12 mb-2">
                                            <label class="form-label fw-bold text-800 fs-11 mb-0">
                                                <?= formatServiceLabel($service); ?> 
                                                <span class="text-muted font-monospace fs-12 d-block">(<?= htmlspecialchars($service); ?>)</span>
                                            </label>
                                        </div>
                                        <div class="col-12">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-200 text-primary fw-bold"><i class="fas fa-coins"></i></span>
                                                <input type="text" 
                                                       class="form-control shadow-none border-200 text-center fw-medium" 
                                                       name="<?= htmlspecialchars($service); ?>" 
                                                       value="<?= htmlspecialchars((string)$current_values[$service]); ?>" 
                                                       placeholder="0">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12 text-center py-4">
                                <span class="text-muted"><i class="fas fa-exclamation-circle me-1"></i> No service columns found in the database table 'service_free'.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if(count($services) > 0): ?>
                <div class="card-footer bg-light text-end py-3 border-top">
                    <button class="btn btn-primary px-5 py-2 fw-bold shadow-none" type="submit" name="update_pricing">
                        <span class="fas fa-save me-2"></span>Save All Configurations
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    <?php if(!empty($error_message)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Operation Failed',
            text: '<?= addslashes($error_message); ?>',
            confirmButtonText: 'OK'
        });
    <?php endif; ?>

    <?php if(!empty($success_message)): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?= addslashes($success_message); ?>',
            showConfirmButton: false,
            timer: 2000
        }).then(() => { 
            window.location.href = window.location.pathname;
        });
    <?php endif; ?>
</script>

<style>
    .form-control { padding: 0.5rem 0.75rem; border-radius: 0.375rem; }
    .card { border-radius: 0.75rem; overflow: hidden; }
    .bg-light-subtle { background-color: #f8f9fa; }
    .font-monospace { font-family: monospace; color: #6c757d !important; }
</style>

<?php include('footer.php'); ?>