<?php
// auth.php
session_start();
$app_version = "v1.5.5-beta";
$dbPath = __DIR__ . '/data.db';
try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE, password_hash TEXT, email TEXT, nickname TEXT, role TEXT DEFAULT 'user', reset_token TEXT, reset_expires INTEGER)");
    $db->exec("CREATE TABLE IF NOT EXISTS items (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, draft_title TEXT, content TEXT, draft_content TEXT, type TEXT, parent_id INTEGER, slug TEXT, is_public INTEGER DEFAULT 0, cover_image TEXT, draft_cover_image TEXT, has_draft INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS admin_terminal (id INTEGER PRIMARY KEY AUTOINCREMENT, sender TEXT, content TEXT, colorClass TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS rooms (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, webhook_key TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS webhook_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, room_id INTEGER, sender TEXT, content TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (Exception $e) { die("LOCK"); }
if (isset($_GET['action']) && $_GET['action'] === 'logout') { session_destroy(); header("Location: index.php"); exit; }
if ($userCount == 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $db->prepare("INSERT INTO users (username, password_hash, email, nickname, role) VALUES (?, ?, ?, ?, 'admin')")
           ->execute([$_POST['u'], password_hash($_POST['p'], PASSWORD_DEFAULT), $_POST['e'], $_POST['n']]);
        $_SESSION['logged_in'] = true; $_SESSION['username'] = $_POST['u']; $_SESSION['user_id'] = $db->lastInsertId(); $_SESSION['role'] = 'admin'; header("Location: index.php"); exit;
    }
    ?>
    <!DOCTYPE html><html><body style="background:#020617;color:white;display:flex;justify-content:center;align-items:center;height:100vh;font-family:sans-serif;">
    <form method="POST" style="background:#0f172a;padding:40px;width:350px;">
    <h1 style="font-weight:900;margin-bottom:30px;">Initialize</h1>
    <input type="text" name="u" placeholder="User" required style="display:block;width:100%;background:#020617;padding:12px;color:white;margin-bottom:10px;border:none;">
    <input type="text" name="n" placeholder="Name" required style="display:block;width:100%;background:#020617;padding:12px;color:white;margin-bottom:10px;border:none;">
    <input type="email" name="e" placeholder="Email" required style="display:block;width:100%;background:#020617;padding:12px;color:white;margin-bottom:10px;border:none;">
    <input type="password" name="p" placeholder="Pass" required style="display:block;width:100%;background:#020617;padding:12px;color:white;margin-bottom:20px;border:none;">
    <button style="width:100%;background:#3b82f6;padding:12px;color:white;font-weight:900;border:none;">START</button>
    </form></body></html>
    <?php exit;
}
if (empty($_SESSION['logged_in'])) {
    if (!isset($_GET['portal']) || $_GET['portal'] !== 'open') { header('HTTP/1.0 403 Forbidden'); exit; }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?"); $stmt->execute([$_POST['un']]);
        if ($u = $stmt->fetch() AND password_verify($_POST['pw'], $u['password_hash'])) {
            $_SESSION['logged_in'] = true; $_SESSION['username'] = $u['username']; $_SESSION['user_id'] = $u['id']; $_SESSION['role'] = $u['role']; header("Location: index.php"); exit;
        }
    }
    ?>
    <!DOCTYPE html><html><body style="background:#020617;color:white;display:flex;justify-content:center;align-items:center;height:100vh;font-family:sans-serif;">
    <form method="POST" style="background:#0f172a;padding:40px;width:350px;">
    <h1 style="font-weight:900;margin-bottom:30px;">Access</h1>
    <input type="text" name="un" placeholder="User" required style="display:block;width:100%;background:#020617;padding:12px;color:white;margin-bottom:10px;border:none;">
    <input type="password" name="pw" placeholder="Pass" required style="display:block;width:100%;background:#020617;padding:12px;color:white;margin-bottom:20px;border:none;">
    <button style="width:100%;background:#3b82f6;padding:12px;color:white;font-weight:900;border:none;">ENTER</button>
    </form></body></html>
    <?php exit;
}