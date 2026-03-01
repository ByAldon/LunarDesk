<?php
// reset.php
$app_version = "v1.6.0-beta";
$dbPath = __DIR__ . '/data.db';
$token = $_GET['token'] ?? '';
try {
    $db = new PDO("sqlite:$dbPath");
    $stmt = $db->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_expires > ?");
    $stmt->execute([$token, time()]); $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) die("Token Expired.");
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $db->prepare("UPDATE users SET username = ?, nickname = ?, password_hash = ?, reset_token = NULL WHERE id = ?");
        $stmt->execute([$_POST['u'] ?? $user['username'], $_POST['n'] ?? $user['nickname'], password_hash($_POST['p'], PASSWORD_DEFAULT), $user['id']]);
        header("Location: index.php?portal=open"); exit;
    }
} catch (Exception $e) { die("Fail"); }
$invite = (strpos($user['username'], 'pending_') === 0);
?>
<!DOCTYPE html><html><body style="background:#020617;color:white;display:flex;justify-content:center;align-items:center;height:100vh;font-family:sans-serif;margin:0;">
    <form method="POST" style="background:#0f172a;padding:40px;width:350px;border-radius:0;box-sizing:border-box;">
        <h1 style="font-weight:900;margin-top:0;margin-bottom:30px;"><?php echo $invite ? 'Join' : 'Reset'; ?></h1>
        <?php if($invite): ?>
        <input type="text" name="u" placeholder="Username" required style="box-sizing:border-box;display:block;width:100%;background:#020617;padding:12px;color:white;margin-bottom:10px;border:none;outline:none;">
        <input type="text" name="n" placeholder="Name" required style="box-sizing:border-box;display:block;width:100%;background:#020617;padding:12px;color:white;margin-bottom:10px;border:none;outline:none;">
        <?php endif; ?>
        <input type="password" name="p" placeholder="Password" required style="box-sizing:border-box;display:block;width:100%;background:#020617;padding:12px;color:white;margin-bottom:20px;border:none;outline:none;">
        <button style="box-sizing:border-box;width:100%;background:#3b82f6;padding:12px;color:white;font-weight:900;border:none;cursor:pointer;">SAVE</button>
    </form>
</body></html>