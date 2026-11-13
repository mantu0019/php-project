<?php
// ==========================================
//        DARKXEN API - CORE SYSTEM
// ==========================================
// File: database.php
// Description: Master Database Connection
// ==========================================

// Database Credentials
$hostname = "localhost";
$username = "u489064697_newprintpdfu";
$password = "|4CwR8&j7Az";
$database = "u489064697_newprintpdf";

// Establish Database Connection
$DarkXen_conn = mysqli_connect($hostname, $username, $password, $database);

// Connection Error Handling
if (!$DarkXen_conn) {
    // Include frontend dependencies (jQuery, SweetAlert, etc.)
    include('links.php');
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'System Offline',
                text: 'Database Connection Failed. Please contact DarkXen Support.',
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Retry Connection',
                background: '#ffffff',
                backdrop: 'rgba(15, 23, 42, 0.85)',
                allowOutsideClick: false
            }).then(() => {
                window.location.reload();
            });
        } else {
            alert("Database Connection Failed. Please contact DarkXen Support.");
        }
    });
    </script>
    <style>
        body { background-color: #f8fafc; }
    </style>
    <?php
    // Halt further execution to prevent cascading PHP errors
    exit;
}
?>