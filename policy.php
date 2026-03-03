<?php
// policy.php
include 'version.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy & Cookies Policy | LunarDesk</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-200 min-h-screen">
    <main class="max-w-4xl mx-auto px-6 py-10">
        <header class="mb-8">
            <h1 class="text-3xl md:text-4xl font-black text-white uppercase tracking-tight">Privacy & Cookies Policy</h1>
            <p class="mt-3 text-slate-400 text-sm">LunarDesk <?php echo htmlspecialchars($app_version, ENT_QUOTES, 'UTF-8'); ?> • Last updated: March 3, 2026</p>
        </header>

        <section class="space-y-4 bg-slate-800/70 border border-slate-700 rounded-2xl p-6">
            <h2 class="text-xl font-bold text-white">1. Scope</h2>
            <p class="text-sm leading-relaxed">This policy applies to your use of this LunarDesk instance, including the admin workspace, public pages, account login, and webhook features.</p>
        </section>

        <section class="mt-6 space-y-4 bg-slate-800/70 border border-slate-700 rounded-2xl p-6">
            <h2 class="text-xl font-bold text-white">2. Data We Store</h2>
            <ul class="list-disc pl-6 text-sm leading-relaxed space-y-1">
                <li>Account data: username, display name, email, role, password hash.</li>
                <li>Workspace data: spaces, pages, subpages, drafts, publish status, timestamps, and editor content.</li>
                <li>Operational data: channel messages, terminal messages, and webhook payload content.</li>
            </ul>
        </section>

        <section class="mt-6 space-y-4 bg-slate-800/70 border border-slate-700 rounded-2xl p-6">
            <h2 class="text-xl font-bold text-white">3. Cookies</h2>
            <p class="text-sm leading-relaxed">LunarDesk uses essential cookies for login and session continuity. These cookies are necessary for authentication and security and are not used for advertising profiling.</p>
            <ul class="list-disc pl-6 text-sm leading-relaxed space-y-1">
                <li>Session cookie: keeps you logged in during an active session.</li>
                <li>Remember-session cookie (optional): keeps you logged in across browser restarts when selected.</li>
            </ul>
        </section>

        <section class="mt-6 space-y-4 bg-slate-800/70 border border-slate-700 rounded-2xl p-6">
            <h2 class="text-xl font-bold text-white">4. How Data Is Used</h2>
            <ul class="list-disc pl-6 text-sm leading-relaxed space-y-1">
                <li>To provide core workspace and publishing functionality.</li>
                <li>To manage accounts, permissions, and security.</li>
                <li>To process webhook and terminal messages inside the platform.</li>
            </ul>
        </section>

        <section class="mt-6 space-y-4 bg-slate-800/70 border border-slate-700 rounded-2xl p-6">
            <h2 class="text-xl font-bold text-white">5. Retention & Control</h2>
            <p class="text-sm leading-relaxed">Data is stored locally in this installation database until modified or removed by administrators. Admins can delete content, users, rooms, and messages from the app interface.</p>
        </section>

        <section class="mt-6 space-y-4 bg-slate-800/70 border border-slate-700 rounded-2xl p-6">
            <h2 class="text-xl font-bold text-white">6. Third-Party Services</h2>
            <p class="text-sm leading-relaxed">This installation may load third-party frontend libraries (for example CDN-hosted UI/editor scripts) and may use outbound email delivery for invites and password reset flows, depending on server configuration.</p>
        </section>

        <section class="mt-6 space-y-4 bg-slate-800/70 border border-slate-700 rounded-2xl p-6">
            <h2 class="text-xl font-bold text-white">7. Security Note</h2>
            <p class="text-sm leading-relaxed">Administrators are responsible for server hardening, HTTPS setup, backup policy, and access control for this instance.</p>
        </section>

        <div class="mt-8 text-xs text-slate-500 uppercase tracking-[0.2em]">
            <a href="index.php" class="hover:text-blue-400 transition-colors">Back to LunarDesk</a>
        </div>
    </main>
</body>
</html>
