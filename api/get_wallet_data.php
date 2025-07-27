<?php
header('Content-Type: application/json');
date_default_timezone_set("Asia/Kolkata");

// DB CONFIG
$host     = "localhost";
$dbname   = "moodzy_quizy_database";
$user     = "root";
$password = "";

// INPUT
$deviceid = $_POST['deviceid'] ?? '';
if (empty($deviceid)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing device ID"]);
    exit;
}

// FORMATTERS
function formatCredits($amount) {
    return ($amount >= 1000)
        ? number_format($amount / 1000, 2) . "K"
        : number_format($amount, 2);
}

function formatReadable($value) {
    return number_format($value, 2, '.', ',');
}

// DB CONNECT
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "DB connection failed"]);
    exit;
}

// FETCH WALLET DATA
$stmt = $conn->prepare("
    SELECT user_points, user_bonuspoints, user_credits, quiz_credit, contest_credit
    FROM user_data WHERE user_deviceid = ?
");
$stmt->bind_param("s", $deviceid);
$stmt->execute();
$result = $stmt->get_result();

if (!$row = $result->fetch_assoc()) {
    echo json_encode(["status" => "error", "message" => "User not found"]);
    $stmt->close();
    $conn->close();
    exit;
}

// Raw values
$user_points       = (float)$row['user_points'];
$user_bonuspoints  = (float)$row['user_bonuspoints'];
$user_credits      = (int)$row['user_credits'];
$quiz_credits      = (int)$row['quiz_credit'];
$contest_credits   = (int)$row['contest_credit'];

// Totals
$total_points  = $user_points + $user_bonuspoints;
$total_credits = $user_credits + $quiz_credits + $contest_credits;

// CONFIG
$min_withdraw = 0;
$withdrawal_enabled = false;

$config_query = "
    SELECT config_key, config_value FROM app_config
    WHERE config_key IN ('min_withdraw_amount', 'withdrawal_enabled')
";
if ($cfgResult = $conn->query($config_query)) {
    while ($cfg = $cfgResult->fetch_assoc()) {
        if ($cfg['config_key'] === 'min_withdraw_amount') {
            $min_withdraw = (int)$cfg['config_value'];
        } elseif ($cfg['config_key'] === 'withdrawal_enabled') {
            $withdrawal_enabled = strtolower($cfg['config_value']) === 'true';
        }
    }
    $cfgResult->close();
}

// FINAL RESPONSE
echo json_encode([
    "status" => "success",
    "message" => "Wallet data fetched",
    "min_withdraw_amount" => $min_withdraw,
    "withdrawal_enabled"  => $withdrawal_enabled,

    // Raw values
    "user_points"      => $user_points,
    "user_bonuspoints" => $user_bonuspoints,
    "total_points"     => $total_points,

    // Formatted values
    "formatted_user_points"      => formatReadable($user_points),
    "formatted_user_bonuspoints" => formatReadable($user_bonuspoints),
    "formatted_total_points"     => formatReadable($total_points),

    "credits" => [
        "user_credits"    => $user_credits,
        "quiz_credits"    => $quiz_credits,
        "contest_credits" => $contest_credits,
        "total_credits"   => $total_credits,

        "formatted_user_credits"    => formatCredits($user_credits),
        "formatted_quiz_credits"    => formatCredits($quiz_credits),
        "formatted_contest_credits" => formatCredits($contest_credits),
        "formatted_total_credits"   => formatCredits($total_credits)
    ]
]);

$stmt->close();
$conn->close();
?>
