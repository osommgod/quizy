<?php
/** user_store.php  — full featured */

header('Content-Type: application/json');
date_default_timezone_set("Asia/Kolkata");
require_once 'config.php';

/* ─── DB CONFIG ─── */
$host = DB_HOST;
$dbname = DB_NAME;
$user = DB_USER;
$password = DB_PASS;
$valid_api_key = VALID_API_KEY;

/* ─── INPUT ─── */
$deviceid = $_POST['deviceid'] ?? '';
$api_key = $_POST['api_key'] ?? '';

function jsonResponse($status, $message, $extra = [])
{
    echo json_encode(array_merge(["status" => $status, "message" => $message], $extra));
    exit;
}

/* ─── IP + Header logging ─── */
function getUserIP()
{
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
}
$user_ip = getUserIP();

/* hybrid header logger */
function logHeadersHybrid($deviceid, $ip)
{
    $today = date('Y-m-d');
    $base = __DIR__ . '/logs/headers/' . $today . '/';
    if (!is_dir($base))
        mkdir($base, 0755, true);
    $safeDev = preg_replace('/[^A-Za-z0-9_\-]/', '_', $deviceid);
    $file = $base . 'device_' . $safeDev . '.log';
    $entry = "---- " . date('Y-m-d H:i:s') . " | IP: $ip ----\n";
    foreach (getallheaders() as $k => $v) {
        $entry .= "$k: $v\n";
    }
    $entry .= "------------------------------\n";
    file_put_contents($file, $entry, FILE_APPEND);
}
logHeadersHybrid($deviceid, $user_ip);

/* ─── simple zip + purge (runs max once/day) ─── */
function rotateLogs()
{
    $flag = __DIR__ . '/logs/headers/.last_cleanup';
    $today = date('Y-m-d');
    if (file_exists($flag) && trim(file_get_contents($flag)) === $today)
        return;

    $baseDir = __DIR__ . '/logs/headers/';
    $zipDir = __DIR__ . '/logs/zipped/';
    $zipAfterDays = 2;
    $deleteZipsAfter = 30;
    if (!is_dir($zipDir))
        mkdir($zipDir, 0755, true);

    $now = time();

    /* zip old date folders */
    foreach (scandir($baseDir) as $folder) {
        if ($folder === '.' || $folder === '..')
            continue;
        $folderPath = $baseDir . $folder;
        if (!is_dir($folderPath))
            continue;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $folder))
            continue;
        $folderTime = strtotime($folder);
        if (!$folderTime || ($now - $folderTime) < ($zipAfterDays * 86400))
            continue;

        $zipFile = $zipDir . $folder . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($folderPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($files as $f) {
                $path = $f->getRealPath();
                $rel = substr($path, strlen($folderPath) + 1);
                $zip->addFile($path, $rel);
            }
            $zip->close();
            /* delete folder */
            $it = new RecursiveDirectoryIterator($folderPath, RecursiveDirectoryIterator::SKIP_DOTS);
            $ri = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($ri as $fi) {
                $fi->isDir() ? rmdir($fi) : unlink($fi);
            }
            rmdir($folderPath);
        }
    }

    /* purge old zips */
    foreach (scandir($zipDir) as $z) {
        if (!str_ends_with($z, '.zip'))
            continue;
        $zipPath = $zipDir . $z;
        if ((time() - filemtime($zipPath)) > ($deleteZipsAfter * 86400))
            unlink($zipPath);
    }
    file_put_contents($flag, $today);
}
rotateLogs();

/* ─── Validate API/Device ─── */
if ($api_key !== $valid_api_key) {
    http_response_code(401);
    jsonResponse("error", "Invalid API key");
}
if (empty($deviceid)) {
    http_response_code(400);
    jsonResponse("error", "Missing device ID");
}

/* ─── Connect DB ─── */
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    jsonResponse("error", "DB connection failed");
}

/* ─── Blocked IP ─── */
$cb = $conn->prepare("SELECT 1 FROM blocked_ips WHERE ip_address=?");
$cb->bind_param("s", $user_ip);
$cb->execute();
$cb->store_result();
if ($cb->num_rows > 0) {
    $log = $conn->prepare("INSERT INTO failed_attempts(ip_address,reason) VALUES(?, 'Blocked IP tried to register')");
    $log->bind_param("s", $user_ip);
    $log->execute();
    $log->close();
    http_response_code(403);
    jsonResponse("error", "Access denied. Your IP is blocked.");
}
$cb->close();

/* ─── Already registered? ─── */
$chk = $conn->prepare("SELECT 1 FROM user_data WHERE user_deviceid=?");
$chk->bind_param("s", $deviceid);
$chk->execute();
$chk->store_result();
if ($chk->num_rows > 0) {
    $chk->close();
    $conn->close();
    jsonResponse("exists", "Device already registered.");
}
$chk->close();

/* ─── Signup config ─── */
$signup_point = $signup_bonus = 0;
$res = $conn->query("SELECT config_key,config_value FROM app_config WHERE config_key IN ('signup_point','signup_bonuspoints')");
while ($r = $res->fetch_assoc()) {
    if ($r['config_key'] == 'signup_point')
        $signup_point = (float) $r['config_value'];
    if ($r['config_key'] == 'signup_bonuspoints')
        $signup_bonus = (float) $r['config_value'];
}
$res->close();

/* ─── Generate unique username ─── */
function uniqueUsername($conn, $retry = 5)
{
    while ($retry--) {
        $u = 'User' . rand(100000, 999999);
        $c = $conn->prepare("SELECT 1 FROM user_data WHERE user_name=?");
        $c->bind_param("s", $u);
        $c->execute();
        $c->store_result();
        if ($c->num_rows == 0) {
            $c->close();
            return $u;
        }
        $c->close();
    }
    return null;
}

/* ─── Insert with transaction & retry ─── */
for ($try = 0; $try < 3; $try++) {
    $uname = uniqueUsername($conn);
    if (!$uname)
        jsonResponse("error", "Username generation failed.");

    $conn->begin_transaction();
    try {
        $ins = $conn->prepare("INSERT INTO user_data
            (user_deviceid,user_name,user_credits,user_bonuspoints,user_ip)
             VALUES(?,?,?,?,?)");
        $ins->bind_param("ssdds", $deviceid, $uname, $signup_point, $signup_bonus, $user_ip);
        $ins->execute();
        $ins->close();

        $msg = json_encode(["Welcome to Moodzy Quiz!\nYour account has been created successfully."]);
        $ntf = $conn->prepare("INSERT INTO notifications(user_deviceid,notifications) VALUES(?,?)");
        $ntf->bind_param("ss", $deviceid, $msg);
        $ntf->execute();
        $ntf->close();

        $conn->commit();
        $conn->close();
        jsonResponse("success", "Registration successful", [
            "username" => $uname,
            "points" => $signup_point,
            "bonus" => $signup_bonus
        ]);
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        if (str_contains($e->getMessage(), 'Duplicate entry') && str_contains($e->getMessage(), 'user_name')) {
            continue; // retry on username collision
        }
        $conn->close();
        jsonResponse("error", "DB error: " . $e->getMessage());
    }
}
$conn->close();
jsonResponse("error", "Registration failed after retries.");
?>