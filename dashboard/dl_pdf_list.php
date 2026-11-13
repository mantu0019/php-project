<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

$appliedby =$udata['phone'];

// Agar koi record delete karna ho (Optional Action)
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    
    // File delete karne ke liye query
    $get_file = mysqli_query($DarkXen_conn, "SELECT pdf_file FROM `dl_pdf_records` WHERE id='$del_id' AND userid='$appliedby'");
    if ($row_file = mysqli_fetch_assoc($get_file)) {
        // Checking both full path and relative path
        $file_to_del =$row_file['pdf_file'];
        if (!file_exists($file_to_del)) {
            $file_to_del = "../" . $row_file['pdf_file']; 
        }

        if (file_exists($file_to_del)) {
            unlink($file_to_del);
        }
        mysqli_query($DarkXen_conn, "DELETE FROM `dl_pdf_records` WHERE id='$del_id' AND userid='$appliedby'");
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Deleted!', 
                    text: 'Record removed successfully.', 
                    icon: 'success',
                    confirmButtonColor: '#22c55e'
                }).then(() => {
                    window.location.href = 'dl_pdf_list.php';
                });
            });
        </script>";
    }
}
?>

<style>
    /* 🌟 Premium UI Styling (Matching Screenshot Theme) 🌟 */
    body {
        background-color: #f4f7f6; /* Soft light background */
    }
    .page-header-premium {
        background: linear-gradient(135deg, #8b5cf6, #3b82f6); /* Gradient Purple to Blue */
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        margin-bottom: 24px;
        color: white;
    }
    .card-premium {
        border-radius: 16px;
        border: none;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        background: #ffffff;
        overflow: hidden;
    }
    .table-premium {
        margin-bottom: 0;
    }
    .table-premium thead th {
        background: #f8fafc;
        color: #475569;
        font-weight: 600;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e2e8f0;
        padding: 16px;
    }
    .table-premium tbody td {
        padding: 16px;
        color: #334155;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }
    .dl-badge {
        background: #f0fdf4;
        color: #22c55e; /* Crisp Green */
        border: 1px dashed #22c55e;
        padding: 8px 14px;
        border-radius: 8px;
        font-family: monospace;
        font-size: 15px;
        font-weight: 700;
        letter-spacing: 2px;
        display: inline-block;
    }
    .status-success {
        color: #22c55e;
        font-size: 14px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .status-missing {
        color: #ef4444;
        font-size: 14px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    /* Buttons Styling */
    .btn-generate {
        background: #ffffff;
        color: #3b82f6;
        border: none;
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 700;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        transition: 0.3s;
    }
    .btn-generate:hover {
        background: #f8fafc;
        transform: translateY(-2px);
    }
    
    .action-container {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .btn-view-dl-group {
        display: flex;
        gap: 8px;
        align-items: center;
        background: #f8fafc;
        padding: 6px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
    }
    .btn-view {
        background: #eff6ff;
        color: #3b82f6;
        border: none;
        border-radius: 6px;
        padding: 8px 14px;
        font-size: 13px;
        font-weight: 600;
        transition: 0.2s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .btn-view:hover {
        background: #3b82f6;
        color: white;
    }
    .btn-dl {
        background: #22c55e;
        color: white;
        border: none;
        border-radius: 6px;
        padding: 8px 14px;
        font-size: 13px;
        font-weight: 600;
        transition: 0.2s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        box-shadow: 0 2px 8px rgba(34,197,94,0.2);
    }
    .btn-dl:hover {
        background: #16a34a;
        color: white;
    }
    .action-divider {
        width: 1px;
        height: 28px;
        background: #cbd5e1;
        margin: 0 15px;
    }
    .btn-delete {
        background: #ffffff;
        color: #ef4444;
        border: 1px solid #fecaca;
        border-radius: 8px;
        padding: 8px 14px;
        font-size: 14px;
        transition: 0.2s;
    }
    .btn-delete:hover {
        background: #ef4444;
        color: white;
    }
</style>

<!-- Premium Header -->
<div class="page-header-premium">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <div style="background: rgba(255,255,255,0.9); padding: 12px; border-radius: 12px; color: #3b82f6;">
                <i class="fas fa-id-card fa-2x"></i>
            </div>
            <div>
                <h4 class="mb-1 fw-bold" style="color: #ffffff;">Transport Records</h4>
                <p class="mb-0 fs-14" style="color: rgba(255,255,255,0.8);">View and manage your downloaded Driving Licences</p>
            </div>
        </div>
        <div>
            <a href="dl_pdf.php" class="btn-generate text-decoration-none">
                <i class="fas fa-plus-circle me-1"></i> Generate New DL
            </a>
        </div>
    </div>
</div>

<!-- Table Card -->
<div class="card card-premium">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-premium table-hover align-middle" id="dlPdfTable">
                <thead>
                    <tr class="text-center">
                        <th style="width: 50px;">#</th>
                        <th>DL Number</th>
                        <th>Date of Birth</th>
                        <th>Txn ID</th>
                        <th>Generated On</th>
                        <th>Status</th>
                        <th style="width: 250px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = mysqli_query($DarkXen_conn, "SELECT * FROM `dl_pdf_records` WHERE userid='$appliedby' ORDER BY id DESC");
                    
                    if (mysqli_num_rows($query) > 0) {$sn = 1;
                        while ($row = mysqli_fetch_assoc($query)) {
                            
                            // SMART PATH FIX
                            $raw_path = $row['pdf_file'];$file_exists = false;
                            $pdf_path = "";

                            if (file_exists($raw_path)) {
                                $pdf_path =$raw_path;
                                $file_exists = true;
                            } elseif (file_exists("../" . $raw_path)) {
                                $pdf_path = "../" . $raw_path;
                                $file_exists = true;
                            } elseif (file_exists("uploads/dl_pdfs/" . basename($raw_path))) {$pdf_path = "uploads/dl_pdfs/" . basename($raw_path);$file_exists = true;
                            }
                    ?>
                        <tr class="text-center">
                            <td class="fw-bold text-muted"><?php echo $sn++; ?></td>
                            <td>
                                <span class="dl-badge"><?php echo htmlspecialchars($row['dl_number']); ?></span>
                            </td>
                            <td class="fw-semibold" style="color: #64748b;"><?php echo htmlspecialchars($row['dob']); ?></td>
                            <td class="small text-muted font-monospace"><?php echo htmlspecialchars($row['txn_id']); ?></td>
                            <td class="small text-muted fw-medium"><?php echo date('d-M-Y, h:i A', strtotime($row['created_at'])); ?></td>
                            <td>
                                <span class="status-success">
                                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-container">
                                    
                                    <!-- View & Download Grouped Together -->
                                    <?php if ($file_exists && !empty($pdf_path)): ?>
                                        <div class="btn-view-dl-group">
                                            <a href="<?php echo htmlspecialchars($pdf_path); ?>" target="_blank" class="btn-view" data-bs-toggle="tooltip" title="View Document">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="<?php echo htmlspecialchars($pdf_path); ?>" download class="btn-dl" data-bs-toggle="tooltip" title="Download Document">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <span class="status-missing"><i class="fas fa-exclamation-triangle"></i> Missing</span>
                                    <?php endif; ?>
                                    
                                    <!-- Separator Line -->
                                    <div class="action-divider"></div>
                                    
                                    <!-- Delete Button (Separate) -->
                                    <button onclick="confirmDelete(<?php echo $row['id']; ?>)" class="btn-delete" data-bs-toggle="tooltip" title="Delete Record">
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
                            <td colspan="7" class="text-center py-5">
                                <div style="padding: 40px 0;">
                                    <i class="fas fa-folder-open fa-4x mb-3" style="color: #cbd5e1;"></i>
                                    <h5 class="fw-bold" style="color: #475569;">No DL records found!</h5>
                                    <p class="text-muted mb-0">Generate a new Driving Licence to see it here.</p>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function confirmDelete(id) {
    Swal.fire({
        title: 'Are you absolutely sure?',
        text: "This record and its downloaded file will be permanently deleted.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444', 
        cancelButtonColor: '#94a3b8',
        confirmButtonText: '<i class="fas fa-trash-alt me-1"></i> Yes, delete it',
        borderRadius: '12px'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'dl_pdf_list.php?delete_id=' + id;
        }
    });
}

// Initialize tooltips if Bootstrap is available
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    })
});
</script>

<?php include('footer.php'); ?>