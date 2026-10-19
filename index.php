<?php
session_start();

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'keydb';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO(
        "pgsql:host=$host;dbname=$dbname",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS keys_table (
            id SERIAL PRIMARY KEY,
            key_code VARCHAR(100) UNIQUE NOT NULL,
            days INT NOT NULL DEFAULT 30,
            device_type VARCHAR(20) NOT NULL DEFAULT '1',
            device_limit INT NOT NULL DEFAULT 1,
            device_list TEXT DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            used INT NOT NULL DEFAULT 0,
            used_by VARCHAR(100),
            last_device VARCHAR(100),
            last_seen TIMESTAMP,
            device_count INT NOT NULL DEFAULT 0,
            referral_bonus INT NOT NULL DEFAULT 0,
            device_name VARCHAR(100),
            device_ip VARCHAR(50),
            referrer VARCHAR(100)
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id SERIAL PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL
        )
    ");
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Check Render environment variables.');
}

/*
 * Set ADMIN_USER and ADMIN_PASSWORD in Render environment variables
 * before first use. Do not use a public/default password.
 */
if (isset($_POST['setup_admin'])) {
    $count = (int)$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();

    $setupUser = getenv('ADMIN_USER') ?: '';
    $setupPass = getenv('ADMIN_PASSWORD') ?: '';

    if ($count === 0 && $setupUser !== '' && strlen($setupPass) >= 12) {
        $stmt = $pdo->prepare(
            "INSERT INTO admins (username, password_hash) VALUES (?, ?)"
        );
        $stmt->execute([
            $setupUser,
            password_hash($setupPass, PASSWORD_DEFAULT)
        ]);

        echo 'Admin created. Remove setup form and sign in.';
        exit;
    }

    exit('Admin setup unavailable. Check environment variables and admin table.');
}

if (isset($_POST['login'])) {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([trim((string)$_POST['username'])]);
    $admin = $stmt->fetch();

    if ($admin && password_verify(
        (string)$_POST['password'],
        $admin['password_hash']
    )) {
        session_regenerate_id(true);
        $_SESSION['logged'] = true;
    } else {
        $error = 'Invalid username or password';
    }
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: /');
    exit;
}

if (empty($_SESSION['logged'])):
?>
<!doctype html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SIDHU OWNER — Login</title>
<style>
body{font-family:Arial,sans-serif;background:#101020;color:white;padding:25px}
main{max-width:420px;margin:10vh auto;background:#20203a;padding:25px;border-radius:16px}
input,button{width:100%;padding:13px;margin:8px 0;box-sizing:border-box;border-radius:8px}
button{background:#8b5cf6;color:white;border:0;font-weight:bold}
.error{color:#ff8b8b}
</style>
</head>
<body>
<main>
<h2>SIDHU OWNER — Admin Login</h2>
<?php if (!empty($error)): ?>
<p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>
<form method="post">
<input name="username" placeholder="Username" required>
<input name="password" type="password" placeholder="Password" required>
<button name="login" value="1">Login</button>
</form>
<p>Initial setup: configure ADMIN_USER and ADMIN_PASSWORD in Render, then use a temporary setup form in a private deployment to create the first admin.</p>
</main>
</body>
</html>
<?php
exit;
endif;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    if (!isset($_POST['csrf']) ||
        !hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['csrf'])) {
        http_response_code(403);
        exit('Invalid request token');
    }

    $days = (int)($_POST['days'] ?? 30);
    $type = (string)($_POST['device_type'] ?? '1');
    $allowedDays = [1, 3, 7, 15, 30, 60, 90, 180, 365];
    $allowedTypes = ['1', '2', '3', '5', '10', 'unlimited'];

    if (!in_array($days, $allowedDays, true) ||
        !in_array($type, $allowedTypes, true)) {
        exit('Invalid settings');
    }

    $custom = strtoupper((string)($_POST['custom_name'] ?? ''));
    $custom = preg_replace('/[^A-Z0-9]/', '', $custom);

    $key = $custom !== ''
        ? substr($custom, 0, 15) . '-' . strtoupper(bin2hex(random_bytes(8)))
        : strtoupper(bin2hex(random_bytes(8)));

    $limit = $type === 'unlimited' ? 999999 : (int)$type;

    $stmt = $pdo->prepare(
        "INSERT INTO keys_table (key_code, days, device_type, device_limit)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$key, $days, $type, $limit]);

    $_SESSION['new_key'] = $key;
    header('Location: /');
    exit;
}

$_SESSION['csrf'] = bin2hex(random_bytes(32));

$keys = $pdo->query(
    "SELECT * FROM keys_table ORDER BY id DESC LIMIT 100"
)->fetchAll();

$newKey = $_SESSION['new_key'] ?? null;
unset($_SESSION['new_key']);
?>
<!doctype html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SIDHU OWNER — Key Panel</title>
<style>
body{font-family:Arial,sans-serif;background:#101020;color:white;padding:16px}
main{max-width:720px;margin:auto;background:#20203a;padding:22px;border-radius:16px}
input,select,button{width:100%;padding:12px;margin:7px 0;box-sizing:border-box;border-radius:8px}
button{background:#8b5cf6;color:white;border:0;font-weight:bold}
.key{padding:14px;margin:12px 0;background:#151528;border-radius:10px;overflow-wrap:anywhere}
.code{color:#67e8f9;font-weight:bold}
a{color:#fca5a5}
</style>
</head>
<body>
<main>
<h1>SIDHU OWNER</h1>
<p><a href="?logout=1">Logout</a></p>

<h2>Generate Key</h2>
<form method="post">
<input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
<input name="custom_name" maxlength="15" placeholder="Custom name (optional)">
<select name="days">
<?php foreach ([1,3,7,15,30,60,90,180,365] as $d): ?>
<option value="<?= $d ?>" <?= $d === 30 ? 'selected' : '' ?>><?= $d ?> days</option>
<?php endforeach; ?>
</select>
<select name="device_type">
<?php foreach (['1','2','3','5','10','unlimited'] as $d): ?>
<option value="<?= $d ?>"><?= $d === 'unlimited' ? 'Unlimited devices' : "$d device(s)" ?></option>
<?php endforeach; ?>
</select>
<button name="generate" value="1">Generate Key</button>
</form>

<?php if ($newKey): ?>
<div class="key">
<p>New key:</p>
<p class="code"><?= htmlspecialchars($newKey) ?></p>
<button type="button" onclick="navigator.clipboard.writeText(<?= json_encode($newKey) ?>)">Copy key</button>
</div>
<?php endif; ?>

<h2>Recent Keys</h2>
<?php foreach ($keys as $k): ?>
<div class="key">
<div class="code"><?= htmlspecialchars($k['key_code']) ?></div>
<p>Validity: <?= (int)$k['days'] ?> days</p>
<p>Device type: <?= htmlspecialchars($k['device_type']) ?></p>
<p>Used: <?= (int)$k['used'] ? 'Yes' : 'No' ?></p>
<p>Devices registered: <?= (int)$k['device_count'] ?></p>
</div>
<?php endforeach; ?>
</main>
</body>
</html>
