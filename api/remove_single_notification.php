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

// Get POST data
$deviceid = $_POST['deviceid'] ?? '';
$notification = $_POST['notification'] ?? '';

if (empty($deviceid) || empty($notification)) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Missing device ID or notification"]);
    exit;
}

// Connect to DB
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Database connection failed"]);
    exit;
}

// Fetch current notifications
$stmt = $conn->prepare("SELECT notifications FROM notifications WHERE user_deviceid = ?");
$stmt->bind_param("s", $deviceid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => false, "message" => "User not found"]);
    $stmt->close();
    $conn->close();
    exit;
}

$row = $result->fetch_assoc();
$current = json_decode($row['notifications'], true);

// Remove the notification if exists
$index = array_search($notification, $current);
if ($index !== false) {
    array_splice($current, $index, 1); // remove item
    $updated = json_encode($current);

    $update_stmt = $conn->prepare("UPDATE notifications SET notifications = ? WHERE user_deviceid = ?");
    $update_stmt->bind_param("ss", $updated, $deviceid);
    $update_stmt->execute();
    $update_stmt->close();

    echo json_encode(["status" => true, "message" => "Notification removed"]);
} else {
    echo json_encode(["status" => false, "message" => "Notification not found"]);
}

$stmt->close();
$conn->close();
?>