<?php
header("Content-Type: application/json");
date_default_timezone_set("Asia/Kolkata");

// Database config
$host = "localhost";
$dbname = "moodzy_quizy_database";
$user = "root";
$password = "";

// Get POST data
$deviceid = $_POST['deviceid'] ?? '';
if (empty($deviceid)) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Device ID required"]);
    exit;
}

// Connect to DB
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Database connection failed"]);
    exit;
}

// Update to empty JSON array
$stmt = $conn->prepare("UPDATE notifications SET notifications = '[]' WHERE user_deviceid = ?");
$stmt->bind_param("s", $deviceid);

if ($stmt->execute()) {
    echo json_encode(["status" => true, "message" => "All notifications cleared"]);
} else {
    echo json_encode(["status" => false, "message" => "Failed to clear notifications"]);
}

$stmt->close();
$conn->close();
?>
