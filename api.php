<?php
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'keydb';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'msg' => 'DB connection failed']);
    exit;
}

$key = $_GET['key'] ?? '';
$device_id = $_GET['device_id'] ?? '';

if (!$key) {
    echo json_encode(['status' => 'no_key']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM keys_table WHERE key_code = ?");
$stmt->execute([$key]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(['status' => 'invalid']);
    exit;
}

// 1 Device wali key
if ($row['device_type'] == '1') {
    if ($row['used'] == 0) {
        $pdo->prepare("UPDATE keys_table SET used=1, used_by=? WHERE id=?")
            ->execute([$device_id ?: $_SERVER['REMOTE_ADDR'], $row['id']]);
        echo json_encode(['status' => 'valid', 'days' => $row['days'], 'device' => '1']);
    } elseif ($row['used_by'] == ($device_id ?: $_SERVER['REMOTE_ADDR'])) {
        echo json_encode(['status' => 'valid', 'days' => $row['days'], 'device' => '1']);
    } else {
        echo json_encode(['status' => 'used', 'msg' => 'Ye key dusre device pe use ho chuki hai']);
    }
} 
// Unlimited devices wali key
else {
    echo json_encode(['status' => 'valid', 'days' => $row['days'], 'device' => 'unlimited']);
}
