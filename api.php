<?php
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'keydb';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

header('Content-Type: application/json');

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
    echo json_encode(['status' => 'error', 'msg' => 'Server error']);
    exit;
}

$action = $_GET['action'] ?? 'verify';
$key = $_GET['key'] ?? '';
$device_id = $_GET['device_id'] ?? $_SERVER['REMOTE_ADDR'];
$device_name = $_GET['device_name'] ?? 'Unknown Device';
$device_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// ─── VERIFY / LOGIN ───
if ($action === 'verify') {
    if (!$key) {
        echo json_encode(['status' => 'no_key', 'msg' => 'Key required']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM keys_table WHERE key_code = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode(['status' => 'invalid', 'msg' => 'Galat key']);
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
            'msg' => "Device limit reached ({$device_limit} max). Contact @SIDHU_MODZ"
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

    echo json_encode([
        'status' => 'valid',
        'days' => $row['days'] + (int)($row['referral_bonus'] ?? 0),
        'device' => $row['device_type'],
        'device_count' => $count_now,
        'device_limit' => $device_limit,
        'message' => 'Login successful'
    ]);
    exit;
}

// ─── HEARTBEAT ───
if ($action === 'heartbeat') {
    if (!$key) {
        echo json_encode(['status' => 'no_key']);
        exit;
    }
    $pdo->prepare("UPDATE keys_table SET last_seen=NOW() WHERE key_code=?")->execute([$key]);
    echo json_encode(['status' => 'ok']);
    exit;
}

// ─── STATUS ───
if ($action === 'status') {
    if (!$key) {
        echo json_encode(['status' => 'no_key']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT days, device_type, device_limit, used_by, last_seen, device_count, device_list, referrer, referral_bonus FROM keys_table WHERE key_code=?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    if (!$row) {
        echo json_encode(['status' => 'invalid']);
    } else {
        echo json_encode(['status' => 'ok', 'data' => $row]);
    }
    exit;
}

// ─── REFERRAL: Register referrer ───
if ($action === 'refer') {
    $referrer = $_GET['referrer'] ?? '';
    $referred = $_GET['referred'] ?? '';
    $bonus = (int)($_GET['bonus'] ?? 7);

    if (!$referrer || !$referred || $referrer === $referred) {
        echo json_encode(['status' => 'invalid', 'msg' => 'Invalid referral']);
        exit;
    }

    // Check both keys exist
    $stmt = $pdo->prepare("SELECT * FROM keys_table WHERE key_code IN (?, ?)");
    $stmt->execute([$referrer, $referred]);
    $rows = $stmt->fetchAll();
    if (count($rows) < 2) {
        echo json_encode(['status' => 'invalid', 'msg' => 'One or both keys invalid']);
        exit;
    }

    // Check if referred key already has a referrer
    $stmt = $pdo->prepare("SELECT referrer FROM keys_table WHERE key_code = ?");
    $stmt->execute([$referred]);
    $referred_row = $stmt->fetch();
    if (!empty($referred_row['referrer'])) {
        echo json_encode(['status' => 'already', 'msg' => 'Already referred']);
        exit;
    }

    // Give bonus to both
    $pdo->prepare("UPDATE keys_table SET referral_bonus = referral_bonus + ? WHERE key_code = ?")
        ->execute([$bonus, $referrer]);
    $pdo->prepare("UPDATE keys_table SET referral_bonus = referral_bonus + ?, referrer = ? WHERE key_code = ?")
        ->execute([$bonus, $referrer, $referred]);

    echo json_encode([
        'status' => 'success',
        'msg' => "Referral successful! Both got +{$bonus} days",
        'referrer' => $referrer,
        'referred' => $referred,
        'bonus' => $bonus
    ]);
    exit;
}

echo json_encode(['status' => 'invalid_action']);
