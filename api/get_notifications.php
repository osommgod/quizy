<?php
header("Content-Type: application/json");
date_default_timezone_set("Asia/Kolkata");

// MySQL config
$host = "localhost";
$dbname = "moodzy_quizy_database";
$user = "root";
$password = "";

// Get deviceid from POST
$deviceid = $_POST['deviceid'] ?? '';
if (empty($deviceid)) {
    http_response_code(400);
    echo json_encode(["error" => "Missing device ID"]);
    exit;
}

// Connect to database
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

// Fetch notifications
$stmt = $conn->prepare("SELECT notifications FROM notifications WHERE user_deviceid = ?");
$stmt->bind_param("s", $deviceid);
$stmt->execute();
$stmt->bind_result($notifications_json);
$stmt->fetch();
$stmt->close();
$conn->close();

if (empty($notifications_json)) {
    echo json_encode([
        "deviceid" => $deviceid,
        "notifications" => []
    ]);
} else {
    echo json_encode([
        "deviceid" => $deviceid,
        "notifications" => json_decode($notifications_json)
    ]);
}
?>
