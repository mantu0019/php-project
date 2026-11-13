<?php
require('header.php');
?>
<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-success-subtle shadow-none me-3">
                        <span class="fas fa-users text-success fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-success fs-10 mb-0">Government Services</h6>
                        <h4 class="text-primary fw-bold mb-0">MP <span class="text-success fw-medium">Samagra</span> Details</h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="mp_samagra_history.php" class="btn btn-sm btn-outline-success shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History List</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-phone me-2"></i>Verify Family Details</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-success border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-success"></i>
                    <p class="mb-0 fs-11">Verification charge: <strong>₹5.00</strong> will be deducted from your wallet upon success.</p>
                </div>
                
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Mobile Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-mobile-alt"></i></span>
                            <input type="text" class="form-control shadow-none" name="mobile_number" placeholder="Enter 10-digit mobile number" maxlength="10" pattern="[6-9]\d{9}" required>
                        </div>
                        <small class="text-muted">Enter the mobile number registered with MP Samagra</small>
                    </div>
                    
                    <button class="btn btn-success w-100 fw-bold shadow-none py-2" type="submit" name="verify_btn">
                        <i class="fas fa-search me-2"></i> Fetch Family Details
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