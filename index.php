<?php
session_start();

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'keydb';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $pdo = null;
}

// 🔐 Admin Password (isko change karna)
$ADMIN_PASS = "brutal@124";

// Login
if (isset($_POST['pass']) && $_POST['pass'] === $ADMIN_PASS) {
    $_SESSION['logged'] = true;
}
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: /");
    exit;
}

// Login form
if (empty($_SESSION['logged'])) {
    echo '<!DOCTYPE html><html><head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Key Panel</title>
    <style>
        body{font-family:-apple-system,sans-serif;background:#111;color:#fff;text-align:center;padding:100px 20px;margin:0;}
        input,button{padding:15px;font-size:16px;border-radius:10px;border:none;margin:5px;width:80%;max-width:300px;box-sizing:border-box;}
        button{background:#007aff;color:#fff;font-weight:bold;cursor:pointer;}
    </style>
    </head><body>
    <h2>🔐 Admin Login</h2>
    <form method="POST">
        <input type="password" name="pass" placeholder="Password" required><br>
        <button type="submit">Login</button>
    </form>
    </body></html>';
    exit;
}

// Key Generate
$new_key = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['days'])) {
    $key = strtoupper(bin2hex(random_bytes(8)));
    $days = (int)$_POST['days'];
    $device_type = $_POST['device_type'] ?? '1';
    if ($pdo) {
        $stmt = $pdo->prepare("INSERT INTO keys_table (key_code, days, device_type) VALUES (?, ?, ?)");
        $stmt->execute([$key, $days, $device_type]);
    }
    $new_key = $key;
}

// Keys fetch
$keys = [];
if ($pdo) {
    $keys = $pdo->query("SELECT * FROM keys_table ORDER BY id DESC LIMIT 20")->fetchAll();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Key Generator Panel</title>
    <style>
        body { font-family: -apple-system, sans-serif; background: #111; color: #eee; padding: 15px; margin: 0; }
        .box { background: #222; padding: 20px; border-radius: 15px; max-width: 500px; margin: auto; }
        h2 { text-align: center; }
        select, input, button { padding: 12px; margin: 5px 0; border-radius: 8px; border: none; font-size: 16px; width: 100%; box-sizing: border-box; background: #333; color: #fff; }
        button { background: #007aff; color: white; font-weight: bold; cursor: pointer; }
        .logout { background: #ff3b30; margin-top: 10px; }
        .key { background: #333; padding: 12px; margin: 8px 0; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; font-family: monospace; font-size: 13px; }
        .copy { background: #00ff88; color: #000; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; border: none; width: auto; }
        hr { border-color: #333; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="box">
        <h2>🔑 Key Generator</h2>
        <form method="POST">
            <select name="days" required>
                <option value="1">1 Day</option>
                <option value="3">3 Days</option>
                <option value="7">7 Days</option>
                <option value="15">15 Days</option>
                <option value="30" selected>30 Days</option>
                <option value="60">60 Days</option>
                <option value="90">90 Days</option>
                <option value="180">180 Days</option>
                <option value="365">365 Days</option>
            </select>
            <select name="device_type" required>
                <option value="1">1 Device (Single)</option>
                <option value="unlimited">Unlimited Devices</option>
            </select>
            <button type="submit">Generate Key</button>
        </form>
        
        <?php if ($new_key): ?>
            <div class="key" style="background:#00ff88;color:#000;font-weight:bold;">
                <?= htmlspecialchars($new_key) ?>
            </div>
        <?php endif; ?>
        
        <a href="?logout=1"><button class="logout">Logout</button></a>
        
        <hr>
        <h3>Recent Keys:</h3>
        <?php if (empty($keys)): ?>
            <p style="color:#888;">Abhi koi key generate nahi hui.</p>
        <?php else: ?>
            <?php foreach ($keys as $k): ?>
                <div class="key">
                    <span>
                        <?= htmlspecialchars($k['key_code']) ?>
                        <small style="color:#888;display:block;">(<?= $k['days'] ?>d · <?= $k['device_type'] == 'unlimited' ? '∞' : '1 dev' ?>)</small>
                    </span>
                    <button class="copy" onclick="navigator.clipboard.writeText('<?= $k['key_code'] ?>')">📋</button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
