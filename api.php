<?php
// ==========================================
// UNIVERSAL API - Supports Lua, Normal Mods, & Third-party
// ==========================================

// 1. POST Request ko GET mein convert karna (Lua compatibility)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    parse_str(file_get_contents("php://input"), $_POST);
    // Agar POST me action nahi hai, toh default verify maan lo
    if (!isset($_GET['action']) && !isset($_POST['action'])) {
        $_GET['action'] = 'verify';
    }
} else {
    $_POST = $_POST ?? []; 
}

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'keydb';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // CORS allow (kuch mods ke liye zaroori)

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ─── AUTO MIGRATE ───
    $migrations = [
        "ALTER TABLE keys_table ADD COLUMN IF NOT EXISTS device_count INT DEFAULT 0",
        "ALTER TABLE keys_table ADD COLUMN IF NOT EXISTS device_limit INT DEFAULT 1",
        "ALTER TABLE keys_table ADD COLUMN IF NOT EXISTS device_list TEXT DEFAULT ''",
        "ALTER TABLE keys_table ADD COLUMN IF NOT EXISTS last_device VARCHAR(100)",
        "ALTER TABLE keys_table ADD COLUMN IF NOT EXISTS last_seen TIMESTAMP",
        "ALTER TABLE keys_table ADD COLUMN IF NOT EXISTS used INT DEFAULT 0",
        "ALTER TABLE keys_table ADD COLUMN IF NOT EXISTS used_by VARCHAR(100)",
        "ALTER TABLE keys_table ADD COLUMN IF NOT EXISTS referrer VARCHAR(50) DEFAULT NULL",
        "ALTER TABLE keys_table ADD COLUMN IF NOT EXISTS referral_bonus INT DEFAULT 0",
        "ALTER TABLE keys_table ADD COLUMN IF NOT EXISTS device_name VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE keys_table ADD COLUMN IF NOT EXISTS device_ip VARCHAR(50) DEFAULT NULL",
    ];
    foreach ($migrations as $sql) {
        try { $pdo->exec($sql); } catch (PDOException $e) {}
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'msg' => 'Server error', 'success' => false]);
    exit;
}

