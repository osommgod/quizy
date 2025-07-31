<?php
header('Content-Type: application/json');
date_default_timezone_set("Asia/Kolkata");
require_once "config.php";

$response = ['message' => 'failed'];

try {
    // Fetch POST values
    $deviceid = $_POST['deviceid'] ?? '';
    $apiKey = $_POST['api_key'] ?? '';

    if ($apiKey !== VALID_API_KEY || empty($deviceid)) {
        throw new Exception("Unauthorized or missing device ID.");
    }

    $date = date("Y-m-d");

    // Connect to DB
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ✅ Check if device ID exists in user_data table
    $checkUser = $pdo->prepare("SELECT user_deviceid FROM user_data WHERE user_deviceid = :dev");
    $checkUser->execute([':dev' => $deviceid]);
    if ($checkUser->rowCount() === 0) {
        throw new Exception("Device ID not registered.");
    }

    // Check if row already exists in ad_watch_counts
    $stmt = $pdo->prepare("SELECT * FROM ad_watch_counts WHERE user_deviceid = :dev AND date = :dt");
    $stmt->execute([':dev' => $deviceid, ':dt' => $date]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // Insert new row with zero counts
        $insert = $pdo->prepare("
            INSERT INTO ad_watch_counts (user_deviceid, date, admob_ad_count, unity_ad_count)
            VALUES (:dev, :dt, 0, 0)
        ");
        $insert->execute([':dev' => $deviceid, ':dt' => $date]);

        $admob = 0;
        $unity = 0;
    } else {
        $admob = (int) $row['admob_ad_count'];
        $unity = (int) $row['unity_ad_count'];
    }

    $response = [
        'message' => 'success',
        'admob_ad_count' => $admob,
        'unity_ad_count' => $unity
    ];

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
