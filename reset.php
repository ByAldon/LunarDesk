<?php
// reset.php - DEVELOPMENT ONLY
session_start();

$dbPath = __DIR__ . '/data.db';
$message = "";

// 1. Delete the SQLite database file
if (file_exists($dbPath)) {
    if (unlink($dbPath)) {
        $message = "Database (data.db) successfully deleted.";
    } else {
        $message = "Error: Could not delete data.db. Check folder permissions.";
    }
} else {
    $message = "Database does not exist. Nothing to reset.";
}

// 2. Destroy the active session so the user is logged out
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Reset</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 h-screen flex items-center justify-center text-white">
    <div class="bg-slate-900 p-8 rounded-xl shadow-2xl w-full max-w-md border border-slate-800 text-center">
        
        <div class="mb-6">
            <svg class="w-16 h-16 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>

        <h1 class="text-2xl font-bold mb-4">Factory Reset Complete</h1>
        
        <p class="text-slate-400 mb-8 text-sm">
            <?php echo htmlspecialchars($message); ?><br>
            Your session has been destroyed. The system is now back to its initial state.
        </p>

        <a href="index.php" class="inline-block bg-blue-600 hover:bg-blue-500 text-white font-bold py-2 px-6 rounded transition">
            Go to Setup
        </a>

        <div class="mt-8 pt-6 border-t border-slate-800">
            <p class="text-xs text-red-400 uppercase tracking-wider font-bold">
                ⚠️ Security Warning
            </p>
            <p class="text-xs text-slate-500 mt-2">
                Delete this reset.php file from your server before moving to production.
            </p>
        </div>
    </div>
</body>
</html>