// ─── UNIVERSAL PARAMETER PARSING ───
// Yeh har tarah ke mods ke parameter names ko pakad lega
$action = $_GET['action'] ?? $_POST['action'] ?? 'verify';
$key = $_GET['key'] ?? $_GET['user_key'] ?? $_GET['license'] ?? $_GET['license_key'] ?? $_POST['key'] ?? $_POST['user_key'] ?? '';
$device_id = $_GET['device_id'] ?? $_GET['hwid'] ?? $_GET['serial'] ?? $_GET['uid'] ?? $_POST['device_id'] ?? $_POST['hwid'] ?? $_POST['serial'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$device_name = $_GET['device_name'] ?? $_GET['model'] ?? $_POST['device_name'] ?? 'Unknown Device';
$device_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// ─── VERIFY / LOGIN ───
if ($action === 'verify' || $action === 'login' || $action === 'connect') {
    if (!$key) {
        echo json_encode(['status' => 'no_key', 'success' => false, 'msg' => 'Key required', 'message' => 'Key required']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM keys_table WHERE key_code = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode(['status' => 'invalid', 'success' => false, 'msg' => 'Galat key', 'message' => 'Invalid Key']);
        exit;
    }

    // Parse device list
    $device_list = !empty($row['device_list']) ? explode(',', $row['device_list']) : [];
    $device_list = array_filter($device_list);
    $device_limit = (int)($row['device_limit'] ?? 1);
    if ($row['device_type'] == 'unlimited') {
        $device_limit = $device_limit > 0 ? $device_limit : 999999;
    }

    // Check if already registered on this device
    $already_registered = in_array($device_id, $device_list);

    // Check limit
    if (!$already_registered && count($device_list) >= $device_limit) {
        echo json_encode([
            'status' => 'limit_reached',
            'success' => false,
            'msg' => "Device limit reached ({$device_limit} max). Contact @SIDHU_MODZ",
            'message' => "Device limit reached"
        ]);
        exit;
    }

    // Register this device if new
    if (!$already_registered) {
        $device_list[] = $device_id;
        $new_device_list = implode(',', $device_list);
        $new_count = count($device_list);
        $pdo->prepare("UPDATE keys_table SET used=1, used_by=?, last_device=?, last_seen=NOW(), device_count=?, device_list=?, device_name=?, device_ip=? WHERE id=?")
            ->execute([$device_id, $device_id, $new_count, $new_device_list, $device_name, $device_ip, $row['id']]);
        $count_now = $new_count;
    } else {
        // Already registered — just update last seen
        $pdo->prepare("UPDATE keys_table SET last_seen=NOW(), last_device=?, device_name=?, device_ip=? WHERE id=?")
            ->execute([$device_id, $device_name, $device_ip, $row['id']]);
        $count_now = count($device_list);
    }

    $days_left = $row['days'] + (int)($row['referral_bonus'] ?? 0);
    
    // 🔥 UNIVERSAL RESPONSE: Sabhi mods ke liye (true, valid, success)
    echo json_encode([
        'status' => 'valid',       // Lua script
        'success' => true,         // Normal mods
        'valid' => true,           // Kuch aur mods
        'days' => $days_left,      // Days left
        'expiry' => $days_left,    // Expiry format
        'device' => $row['device_type'],
        'device_count' => $count_now,
        'device_limit' => $device_limit,
        'message' => 'Login successful',
        'msg' => 'Login successful',
        'data' => [
            'status' => 'valid',
            'days' => $days_left
        ]
    ]);
    exit;
}

// ─── HEARTBEAT ───
if ($action === 'heartbeat') {
    if (!$key) {
        echo json_encode(['status' => 'no_key', 'success' => false]);
        exit;
    }
    $pdo->prepare("UPDATE keys_table SET last_seen=NOW() WHERE key_code=?")->execute([$key]);
    echo json_encode(['status' => 'ok', 'success' => true]);
    exit;
}

// ─── STATUS ───
if ($action === 'status') {
    if (!$key) {
        echo json_encode(['status' => 'no_key', 'success' => false]);
        exit;
    }
    $stmt = $pdo->prepare("SELECT days, device_type, device_limit, used_by, last_seen, device_count, device_list, referrer, referral_bonus FROM keys_table WHERE key_code=?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    if (!$row) {
        echo json_encode(['status' => 'invalid', 'success' => false]);
    } else {
        echo json_encode(['status' => 'ok', 'success' => true, 'data' => $row]);
    }
    exit;
}

// ─── REFERRAL ───
if ($action === 'refer') {
    $referrer = $_GET['referrer'] ?? $_POST['referrer'] ?? '';
    $referred = $_GET['referred'] ?? $_POST['referred'] ?? '';
    $bonus = (int)($_GET['bonus'] ?? $_POST['bonus'] ?? 7);

    if (!$referrer || !$referred || $referrer === $referred) {
        echo json_encode(['status' => 'invalid', 'success' => false, 'msg' => 'Invalid referral']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM keys_table WHERE key_code IN (?, ?)");
    $stmt->execute([$referrer, $referred]);
    $rows = $stmt->fetchAll();
    if (count($rows) < 2) {
        echo json_encode(['status' => 'invalid', 'success' => false, 'msg' => 'One or both keys invalid']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT referrer FROM keys_table WHERE key_code = ?");
    $stmt->execute([$referred]);
    $referred_row = $stmt->fetch();
    if (!empty($referred_row['referrer'])) {
        echo json_encode(['status' => 'already', 'success' => false, 'msg' => 'Already referred']);
        exit;
    }

    $pdo->prepare("UPDATE keys_table SET referral_bonus = referral_bonus + ? WHERE key_code = ?")
        ->execute([$bonus, $referrer]);
    $pdo->prepare("UPDATE keys_table SET referral_bonus = referral_bonus + ?, referrer = ? WHERE key_code = ?")
        ->execute([$bonus, $referrer, $referred]);

    echo json_encode([
        'status' => 'success',
        'success' => true,
        'msg' => "Referral successful! Both got +{$bonus} days",
        'referrer' => $referrer,
        'referred' => $referred,
        'bonus' => $bonus
    ]);
    exit;
}

echo json_encode(['status' => 'invalid_action', 'success' => false]);
