<?php
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'keydb';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

header('Content-Type: application/json');

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ─── AUTO MIGRATE (safe) ───
    $migrations = [
        "ALTER TABLE keys_table ADD COLUMN IF NOT EXISTS device_count INT DEFAULT 0",
        "ALTER TABLE keys_table ADD COLUMN IF NOT EXISTS last_device VARCHAR(100)",
        "ALTER TABLE keys_table ADD COLUMN IF NOT EXISTS last_seen TIMESTAMP",
        "ALTER TABLE keys_table ADD COLUMN IF NOT EXISTS used INT DEFAULT 0",
        "ALTER TABLE keys_table ADD COLUMN IF NOT EXISTS used_by VARCHAR(100)",
    ];
    foreach ($migrations as $sql) {
        try { $pdo->exec($sql); } catch (PDOException $e) { /* ignore */ }
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'msg' => 'Server error']);
    exit;
}

$action = $_GET['action'] ?? 'verify';
$key = $_GET['key'] ?? '';
$device_id = $_GET['device_id'] ?? $_SERVER['REMOTE_ADDR'];

// ─── ACTION 1: VERIFY / LOGIN ───
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

    // 1 Device wali key
    if ($row['device_type'] == '1') {
        if ($row['used'] == 0 || $row['used_by'] == $device_id) {
            try {
                $pdo->prepare("UPDATE keys_table SET used=1, used_by=?, last_device=?, last_seen=NOW(), device_count=COALESCE(device_count,0)+1 WHERE id=?")
                    ->execute([$device_id, $device_id, $row['id']]);
            } catch (PDOException $e) {
                // fallback
                $pdo->prepare("UPDATE keys_table SET used=1, used_by=?, last_device=?, last_seen=NOW() WHERE id=?")
                    ->execute([$device_id, $device_id, $row['id']]);
            }
            echo json_encode([
                'status' => 'valid',
                'days' => $row['days'],
                'device' => '1',
                'device_count' => ($row['device_count'] ?? 0) + 1,
                'message' => 'Login successful'
            ]);
        } else {
            echo json_encode([
                'status' => 'used',
                'msg' => 'Ye key kisi aur device pe use ho chuki hai'
            ]);
        }
    }
    // Unlimited wali key
    else {
        try {
            $pdo->prepare("UPDATE keys_table SET last_device=?, last_seen=NOW(), device_count=COALESCE(device_count,0)+1 WHERE id=?")
                ->execute([$device_id, $row['id']]);
        } catch (PDOException $e) {
            $pdo->prepare("UPDATE keys_table SET last_device=?, last_seen=NOW() WHERE id=?")
                ->execute([$device_id, $row['id']]);
        }
        echo json_encode([
            'status' => 'valid',
            'days' => $row['days'],
            'device' => 'unlimited',
            'device_count' => ($row['device_count'] ?? 0) + 1,
            'message' => 'Login successful'
        ]);
    }
    exit;
}

// ─── ACTION 2: HEARTBEAT ───
if ($action === 'heartbeat') {
    if (!$key) {
        echo json_encode(['status' => 'no_key']);
        exit;
    }
    $pdo->prepare("UPDATE keys_table SET last_seen=NOW() WHERE key_code=?")->execute([$key]);
    echo json_encode(['status' => 'ok']);
    exit;
}

// ─── ACTION 3: STATUS CHECK ───
if ($action === 'status') {
    if (!$key) {
        echo json_encode(['status' => 'no_key']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT days, device_type, used_by, last_seen, device_count FROM keys_table WHERE key_code=?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    if (!$row) {
        echo json_encode(['status' => 'invalid']);
    } else {
        echo json_encode(['status' => 'ok', 'data' => $row]);
    }
    exit;
}

echo json_encode(['status' => 'invalid_action']);
