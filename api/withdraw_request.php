<?php
header('Content-Type: application/json');
date_default_timezone_set("Asia/Kolkata");
require_once 'config.php';

/* ──────────  CONFIG  ────────── */
$host = DB_HOST;
$dbname = DB_NAME;
$user = DB_USER;
$password = DB_PASS;
$valid_api_key = VALID_API_KEY;

/* ──────────  INPUT  ────────── */
$api_key = $_POST['api_key'] ?? '';
$deviceid = $_POST['deviceid'] ?? '';
$pointsRequest = floatval($_POST['points'] ?? 0);
$redeem_method = $_POST['redeem_method'] ?? '';
$redeem_id = $_POST['redeem_id'] ?? '';
$sim_circle = $_POST['sim_circle'] ?? '';

if ($api_key !== $valid_api_key) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid API Key"]);
    exit;
}
if (empty($deviceid) || empty($redeem_method) || empty($redeem_id) || $pointsRequest <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing or invalid parameters"]);
    exit;
}

/* ──────────  HELPERS  ────────── */
function fmtK($n)
{
    return ($n >= 1000) ? number_format($n / 1000, 2) . 'K' : number_format($n, 2);
}
function fmtNum($n)
{
    return number_format($n, 2, '.', ',');
}   // e.g. 1,523.00

/* ──────────  DB CONNECT  ────────── */
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

/* ──────────  APP CONFIG  ────────── */
$withdraw_enabled = false;
$min_withdraw_pts = 0;
$credit_value = 1000;
$resCfg = $conn->query("SELECT config_key,config_value FROM app_config
                      WHERE config_key IN ('withdrawal_enabled','min_withdraw_amount','credit_value')");
while ($c = $resCfg->fetch_assoc()) {
    if ($c['config_key'] == 'withdrawal_enabled')
        $withdraw_enabled = strtolower($c['config_value']) === 'true';
    if ($c['config_key'] == 'min_withdraw_amount')
        $min_withdraw_pts = (float) $c['config_value'];
    if ($c['config_key'] == 'credit_value')
        $credit_value = max(1, (int) $c['config_value']);
}
$resCfg->close();

if (!$withdraw_enabled) {
    echo json_encode(["status" => "error", "message" => "Withdrawals are currently disabled."]);
    $conn->close();
    exit;
}
if ($pointsRequest < $min_withdraw_pts) {
    echo json_encode(["status" => "error", "message" => "Minimum withdrawal is $min_withdraw_pts points"]);
    $conn->close();
    exit;
}

/* ──────────  USER DATA  ────────── */
$stmt = $conn->prepare("SELECT user_credits,quiz_credit,contest_credit,user_bonuspoints,user_points
                      FROM user_data WHERE user_deviceid=?");
$stmt->bind_param("s", $deviceid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) {
    echo json_encode(["status" => "error", "message" => "User not found"]);
    $conn->close();
    exit;
}

$bonusPts = (float) $user['user_bonuspoints'];
$credits = (int) $user['user_credits'];
$quiz = (int) $user['quiz_credit'];
$contest = (int) $user['contest_credit'];

/* ──────────  TOTALS & VALIDATION  ────────── */
$totalCredits = $credits + $quiz + $contest;
$totalPoints = $bonusPts + ($totalCredits / $credit_value);

if ($pointsRequest > $totalPoints) {
    echo json_encode(["status" => "error", "message" => "Insufficient points"]);
    $conn->close();
    exit;
}

/* ───── 1️⃣ deduct bonus points ───── */
$remainPts = $pointsRequest;
if ($bonusPts >= $remainPts) {
    $bonusPts -= $remainPts;
    $remainPts = 0;
} else {
    $remainPts -= $bonusPts;
    $bonusPts = 0;
}

/* ───── 2️⃣ deduct credits ───── */
$remainCr = (int) round($remainPts * $credit_value);
if ($credits >= $remainCr) {
    $credits -= $remainCr;
    $remainCr = 0;
} else {
    $remainCr -= $credits;
    $credits = 0;
}

if ($remainCr > 0) {
    if ($quiz >= $remainCr) {
        $quiz -= $remainCr;
        $remainCr = 0;
    } else {
        $remainCr -= $quiz;
        $quiz = 0;
    }
}
if ($remainCr > 0) {
    $contest -= $remainCr;
}

/* ───── new balances ───── */
$newTotalCredits = $credits + $quiz + $contest;
$newUserPoints = $newTotalCredits / $credit_value;
$newTotalPoints = $newUserPoints + $bonusPts;

/* ───── Insert withdraw row ───── */
$txn_id = strval(mt_rand(1000000000, 9999999999));
$dateTime = date("Y-m-d H:i:s");
$ins = $conn->prepare("INSERT INTO withdraw
        (user_deviceid,transaction_id,redeem_id,redeem_method,sim_circle,date_time,
         amount,closing_balance,status)
        VALUES(?,?,?,?,?,?,?,?,'Pending')");
$ins->bind_param(
    "ssssssdd",
    $deviceid,
    $txn_id,
    $redeem_id,
    $redeem_method,
    $sim_circle,
    $dateTime,
    $pointsRequest,
    $newUserPoints
);
$success = $ins->execute();
$ins->close();

/* ───── Update user_data ───── */
$upd = $conn->prepare("UPDATE user_data
                     SET user_credits=?,quiz_credit=?,contest_credit=?,user_bonuspoints=?
                     WHERE user_deviceid=?");
$upd->bind_param("iiids", $credits, $quiz, $contest, $bonusPts, $deviceid);
$upd->execute();
$upd->close();

/* ───── Response ───── */
echo json_encode([
    "status" => $success ? "success" : "error",
    "message" => $success ? "Withdraw request placed successfully" : "Failed to place request",
    "transaction_id" => $txn_id,
    "min_withdraw_amount" => $min_withdraw_pts,
    "withdrawal_enabled" => $withdraw_enabled,

    // raw numbers
    "points" => [
        "user_points" => $newUserPoints,
        "user_bonuspoints" => $bonusPts,
        "total_points" => $newTotalPoints,
        "formatted_user_points" => fmtNum($newUserPoints),
        "formatted_user_bonuspoints" => fmtNum($bonusPts),
        "formatted_total_points" => fmtNum($newTotalPoints)
    ],

    "credits" => [
        // raw
        "user_credits" => $credits,
        "quiz_credits" => $quiz,
        "contest_credits" => $contest,
        "total_credits" => $newTotalCredits,

        // formatted
        "formatted_user_credits" => fmtK($credits),
        "formatted_quiz_credits" => fmtK($quiz),
        "formatted_contest_credits" => fmtK($contest),
        "formatted_total_credits" => fmtK($newTotalCredits)
    ]
]);

$conn->close();
?>