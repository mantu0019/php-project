<?php
require('header.php');
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-success-subtle shadow-none me-3">
                        <span class="fas fa-search text-success fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-success fs-10 mb-0">Identity Search</h6>
                        <h4 class="text-primary fw-bold mb-0">Ration To <span class="text-success fw-medium">Aadhar Find</span></h4>
                    </div>
                </div>
                <div class="col-md-auto">
                    <a href="Ration_To_Uid_List.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-bolt me-2 text-warning"></i>Aadhar Find Process</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-success border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-success"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹140.00</strong> will be deducted instantly.</p>
                </div>
                
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Ration Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-hashtag"></i></span>
                            <input type="text" class="form-control form-control-lg shadow-none" name="ration_number" placeholder="Enter Ration Number" required>
                        </div>
                    </div>
                    
                    <button class="btn btn-success w-100 fw-bold shadow-none py-2" type="submit" name="find_ration_btn">
                        <i class="fas fa-search me-2"></i> Fetch Details Now
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    </script>
<?php include('footer.php'); ?>