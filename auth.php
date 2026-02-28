<?php
// auth.php
session_start();

$app_version = "v1.1.3";
$dbPath = __DIR__ . '/data.db';

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Volledige tabel aanmaken inclusief de nieuwe kolommen voor een verse installatie
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        email TEXT,
        nickname TEXT,
        role TEXT DEFAULT 'user'
    )");

    // Achterwaartse compatibiliteit voor oudere databases. 
    // Let op: SQLite accepteert geen UNIQUE of PRIMARY KEY via ALTER TABLE ADD COLUMN.
    try { $db->exec("ALTER TABLE users ADD COLUMN email TEXT"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE users ADD COLUMN nickname TEXT"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE users ADD COLUMN role TEXT DEFAULT 'user'"); } catch(Exception $e) {}

    $stmt = $db->query("SELECT COUNT(*) FROM users");
    $userCount = $stmt->fetchColumn();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// --- LOGOUT ROUTE ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

// --- SETUP ROUTE ---
if ($userCount == 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_username'])) {
        $hash = password_hash($_POST['setup_password'], PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password_hash, email, nickname, role) VALUES (:username, :password, :email, :nickname, 'admin')");
        $stmt->execute([
            ':username' => $_POST['setup_username'],
            ':password' => $hash,
            ':email' => $_POST['setup_email'],
            ':nickname' => $_POST['setup_nickname']
        ]);
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $_POST['setup_username'];
        $_SESSION['user_id'] = $db->lastInsertId();
        $_SESSION['role'] = 'admin';
        header("Location: index.php");
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <title>LunarDesk Setup</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-slate-950 h-screen flex items-center justify-center text-white font-sans">
        <div class="bg-slate-900 p-8 rounded-xl shadow-2xl w-96 border border-slate-800">
            <h1 class="text-2xl font-bold mb-2 text-blue-500">Welcome to LunarDesk</h1>
            <p class="text-sm text-slate-400 mb-6">Create your admin account to finalize the installation.</p>
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-xs font-bold mb-2 text-slate-400 uppercase">Username (Login)</label>
                    <input type="text" name="setup_username" required class="w-full bg-slate-950 border border-slate-700 rounded py-2 px-3 text-white outline-none focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-xs font-bold mb-2 text-slate-400 uppercase">Email Address</label>
                    <input type="email" name="setup_email" required class="w-full bg-slate-950 border border-slate-700 rounded py-2 px-3 text-white outline-none focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-xs font-bold mb-2 text-slate-400 uppercase">Display Name</label>
                    <input type="text" name="setup_nickname" required class="w-full bg-slate-950 border border-slate-700 rounded py-2 px-3 text-white outline-none focus:border-blue-500">
                </div>
                <div class="mb-6">
                    <label class="block text-xs font-bold mb-2 text-slate-400 uppercase">Password</label>
                    <input type="password" name="setup_password" required class="w-full bg-slate-950 border border-slate-700 rounded py-2 px-3 text-white outline-none focus:border-blue-500">
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-2 px-4 rounded transition">Complete Setup</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// --- LOGIN ROUTE ---
if (empty($_SESSION['logged_in'])) {
    if (!isset($_GET['portal']) || $_GET['portal'] !== 'open') {
        header('HTTP/1.0 403 Forbidden');
        echo "403 Forbidden";
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_username'])) {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute([':username' => $_POST['login_username']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($_POST['login_password'], $user['password_hash'])) {
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid credentials.";
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <title>Login | LunarDesk</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-slate-950 h-screen flex items-center justify-center text-white font-sans">
        <div class="bg-slate-900 p-8 rounded-xl shadow-2xl w-96 border border-slate-800">
            <h1 class="text-xl font-bold mb-6 text-center text-blue-500">Secure Access</h1>
            <?php if(isset($error)) echo "<p class='text-red-400 text-sm mb-4 text-center'>$error</p>"; ?>
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-xs font-bold mb-2 text-slate-400 uppercase">Username</label>
                    <input type="text" name="login_username" required class="w-full bg-slate-950 border border-slate-700 rounded py-2 px-3 text-white outline-none focus:border-blue-500">
                </div>
                <div class="mb-6">
                    <label class="block text-xs font-bold mb-2 text-slate-400 uppercase">Password</label>
                    <input type="password" name="login_password" required class="w-full bg-slate-950 border border-slate-700 rounded py-2 px-3 text-white outline-none focus:border-blue-500">
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-2 px-4 rounded transition">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>