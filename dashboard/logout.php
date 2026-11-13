<?php
session_start();
// Database se token null kar dein taaki purana session kaam na kare
if(isset($_SESSION['id'])){
    include('../includes/config.php'); // config path sahi karein
    mysqli_query($DarkXen_conn, "UPDATE users SET reset_token = NULL WHERE id = '".$_SESSION['id']."'");
}

session_unset();
session_destroy();
?>
<script>
    window.location.href='login.php';
</script>