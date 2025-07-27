<?php
header("Content-Type: application/json");
date_default_timezone_set("Asia/Kolkata");
require_once 'config.php';

// DB config
$host = DB_HOST;
$dbname = DB_NAME;
$user = DB_USER;
$password = DB_PASS;
$valid_api_key = VALID_API_KEY;

// Get device ID from POST
$deviceid = $_POST['deviceid'] ?? '';
$api_key = $_POST['api_key'] ?? '';


// VALIDATION
if ($api_key !== $valid_api_key) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid API Key"]);
    exit;
}

if (empty($deviceid)) {
    http_response_code(400);
    echo json_encode(["error" => "Device ID required"]);
    exit;
}

// DB connection
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

// Step 1: Fetch user data
$user_stmt = $conn->prepare("SELECT user_name, user_followers, user_following, user_rts FROM user_data WHERE user_deviceid = ?");
$user_stmt->bind_param("s", $deviceid);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows === 0) {
    echo json_encode(["error" => "User not found"]);
    $user_stmt->close();
    $conn->close();
    exit;
}
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

// Step 2: Fetch user rank based on RTS
$rts = (int) $user_data['user_rts'];

$rank_stmt = $conn->prepare("SELECT COUNT(*) + 1 AS user_rank FROM user_data WHERE user_rts > ?");
$rank_stmt->bind_param("i", $rts);
$rank_stmt->execute();
$rank_result = $rank_stmt->get_result();
$rank_row = $rank_result->fetch_assoc();
$user_rank = $rank_row['user_rank'] ?? null;
$rank_stmt->close();

// Step 3: Fetch links from links_config
$links_result = $conn->query("SELECT link_key, link_value FROM links_config");
$links = [];
while ($row = $links_result->fetch_assoc()) {
    $links[$row['link_key']] = $row['link_value'];
}
$links_result->close();

// Step 4: Fetch app_version and app_update from app_config
$app_version = "";
$app_update = "";

$config_stmt = $conn->prepare("SELECT config_key, config_value FROM app_config WHERE config_key IN ('app_version', 'app_update')");
$config_stmt->execute();
$config_result = $config_stmt->get_result();

while ($row = $config_result->fetch_assoc()) {
    if ($row['config_key'] === 'app_version') {
        $app_version = $row['config_value'];
    }
    if ($row['config_key'] === 'app_update') {
        $app_update = $row['config_value'];
    }
}
$config_stmt->close();

// Final output
$response = [
    "user_name" => $user_data['user_name'],
    "followers" => (int) $user_data['user_followers'],
    "following" => (int) $user_data['user_following'],
    "rts" => $rts,
    "rank" => $user_rank,
    "links" => $links,
    "app_version" => $app_version,
    "app_update" => $app_update
];

echo json_encode($response);
$conn->close();
?>