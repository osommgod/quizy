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
$log_dir = __DIR__ . '/reward_log/';

// INPUT
$api_key = $_POST['api_key'] ?? '';
$deviceid = $_POST['deviceid'] ?? '';
$credit_type = strtolower($_POST['credit_type'] ?? '');
$amount = floatval($_POST['amount'] ?? 0);

// VALIDATION
if ($api_key !== $valid_api_key) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid API Key"]);
    exit;
}
if (empty($deviceid) || !in_array($credit_type, ['quiz', 'contest']) || $amount < 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid parameters"]);
    exit;
}
if ($amount == 0) {
    echo json_encode([
        "status" => "success",
        "message" => "Your Credits are added Successfully.",
        "credit_type" => $credit_type,
        "added_amount" => "0.00"
    ]);
    exit;
}

// DB CONNECT
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

// FETCH USER
$stmt = $conn->prepare("SELECT user_credits, quiz_credit, contest_credit, user_rts FROM user_data WHERE user_deviceid = ?");
$stmt->bind_param("s", $deviceid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(["status" => "error", "message" => "User not found"]);
    $conn->close();
    exit;
}

// FETCH CONFIG: credit_value, rtsgain values
$config_values = [
    "credit_value" => 1000,
    "rtsgain_quiz" => 0,
    "rtsgain_contest" => 0
];

$sql = "SELECT config_key, config_value FROM app_config WHERE config_key IN ('credit_value', 'rtsgain_quiz', 'rtsgain_contest')";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $k = $row['config_key'];
        $v = $row['config_value'];
        if (isset($config_values[$k])) {
            $config_values[$k] = is_numeric($v) ? floatval($v) : $config_values[$k];
        }
    }
    $result->close();
}

$credit_value = max(1, intval($config_values['credit_value']));
$rts_gain_value = $credit_type === 'quiz' ? intval($config_values['rtsgain_quiz']) : intval($config_values['rtsgain_contest']);

// UPDATE CREDITS + RTS
if ($credit_type === 'quiz') {
    $user['quiz_credit'] += $amount;
} else {
    $user['contest_credit'] += $amount;
}
$user['user_rts'] += $rts_gain_value;

$update = $conn->prepare(
    "UPDATE user_data SET quiz_credit=?, contest_credit=?, user_rts=? WHERE user_deviceid=?"
);
$update->bind_param(
    "iiis",
    $user['quiz_credit'],
    $user['contest_credit'],
    $user['user_rts'],
    $deviceid
);
$success = $update->execute();
$update->close();

// CALCULATE TOTALS
$total_credits = $user['user_credits'] + $user['quiz_credit'] + $user['contest_credit'];
$user_points = $total_credits / $credit_value;

// WRITE LOG
if ($success) {
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $log_file = $log_dir . "device_" . $deviceid . ".log";
    $log_entry = "[" . date("Y-m-d H:i:s") . "] "
        . strtoupper($credit_type) . " +"
        . number_format($amount, 2)
        . " | RTS +$rts_gain_value"
        . " | Total Credits: " . $total_credits
        . " | Points: " . number_format($user_points, 2)
        . " | RTS: " . $user['user_rts']
        . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// RESPONSE
if ($success) {
    echo json_encode([
        "status" => "success",
        "message" => "Your Credits are added Successfully.",
        "credit_type" => $credit_type,
        "added_amount" => number_format($amount, 2),
        "rts_gained" => $rts_gain_value,
        "balances" => [
            "quiz_credit" => $user['quiz_credit'],
            "contest_credit" => $user['contest_credit'],
            "total_credits" => $total_credits,
            "user_points" => number_format($user_points, 2),
            "user_rts" => $user['user_rts']
        ]
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Update failed: " . $conn->error]);
}

$conn->close();
?>