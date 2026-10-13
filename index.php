<?php
session_start();

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'keydb';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Table auto-create
    $pdo->exec("CREATE TABLE IF NOT EXISTS keys_table (
        id SERIAL PRIMARY KEY,
        key_code VARCHAR(50) UNIQUE,
        days INT DEFAULT 30,
        device_type VARCHAR(20) DEFAULT '1',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        used INT DEFAULT 0,
        used_by VARCHAR(100),
        last_device VARCHAR(100),
        last_seen TIMESTAMP
    )");
} catch (PDOException $e) {
    $pdo = null;
}

$ADMIN_PASS = "brutal@124"; // 👈 Apna password

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
    <title>Login</title>
    <style>
        body{font-family:-apple-system,sans-serif;background:#111;color:#fff;text-align:center;padding:100px 20px;margin:0;}
        input,button{padding:15px;font-size:16px;border-radius:10px;border:none;margin:5px;width:80%;max-width:300px;box-sizing:border-box;}
        button{background:#007aff;color:#fff;font-weight:bold;cursor:pointer;}
    </style>
    </head><body><h2>🔐 Admin Login</h2>
    <form method="POST"><input type="password" name="pass" placeholder="Password" required><br>
    <button type="submit">Login</button></form></body></html>';
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

// Key Reset
if (isset($_GET['reset'])) {
    if ($pdo) {
        $pdo->prepare("UPDATE keys_table SET used=0, used_by=NULL, last_device=NULL WHERE id=?")->execute([(int)$_GET['reset']]);
    }
    header("Location: /");
    exit;
}

// Key Delete
if (isset($_GET['delete'])) {
    if ($pdo) {
        $pdo->prepare("DELETE FROM keys_table WHERE id=?")->execute([(int)$_GET['delete']]);
    }
    header("Location: /");
    exit;
}

// Keys fetch
$keys = [];
if ($pdo) {
    $keys = $pdo->query("SELECT * FROM keys_table ORDER BY id DESC LIMIT 50")->fetchAll();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Key Generator Panel</title>
    <style>
        body { font-family: -apple-system, sans-serif; background: #111; color: #eee; padding: 15px; margin: 0; }
        .box { background: #222; padding: 20px; border-radius: 15px; max-width: 600px; margin: auto; }
        h2 { text-align: center; }
        select, input, button { padding: 12px; margin: 5px 0; border-radius: 8px; border: none; font-size: 15px; width: 100%; box-sizing: border-box; background: #333; color: #fff; }
        button { background: #007aff; color: white; font-weight: bold; cursor: pointer; }
        .logout { background: #ff3b30; }
        .key { background: #333; padding: 12px; margin: 10px 0; border-radius: 8px; font-family: monospace; font-size: 13px; }
        .key .code { font-size: 15px; font-weight: bold; color: #00ff88; }
        .key .meta { color: #aaa; font-size: 12px; margin-top: 4px; }
        .key .actions { margin-top: 8px; display: flex; gap: 6px; flex-wrap: wrap; }
        .key button { padding: 6px 10px; font-size: 12px; width: auto; border-radius: 6px; }
        .copy { background: #00ff88; color: #000; }
        .reset { background: #ffaa00; color: #000; }
        .delete { background: #ff3b30; }
        .device { color: #4da6ff; }
        hr { border-color: #333; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="box">
        <h2>🔑 Key Generator Panel</h2>
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
            <div class="key" style="background:#00ff88;color:#000;">
                <span class="code" style="color:#000;"><?= htmlspecialchars($new_key) ?></span>
            </div>
        <?php endif; ?>
        
        <a href="?logout=1"><button class="logout">Logout</button></a>
        
        <hr>
        <h3>All Keys (<?= count($keys) ?>)</h3>
        <?php if (empty($keys)): ?>
            <p style="color:#888;">Abhi koi key nahi hai.</p>
        <?php else: ?>
            <?php foreach ($keys as $k): ?>
                <div class="key">
                    <div class="code"><?= htmlspecialchars($k['key_code']) ?></div>
                    <div class="meta">
                        📅 <?= $k['days'] ?> days · 
                        💻 <?= $k['device_type'] == 'unlimited' ? '∞ Unlimited' : '1 Device' ?> · 
                        <?php if ($k['used']): ?>
                            🔴 <span style="color:#ff6666;">USED</span>
                        <?php else: ?>
                            🟢 <span style="color:#66ff66;">UNUSED</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($k['used_by']): ?>
                        <div class="meta device">📱 Device: <?= htmlspecialchars($k['used_by']) ?></div>
                    <?php endif; ?>
                    <?php if ($k['last_seen']): ?>
                        <div class="meta">🕐 Last seen: <?= $k['last_seen'] ?></div>
                    <?php endif; ?>
                    <div class="actions">
                        <button class="copy" onclick="navigator.clipboard.writeText('<?= $k['key_code'] ?>')">📋 Copy</button>
                        <a href="?reset=<?= $k['id'] ?>" onclick="return confirm('Reset karega? Device unlock ho jayega.')"><button class="reset">🔄 Reset</button></a>
                        <a href="?delete=<?= $k['id'] ?>" onclick="return confirm('Pakka delete karna hai?')"><button class="delete">🗑 Delete</button></a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
