<?php
// ==========================================
//        DARKXEN API - CORE SETTINGS
// ==========================================

// Initialize Session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require('database.php');

// Fetch Website Settings Data
$host = $_SERVER['SERVER_NAME'];
$dir = dirname($_SERVER['SCRIPT_NAME']);
date_default_timezone_set("Asia/Kolkata");

// Global Database Connection 
global $DarkXen_conn;

$webres = mysqli_query($DarkXen_conn, "SELECT * FROM settings WHERE id=1");
$webdata = mysqli_fetch_assoc($webres);

// Core API Variables Setup
$DarkXen_url = base64_decode('ZGFya3hlbi5pbg==');
$DarkXen_apikey = $webdata['api_key']; 
$websiteName = $webdata['webname'];
$websiteUrl = $webdata['weburl'];

// Session Validation Function
function checkSession() {
    // Check if session exists
    if (!isset($_SESSION['id']) || !isset($_SESSION['token'])) {
        header('Location: ../login.php');
        exit();
    }
}

// User Authentication and Data Fetch
if (isset($_SESSION['phone'])) {
    $udata = mysqli_fetch_assoc(mysqli_query($DarkXen_conn, "SELECT * FROM users WHERE phone='" . $_SESSION['phone'] . "'"));

    // Check Admin Privileges
    function checkadmin($usertype) {
        if ($usertype == "admin") {
            return true;
        } else {
            return false;
        }
    }

    // Global Admin Verification
    $nutype = $udata['type'];
    function ifadmin() {
        global $nutype;
        if ($nutype == 'admin') {
            return true;
        } else {
            return false;
        }
    }

    // Retrieve Total Users (For Admin Dashboard)
    $allusersforadmin = Rows("SELECT * FROM users");
}

/* ==========================================
   DARKXEN API - HELPER FUNCTIONS
========================================== */

function Query($sql) {
    global $DarkXen_conn;
    return mysqli_query($DarkXen_conn, $sql);
}

function Rows($sql) {
    $query = Query($sql);
    return ($query) ? mysqli_num_rows($query) : 0;
}

function fetchRow($sql) {
    $res = Query($sql);
    return mysqli_fetch_assoc($res);
}

function web($columnName) {
    $res = fetchRow("SELECT $columnName FROM settings WHERE id=1");
    echo htmlspecialchars($res[$columnName] ?? '');
}

function getColumn($tableName, $columnName, $Identity) {
    $sql = "SELECT $columnName FROM $tableName WHERE $Identity";
    $res = fetchRow($sql);
    return ($res) ? $res[$columnName] : "Data not Found";
}

function getSafe($value) {
    global $DarkXen_conn;
    return mysqli_real_escape_string($DarkXen_conn, $value);
}

function Redirect($url = '', $time = 1000) {
    echo "<script>setTimeout(() => { window.location = '$url'; }, $time);</script>";
}

function showAlert($fmsg = '', $smsg = '', $type = 'success') {
    echo "<script>Swal.fire('$fmsg', '$smsg', '$type');</script>";
}

function loginAsadmin() {
    if (isset($_SESSION['adminasuser']) && $_SESSION['adminusername']) {
        $_SESSION['phone'] = $_SESSION['adminusername'];
        unset($_SESSION['adminasuser'], $_SESSION['adminusername']);
        Redirect('index.php', 1200);
    }
}
?>