
<?php
include('../includes/session.php');
include('../includes/config.php');

if(isset($_GET['delete'])){

    $id = mysqli_real_escape_string($DarkXen_conn, $_GET['delete']);

    $check = mysqli_query(
        $DarkXen_conn,
        "SELECT * FROM voterauto WHERE id='$id' AND username='{$udata['phone']}'"
    );

    if(mysqli_num_rows($check) > 0){

        mysqli_query(
            $DarkXen_conn,
            "DELETE FROM voterauto WHERE id='$id' AND username='{$udata['phone']}'"
        );

        echo "<script>
            alert('Record Deleted Successfully');
            window.location='';
        </script>";
        exit;
    }
}

include('header.php');
?>


<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-info-subtle shadow-none me-3">
                        <span class="fas fa-list text-info fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-11 mb-0">Welcome back, <?php echo $udata['name']; ?></h6>

                        <h4 class="text-primary fw-bold mb-0">Voter Manual <span class="text-info fw-medium">Records</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="voter_advance.php" class="btn btn-primary btn-sm shadow-sm fw-bold">
                        <i class="fas fa-plus me-1"></i> Apply New Voter
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 border-top border-4 border-info">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-1000 fw-bold"><i class="fas fa-history me-2 text-info"></i>Your Print History</h5>
                    <span class="badge badge-subtle-success fs-10"><i class="fas fa-check-circle me-1"></i> Print Server Active</span>
            </div>
    <div class="card-body p-0 bg-white">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped table-hover fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="ps-3 py-3">#</th>
                          
                            <th>Voter Number</th>
                            <th>Name</th>
                            <th>Father</th>
                            <th>DOB</th>
                            <th>Gender</th>
                            <th>Photo</th>
                          
                            <th>Created Date</th>
                             <th>PDF </th>
                           
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
               <tbody>

                        <?php
                        $i = 1;

                       $query = mysqli_query(
    $DarkXen_conn,
    "SELECT * FROM voterauto WHERE username='{$udata['phone']}' ORDER BY id DESC"
);

                        while ($row = mysqli_fetch_assoc($query)) {
                        ?>

                        <tr>
                            <td><?= $i++; ?></td>

                            <td>
                                <?= htmlspecialchars($row['epicno']); ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['votername']); ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['fathername']); ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['dob']); ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['gender']); ?>
                            </td>

                            <td>
                                <?php if (!empty($row['imagepathoriginal'])) { ?>
                                    <a href="<?= $row['imagepathoriginal']; ?>" target="_blank">
                                        <img src="<?= $row['imagepathoriginal']; ?>"
                                             width="60"
                                             height="60"
                                             style="object-fit:cover;border-radius:5px;">
                                    </a>
                                <?php } ?>
                            </td>

                           

                            <td>
                                <?= $row['createdatetime']; ?>
                            </td>
                            
                           <td align="center">
    <a class="btn btn-success btn-sm"
       href="dacoment/voter-print.php?searchid=<?= base64_encode($row['id']) ?>"
       target="_blank">
       Print
    </a>
</td>

<td>
    <a href="?delete=<?= $row['id']; ?>"
       class="btn btn-danger btn-sm"
       onclick="return confirm('Are you sure you want to delete this PAN record?');">
       Delete
    </a>
</td>
                        </tr>
          
                        <?php } ?>

                    </tbody>
                                
                       
            </table>
        </div>
    </div>


</div>

 <?php include('footer.php'); ?>