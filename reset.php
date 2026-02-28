<?php
// reset.php - Password Recovery
$app_version = "v1.2.5";
$dbPath = __DIR__ . '/data.db';
$token = $_GET['token'] ?? '';
if (empty($token)) { header("Location: index.php"); exit; }

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("UPDATE users SET reset_token = NULL, reset_expires = NULL WHERE reset_expires < " . time());
    $stmt = $db->prepare("SELECT id FROM users WHERE reset_token = :token");
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) { $error = "Invalid or expired link."; } 
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['new_password'])) {
        $db->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?")->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $user['id']]);
        header("Location: index.php?portal=open&reset_success=1"); exit;
    }
} catch (PDOException $e) { die("System error"); }
?>
<!DOCTYPE html><html><head><title>Set Password</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-slate-950 h-screen flex items-center justify-center text-white"><div class="bg-slate-900 p-8 rounded-xl border border-slate-800 w-96">
    <h1 class="text-xl font-bold mb-6 text-blue-500 text-center">Set New Password</h1>
    <?php if(isset($error)) { echo "<p class='text-red-400 text-sm mb-4'>$error</p><a href='index.php?portal=open' class='block text-center text-xs'>Return</a>"; } 
    else { ?>
    <form method="POST" class="space-y-4">
        <input type="password" name="new_password" placeholder="New Password" required class="w-full bg-slate-950 border border-slate-700 rounded p-2 text-white outline-none">
        <button type="submit" class="w-full bg-green-600 p-2 rounded font-bold">Update Password</button>
    </form><?php } ?>
</div></body></html>