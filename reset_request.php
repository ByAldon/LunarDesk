<?php
date_default_timezone_set('UTC');
// reset_request.php
session_start();
include 'version.php';
$dbPath = __DIR__ . '/data.db';

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) { die("DB Offline"); }

$msg = "";
$isError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['e']);
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        date_default_timezone_set('UTC'); // Forceer UTC voor consistentie
$expires = time() + 3600; 
        $db->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?")
           ->execute([$token, $expires, $user['id']]);

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $dir_path = rtrim(dirname($uri_path), '/\\');
        $link = $protocol . $_SERVER['HTTP_HOST'] . $dir_path . "/reset.php?token=" . $token;
        
        $subject = "LunarDesk Password Reset";
        $body = "Click the link to reset your password: " . $link;
        $headers = "From: noreply@" . $_SERVER['HTTP_HOST'];
        @mail($email, $subject, $body, $headers);
    }

    // VEILIGHEID: We tonen de link NOOIT op het scherm en geven altijd dezelfde melding.
    $msg = "If this email address is registered, a reset link has been sent. Please check your inbox.";
    $isError = false;
}
?>
<!DOCTYPE html><html><body style="background:#020617;color:white;display:flex;justify-content:center;align-items:center;height:100vh;font-family:sans-serif;margin:0;">
<form method="POST" style="background:rgba(15,23,42,0.4);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.05);padding:50px;width:380px;border-radius:24px;box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);box-sizing:border-box;">
    <div style="text-align:center;margin-bottom:30px;">
        <h1 style="font-weight:900;margin:0;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.2em;">Reset Request</h1>
    </div>
    <?php if($msg): ?>
        <div style="background:<?php echo $isError ? 'rgba(239,68,68,0.1)' : 'rgba(59,130,246,0.1)'; ?>; border:1px solid <?php echo $isError ? '#ef4444' : '#3b82f6'; ?>; border-radius:12px; padding:15px; margin-bottom:20px; font-size:11px; text-align:center; color:<?php echo $isError ? '#fca5a5' : '#93c5fd'; ?>;">
            <?php echo $msg; ?>
        </div>
    <?php endif; ?>
    <input type="email" name="e" placeholder="Enter your email" required style="box-sizing:border-box;display:block;width:100%;background:rgba(0,0,0,0.4);border:1px solid rgba(255,255,255,0.05);padding:14px;color:white;margin-bottom:20px;border-radius:12px;outline:none;">
    <button style="box-sizing:border-box;width:100%;background:#2563eb;padding:14px;color:white;font-weight:900;border:none;border-radius:12px;cursor:pointer;text-transform:uppercase;letter-spacing:0.1em;">Request Link</button>
    <div style="margin-top:20px; text-align:center;">
        <a href="index.php" style="color:#475569; font-size:9px; font-weight:900; text-decoration:none; text-transform:uppercase;">Back to Login</a>
    </div>
</form></body></html>