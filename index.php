<?php
session_start();

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'keydb';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

$TELEGRAM = "SIDHU_MODZ";
$PANEL_PRICE = "₹190";
$PANEL_NAME = "SIDHU PANEL";
$BG_IMAGE = "https://raw.githubusercontent.com/brutalxdop-dot/Brutalxdop/main/IMG_3017.jpeg";

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE TABLE IF NOT EXISTS keys_table (
        id SERIAL PRIMARY KEY,
        key_code VARCHAR(50) UNIQUE,
        days INT DEFAULT 30,
        device_type VARCHAR(20) DEFAULT '1',
        device_limit INT DEFAULT 1,
        device_list TEXT DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        used INT DEFAULT 0,
        used_by VARCHAR(100),
        last_device VARCHAR(100),
        last_seen TIMESTAMP,
        device_count INT DEFAULT 0,
        referrer VARCHAR(50) DEFAULT NULL,
        referral_bonus INT DEFAULT 0,
        device_name VARCHAR(100) DEFAULT NULL,
        device_ip VARCHAR(50) DEFAULT NULL
    )");

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

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: /");
    exit;
}

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

if (empty($_SESSION['logged'])) {
    echo '<!DOCTYPE html><html><head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - ' . $PANEL_NAME . '</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:-apple-system,sans-serif;background:url(\'' . $BG_IMAGE . '\') no-repeat center center fixed;background-size:cover;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
        body::before{content:"";position:fixed;top:0;left:0;width:100%;height:100%;background:linear-gradient(135deg, rgba(0,0,0,0.9) 0%, rgba(20,0,40,0.85) 100%);z-index:-1;}
        .login-box{background:linear-gradient(135deg, rgba(20,0,40,0.9) 0%, rgba(0,20,40,0.9) 100%);backdrop-filter:blur(20px);border:2px solid rgba(138,43,226,0.4);padding:45px 30px;border-radius:24px;max-width:400px;width:100%;box-shadow:0 25px 70px rgba(138,43,226,0.3);}
        .title{text-align:center;font-size:28px;font-weight:900;letter-spacing:2px;background:linear-gradient(90deg,#a855f7,#06b6d4,#ec4899);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:8px;}
        .sub{text-align:center;color:#a78bfa;font-size:12px;margin-bottom:30px;letter-spacing:4px;text-transform:uppercase;}
        input{width:100%;padding:16px;font-size:16px;border-radius:14px;border:2px solid rgba(168,85,247,0.3);background:rgba(0,0,0,0.6);color:#fff;margin-bottom:14px;outline:none;transition:0.3s;}
        input:focus{border-color:#a855f7;box-shadow:0 0 0 4px rgba(168,85,247,0.2);}
        button{width:100%;padding:16px;font-size:16px;font-weight:900;letter-spacing:2px;border-radius:14px;border:none;background:linear-gradient(90deg,#a855f7,#06b6d4);color:#fff;cursor:pointer;transition:0.3s;text-transform:uppercase;}
        button:hover{transform:translateY(-3px);box-shadow:0 15px 40px rgba(168,85,247,0.5);}
        .error{color:#f87171;font-size:13px;text-align:center;margin-top:10px;}
        .footer{margin-top:25px;text-align:center;font-size:11px;color:#8b8ba7;letter-spacing:1px;}
        .footer a{color:#a855f7;text-decoration:none;font-weight:bold;}
    </style>
    </head><body>
    <div class="login-box">
        <div class="title">🔐 ADMIN LOGIN</div>
        <div class="sub">' . $PANEL_NAME . '</div>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">⚡ Login</button>
        </form>
        ' . ($login_error ? '<p class="error">' . $login_error . '</p>' : '') . '
        <div class="footer">💬 <a href="https://t.me/' . $TELEGRAM . '" target="_blank">@' . $TELEGRAM . '</a></div>
    </div>
    </body></html>';
    exit;
}

$admin_msg = "";
if (isset($_POST['new_username']) && isset($_POST['new_password'])) {
    if ($pdo) {
        try {
            $hashed = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)")
                ->execute([$_POST['new_username'], $hashed]);
            $admin_msg = "✅ Admin added: " . htmlspecialchars($_POST['new_username']);
        } catch (PDOException $e) {
            $admin_msg = "❌ Username already exists";
        }
    }
}

if (isset($_GET['del_admin'])) {
    if ($pdo && $_GET['del_admin'] != $_SESSION['username']) {
        $pdo->prepare("DELETE FROM admins WHERE username = ?")->execute([$_GET['del_admin']]);
    }
    header("Location: /");
    exit;
}

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
    $device_limit = (int)($_POST['device_limit'] ?? 1);
    if ($device_type == '1') {
        $device_limit = 1;
    }
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("INSERT INTO keys_table (key_code, days, device_type, device_limit) VALUES (?, ?, ?, ?)");
            $stmt->execute([$key, $days, $device_type, $device_limit]);
            $new_key = $key;
        } catch (PDOException $e) {
            $new_key = "ERROR";
        }
    } else {
        $new_key = $key;
    }
}

if (isset($_GET['reset'])) {
    if ($pdo) {
        $pdo->prepare("UPDATE keys_table SET used=0, used_by=NULL, last_device=NULL, device_count=0, device_list='', device_name=NULL, device_ip=NULL WHERE id=?")->execute([(int)$_GET['reset']]);
    }
    header("Location: /");
    exit;
}

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
    $keys = $pdo->query("SELECT * FROM keys_table ORDER BY id DESC LIMIT 100")->fetchAll();
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
        body::before{content:"";position:fixed;top:0;left:0;width:100%;height:100%;background:linear-gradient(135deg, rgba(0,0,0,0.92) 0%, rgba(20,0,40,0.88) 100%);z-index:-1;}
        .box{background:linear-gradient(135deg, rgba(20,0,40,0.9) 0%, rgba(0,20,40,0.9) 100%);backdrop-filter:blur(20px);border:2px solid rgba(168,85,247,0.3);padding:25px;border-radius:24px;max-width:640px;margin:auto;box-shadow:0 25px 70px rgba(168,85,247,0.2);}
        .brand{text-align:center;padding:14px;margin-bottom:16px;border-radius:14px;background:linear-gradient(90deg,rgba(168,85,247,0.2),rgba(6,182,212,0.2));border:1px solid rgba(168,85,247,0.3);}
        .brand-title{font-size:20px;font-weight:900;letter-spacing:8px;background:linear-gradient(90deg,#a855f7,#06b6d4,#ec4899);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
        .brand-sub{font-size:10px;color:#a78bfa;letter-spacing:5px;margin-top:4px;}
        .userline{text-align:center;color:#a78bfa;font-size:12px;margin-bottom:22px;letter-spacing:1px;text-transform:uppercase;}
        .userline b{color:#06b6d4;}
        .btn-row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;}
        .btn-row a{flex:1;min-width:100px;text-decoration:none;}
        .tg-btn,.buy-btn,.pass-btn{padding:13px;text-align:center;border-radius:14px;font-weight:900;font-size:13px;color:#fff;transition:0.3s;display:block;border:none;cursor:pointer;width:100%;letter-spacing:1px;text-transform:uppercase;}
        .tg-btn{background:linear-gradient(90deg,#0088cc,#06b6d4);}
        .buy-btn{background:linear-gradient(90deg,#ec4899,#f97316);}
        .pass-btn{background:linear-gradient(90deg,#a855f7,#06b6d4);}
        .tg-btn:hover,.buy-btn:hover,.pass-btn:hover{transform:translateY(-3px);box-shadow:0 15px 40px rgba(168,85,247,0.4);}
        select,input,button{padding:14px;margin:6px 0;border-radius:14px;border:2px solid rgba(168,85,247,0.25);font-size:15px;width:100%;background:rgba(0,0,0,0.6);color:#fff;outline:none;transition:0.3s;}
        select:focus,input:focus{border-color:#a855f7;box-shadow:0 0 0 4px rgba(168,85,247,0.2);}
        .gen-btn{background:linear-gradient(90deg,#a855f7,#06b6d4);color:#fff;font-weight:900;cursor:pointer;border:none;letter-spacing:2px;text-transform:uppercase;}
        .gen-btn:hover{transform:translateY(-3px);box-shadow:0 15px 40px rgba(168,85,247,0.5);}
        .logout{background:rgba(220,20,60,0.3);color:#f87171;border:2px solid #dc143c;cursor:pointer;font-weight:900;letter-spacing:2px;text-transform:uppercase;}
        .key{background:rgba(0,0,0,0.55);padding:16px;margin:12px 0;border-radius:16px;border:1px solid rgba(168,85,247,0.2);font-family:monospace;font-size:13px;transition:0.3s;}
        .key:hover{border-color:#a855f7;box-shadow:0 0 20px rgba(168,85,247,0.2);}
        .key .code{font-size:15px;font-weight:900;color:#06b6d4;letter-spacing:1px;text-shadow:0 0 10px rgba(6,182,212,0.5);}
        .key .meta{color:#a78bfa;font-size:12px;margin-top:6px;}
        .key .actions{margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;}
        .key button{padding:8px 14px;font-size:12px;width:auto;border-radius:10px;border:none;cursor:pointer;font-weight:900;letter-spacing:1px;}
        .copy{background:#10b981;color:#fff;}
        .reset{background:#f59e0b;color:#000;}
        .delete{background:#dc143c;color:#fff;}
        .device{color:#06b6d4;}
        .devcount{color:#ec4899;}
        hr{border:none;border-top:1px solid rgba(168,85,247,0.2);margin:24px 0;}
        h3{font-size:16px;margin-bottom:12px;color:#fff;letter-spacing:2px;text-transform:uppercase;}
        .admin-row{background:rgba(0,0,0,0.5);padding:12px 16px;margin:8px 0;border-radius:12px;display:flex;justify-content:space-between;align-items:center;font-size:14px;border:1px solid rgba(168,85,247,0.15);}
        .admin-del{background:#dc143c;padding:6px 14px;font-size:11px;border-radius:8px;width:auto;border:none;color:#fff;cursor:pointer;font-weight:900;letter-spacing:1px;}
        .msg{padding:12px;margin:12px 0;border-radius:12px;background:rgba(16,185,129,0.15);color:#10b981;text-align:center;font-size:13px;border:1px solid rgba(16,185,129,0.3);}
        .footer{text-align:center;color:#8b8ba7;font-size:11px;margin-top:24px;padding:12px;letter-spacing:2px;}
        .footer a{color:#a855f7;text-decoration:none;font-weight:bold;}
        .key .device-info{background:rgba(168,85,247,0.1);padding:8px 10px;border-radius:8px;margin-top:6px;border-left:3px solid #a855f7;}
        .key .device-info div{color:#c4b5fd;font-size:11px;margin:2px 0;}
    </style>
</head>
<body>
    <div class="box">
        <div class="brand">
            <div class="brand-title">SIDHU</div>
            <div class="brand-sub">OWNER</div>
        </div>

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
            <input type="text" name="custom_name" placeholder="CUSTOM NAME (OPTIONAL)" maxlength="15" style="text-transform:uppercase;">
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
            <select name="device_type" required onchange="toggleLimit(this.value)">
                <option value="1">1 Device (Single)</option>
                <option value="2">2 Devices</option>
                <option value="3">3 Devices</option>
                <option value="5">5 Devices</option>
                <option value="10">10 Devices</option>
                <option value="unlimited">Unlimited Devices</option>
            </select>
            <input type="number" name="device_limit" id="device_limit_input" placeholder="Device Limit (numbers only, for custom)" min="1" value="1" style="display:none;">
            <button type="submit" class="gen-btn">⚡ Generate Key</button>
        </form>

        <script>
        function toggleLimit(val) {
            var inp = document.getElementById('device_limit_input');
            if (val === 'unlimited') {
                inp.style.display = 'block';
                inp.value = 999999;
                inp.placeholder = 'Unlimited devices (leave as 999999)';
            } else if (val === '1') {
                inp.style.display = 'none';
            } else {
                inp.style.display = 'block';
                inp.value = val;
            }
        }
        </script>

        <?php if ($new_key && $new_key != "ERROR"): ?>
            <div class="key" style="background:linear-gradient(90deg,#a855f7,#06b6d4);text-align:center;">
                <span class="code" style="color:#fff;font-size:18px;"><?= htmlspecialchars($new_key) ?></span>
            </div>
        <?php elseif ($new_key == "ERROR"): ?>
            <div class="key" style="background:rgba(220,20,60,0.3);color:#f87171;text-align:center;">
                ❌ Failed to create key
            </div>
        <?php endif; ?>

        <a href="?logout=1"><button class="logout">🚪 Logout</button></a>

        <hr>
        <h3>📋 All Keys (<?= count($keys) ?>)</h3>
        <?php if (empty($keys)): ?>
            <p style="color:#8b8ba7;text-align:center;padding:20px;">No keys yet.</p>
        <?php else: ?>
            <?php foreach ($keys as $k):
                $limit = (int)($k['device_limit'] ?? 1);
                if ($k['device_type'] == '1') $limit = 1;
                if ($k['device_type'] == 'unlimited' && $limit == 0) $limit = 999999;
                $dcount = (int)($k['device_count'] ?? 0);
            ?>
                <div class="key">
                    <div class="code"><?= htmlspecialchars($k['key_code']) ?></div>
                    <div class="meta">
                        📅 <?= $k['days'] ?> days ·
                        <?php if ($k['device_type'] == '1'): ?>
                            💻 1 Device
                        <?php elseif ($k['device_type'] == 'unlimited'): ?>
                            💻 Unlimited
                        <?php else: ?>
                            💻 <?= $limit ?> Devices
                        <?php endif; ?>
                        ·
                        <?php if ($k['used']): ?>
                            🔴 <span style="color:#f87171;">USED</span>
                        <?php else: ?>
                            🟢 <span style="color:#10b981;">UNUSED</span>
                        <?php endif; ?>
                    </div>
                    <div class="meta devcount">📱 Devices Used: <?= $dcount ?><?= $limit < 999999 ? ' / ' . $limit : '' ?></div>
                    <?php if (!empty($k['referrer'])): ?>
                        <div class="meta" style="color:#10b981;">👥 Referred by: <?= htmlspecialchars($k['referrer']) ?></div>
                    <?php endif; ?>
                    <?php if ((int)($k['referral_bonus'] ?? 0) > 0): ?>
                        <div class="meta" style="color:#10b981;">🎁 Referral Bonus: +<?= $k['referral_bonus'] ?> days</div>
                    <?php endif; ?>
                    <?php if (!empty($k['used_by']) || !empty($k['device_name'])): ?>
                        <div class="device-info">
                            <?php if (!empty($k['device_name'])): ?>
                                <div>📱 Device: <?= htmlspecialchars(substr($k['device_name'], 0, 40)) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($k['device_ip'])): ?>
                                <div>🌐 IP: <?= htmlspecialchars($k['device_ip']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($k['used_by'])): ?>
                                <div>🔑 ID: <?= htmlspecialchars(substr($k['used_by'], 0, 20)) ?>...</div>
                            <?php endif; ?>
                            <?php if (!empty($k['last_seen'])): ?>
                                <div>🕐 Last: <?= htmlspecialchars($k['last_seen']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="actions">
                        <button class="copy" onclick="navigator.clipboard.writeText('<?= $k['key_code'] ?>')">📋 Copy</button>
                        <a href="?reset=<?= $k['id'] ?>" onclick="return confirm('Reset?')"><button class="reset">🔄 Reset</button></a>
                        <a href="?delete=<?= $k['id'] ?>" onclick="return confirm('Delete?')"><button class="delete">🗑 Delete</button></a>
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
            💬 <a href="https://t.me/<?= $TELEGRAM ?>" target="_blank">@<?= $TELEGRAM ?></a>
        </div>
    </div>
</body>
</html>
