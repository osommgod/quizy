<?php
// Set timezone
date_default_timezone_set("Asia/Kolkata");
require_once 'config.php';

// MySQL config
$host = DB_HOST;
$dbname = DB_NAME;
$user = DB_USER;
$password = DB_PASS;
$valid_api_key = VALID_API_KEY;

// API Key
$valid_api_key = "9b5e35a1-4d20-427d-97f3-83c17499a7c2";

// Get POST data
$deviceid = $_POST['deviceid'] ?? '';
$api_key = $_POST['api_key'] ?? '';

// Detect IP (proxy-safe)
function getUserIP()
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ipList[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
}
$user_ip = getUserIP();

// Log full headers
function logHeaders($ip)
{
    $headers = getallheaders();
    $logData = "---- Request from IP: $ip @ " . date('Y-m-d H:i:s') . " ----\n";
    foreach ($headers as $key => $value) {
        $logData .= "$key: $value\n";
    }
    $logData .= "-------------------------------------------\n";
    file_put_contents(__DIR__ . '/logs/request_headers.log', $logData, FILE_APPEND);
}
logHeaders($user_ip);

// Validate API key
if ($api_key !== $valid_api_key) {
    http_response_code(401);
    echo "Invalid API Key";
    exit;
}

// Validate input
if (empty($deviceid)) {
    http_response_code(400);
    echo "Missing device ID";
    exit;
}

// Connect to DB
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo "Database connection failed: " . $conn->connect_error;
    exit;
}

// Check if IP is blocked
$check_block = $conn->prepare("SELECT ip_address FROM blocked_ips WHERE ip_address = ?");
$check_block->bind_param("s", $user_ip);
$check_block->execute();
$check_block->store_result();

if ($check_block->num_rows > 0) {
    $log = $conn->prepare("INSERT INTO failed_attempts (ip_address, reason) VALUES (?, 'Blocked IP tried to register')");
    $log->bind_param("s", $user_ip);
    $log->execute();
    $log->close();

    http_response_code(403);
    echo "Access denied. Your IP is blocked.";
    $check_block->close();
    $conn->close();
    exit;
}
$check_block->close();

// Check if device already exists
$stmt = $conn->prepare("SELECT user_deviceid FROM user_data WHERE user_deviceid = ?");
$stmt->bind_param("s", $deviceid);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo "Device already registered.";
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// Fetch signup values
$signup_point = 0;
$signup_bonus = 0;

$config_query = "SELECT config_key, config_value FROM app_config WHERE config_key IN ('signup_point', 'signup_bonuspoints')";
$config_result = $conn->query($config_query);
if ($config_result) {
    while ($row = $config_result->fetch_assoc()) {
        if ($row['config_key'] == 'signup_point') {
            $signup_point = floatval($row['config_value']);
        } elseif ($row['config_key'] == 'signup_bonuspoints') {
            $signup_bonus = floatval($row['config_value']);
        }
    }
    $config_result->close();
}

// Generate a unique username
function generateUniqueUserName($conn)
{
    do {
        $user_name = "User" . rand(100000, 999999);
        $check = $conn->prepare("SELECT user_name FROM user_data WHERE user_name = ?");
        $check->bind_param("s", $user_name);
        $check->execute();
        $check->store_result();
        $exists = $check->num_rows > 0;
        $check->close();
    } while ($exists);
    return $user_name;
}
$user_name = generateUniqueUserName($conn);

// Insert new user
$insert = $conn->prepare("INSERT INTO user_data (user_deviceid, user_name, user_points, user_bonuspoints, user_ip) VALUES (?, ?, ?, ?, ?)");
$insert->bind_param("ssdds", $deviceid, $user_name, $signup_point, $signup_bonus, $user_ip);

if ($insert->execute()) {
    echo "Device registered successfully. Username: $user_name";

    // Prepare default notifications
    $default_notifications = json_encode([
        "Welcome to Moodzy Quiz!",
        "Your account has been created successfully."
    ]);

    // Insert into notifications table
    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_deviceid, notifications) VALUES (?, ?)");
    $notif_stmt->bind_param("ss", $deviceid, $default_notifications);
    $notif_stmt->execute();
    $notif_stmt->close();
} else {
    echo "Registration failed: " . $insert->error;
}

$insert->close();
$conn->close();
?>