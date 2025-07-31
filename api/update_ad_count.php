<?php
header('Content-Type: application/json');
date_default_timezone_set("Asia/Kolkata");
require_once "config.php";

$response = ['message' => 'failed'];

try {
    $deviceid = $_POST['deviceid'] ?? '';
    $ad_type = $_POST['ad_type'] ?? ''; // Expected: admob or unity
    $apiKey = $_POST['api_key'] ?? '';

    if ($apiKey !== VALID_API_KEY || empty($deviceid) || !in_array($ad_type, ['admob', 'unity'])) {
        throw new Exception("Unauthorized or invalid parameters.");
    }

    $date = date("Y-m-d");

    // Connect to DB
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ✅ Check if device ID exists in user_data table
    $checkUser = $pdo->prepare("SELECT id FROM user_data WHERE user_deviceid = :dev");
    $checkUser->execute([':dev' => $deviceid]);
    if ($checkUser->rowCount() === 0) {
        throw new Exception("Device ID not registered.");
    }

    // Check for existing row
    $stmt = $pdo->prepare("SELECT * FROM ad_watch_counts WHERE user_deviceid = :dev AND date = :dt");
    $stmt->execute([':dev' => $deviceid, ':dt' => $date]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // Insert new with correct initial values
        $admob = ($ad_type === 'admob') ? 1 : 0;
        $unity = ($ad_type === 'unity') ? 1 : 0;

        $insert = $pdo->prepare("
            INSERT INTO ad_watch_counts (user_deviceid, date, admob_ad_count, unity_ad_count)
            VALUES (:dev, :dt, :admob, :unity)
        ");
        $insert->execute([
            ':dev' => $deviceid,
            ':dt' => $date,
            ':admob' => $admob,
            ':unity' => $unity
        ]);
    } else {
        // Update existing record
        if ($ad_type === 'admob') {
            $update = $pdo->prepare("
                UPDATE ad_watch_counts 
                SET admob_ad_count = admob_ad_count + 1 
                WHERE user_deviceid = :dev AND date = :dt
            ");
        } else {
            $update = $pdo->prepare("
                UPDATE ad_watch_counts 
                SET unity_ad_count = unity_ad_count + 1 
                WHERE user_deviceid = :dev AND date = :dt
            ");
        }
        $update->execute([':dev' => $deviceid, ':dt' => $date]);
    }

    // Return updated values
    $stmt = $pdo->prepare("
        SELECT admob_ad_count, unity_ad_count 
        FROM ad_watch_counts 
        WHERE user_deviceid = :dev AND date = :dt
    ");
    $stmt->execute([':dev' => $deviceid, ':dt' => $date]);
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);

    $response = [
        'message' => 'success',
        'admob_ad_count' => (int) $updated['admob_ad_count'],
        'unity_ad_count' => (int) $updated['unity_ad_count']
    ];

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
