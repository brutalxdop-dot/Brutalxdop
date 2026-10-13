<?php
// api.php - App/Game se key check karne ke liye

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
if (!$key) {
    echo json_encode(['status' => 'no_key', 'msg' => 'Key provide karo']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM keys_table WHERE key_code = ?");
$stmt->execute([$key]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(['status' => 'invalid', 'msg' => 'Key galat hai']);
} elseif ($row['used']) {
    echo json_encode(['status' => 'used', 'msg' => 'Key pehle use ho chuki hai']);
} else {
    $pdo->prepare("UPDATE keys_table SET used=1, used_by=? WHERE id=?")
        ->execute([$_SERVER['REMOTE_ADDR'], $row['id']]);
    echo json_encode([
        'status' => 'valid',
        'days' => $row['days'],
        'msg' => 'Key sahi hai'
    ]);
}
