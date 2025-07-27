<?php
header('Content-Type: application/json');
date_default_timezone_set("Asia/Kolkata");

// DB Config
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

// Connect DB
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "DB connection failed"]);
    exit;
}

// Fetch withdrawal history
$stmt = $conn->prepare("SELECT transaction_id, redeem_id, redeem_method, sim_circle, date_time, amount, closing_balance, status FROM withdraw WHERE user_deviceid = ? ORDER BY sl DESC");
$stmt->bind_param("s", $deviceid);
$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
while ($row = $result->fetch_assoc()) {
    $dateTime = new DateTime($row['date_time']);

    $transactions[] = [
        "transaction_id"   => $row['transaction_id'],
        "redeem_id"        => $row['redeem_id'],
        "redeem_method"    => $row['redeem_method'],
        "sim_circle"       => $row['sim_circle'],
        "date"             => $dateTime->format('Y-m-d'),
        "time"             => $dateTime->format('H:i:s'),
        "amount"           => number_format((float)$row['amount'], 2),
        "closing_balance"  => number_format((float)$row['closing_balance'], 2),
        "status"           => $row['status']
    ];
}

echo json_encode([
    "status" => "success",
    "message" => "Withdraw transactions fetched",
    "transactions" => $transactions
]);

$stmt->close();
$conn->close();
?>
