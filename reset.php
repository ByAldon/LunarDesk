<?php
// reset.php
$app_version = "v1.3.7-beta";
$dbPath = __DIR__ . '/data.db';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header("Location: index.php");
    exit;
}

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Clean up expired tokens
    $db->exec("UPDATE users SET reset_token = NULL, reset_expires = NULL WHERE reset_expires < " . time());

    $stmt = $db->prepare("SELECT id FROM users WHERE reset_token = :token");
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $error = "Invalid or expired reset link.";
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['new_password'])) {
        $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password_hash = :hash, reset_token = NULL, reset_expires = NULL WHERE id = :id")
           ->execute([':hash' => $hash, ':id' => $user['id']]);
        
        header("Location: index.php?portal=open&reset_success=1");
        exit;
    }
} catch (PDOException $e) {
    die("System error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set New Password | LunarDesk <?php echo $app_version; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 h-screen flex items-center justify-center text-white font-sans">
    <div class="bg-slate-900 p-8 rounded-xl shadow-2xl w-96 border border-slate-800">
        <h1 class="text-xl font-bold mb-6 text-center text-blue-500">Set New Password</h1>
        <?php if(isset($error)): ?>
            <p class='text-red-400 text-sm mb-6 text-center'><?php echo $error; ?></p>
            <div class="text-center">
                <a href="index.php?portal=open" class="text-xs text-blue-400 hover:text-blue-300 transition">Return to Login</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="mb-6">
                    <label class="block text-xs font-bold mb-2 text-slate-400 uppercase">New Password</label>
                    <input type="password" name="new_password" required class="w-full bg-slate-950 border border-slate-700 rounded py-2 px-3 text-white outline-none focus:border-blue-500">
                </div>
                <button type="submit" class="w-full bg-green-600 hover:bg-green-500 text-white font-bold py-2 px-4 rounded transition">Update Password</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>