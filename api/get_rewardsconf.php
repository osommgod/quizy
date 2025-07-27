<?php
header('Content-Type: application/json');
date_default_timezone_set("Asia/Kolkata");

// DB CONFIG
$host     = "localhost";
$dbname   = "moodzy_quizy_database";
$user     = "root";
$password = "";
$valid_api_key = "9b5e35a1-4d20-427d-97f3-83c17499a7c2";

// INPUT
$api_key = $_POST['api_key'] ?? '';

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
$quiz_reward    = 0;
$contest_reward = 0;
$contest_interval = 0;

$sql = "SELECT config_key, config_value FROM app_config WHERE config_key IN ('quiz_reward', 'contest_reward','contest_interval')";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        if ($row['config_key'] === 'quiz_reward') {
            $quiz_reward = floatval($row['config_value']);
        }
        if ($row['config_key'] === 'contest_reward') {
            $contest_reward = floatval($row['config_value']);
        }
        if ($row['config_key'] === 'contest_interval') {
            $contest_interval = intval($row['config_value']);
        }
    }
    $result->close();
}

// RESPONSE
echo json_encode([
    "status" => "success",
    "message" => "Reward values fetched successfully",
    "formatted_quiz_reward"    => number_format($quiz_reward, 2),
    "formatted_contest_reward" => number_format($contest_reward, 2),
    "quiz_reward"    => $quiz_reward,
    "contest_reward" => $contest_reward,
    "contest_interval" => $contest_interval
]);

$conn->close();
?>
