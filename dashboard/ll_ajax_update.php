<?php
// ll_ajax_update.php
include('../includes/session.php');
include('../includes/config.php');

$appliedby =$udata['phone'] ?? '';
if(empty($appliedby)){
    echo json_encode(['updated' => 0]);
    exit;
}

// Check only 'pending' records for this specific user
$query = mysqli_query($DarkXen_conn, "SELECT `id`, `application_no` FROM `ll_exam_history` WHERE `username` = '$appliedby' AND `status` = 'pending'");
$updated_count = 0;

if ($query && mysqli_num_rows($query) > 0) {
    while($row = mysqli_fetch_assoc($query)) {
        
        $app_no = urlencode($row['application_no']);
        $api_key = urlencode($DarkXen_apikey);
        $url = "https://".$DarkXen_url."/api/v3/EXAM/ll_status_api.php?api_key=$api_key&application_no=$app_no";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $res = json_decode($response, true);
        
        // If Status API says 'success', we update our Database
        if(isset($res['status']) &&$res['status'] === true && isset($res['request_status']) && strtolower($res['request_status']) === 'success') {
            
            $img =$res['image_base64'] ?? '';
            
            if(!empty($img)){
                 // Append base64 headers if raw base64 is returned
                 if (strpos($img, 'data:image') === false && preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/',$img)) {
                     $img = 'data:image/png;base64,' .$img;
                 }
                 
                 $id =$row['id'];
                 // Update DB using prepared statement for longtext image
                 $stmt = mysqli_prepare($DarkXen_conn, "UPDATE `ll_exam_history` SET `status` = 'success', `image_base64` = ? WHERE `id` = ?");
                 if($stmt) {
                     mysqli_stmt_bind_param($stmt, "si", $img,$id);
                     mysqli_stmt_execute($stmt);$updated_count++;
                 }
            }
        }
    }
}

echo json_encode(['updated' => $updated_count]);
?>