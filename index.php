<?php
session_start();

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'keydb';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

// ─── CONFIG ───
$TELEGRAM = "xdopshit";
$PANEL_PRICE = "₹190";
$PANEL_NAME = "BRUTAL PANEL";
$BG_IMAGE = "https://raw.githubusercontent.com/brutalxdop-dot/Brutalxdop/main/IMG_3017.jpeg";

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ─── CREATE TABLE IF NOT EXISTS ───
    $pdo->exec("CREATE TABLE IF NOT EXISTS keys_table (
        id SERIAL PRIMARY KEY,
        key_code VARCHAR(50) UNIQUE,
        days INT DEFAULT 30,
        device_type VARCHAR(20) DEFAULT '1',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        used INT DEFAULT 0,
        used_by VARCHAR(100),
        last_device VARCHAR(100),
        last_seen TIMESTAMP,
        device_count INT DEFAULT 0
    )");

    // ─── AUTO MIGRATE: Missing columns add karo ───
    $migrations = [
        "ALTER TABLE keys_table ADD COLUMN IF NOT EXISTS device_count INT DEFAULT 0",
        "ALTER TABLE keys_table ADD COLUMN IF NOT EXISTS last_device VARCHAR(100)",
        "ALTER TABLE keys_table ADD COLUMN IF NOT EXISTS last_seen TIMESTAMP",
        "ALTER TABLE keys_table ADD COLUMN IF NOT EXISTS used INT DEFAULT 0",
        "ALTER TABLE keys_table ADD COLUMN IF NOT EXISTS used_by VARCHAR(100)",
        "ALTER TABLE keys_table ADD COLUMN IF NOT EXISTS device_type VARCHAR(20) DEFAULT '1'",
    ];
    foreach ($migrations as $sql) {
        try { $pdo->exec($sql); } catch (PDOException $e) { /* ignore */ }
    }

    // ─── ADMINS TABLE ───
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) UNIQUE,
        password_hash VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $check = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    if ($check == 0) {
        $hashed = password_hash("brutal@124", PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)")
            ->execute(["brutal", $hashed]);
    }
} catch (PDOException $e) {
    $pdo = null;
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: /");
    exit;
}

// Login
$login_error = "";
if (isset($_POST['username']) && isset($_POST['password'])) {
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$_POST['username']]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($_POST['password'], $admin['password_hash'])) {
            $_SESSION['logged'] = true;
            $_SESSION['username'] = $admin['username'];
        } else {
            $login_error = "Galat username ya password";
        }
    }
}

