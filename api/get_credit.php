<?php
header('Content-Type: application/json');
date_default_timezone_set("Asia/Kolkata");

// DB config
$host = "localhost";
$dbname = "moodzy_quizy_database";
$user = "root";
$password = "";

// Get POST data
$deviceid = $_POST['deviceid'] ?? '';

if (empty($deviceid)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing device ID"]);
    exit;
}

// Format credits with decimal support (e.g., 15500 -> 15.5K or 15.50K)
function formatCredit($amount) {
    if ($amount >= 1000) {
        $formatted = $amount / 1000;
        return number_format($formatted, ($formatted * 10) % 10 === 0 ? 0 : 2) . "K";
    }
    return number_format($amount, (is_float($amount) ? 2 : 0));
}

// DB connect
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "DB connection failed"]);
    exit;
}

// Fetch credits
$stmt = $conn->prepare("SELECT user_credits, quiz_credit, contest_credit FROM user_data WHERE user_deviceid = ?");
$stmt->bind_param("s", $deviceid);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $user_credits = floatval($row['user_credits']);
    $quiz_credit = floatval($row['quiz_credit']);
    $contest_credit = floatval($row['contest_credit']);
    $total_credit = $user_credits + $quiz_credit + $contest_credit;

    echo json_encode([
        "status" => "success",
        "message" => "Credits fetched",
        "user_credits" => formatCredit($user_credits),
        "quiz_credits" => formatCredit($quiz_credit),
        "contest_credits" => formatCredit($contest_credit),
	"total_credits" => $total_credit,
        "fomatted_total_credits" => formatCredit($total_credit)
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "User not found"]);
}

$stmt->close();
$conn->close();
?>
