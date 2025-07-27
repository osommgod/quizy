<?php
header('Content-Type: application/json');
date_default_timezone_set("Asia/Kolkata");

// MySQL config
$host = "localhost";
$dbname = "moodzy_quizy_database";
$user = "root";
$password = "";
$valid_api_key = "9b5e35a1-4d20-427d-97f3-83c17499a7c2";

// Get POST data
$deviceid = $_POST['deviceid'] ?? '';
$api_key = $_POST['api_key'] ?? '';

function jsonResponse($status, $message, $extra = []) {
    echo json_encode(array_merge([
        "status" => $status,
        "message" => $message
    ], $extra));
    exit;
}

// Get user IP
function getUserIP() {
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
}
$user_ip = getUserIP();

// Log headers (optional)
function logHeaders($ip) {
    $headers = getallheaders();
    $logData = "---- IP: $ip @ " . date('Y-m-d H:i:s') . " ----\n";
    foreach ($headers as $key => $value) {
        $logData .= "$key: $value\n";
    }
    $logData .= "---------------------------\n";
    file_put_contents(__DIR__ . '/logs/request_headers.log', $logData, FILE_APPEND);
}
logHeaders($user_ip);

// Validate inputs
if ($api_key !== $valid_api_key) {
    http_response_code(401);
    jsonResponse("error", "Invalid API key");
}
if (empty($deviceid)) {
    http_response_code(400);
    jsonResponse("error", "Missing device ID");
}

// Connect to DB
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    jsonResponse("error", "DB connection failed: " . $conn->connect_error);
}

// Blocked IP check
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
    jsonResponse("error", "Access denied. Your IP is blocked.");
}
$check_block->close();

// Already registered?
$stmt = $conn->prepare("SELECT user_deviceid FROM user_data WHERE user_deviceid = ?");
$stmt->bind_param("s", $deviceid);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    $conn->close();
    jsonResponse("exists", "Device already registered.");
}
$stmt->close();

// Fetch signup config
$signup_point = 0;
$signup_bonus = 0;
$config_result = $conn->query("SELECT config_key, config_value FROM app_config WHERE config_key IN ('signup_point', 'signup_bonuspoints')");
while ($row = $config_result->fetch_assoc()) {
    if ($row['config_key'] === 'signup_point') $signup_point = floatval($row['config_value']);
    if ($row['config_key'] === 'signup_bonuspoints') $signup_bonus = floatval($row['config_value']);
}
$config_result->close();

// Generate unique username
function generateUniqueUsername($conn, $maxRetries = 5) {
    for ($i = 0; $i < $maxRetries; $i++) {
        $username = "User" . rand(100000, 999999);
        $check = $conn->prepare("SELECT user_name FROM user_data WHERE user_name = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $check->store_result();
        if ($check->num_rows === 0) {
            $check->close();
            return $username;
        }
        $check->close();
    }
    return null;
}

// Insert with retry
$success = false;
$maxRetries = 3;

for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
    $user_name = generateUniqueUsername($conn);
    if (!$user_name) {
        jsonResponse("error", "Username generation failed. Try again.");
    }

    $conn->begin_transaction();

    try {
        // Insert user
        $insert = $conn->prepare("INSERT INTO user_data (user_deviceid, user_name, user_points, user_bonuspoints, user_ip) VALUES (?, ?, ?, ?, ?)");
        $insert->bind_param("ssdds", $deviceid, $user_name, $signup_point, $signup_bonus, $user_ip);
        $insert->execute();
        $insert->close();

        // Insert notification
        $notifications = json_encode([
            "Welcome to Moodzy Quiz!\nYour account has been created successfully."
        ]);
        $notif = $conn->prepare("INSERT INTO notifications (user_deviceid, notifications) VALUES (?, ?)");
        $notif->bind_param("ss", $deviceid, $notifications);
        $notif->execute();
        $notif->close();

        $conn->commit();
        $success = true;

        jsonResponse("success", "Registration successful", [
            "username" => $user_name,
            "points" => $signup_point,
            "bonus" => $signup_bonus
        ]);

    } catch (mysqli_sql_exception $e) {
        $conn->rollback();

        // If username was duplicate, retry
        if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'user_name') !== false) {
            continue;
        } else {
            jsonResponse("error", "Database error: " . $e->getMessage());
        }
    }
}

$conn->close();
jsonResponse("error", "Registration failed after retries.");
?>