// Login form
if (empty($_SESSION['logged'])) {
    echo '<!DOCTYPE html><html><head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - ' . $PANEL_NAME . '</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:-apple-system,sans-serif;background:url(\'' . $BG_IMAGE . '\') no-repeat center center fixed;background-size:cover;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
        body::before{content:"";position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.75);z-index:-1;}
        .login-box{background:rgba(0,0,0,0.75);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.15);padding:40px 25px;border-radius:20px;max-width:380px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.7);}
        .title{text-align:center;font-size:24px;font-weight:bold;background:linear-gradient(90deg,#00ff88,#00d4ff,#ff00cc);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:8px;}
        .sub{text-align:center;color:#aaa;font-size:13px;margin-bottom:25px;}
        input{width:100%;padding:15px;font-size:16px;border-radius:12px;border:1px solid rgba(255,255,255,0.15);background:rgba(0,0,0,0.5);color:#fff;margin-bottom:12px;outline:none;transition:0.3s;}
        input:focus{border-color:#00ff88;box-shadow:0 0 0 3px rgba(0,255,136,0.2);}
        button{width:100%;padding:15px;font-size:16px;font-weight:bold;border-radius:12px;border:none;background:linear-gradient(90deg,#007aff,#00d4ff);color:#fff;cursor:pointer;transition:0.3s;}
        button:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(0,122,255,0.4);}
        .error{color:#ff3b30;font-size:13px;text-align:center;margin-top:10px;}
        .tg{margin-top:20px;text-align:center;font-size:12px;color:#888;}
        .tg a{color:#00d4ff;text-decoration:none;}
    </style>
    </head><body>
    <div class="login-box">
        <div class="title">🔐 ADMIN LOGIN</div>
        <div class="sub">' . $PANEL_NAME . '</div>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        ' . ($login_error ? '<p class="error">' . $login_error . '</p>' : '') . '
        <div class="tg">💬 Contact: <a href="https://t.me/' . $TELEGRAM . '" target="_blank">@' . $TELEGRAM . '</a></div>
    </div>
    </body></html>';
    exit;
}

// Naya Admin
$admin_msg = "";
if (isset($_POST['new_username']) && isset($_POST['new_password'])) {
    if ($pdo) {
        try {
            $hashed = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)")
                ->execute([$_POST['new_username'], $hashed]);
            $admin_msg = "✅ Admin add ho gaya: " . htmlspecialchars($_POST['new_username']);
        } catch (PDOException $e) {
            $admin_msg = "❌ Username pehle se hai";
        }
    }
}

// Admin Delete
if (isset($_GET['del_admin'])) {
    if ($pdo && $_GET['del_admin'] != $_SESSION['username']) {
        $pdo->prepare("DELETE FROM admins WHERE username = ?")->execute([$_GET['del_admin']]);
    }
    header("Location: /");
    exit;
}

// Key Generate
$new_key = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['days'])) {
    $custom = strtoupper(trim($_POST['custom_name'] ?? ''));
    $custom = preg_replace('/[^A-Z0-9]/', '', $custom);
    if ($custom !== '') {
        $key = $custom . '-' . strtoupper(bin2hex(random_bytes(4)));
    } else {
        $key = strtoupper(bin2hex(random_bytes(8)));
    }
    $days = (int)$_POST['days'];
    $device_type = $_POST['device_type'] ?? '1';
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("INSERT INTO keys_table (key_code, days, device_type) VALUES (?, ?, ?)");
            $stmt->execute([$key, $days, $device_type]);
            $new_key = $key;
        } catch (PDOException $e) {
            $new_key = "ERROR";
        }
    } else {
        $new_key = $key;
    }
}

// Reset
if (isset($_GET['reset'])) {
    if ($pdo) {
        $pdo->prepare("UPDATE keys_table SET used=0, used_by=NULL, last_device=NULL, device_count=0 WHERE id=?")->execute([(int)$_GET['reset']]);
    }
    header("Location: /");
    exit;
}

// Delete
if (isset($_GET['delete'])) {
    if ($pdo) {
        $pdo->prepare("DELETE FROM keys_table WHERE id=?")->execute([(int)$_GET['delete']]);
    }
    header("Location: /");
    exit;
}

$keys = [];
$admins = [];
if ($pdo) {
    $keys = $pdo->query("SELECT * FROM keys_table ORDER BY id DESC LIMIT 50")->fetchAll();
    $admins = $pdo->query("SELECT * FROM admins ORDER BY id ASC")->fetchAll();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $PANEL_NAME ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:-apple-system,sans-serif;background:url('<?= $BG_IMAGE ?>') no-repeat center center fixed;background-size:cover;color:#eee;padding:15px;min-height:100vh;}
        body::before{content:"";position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:-1;}
        .box{background:rgba(10,10,20,0.85);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.1);padding:20px;border-radius:20px;max-width:600px;margin:auto;box-shadow:0 20px 60px rgba(0,0,0,0.7);}
        .welcome{text-align:center;font-size:20px;font-weight:bold;background:linear-gradient(90deg,#00ff88,#00d4ff,#ff00cc);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:5px;}
        .userline{text-align:center;color:#aaa;font-size:12px;margin-bottom:20px;}
        .userline b{color:#00ff88;}
        .btn-row{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;}
        .btn-row a{flex:1;min-width:100px;text-decoration:none;}
        .tg-btn,.buy-btn,.pass-btn{padding:12px;text-align:center;border-radius:12px;font-weight:bold;font-size:13px;color:#fff;transition:0.3s;display:block;border:none;cursor:pointer;width:100%;}
        .tg-btn{background:linear-gradient(90deg,#0088cc,#00b4ff);}
        .buy-btn{background:linear-gradient(90deg,#ff0080,#ff8c00);}
        .pass-btn{background:linear-gradient(90deg,#8a2be2,#00d4ff);}
        .tg-btn:hover,.buy-btn:hover,.pass-btn:hover{transform:translateY(-2px);box-shadow:0 10px 25px rgba(0,0,0,0.5);}
        select,input,button{padding:12px;margin:5px 0;border-radius:12px;border:1px solid rgba(255,255,255,0.15);font-size:15px;width:100%;background:rgba(0,0,0,0.5);color:#fff;outline:none;}
        select:focus,input:focus{border-color:#00ff88;box-shadow:0 0 0 3px rgba(0,255,136,0.2);}
        .gen-btn{background:linear-gradient(90deg,#00ff88,#00d4ff);color:#000;font-weight:bold;cursor:pointer;border:none;transition:0.3s;}
        .gen-btn:hover{transform:translateY(-2px);box-shadow:0 10px 25px rgba(0,255,136,0.4);}
        .logout{background:rgba(255,59,48,0.3);color:#ff3b30;border:1px solid #ff3b30;cursor:pointer;}
        .key{background:rgba(0,0,0,0.5);padding:14px;margin:10px 0;border-radius:12px;border:1px solid rgba(255,255,255,0.1);font-family:monospace;font-size:13px;}
        .key .code{font-size:15px;font-weight:bold;color:#00ff88;}
        .key .meta{color:#aaa;font-size:12px;margin-top:4px;}
        .key .actions{margin-top:10px;display:flex;gap:6px;flex-wrap:wrap;}
        .key button{padding:6px 10px;font-size:12px;width:auto;border-radius:8px;border:none;cursor:pointer;font-weight:bold;}
        .copy{background:#00ff88;color:#000;}
        .reset{background:#ffaa00;color:#000;}
        .delete{background:#ff3b30;color:#fff;}
        .device{color:#4da6ff;}
        .devcount{color:#ffaa00;}
        hr{border:none;border-top:1px solid rgba(255,255,255,0.15);margin:20px 0;}
        h3{font-size:16px;margin-bottom:10px;color:#fff;}
        .admin-row{background:rgba(0,0,0,0.5);padding:10px 14px;margin:6px 0;border-radius:10px;display:flex;justify-content:space-between;align-items:center;font-size:14px;}
        .admin-del{background:#ff3b30;padding:5px 12px;font-size:11px;border-radius:6px;width:auto;border:none;color:#fff;cursor:pointer;font-weight:bold;}
        .msg{padding:10px;margin:10px 0;border-radius:10px;background:rgba(0,255,136,0.15);color:#00ff88;text-align:center;font-size:13px;border:1px solid rgba(0,255,136,0.3);}
        .footer{text-align:center;color:#666;font-size:11px;margin-top:20px;padding:10px;}
        .footer a{color:#00d4ff;text-decoration:none;}
    </style>
</head>
<body>
    <div class="box">
        <div class="welcome">✨ WELCOME IN <?= $PANEL_NAME ?> ✨</div>
        <div class="userline">Logged in as: <b><?= htmlspecialchars($_SESSION['username']) ?></b></div>

        <div class="btn-row">
            <a href="https://t.me/<?= $TELEGRAM ?>" target="_blank"><button class="tg-btn">💬 Telegram</button></a>
            <a href="https://t.me/<?= $TELEGRAM ?>?text=Buy%20Panel" target="_blank"><button class="buy-btn">🛒 Buy Panel</button></a>
        </div>
        <div class="btn-row">
            <a href="https://t.me/<?= $TELEGRAM ?>?text=Panel%20Pass" target="_blank"><button class="pass-btn">🔑 Panel Pass (<?= $PANEL_PRICE ?>)</button></a>
        </div>

        <hr>

        <h3>🔑 Generate Key</h3>
        <form method="POST">
            <input type="text" name="custom_name" placeholder="Custom Name (optional) e.g. BRUTALX" maxlength="15" style="text-transform:uppercase;">
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
            <button type="submit" class="gen-btn">⚡ Generate Key</button>
        </form>

        <?php if ($new_key && $new_key != "ERROR"): ?>
            <div class="key" style="background:linear-gradient(90deg,#00ff88,#00d4ff);color:#000;text-align:center;">
                <span class="code" style="color:#000;font-size:16px;"><?= htmlspecialchars($new_key) ?></span>
            </div>
        <?php elseif ($new_key == "ERROR"): ?>
            <div class="key" style="background:rgba(255,59,48,0.3);color:#ff3b30;text-align:center;">
                ❌ Key banane me error aaya
            </div>
        <?php endif; ?>

        <a href="?logout=1"><button class="logout">🚪 Logout</button></a>

        <hr>
        <h3>📋 All Keys (<?= count($keys) ?>)</h3>
        <?php if (empty($keys)): ?>
            <p style="color:#666;text-align:center;padding:20px;">Abhi koi key nahi hai.</p>
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
                    <div class="meta devcount">📱 Devices: <?= $k['device_count'] ?? 0 ?> device<?= ($k['device_count'] ?? 0) != 1 ? 's' : '' ?></div>
                    <?php if ($k['used_by']): ?>
                        <div class="meta device">🔒 Device: <?= htmlspecialchars(substr($k['used_by'], 0, 16)) ?>...</div>
                    <?php endif; ?>
                    <?php if ($k['last_seen']): ?>
                        <div class="meta">🕐 Last seen: <?= $k['last_seen'] ?></div>
                    <?php endif; ?>
                    <div class="actions">
                        <button class="copy" onclick="navigator.clipboard.writeText('<?= $k['key_code'] ?>')">📋 Copy</button>
                        <a href="?reset=<?= $k['id'] ?>" onclick="return confirm('Reset karega?')"><button class="reset">🔄 Reset</button></a>
                        <a href="?delete=<?= $k['id'] ?>" onclick="return confirm('Delete karna hai?')"><button class="delete">🗑 Delete</button></a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <hr>
        <h3>👤 Admins (<?= count($admins) ?>)</h3>
        <?php if ($admin_msg): ?>
            <div class="msg"><?= $admin_msg ?></div>
        <?php endif; ?>
        <?php foreach ($admins as $a): ?>
            <div class="admin-row">
                <span><?= htmlspecialchars($a['username']) ?> <?= $a['username'] == $_SESSION['username'] ? '(you)' : '' ?></span>
                <?php if ($a['username'] != $_SESSION['username']): ?>
                    <a href="?del_admin=<?= urlencode($a['username']) ?>" onclick="return confirm('Delete?')"><button class="admin-del">Delete</button></a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <hr>
        <h3>➕ Add New Admin</h3>
        <form method="POST">
            <input type="text" name="new_username" placeholder="Username" required>
            <input type="password" name="new_password" placeholder="Password" required>
            <button type="submit" class="gen-btn">Add Admin</button>
        </form>

        <div class="footer">
            💬 Contact: <a href="https://t.me/<?= $TELEGRAM ?>" target="_blank">@<?= $TELEGRAM ?></a>
        </div>
    </div>
</body>
</html>
