<?php

require('../includes/config.php');



if(!$DarkXen_conn){
    die("Database Connection Failed: " . mysqli_query());
}

$webres = mysqli_query($DarkXen_conn, "SELECT * FROM settings WHERE id=1");

//api url and access key here 
// Get API key from settings



$webdata = mysqli_fetch_assoc($webres);
// Check If User Is Logged In or Not

$token = $webdata['upi_key']; // ✅ Use key from DB
$upiuid = $webdata['upi_id']; // ✅ Use key from DB
$secret = $webdata['salt'];
$url = 'https://' . $DarkXen_url . '/order/process';
  




?>
