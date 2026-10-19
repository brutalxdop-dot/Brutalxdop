<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(array $data, int $httpCode = 200): never {
    http_response_code($httpCode);
    echo json_encode($data);
    exit;
}

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'keydb';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO(
        "pgsql:host={$host};dbname={$dbname}",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    respond([
        'status' => 'error',
        'success' => false,
        'message' => 'Database unavailable'
    ], 500);
}

$input = $_POST;

if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $json = json_decode(file_get_contents('php://input'), true);
    if (is_array($json)) {
        $input = array_merge($input, $json);
    }
}

$action = (string)($input['action'] ?? $_GET['action'] ?? 'verify');
$key = trim((string)(
    $input['key']
    ?? $input['user_key']
    ?? $input['license']
    ?? $_GET['key']
    ?? ''
));

if ($action === 'verify' || $action === 'login' || $action === 'connect') {
    if ($key === '' || strlen($key) > 100) {
        respond([
            'status' => 'no_key',
            'success' => false,
            'valid' => false,
            'message' => 'Key required'
        ], 400);
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT id, key_code, days, device_type, device_limit,
                    device_list, created_at, referral_bonus
             FROM keys_table
             WHERE key_code = :key
             LIMIT 1'
        );
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Key lookup failed: ' . $e->getMessage());
        respond([
            'status' => 'error',
            'success' => false,
            'valid' => false,
            'message' => 'Verification failed'
        ], 500);
    }

    if (!$row) {
        respond([
            'status' => 'invalid',
            'success' => false,
            'valid' => false,
            'message' => 'Invalid key'
        ]);
    }

    try {
        $created = new DateTimeImmutable((string)$row['created_at']);
        $days = max(
            0,
            (int)$row['days'] + (int)$row['referral_bonus']
        );

        if ($days < 1) {
            respond([
                'status' => 'expired',
                'success' => false,
                'valid' => false,
                'message' => 'Key expired'
            ]);
        }

        $expiry = $created->modify("+{$days} days");
        $now = new DateTimeImmutable('now');

        if ($now >= $expiry) {
            respond([
                'status' => 'expired',
                'success' => false,
                'valid' => false,
                'message' => 'Key expired',
                'expiry' => $expiry->format(DateTimeInterface::ATOM)
            ]);
        }
    } catch (Throwable $e) {
        error_log('Expiry calculation failed: ' . $e->getMessage());
        respond([
            'status' => 'error',
            'success' => false,
            'valid' => false,
            'message' => 'Expiry validation failed'
        ], 500);
    }

    $deviceId = trim((string)($input['device_id'] ?? ''));

    if ($deviceId === '' || strlen($deviceId) > 100) {
        respond([
            'status' => 'no_device',
            'success' => false,
            'valid' => false,
            'message' => 'A stable device_id is required'
        ], 400);
    }

    $deviceType = (string)($row['device_type'] ?? '1');
    $deviceLimit = ($deviceType === 'unlimited')
        ? PHP_INT_MAX
        : max(1, (int)$deviceType);

    $devices = array_values(array_unique(array_filter(
        array_map('trim', explode(',', (string)($row['device_list'] ?? '')))
    )));

    if (!in_array($deviceId, $devices, true)) {
        if (count($devices) >= $deviceLimit) {
            respond([
                'status' => 'limit_reached',
                'success' => false,
                'valid' => false,
                'message' => 'Device limit reached'
            ]);
        }

        $devices[] = $deviceId;
    }

    try {
        $update = $pdo->prepare(
            'UPDATE keys_table
             SET used = 1,
                 used_by = :device,
                 last_device = :device,
                 last_seen = NOW(),
                 device_count = :count,
                 device_list = :devices
             WHERE id = :id'
        );

        $update->execute([
            'device' => $deviceId,
            'count' => count($devices),
            'devices' => implode(',', $devices),
            'id' => $row['id']
        ]);
    } catch (PDOException $e) {
        error_log('Key update failed: ' . $e->getMessage());
        respond([
            'status' => 'error',
            'success' => false,
            'valid' => false,
            'message' => 'Could not update key'
        ], 500);
    }

    respond([
        'status' => 'valid',
        'success' => true,
        'valid' => true,
        'days' => max(0, (int)ceil(
            ($expiry->getTimestamp() - time()) / 86400
        )),
        'expiry' => $expiry->format(DateTimeInterface::ATOM),
        'device_count' => count($devices),
        'device_limit' => $deviceLimit === PHP_INT_MAX
            ? 'unlimited'
            : $deviceLimit,
        'message' => 'Key verified'
    ]);
}

if ($action === 'heartbeat') {
    if ($key === '') {
        respond(['status' => 'no_key', 'success' => false], 400);
    }

    $stmt = $pdo->prepare(
        'UPDATE keys_table SET last_seen = NOW() WHERE key_code = :key'
    );
    $stmt->execute(['key' => $key]);

    respond([
        'status' => $stmt->rowCount() ? 'ok' : 'invalid',
        'success' => $stmt->rowCount() > 0
    ]);
}

if ($action === 'status') {
    if ($key === '') {
        respond(['status' => 'no_key', 'success' => false], 400);
    }

    $stmt = $pdo->prepare(
        'SELECT key_code, days, device_type, device_limit, used,
                device_count, last_seen, created_at, referral_bonus
         FROM keys_table WHERE key_code = :key LIMIT 1'
    );
    $stmt->execute(['key' => $key]);
    $row = $stmt->fetch();

    if (!$row) {
        respond(['status' => 'invalid', 'success' => false]);
    }

    respond(['status' => 'ok', 'success' => true, 'data' => $row]);
}

respond([
    'status' => 'invalid_action',
    'success' => false,
    'message' => 'Unsupported action'
], 400);
