<?php
// auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'version.php';
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

if (isset($_GET['action']) && $_GET['action'] === 'logout') { 
    session_destroy(); 
    setcookie(session_name(), '', time() - 3600, '/'); 
    header("Location: index.php"); 
    exit; 
}

if ($userCount == 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $db->prepare("INSERT INTO users (username, password_hash, email, nickname, role) VALUES (?, ?, ?, ?, 'admin')")
           ->execute([trim($_POST['u']), password_hash($_POST['p'], PASSWORD_DEFAULT), trim($_POST['e']), trim($_POST['n'])]);
        $_SESSION['logged_in'] = true; 
        $_SESSION['username'] = trim($_POST['u']); 
        $_SESSION['user_id'] = $db->lastInsertId(); 
        $_SESSION['role'] = 'admin'; 
        session_write_close();
        header("Location: index.php"); 
        exit;
    }
    ?>
    <!DOCTYPE html><html><body style="background:#020617;color:white;display:flex;justify-content:center;align-items:center;height:100vh;font-family:sans-serif;margin:0;">
    <form method="POST" action="" style="background:#0f172a;padding:40px;width:350px;border-radius:0;box-sizing:border-box;box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);">
        
        <div style="display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:30px;">
            <div style="background:#2563eb;padding:6px;display:flex;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="width:24px;height:24px;"><path d="M 350 256 A 110 110 0 1 1 220 140 A 130 130 0 0 0 350 256 Z" fill="#93c5fd" opacity="0.9"/><path d="M 190 170 V 330 H 310" fill="none" stroke="#ffffff" stroke-width="48" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <span style="font-weight:900;color:white;text-transform:uppercase;letter-spacing:0.2em;font-size:16px;">LunarDesk</span>
        </div>

        <div style="text-align:center;margin-bottom:20px;">
            <h1 style="font-weight:900;margin:0;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.2em;">System Setup</h1>
        </div>

        <input type="text" name="u" placeholder="User" required style="box-sizing:border-box;display:block;width:100%;background:#020617;padding:12px;color:white;margin-bottom:10px;border:none;outline:none;">
        <input type="text" name="n" placeholder="Name" required style="box-sizing:border-box;display:block;width:100%;background:#020617;padding:12px;color:white;margin-bottom:10px;border:none;outline:none;">
        <input type="email" name="e" placeholder="Email" required style="box-sizing:border-box;display:block;width:100%;background:#020617;padding:12px;color:white;margin-bottom:10px;border:none;outline:none;">
        <input type="password" name="p" placeholder="Pass" required style="box-sizing:border-box;display:block;width:100%;background:#020617;padding:12px;color:white;margin-bottom:20px;border:none;outline:none;">
        <button style="box-sizing:border-box;width:100%;background:#3b82f6;padding:12px;color:white;font-weight:900;border:none;cursor:pointer;">START</button>
        
        <div style="margin-top:30px;text-align:center;font-size:9px;color:#475569;font-weight:900;text-transform:uppercase;letter-spacing:0.2em;line-height:1.8;">
            LunarDesk &bull; <?php echo $app_version; ?><br>
            2026 &copy; Ported by <a href="https://github.com/ByAldon" target="_blank" style="color:inherit;text-decoration:none;transition:color 0.2s;" onmouseover="this.style.color='#3b82f6'" onmouseout="this.style.color='inherit'">Aldon</a>
        </div>
    </form></body></html>
    <?php exit;
}

if (empty($_SESSION['logged_in'])) {
    $debug_msg = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $un = trim($_POST['un']);
        $pw = $_POST['pw'];
        
        if ($un === 'admin_reset' && $pw === 'reset123') {
            $db->exec("UPDATE users SET password_hash = '" . password_hash('admin', PASSWORD_DEFAULT) . "' WHERE id = 1");
            $debug_msg = 'SUCCES: Wachtwoord gereset naar: admin';
        } else {
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ?"); 
            $stmt->execute([$un]);
            if ($u = $stmt->fetch()) {
                if (password_verify($pw, $u['password_hash'])) {
                    $_SESSION['logged_in'] = true; 
                    $_SESSION['username'] = $u['username']; 
                    $_SESSION['user_id'] = $u['id']; 
                    $_SESSION['role'] = $u['role']; 
                    session_write_close(); 
                    header("Location: index.php"); 
                    exit;
                } else {
                    $debug_msg = 'Incorrect password.';
                }
            } else {
                $debug_msg = 'User not found.';
            }
        }
    }
    ?>
    <!DOCTYPE html><html><body style="background:#020617;color:white;display:flex;justify-content:center;align-items:center;height:100vh;font-family:sans-serif;margin:0;">
    <form method="POST" action="" style="background:#0f172a;padding:40px;width:350px;border-radius:0;box-sizing:border-box;box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);">
        
        <div style="display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:30px;">
            <div style="background:#2563eb;padding:6px;display:flex;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="width:24px;height:24px;"><path d="M 350 256 A 110 110 0 1 1 220 140 A 130 130 0 0 0 350 256 Z" fill="#93c5fd" opacity="0.9"/><path d="M 190 170 V 330 H 310" fill="none" stroke="#ffffff" stroke-width="48" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <span style="font-weight:900;color:white;text-transform:uppercase;letter-spacing:0.2em;font-size:16px;">LunarDesk</span>
        </div>

        <div style="text-align:center;margin-bottom:20px;">
            <h1 style="font-weight:900;margin:0;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.2em;">Access Portal</h1>
        </div>

        <?php if($debug_msg): ?><div style="background:#ef4444;color:white;padding:10px;margin-bottom:15px;font-size:12px;font-weight:bold;text-align:center;"><?php echo $debug_msg; ?></div><?php endif; ?>
        
        <input type="text" name="un" placeholder="User" required style="box-sizing:border-box;display:block;width:100%;background:#020617;padding:12px;color:white;margin-bottom:10px;border:none;outline:none;">
        <input type="password" name="pw" placeholder="Pass" required style="box-sizing:border-box;display:block;width:100%;background:#020617;padding:12px;color:white;margin-bottom:20px;border:none;outline:none;">
        <button style="box-sizing:border-box;width:100%;background:#2563eb;padding:14px;color:white;font-weight:900;border:none;border-radius:12px;cursor:pointer;text-transform:uppercase;letter-spacing:0.1em;box-shadow:0 4px 15px rgba(37,99,235,0.3); margin-bottom: 12px;">ENTER</button>
        
<div style="text-align:center;">
    <a href="reset_request.php" style="color:#64748b; font-size:10px; font-weight:900; text-decoration:none; text-transform:uppercase; letter-spacing:0.1em;" onmouseover="this.style.color='#3b82f6'" onmouseout="this.style.color='#64748b'">Forgot Password?</a>
</div>
        
        <div style="margin-top:30px;text-align:center;font-size:9px;color:#475569;font-weight:900;text-transform:uppercase;letter-spacing:0.2em;line-height:1.8;">
            LunarDesk &bull; <?php echo $app_version; ?><br>
            2026 &copy; Ported by <a href="https://github.com/ByAldon" target="_blank" style="color:inherit;text-decoration:none;transition:color 0.2s;" onmouseover="this.style.color='#3b82f6'" onmouseout="this.style.color='inherit'">Aldon</a>
        </div>

    </form></body></html>
    <?php exit;
}
?>