<?php
include('../includes/session.php');
include('../includes/config.php');
include('header.php');

// टाइमज़ोन सेट करें
date_default_timezone_set("Asia/Kolkata");

$user_phone =$udata['phone'];
$user_role =$udata['role'] ?? 'User'; // Role Default Fallback

// अगर एडमिन है तो सबका डेटा दिखाए, वरना सिर्फ अपना
if ($user_role === 'Admin') {$query = "SELECT * FROM `pincode_history` ORDER BY id DESC";
} else {
    $query = "SELECT * FROM `pincode_history` WHERE `username` = '$user_phone' ORDER BY id DESC";
}

$result = mysqli_query($DarkXen_conn,$query);
?>

<!-- Print CSS: Ye CSS सिर्फ print करते वक़्त वेबसाइट को क्लीन बनाएगी -->
<style>
    @media print {
        body * {
            visibility: hidden; /* बाकी सब गायब */
        }
        #printCard, #printCard * {
            visibility: visible; /* सिर्फ Card दिखेगा */
        }
        #printCard {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }
        .no-print {
            display: none !important; /* Print बटन वगेरा छुपाने के लिए */
        }
        .card {
            border: 1px solid #ddd !important;
            box-shadow: none !important;
        }
    }
</style>

<div class="row mb-3 no-print">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3" style="width: 50px; height: 50px;">
                        <span class="fas fa-map-marker-alt text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Utility Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Pincode Search <span class="text-info fw-medium">History</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="pinecode-info.php" class="btn btn-sm btn-primary shadow-none fw-bold">
                        <i class="fas fa-search me-1"></i> New Search
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- यहाँ 'printCard' ID लगाई है ताकि सिर्फ ये बॉक्स प्रिंट हो -->
<div class="card shadow-sm border border-top border-4 border-info mb-4" id="printCard">
    <div class="card-header bg-light border-bottom py-3">
        <div class="row align-items-center flex-between-center">
            <div class="col-auto">
                <h5 class="mb-0 text-dark fw-bold"><i class="fas fa-list me-2 text-info"></i>Recent Pincode Searches</h5>
            </div>
            <div class="col-auto no-print">
                <!-- Print Button -->
                <button onclick="window.print()" class="btn btn-sm btn-dark shadow-none fw-bold">
                    <i class="fas fa-print me-1"></i> Print Report
                </button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped table-hover align-middle fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="white-space-nowrap py-3 text-center">#</th>
                        <th class="white-space-nowrap py-3">App No</th>
                        <?php if($user_role === 'Admin'): ?>
                            <th class="white-space-nowrap py-3">User</th>
                        <?php endif; ?>
                        <th class="white-space-nowrap py-3">Pincode</th>
                        <th class="white-space-nowrap py-3">State</th>
                        <th class="white-space-nowrap py-3">District</th>
                        <th class="white-space-nowrap py-3 text-center">Offices Found</th>
                        <th class="white-space-nowrap py-3 text-center">Date & Time</th>
                        <th class="white-space-nowrap py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="list">
                    <?php 
                    if ($result && mysqli_num_rows($result) > 0) {$count = 1;
                        while ($row = mysqli_fetch_assoc($result)) {
                    ?>
                    <tr>
                        <td class="py-3 text-center fw-bold text-dark"><?= $count++; ?></td>
                        <td class="py-3 fw-bold text-primary font-monospace"><?= htmlspecialchars($row['application_no']); ?></td>
                        
                        <?php if($user_role === 'Admin'): ?>
                            <td class="py-3 fw-medium"><?= htmlspecialchars($row['username']); ?></td>
                        <?php endif; ?>
                        
                        <td class="py-3 fw-bold text-900 font-monospace"><?= htmlspecialchars($row['pincode']); ?></td>
                        <td class="py-3 fw-bold text-700 text-uppercase"><?= htmlspecialchars($row['state']); ?></td>
                        <td class="py-3 text-uppercase"><?= htmlspecialchars($row['district']); ?></td>
                        <td class="py-3 text-center">
                            <span class="badge bg-secondary px-2 fs-11"><?= htmlspecialchars($row['total_offices']); ?></span>
                        </td>
                        <td class="py-3 text-center text-muted fw-medium"><?= date('d M Y, h:i A', strtotime($row['date'])); ?></td>
                        <td class="py-3 text-center">
                            <span class="badge badge-subtle-success fs-11 px-2 py-1 border border-success">
                                <i class="fas fa-check-circle me-1"></i> Success
                            </span>
                        </td>
                    </tr>
                    <?php 
                        }
                    } else {
                        // Dynamic Colspan Handling
                        $colspan = ($user_role === 'Admin') ? 9 : 8;
                        echo "<tr>
                                <td colspan='$colspan' class='text-center py-5 text-muted fw-bold'>
                                    <i class='fas fa-folder-open fs-2 mb-2 d-block'></i> No Pincode Search History Found!
                                </td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-light py-2 border-top no-print">
        <p class="mb-0 fs-11 text-600 text-center">Showing all previous Pincode search transactions.</p>
    </div>
</div>

<?php include('footer.php'); ?>