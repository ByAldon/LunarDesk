<?php
// reset.php
session_start();
include 'version.php';
$dbPath = __DIR__ . '/data.db';

$token = trim($_GET['token'] ?? '');

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $db->prepare("SELECT * FROM users WHERE reset_token = ?");
    $stmt->execute([trim($token)]); 
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Veilige check: Bestaat de user? En is de tijd (met 5 min speling) nog niet verstreken?
    if (!$user || ($user['reset_expires'] + 300) < time()) {
        die("<!DOCTYPE html><html><body style='background:#020617;color:#fca5a5;display:flex;justify-content:center;align-items:center;height:100vh;font-family:sans-serif;'><h2>Link is invalid or expired.</h2></body></html>");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $db->prepare("UPDATE users SET username = ?, nickname = ?, password_hash = ?, reset_token = NULL WHERE id = ?");
        $stmt->execute([
            $_POST['u'] ?? $user['username'], 
            $_POST['n'] ?? $user['nickname'], 
            password_hash($_POST['p'], PASSWORD_DEFAULT), 
            $user['id']
        ]);
        header("Location: index.php"); 
        exit;
    }
} catch (Exception $e) { die("Database Error."); }

$invite = (strpos($user['username'], 'pending_') === 0);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $invite ? 'Setup Account' : 'Reset Password'; ?> | LunarDesk</title>
</head>
<body style="background:#020617;color:white;display:flex;justify-content:center;align-items:center;height:100vh;font-family:sans-serif;margin:0;">
    <form method="POST" style="background:rgba(15,23,42,0.4);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.05);padding:50px;width:380px;border-radius:24px;box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);box-sizing:border-box;">
        
        <div style="display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:30px;">
            <div style="background:#2563eb;padding:8px;border-radius:12px;box-shadow:0 4px 15px rgba(37,99,235,0.3);display:flex;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="width:24px;height:24px;"><path d="M 350 256 A 110 110 0 1 1 220 140 A 130 130 0 0 0 350 256 Z" fill="#93c5fd" opacity="0.9"/><path d="M 190 170 V 330 H 310" fill="none" stroke="#ffffff" stroke-width="48" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <span style="font-weight:900;color:white;text-transform:uppercase;letter-spacing:0.2em;font-size:16px;text-shadow:0 2px 4px rgba(0,0,0,0.5);">LunarDesk</span>
        </div>

        <div style="text-align:center;margin-bottom:30px;">
            <h1 style="font-weight:900;margin:0;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.2em;">
                <?php echo $invite ? 'Setup Account' : 'Reset Password'; ?>
            </h1>
            <p style="font-size:10px; color:#94a3b8; margin-top:8px;">
                <?php echo $invite ? 'Choose your username, display name, and password.' : 'Enter a new password for your account.'; ?>
            </p>
        </div>

        <?php if($invite): ?>
            <input type="text" name="u" placeholder="Username" required style="box-sizing:border-box;display:block;width:100%;background:rgba(0,0,0,0.4);border:1px solid rgba(255,255,255,0.05);padding:14px;color:white;margin-bottom:12px;border-radius:12px;outline:none;transition:border-color 0.2s;" onfocus="this.style.borderColor='rgba(59,130,246,0.5)'" onblur="this.style.borderColor='rgba(255,255,255,0.05)'">
            <input type="text" name="n" placeholder="Display Name" required style="box-sizing:border-box;display:block;width:100%;background:rgba(0,0,0,0.4);border:1px solid rgba(255,255,255,0.05);padding:14px;color:white;margin-bottom:12px;border-radius:12px;outline:none;transition:border-color 0.2s;" onfocus="this.style.borderColor='rgba(59,130,246,0.5)'" onblur="this.style.borderColor='rgba(255,255,255,0.05)'">
        <?php else: ?>
            <div style="text-align:center; margin-bottom:20px; font-size:11px; color:#cbd5e1; background:rgba(255,255,255,0.05); padding:10px; border-radius:8px;">
                Account: <strong style="color:white;"><?php echo htmlspecialchars($user['email']); ?></strong>
            </div>
        <?php endif; ?>
        
        <input type="password" name="p" placeholder="New Password" required style="box-sizing:border-box;display:block;width:100%;background:rgba(0,0,0,0.4);border:1px solid rgba(255,255,255,0.05);padding:14px;color:white;margin-bottom:24px;border-radius:12px;outline:none;transition:border-color 0.2s;" onfocus="this.style.borderColor='rgba(59,130,246,0.5)'" onblur="this.style.borderColor='rgba(255,255,255,0.05)'">
        
        <button style="box-sizing:border-box;width:100%;background:#2563eb;padding:14px;color:white;font-weight:900;border:none;border-radius:12px;cursor:pointer;text-transform:uppercase;letter-spacing:0.1em;box-shadow:0 4px 15px rgba(37,99,235,0.3);">
            <?php echo $invite ? 'Create Account' : 'Save Password'; ?>
        </button>
        
    </form>
</body>
</html>
