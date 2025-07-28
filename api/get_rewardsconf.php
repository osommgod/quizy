<?php
header('Content-Type: application/json');
date_default_timezone_set("Asia/Kolkata");
require_once 'config.php';

// DB CONFIG
$host = DB_HOST;
$dbname = DB_NAME;
$user = DB_USER;
$password = DB_PASS;
$valid_api_key = VALID_API_KEY;

// INPUT
$api_key = $_POST['api_key'] ?? '';
$device_id = $_POST['device_id'] ?? '';

if ($api_key !== $valid_api_key) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid API Key"
    ]);
    exit;
}

// CONNECT
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed"
    ]);
    exit;
}

// FETCH VALUES
$quiz_reward = 0;
$contest_reward = 0;
$contest_interval = 0;
$user_rts = 0;
$min_rts = 0;
$admob_adid = "";
$unity_adid = "";
$ad_type = "";

if (!empty($device_id)) {
    $stmt = $conn->prepare("SELECT user_rts FROM user_data WHERE device_id = ?");
    $stmt->bind_param("s", $device_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $user_rts = intval($row['user_rts']);
        }
        $result->close();
    }
    $stmt->close();
}

$sql = "SELECT config_key, config_value FROM app_config 
        WHERE config_key IN ('quiz_reward', 'contest_reward', 'contest_interval', 'min_rts', 'admob_adid', 'unity_adid', 'ad_type')";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        switch ($row['config_key']) {
            case 'quiz_reward':
                $quiz_reward = floatval($row['config_value']);
                break;
            case 'contest_reward':
                $contest_reward = floatval($row['config_value']);
                break;
            case 'contest_interval':
                $contest_interval = intval($row['config_value']);
                break;
            case 'min_rts':
                $min_rts = intval($row['config_value']);
                break;
            case 'admob_adid':
                $admob_adid = $row['config_value'];
                break;
            case 'unity_adid':
                $unity_adid = $row['config_value'];
                break;
            case 'ad_type':
                $ad_type = $row['config_value'];
                break;
        }
    }
    $result->close();
}

// RESPONSE
echo json_encode([
    "status" => "success",
    "message" => "Reward values fetched successfully",
    "formatted_quiz_reward" => number_format($quiz_reward, 2),
    "formatted_contest_reward" => number_format($contest_reward, 2),
    "quiz_reward" => $quiz_reward,
    "contest_reward" => $contest_reward,
    "contest_interval" => $contest_interval,
    "ad_type" => $ad_type,
    "admob_adid" => $admob_adid,
    "unity_adid" => $unity_adid,
    "min_rts" => $min_rts,
    "user_rts" => $user_rts
]);

$conn->close();
?>