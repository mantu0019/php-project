<?php
// Buffer start to catch any output from database.php or config.php
ob_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

function respond($success, $message){
    // Hamesha clean JSON return karega
    echo json_encode(["success" => $success, "message" => $message]);
    exit;
}

try {
    session_start();

    // Working directory ko 'includes' folder me shift kar rahe hain 
    $include_dir = realpath(__DIR__ . '/../includes');
    if ($include_dir) {
        chdir($include_dir);
    }
    
    // Ab safely database load ho jayega
    if (file_exists("database.php")) {
        require_once("database.php");
    } else {
        respond(false, "Database file not found in includes folder.");
    }

    // Wapas update folder me aate hain
    chdir(__DIR__);
    
    // Clear any accidental output before sending JSON
    ob_clean();

    // ==========================================
    // 1. ADMIN SESSION CHECK
    // ==========================================
    $userID = $_SESSION['id'] ?? null;
    if (!$userID) {
        respond(false, "Unauthorized access: Session expired or invalid.");
    }

    // 🟢 FIX: Changed get_result() to bind_result() for Universal Server Compatibility
    $stmt = $DarkXen_conn->prepare("SELECT id, type FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $stmt->bind_result($db_id, $db_type);
    
    $admin = null;
    if ($stmt->fetch()) {
        $admin = ['id' => $db_id, 'type' => $db_type];
    }
    $stmt->close();

    if (!$admin || strtolower($admin['type']) !== 'admin') {
        respond(false, "Admin access required.");
    }

    // ==========================================
    // 2. CHECK POST REQUEST
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(false, "Invalid request method");
    }

    $input = json_decode(file_get_contents("php://input"), true);

    if (empty($input['update_url']) || empty($input['version'])) {
        respond(false, "Invalid request data. Update URL missing.");
    }

    $updateUrl   = trim($input['update_url']);
    $newVersion  = trim($input['version']);
    
    // Extract Path (Main Root Folder)
    $extractPath = realpath(dirname(__DIR__)); 

    if (!$extractPath) {
        respond(false, "Invalid extraction path on server.");
    }

    // ==========================================
    // 3. ZIP VALIDATION & DOWNLOAD
    // ==========================================
    if (strtolower(pathinfo(parse_url($updateUrl, PHP_URL_PATH), PATHINFO_EXTENSION)) !== 'zip') {
        respond(false, "Invalid File: Only ZIP updates are allowed. URL must end with .zip");
    }

    $zipFile = __DIR__ . "/update_package.zip";
    $fp = fopen($zipFile, 'w');

    if (!$fp) {
        respond(false, "Permission Denied: Unable to create ZIP file in update folder.");
    }

    $ch = curl_init($updateUrl);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 120
    ]);

    curl_exec($ch);

    if (curl_errno($ch)) {
        fclose($fp);
        if(file_exists($zipFile)) unlink($zipFile);
        respond(false, "Download failed: " . curl_error($ch));
    }

    curl_close($ch);
    fclose($fp);

    // ==========================================
    // 4. ZIP EXTRACTION
    // ==========================================
    if (!file_exists($zipFile) || filesize($zipFile) < 1000) {
        if(file_exists($zipFile)) unlink($zipFile);
        respond(false, "Corrupted ZIP downloaded. Check API link. (Filesize too small)");
    }

    if (!class_exists('ZipArchive')) {
        unlink($zipFile);
        respond(false, "ZipArchive PHP extension is missing on your server.");
    }

    $zip = new ZipArchive;

    if ($zip->open($zipFile) !== TRUE) {
        unlink($zipFile);
        respond(false, "Unable to open the downloaded ZIP file. May be corrupted.");
    }

    if (!$zip->extractTo($extractPath)) {
        $zip->close();
        unlink($zipFile);
        respond(false, "Extraction failed. Folder permissions might be blocking extraction.");
    }

    $zip->close();
    unlink($zipFile);

    // ==========================================
    // 5. RUN SQL IF EXISTS (update.sql)
    // ==========================================
    $sqlFile = $extractPath . '/update.sql';

    if (file_exists($sqlFile)) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $queries = explode(";", file_get_contents($sqlFile));
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    try {
                        $DarkXen_conn->query($query);
                    } catch (mysqli_sql_exception $e) {
                        continue; // Ignore duplicate tables or columns gracefully
                    }
                }
            }
            unlink($sqlFile);
        } catch (Exception $e) {}
    }

    // ==========================================
    // 6. SAVE NEW VERSION
    // ==========================================
    file_put_contents(__DIR__ . '/current_version.txt', $newVersion);

    // Final Success Response
    respond(true, "System successfully updated to v" . $newVersion);

} catch (Throwable $e) {
    // Agar koi bhi PHP error aata hai to usko JSON me bhej dega
    ob_clean(); // Extra output hatao
    respond(false, "Fatal Error: " . $e->getMessage() . " on line " . $e->getLine());
}
?>