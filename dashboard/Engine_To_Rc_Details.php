<?php
require('header.php');
?>
<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-warning-subtle shadow-none me-3">
                        <span class="fas fa-car text-warning fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-warning fs-10 mb-0">Vehicle Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Engine to <span class="text-warning fw-medium">RC Details</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="engine_rc_history.php" class="btn btn-sm btn-outline-warning shadow-none fw-bold">
                        <i class="fas fa-list me-1"></i> View History
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-engine me-2"></i>Engine Details</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-info border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-info"></i>
                    <p class="mb-0 fs-11">Verification charge: <strong>₹10.00</strong> will be deducted from your wallet upon success.</p>
                </div>
                
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Engine Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-cog"></i></span>
                            <input type="text" class="form-control shadow-none" name="engine_number" placeholder="Enter engine number" required>
                        </div>
                        <small class="text-muted">Enter the engine number to fetch RC details</small>
                    </div>
                    
                    <button class="btn btn-warning w-100 fw-bold shadow-none py-2" type="submit" name="verify_btn">
                        <i class="fas fa-search me-2"></i> Fetch RC Details
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