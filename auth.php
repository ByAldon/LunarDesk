<?php
// auth.php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') { ini_set('session.cookie_secure', 1); }
session_set_cookie_params(['samesite' => 'Lax', 'httponly' => true, 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on']);
session_start();

$app_version = "v1.3.7-beta";
$dbPath = __DIR__ . '/data.db';

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE, password_hash TEXT, email TEXT, nickname TEXT, role TEXT DEFAULT 'user', reset_token TEXT, reset_expires INTEGER)");
    $db->exec("CREATE TABLE IF NOT EXISTS login_attempts (ip TEXT PRIMARY KEY, attempts INTEGER DEFAULT 0, last_attempt INTEGER)");
    $db->exec("CREATE TABLE IF NOT EXISTS items (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, draft_title TEXT, content TEXT, draft_content TEXT, type TEXT, parent_id INTEGER, slug TEXT, is_public INTEGER DEFAULT 0, cover_image TEXT, draft_cover_image TEXT, has_draft INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS admin_terminal (id INTEGER PRIMARY KEY AUTOINCREMENT, sender TEXT, content TEXT, colorClass TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS rooms (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, webhook_key TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS webhook_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, room_id INTEGER, sender TEXT, content TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    
    $userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (PDOException $e) { die("Database error"); }

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = array();
    session_destroy();
    header("Location: index.php");
    exit;
}

if ($userCount == 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_username'])) {
        $stmt = $db->prepare("INSERT INTO users (username, password_hash, email, nickname, role) VALUES (?, ?, ?, ?, 'admin')");
        $stmt->execute([$_POST['setup_username'], password_hash($_POST['setup_password'], PASSWORD_DEFAULT), $_POST['setup_email'], $_POST['setup_nickname']]);
        $_SESSION['logged_in'] = true; $_SESSION['username'] = $_POST['setup_username']; $_SESSION['role'] = 'admin';
        header("Location: index.php"); exit;
    }
    ?>
    <!DOCTYPE html><html><head><title>Setup</title><script src="https://cdn.tailwindcss.com"></script></head>
    <body class="bg-slate-950 h-screen flex items-center justify-center text-white"><div class="bg-slate-900 p-8 rounded-xl border border-slate-800 w-96">
    <h1 class="text-2xl font-bold mb-6 text-blue-500">LunarDesk Setup</h1>
    <form method="POST" class="space-y-4">
        <input type="text" name="setup_username" placeholder="Username" required class="w-full bg-slate-950 border border-slate-700 rounded p-2">
        <input type="email" name="setup_email" placeholder="Email" required class="w-full bg-slate-950 border border-slate-700 rounded p-2">
        <input type="text" name="setup_nickname" placeholder="Display Name" required class="w-full bg-slate-950 border border-slate-700 rounded p-2">
        <input type="password" name="setup_password" placeholder="Password" required class="w-full bg-slate-950 border border-slate-700 rounded p-2">
        <button type="submit" class="w-full bg-blue-600 p-2 rounded font-bold">Complete Setup</button>
    </form></div></body></html>
    <?php exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'forgot' && empty($_SESSION['logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$_POST['forgot_email']]);
        if ($u = $stmt->fetch()) {
            $token = bin2hex(random_bytes(32));
            $db->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?")->execute([$token, time()+3600, $u['id']]);
            $link = "http://".$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF'])."/reset.php?token=".$token;
            @mail($_POST['forgot_email'], "Password Reset", "Reset here: ".$link);
        }
        $msg = "Reset link sent if email exists.";
    }
    ?>
    <!DOCTYPE html><html><head><title>Forgot</title><script src="https://cdn.tailwindcss.com"></script></head>
    <body class="bg-slate-950 h-screen flex items-center justify-center text-white"><div class="bg-slate-900 p-8 rounded-xl border border-slate-800 w-96 text-center">
    <h1 class="text-xl font-bold mb-4 text-blue-500">Reset Password</h1>
    <?php if(isset($msg)) echo "<p class='text-green-400 text-sm mb-4'>$msg</p>"; ?>
    <form method="POST"><input type="email" name="forgot_email" required class="w-full bg-slate-950 border border-slate-700 rounded p-2 mb-4">
    <button type="submit" class="w-full bg-blue-600 p-2 rounded font-bold">Send Link</button></form>
    <a href="?portal=open" class="text-xs text-slate-500 mt-4 block">Back to Login</a></div></body></html>
    <?php exit;
}

if (empty($_SESSION['logged_in'])) {
    if (!isset($_GET['portal']) || $_GET['portal'] !== 'open') { header('HTTP/1.0 403 Forbidden'); echo "403 Forbidden"; exit; }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?"); $stmt->execute([$_POST['login_username']]);
        if ($u = $stmt->fetch() AND password_verify($_POST['login_password'], $u['password_hash'])) {
            session_regenerate_id(true); $_SESSION['logged_in'] = true; $_SESSION['username'] = $u['username']; $_SESSION['user_id'] = $u['id']; $_SESSION['role'] = $u['role'];
            header("Location: index.php"); exit;
        } else { $error = "Invalid credentials."; }
    }
    ?>
    <!DOCTYPE html><html><head><title>Login</title><script src="https://cdn.tailwindcss.com"></script></head>
    <body class="bg-slate-950 h-screen flex items-center justify-center text-white font-sans"><div class="bg-slate-900 p-8 rounded-xl border border-slate-800 w-96">
    <h1 class="text-xl font-bold mb-6 text-center text-blue-500">Secure Access</h1>
    <?php if(isset($_GET['reset_success'])) echo "<p class='text-green-400 text-sm mb-4 text-center'>Password updated.</p>"; ?>
    <?php if(isset($error)) echo "<p class='text-red-400 text-sm mb-4 text-center'>$error</p>"; ?>
    <form method="POST" class="space-y-4">
        <div><label class="block text-xs font-bold text-slate-400 uppercase mb-1">Username</label>
        <input type="text" name="login_username" required class="w-full bg-slate-950 border border-slate-700 rounded p-2"></div>
        <div><div class="flex justify-between items-center mb-1"><label class="block text-xs font-bold text-slate-400 uppercase">Password</label>
        <a href="?portal=open&action=forgot" class="text-[10px] text-blue-400 font-bold">Forgot?</a></div>
        <input type="password" name="login_password" required class="w-full bg-slate-950 border border-slate-700 rounded p-2"></div>
        <button type="submit" class="w-full bg-blue-600 p-2 rounded font-bold">Login</button>
    </form></div></body></html>
    <?php exit;
}
?>