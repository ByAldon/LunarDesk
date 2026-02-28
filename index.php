<?php
// index.php
session_start();

$app_version = "v1.0.3";

$dbPath = __DIR__ . '/data.db';
$db = new PDO("sqlite:$dbPath");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1. Create users table if it does not exist
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL
)");

$stmt = $db->query("SELECT COUNT(*) FROM users");
$userCount = $stmt->fetchColumn();

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
        $stmt = $db->prepare("INSERT INTO users (username, password_hash) VALUES (:username, :password)");
        $stmt->execute([
            ':username' => $_POST['setup_username'],
            ':password' => $hash
        ]);
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $_POST['setup_username'];
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
    <body class="bg-slate-950 h-screen flex items-center justify-center text-white">
        <div class="bg-slate-900 p-8 rounded-xl shadow-2xl w-96 border border-slate-800">
            <h1 class="text-2xl font-bold mb-2 text-blue-500">Welcome to LunarDesk</h1>
            <p class="text-sm text-slate-400 mb-6">Create your admin account to finalize the installation.</p>
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-xs font-bold mb-2 text-slate-400 uppercase">Username</label>
                    <input type="text" name="setup_username" required class="w-full bg-slate-950 border border-slate-700 rounded py-2 px-3 text-white outline-none focus:border-blue-500">
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
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_username'])) {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute([':username' => $_POST['login_username']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($_POST['login_password'], $user['password_hash'])) {
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $user['username'];
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
    <body class="bg-slate-950 h-screen flex items-center justify-center text-white">
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
// --- END AUTHENTICATION ROUTES ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LunarDesk <?php echo $app_version; ?></title>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/editorjs@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/header@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/checklist@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/code@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/table@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/quote@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/warning@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/delimiter@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/inline-code@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/simple-image@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/embed@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/list@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/editorjs-text-color-plugin@2.0.4/dist/bundle.js"></script>

    <style>
        .ce-block__content, .ce-toolbar__content { color: #cbd5e1; max-width: 850px; margin: 0 auto; }
        .ce-toolbar__actions { color: #cbd5e1; }
        .ce-toolbar__plus, .ce-toolbar__settings-btn { background-color: #1e293b; color: white; border: 1px solid #334155; }
        .ce-toolbar__plus:hover, .ce-toolbar__settings-btn:hover { background-color: #334155; }
        .ce-popover { background-color: #1e293b; border-color: #334155; color: #cbd5e1; }
        .ce-popover__item:hover, .ce-popover__item--active { background-color: #334155 !important; color: white; }
        .ce-popover__item-icon { background-color: #0f172a; color: white; box-shadow: none; border: 1px solid #334155; }
        .ce-inline-toolbar { background-color: #1e293b; border-color: #334155; color: white; }
        .ce-inline-toolbar__dropdown:hover, .ce-inline-tool:hover { background-color: #334155; }
        .cdx-input { background-color: #0f172a; border-color: #334155; color: white; }
        .ce-header { color: white; font-weight: bold; }
        .cdx-list { padding-left: 20px; }
        .cdx-checklist__item--checked .cdx-checklist__item-text { color: #64748b; text-decoration: line-through; }
        .cdx-checklist__item-checkbox { background-color: #1e293b; border-color: #334155; }
        .cdx-checklist__item--checked .cdx-checklist__item-checkbox { background-color: #3b82f6; border-color: #3b82f6; }
        
        .tc-table, .tc-cell { border-color: #334155 !important; color: #cbd5e1; transition: background-color 0.2s; }
        .tc-row::after { border-color: #334155; }
        .tc-toolbox__button { border-color: #334155; color: #cbd5e1; }
        .tc-toolbox__button:hover { background-color: #1e293b; color: white; }
        
        .cdx-quote { border-left: 4px solid #3b82f6; padding-left: 1rem; }
        .cdx-quote__text { color: #e2e8f0; font-style: italic; }
        .cdx-warning { background-color: #1e293b; border-left: 4px solid #f59e0b; padding: 1rem; border-radius: 4px; }
        .cdx-warning__title { color: #fcd34d; font-weight: bold; margin-bottom: 0.5rem; }
        .inline-code { background: #1e293b; color: #4ade80; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 0.9em; border: 1px solid #334155; }
        
        .ce-popover__custom-content { background-color: #1e293b !important; }
        mark.cdx-marker { background: rgba(245, 158, 11, 0.3); color: inherit; padding: 0 4px; border-radius: 3px; }
        
        #editorjs { min-height: 100%; cursor: text; padding-bottom: 200px; }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }

        .chat-link { color: #60a5fa; text-decoration: underline; }
        .chat-link:hover { color: #93c5fd; }
    </style>
</head>
<body class="bg-slate-950 h-screen overflow-hidden text-slate-300">
    
    <div id="app" class="flex flex-col h-full w-full">
        
        <header class="bg-slate-900 border-b border-slate-800 h-14 shrink-0 flex items-center justify-between px-6 shadow-sm z-30">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-blue-600 rounded flex items-center justify-center text-white font-black text-lg shadow-md">L</div>
                <span class="font-black text-white uppercase tracking-widest text-sm">LunarDesk</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-[10px] text-slate-500 font-bold uppercase tracking-widest hidden sm:inline-block">Admin Session Active</span>
                <a href="?action=logout" class="bg-slate-800 hover:bg-red-600/20 text-red-400 hover:text-red-300 border border-slate-700 hover:border-red-500/50 px-3 py-1.5 rounded transition text-[10px] font-bold uppercase tracking-widest">Logout</a>
            </div>
        </header>

        <div class="flex-1 flex overflow-hidden w-full relative">

            <aside class="bg-slate-950 flex flex-col shrink-0 z-10 relative" :style="{ width: leftColWidth + 'px' }">
                <div class="flex flex-col flex-1 overflow-hidden">
                    <div class="h-1/3 min-h-[150px] border-b border-slate-800 flex flex-col bg-slate-900 shadow-sm shrink-0">
                        <div class="p-4 border-b border-slate-800 flex justify-between items-center shadow-sm z-10">
                            <span class="font-black text-xs uppercase tracking-widest text-white flex items-center gap-2">
                                <span class="text-blue-500">#</span> Channels
                            </span>
                            <button @click="createRoom" class="text-slate-400 hover:text-white transition font-bold text-lg leading-none" title="Create Channel">+</button>
                        </div>
                        
                        <div class="flex-1 overflow-y-auto p-2 space-y-1">
                            <div v-if="rooms.length === 0" class="text-xs text-slate-600 italic text-center mt-4">No channels yet.</div>
                            <div v-for="room in rooms" :key="room.id" 
                                @click="selectRoom(room)"
                                class="px-3 py-2 rounded-md cursor-pointer text-sm flex justify-between items-center group transition"
                                :class="activeRoom?.id === room.id ? 'bg-blue-600/20 text-blue-400 font-bold' : 'text-slate-400 hover:bg-slate-800'">
                                <span class="truncate flex items-center gap-2">
                                    <span class="text-slate-600 font-normal">#</span> {{ room.title }}
                                </span>
                                <button @click.stop="openSettings(room)" class="opacity-0 group-hover:opacity-100 text-slate-500 hover:text-white text-xs transition">⚙️</button>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 flex flex-col overflow-hidden relative bg-slate-950/80">
                        <div class="p-3 bg-slate-900 border-b border-slate-800 flex justify-between items-center shadow-sm z-10">
                            <span class="font-bold text-[10px] uppercase tracking-widest text-slate-400 truncate pr-2">
                                <span v-if="activeRoom" class="text-blue-400">Stream: #{{ activeRoom.title }}</span>
                                <span v-else>Select a channel</span>
                            </span>
                            <div class="flex items-center gap-3 shrink-0">
                                <button v-if="activeRoom && roomMessages.length > 0" @click="clearRoomMessages" class="text-[10px] uppercase font-bold text-slate-500 hover:text-red-400 transition tracking-widest" title="Clear Stream">Clear</button>
                                <span v-if="activeRoom" class="flex h-2 w-2 relative">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
                                </span>
                            </div>
                        </div>
                        
                        <div class="flex-1 overflow-y-auto p-4 space-y-3" id="webhook-stream">
                            <div v-if="!activeRoom" class="text-xs text-slate-600 italic text-center mt-4">
                                Select a channel above to view data.
                            </div>
                            <div v-else-if="roomMessages.length === 0" class="text-xs text-slate-600 italic text-center mt-4">
                                Waiting for incoming webhooks...
                            </div>
                            
                            <div v-for="msg in roomMessages" :key="msg.id" class="text-xs bg-slate-800/80 p-3 rounded-lg border border-slate-700/50 text-slate-300 shadow-sm">
                                <div class="text-[9px] text-slate-500 mb-1 flex justify-between">
                                    <span class="font-bold text-slate-400">{{ msg.sender }}</span>
                                    <span>{{ new Date(msg.created_at).toLocaleTimeString() }}</span>
                                </div>
                                <div class="font-mono text-green-400 whitespace-pre-wrap" v-html="linkify(msg.content)"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div @mousedown="startDrag('admin')" class="h-1.5 bg-slate-800 hover:bg-blue-500 cursor-row-resize z-50 transition-colors flex items-center justify-center group">
                    <div class="w-8 h-0.5 bg-slate-600 group-hover:bg-white rounded-full"></div>
                </div>

                <div class="flex flex-col bg-slate-900 shrink-0" :style="{ height: adminHeight + 'px' }">
                    <div class="p-3 bg-slate-800 border-b border-slate-700 font-bold text-[10px] uppercase tracking-widest text-slate-400 shadow-sm z-10">
                        Admin Terminal
                    </div>
                    <div class="flex-1 overflow-y-auto p-4 space-y-2 bg-slate-900" id="admin-chat">
                        <div v-for="chat in adminMessages" :key="chat.id" class="text-xs">
                            <span :class="[chat.colorClass, 'font-bold mr-2']">{{ chat.sender }}:</span>
                            <span class="text-slate-300" v-html="linkify(chat.content)"></span>
                        </div>
                    </div>
                    <div class="p-3 border-t border-slate-800 bg-slate-950">
                        <form @submit.prevent="sendAdminMessage" class="flex gap-2">
                            <input v-model="newAdminMsg" type="text" placeholder="Cmds: /create, /delete, /help..." class="w-full bg-slate-800 border border-slate-700 rounded-md px-3 py-2 text-xs text-white outline-none focus:border-blue-500 transition font-mono">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white rounded px-3 py-2 text-xs font-bold transition">Send</button>
                        </form>
                    </div>
                </div>
            </aside>

            <div @mousedown="startDrag('leftCol')" class="w-1.5 bg-slate-800 hover:bg-blue-500 cursor-col-resize z-50 transition-colors flex items-center justify-center group shrink-0">
                <div class="h-8 w-0.5 bg-slate-600 group-hover:bg-white rounded-full"></div>
            </div>

            <aside class="bg-slate-900 flex flex-col shrink-0 z-20 shadow-xl" :style="{ width: midColWidth + 'px' }">
                <div class="flex flex-col h-full p-4">
                    <div class="flex justify-between items-center mb-6 border-b border-slate-800 pb-4 shrink-0">
                        <h1 class="text-lg font-bold text-white truncate">Workspace</h1>
                    </div>
                    
                    <button @click="createItem('space')" class="bg-blue-600 hover:bg-blue-500 text-white p-2 rounded text-sm mb-4 transition shadow-lg shrink-0">
                        + New Space
                    </button>

                    <div class="flex-1 overflow-y-auto">
                        <div v-for="space in spaces" :key="space.id" class="mb-4">
                            <div class="flex justify-between items-center group mb-2 pr-2">
                                <span class="text-xs font-bold text-slate-500 uppercase tracking-widest truncate">{{ space.title }}</span>
                                <div class="hidden group-hover:flex items-center space-x-1 shrink-0">
                                    <button @click="createItem('page', space.id)" class="bg-slate-800 hover:bg-blue-600 text-blue-400 hover:text-white border border-slate-700 hover:border-blue-500 rounded px-2 py-1 text-[10px] font-bold transition shadow-sm" title="Add Page">+</button>
                                    <button @click="deleteItem(space.id, 'space')" class="bg-slate-800 hover:bg-red-600 text-red-400 hover:text-white border border-slate-700 hover:border-red-500 rounded px-2 py-1 text-[10px] transition shadow-sm" title="Delete Space">🗑️</button>
                                </div>
                            </div>
                            <ul class="mt-1 space-y-1 border-l border-slate-700 ml-1">
                                <li v-for="page in getPages(space.id)" :key="page.id" 
                                    @click="selectDoc(page)"
                                    class="pl-3 py-1.5 text-sm cursor-pointer hover:bg-slate-800 hover:text-white rounded transition flex items-center justify-between"
                                    :class="{'bg-slate-800 text-blue-400 font-bold': activePage?.id == page.id}">
                                    <span class="truncate">{{ page.has_draft ? page.draft_title : page.title }}</span>
                                    <span v-if="page.is_public == 1" class="ml-2 text-[9px] bg-green-900/50 text-green-400 border border-green-800 px-1.5 py-0.5 rounded shrink-0">PUB</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="mt-auto pt-4 border-t border-slate-800 text-center shrink-0">
                        <span class="text-[10px] text-slate-600 uppercase tracking-widest font-bold">
                        </span>
                    </div>
                </div>
            </aside>

            <div @mousedown="startDrag('midCol')" class="w-1.5 bg-slate-800 hover:bg-blue-500 cursor-col-resize z-50 transition-colors flex items-center justify-center group shrink-0">
                <div class="h-8 w-0.5 bg-slate-600 group-hover:bg-white rounded-full"></div>
            </div>

            <main class="flex-1 flex flex-col bg-slate-950 relative overflow-hidden min-w-[300px]">
                <div v-if="loading" class="absolute top-0 left-0 right-0 h-1 bg-blue-500 animate-pulse z-50"></div>

                <template v-if="activePage">
                    <header class="p-4 border-b border-slate-800 flex justify-between items-center bg-slate-900 shrink-0">
                        <input v-model="activePage.title" class="text-xl font-bold bg-transparent text-white outline-none w-full min-w-[100px]">
                        <div class="flex items-center space-x-4 shrink-0">
                            <span v-if="lastSaveTime" class="text-[11px] text-slate-500 italic mr-2 whitespace-nowrap hidden sm:inline">Draft saved: {{ lastSaveTime }}</span>
                            <label class="flex items-center text-xs text-slate-400 gap-2 cursor-pointer hover:text-slate-200 transition">
                                <span>Public:</span>
                                <input type="checkbox" v-model="activePage.is_public" :true-value="1" :false-value="0" class="accent-blue-600">
                            </label>
                            <button @click="manualPublish" class="bg-green-600 text-white px-4 py-1.5 rounded text-sm hover:bg-green-500 transition shadow-lg whitespace-nowrap">Publish to Live</button>
                            <button @click="deleteItem(activePage.id, 'page')" class="text-red-500 text-xs hover:text-red-400 hover:underline transition">Delete</button>
                        </div>
                    </header>

                    <div class="bg-slate-900 border-b border-slate-800 p-2 px-4 flex justify-between items-center text-xs shrink-0 overflow-hidden">
                        <span class="text-slate-400 truncate">
                            <span class="text-blue-400 font-bold">ℹ️ Tables:</span> Select the text inside a cell to format it. Click a cell to adjust its background color.
                        </span>
                        <span v-if="activePage.has_draft == 1" class="text-amber-500 font-bold animate-pulse uppercase tracking-wider text-[10px] ml-4 shrink-0">Unpublished Draft</span>
                    </div>
                    
                    <div v-if="activePage.is_public == 1" class="bg-blue-900/20 text-[11px] text-blue-300 border-b border-slate-800 p-2 px-4 flex justify-between shrink-0">
                        <span class="truncate">Public Link: <a :href="'p.php?s=' + activePage.slug" target="_blank" class="text-blue-400 hover:text-blue-300 underline font-mono">p.php?s={{ activePage.slug }}</a></span>
                    </div>

                    <div v-show="showCellMenu" 
                         @click.stop
                         class="absolute z-50 bg-slate-800 border border-slate-600 shadow-xl rounded-md p-2 flex items-center gap-3 transition-opacity duration-200"
                         :style="{ top: cellMenuTop + 'px', left: cellMenuLeft + 'px' }">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Cell BG:</span>
                        <input type="color" v-model="activeCellColor" @input="applyCellColor" class="w-6 h-6 cursor-pointer rounded bg-transparent border-0 p-0">
                    </div>

                    <div class="flex-1 p-8 overflow-y-auto w-full h-full relative" id="editor-wrapper" onclick="if(event.target.id === 'editor-wrapper') focusEditor()">
                        <div id="editorjs" class="w-full"></div>
                    </div>
                </template>
                <div v-else class="flex-1 flex items-center justify-center text-slate-600 italic">
                    Select or create a page.
                </div>
            </main>

        </div>

        <footer class="bg-slate-900 border-t border-slate-800 p-3 px-6 shrink-0 flex items-center justify-between z-30 shadow-inner">
            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">
                LunarDesk &copy; <?php echo date('Y'); ?> &bull; Data securely handled by SQLite
            </span>
            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">
                Version <?php echo $app_version; ?>
            </span>
        </footer>

        <div v-if="showSettingsModal" class="fixed inset-0 z-[100] bg-black/80 flex items-center justify-center backdrop-blur-sm">
            <div class="bg-slate-900 p-6 rounded-xl border border-slate-700 w-full max-w-lg shadow-2xl">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-white flex items-center gap-2"><span class="text-blue-500">#</span> {{ settingsRoom.title }} Settings</h2>
                    <button @click="showSettingsModal = false" class="text-slate-500 hover:text-white text-xl leading-none">&times;</button>
                </div>
                
                <div class="bg-slate-950 border border-slate-800 p-4 rounded-lg mb-6">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Webhook Integration</label>
                    <p class="text-xs text-slate-500 mb-4">Send POST requests to this URL to inject data into this specific channel stream.</p>
                    
                    <div v-if="settingsRoom.webhook_key" class="flex items-center gap-2">
                        <input type="text" readonly :value="getWebhookUrl(settingsRoom.webhook_key)" class="w-full bg-slate-900 border border-slate-700 rounded p-2 text-xs text-green-400 font-mono focus:outline-none">
                        <button @click="deleteWebhook" class="bg-red-900/50 text-red-400 border border-red-800 p-2 rounded hover:bg-red-900 hover:text-white transition" title="Revoke Webhook URL">🗑️</button>
                    </div>
                    <div v-else>
                        <button @click="generateWebhook" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded text-xs font-bold transition w-full">Generate Webhook URL</button>
                    </div>
                </div>

                <div class="border-t border-slate-800 pt-4 flex justify-between items-center mt-8">
                    <button @click="deleteRoom" class="text-red-500 hover:text-red-400 hover:underline text-xs font-bold">Delete entire channel</button>
                    <button @click="showSettingsModal = false" class="bg-slate-800 border border-slate-700 text-white px-6 py-2 rounded text-sm hover:bg-slate-700 transition font-bold">Done</button>
                </div>
            </div>
        </div>

    </div>

    <script>
        let globalEditorInstance = null;

        function focusEditor() {
            if (globalEditorInstance) globalEditorInstance.focus();
        }

        const { createApp } = Vue;
        createApp({
            data() {
                return {
                    items: [],
                    activePage: null,
                    loading: false,
                    lastSavedContent: null,
                    lastSavedTitle: null,
                    lastSavedPublic: null,
                    lastSaveTime: null,
                    needsSave: false,
                    
                    rooms: [],
                    activeRoom: null,
                    roomMessages: [],
                    adminMessages: [{ id: 1, sender: "System", content: "Terminal ready. Type /help for commands.", colorClass: "text-blue-400" }],
                    newAdminMsg: '',
                    
                    adminHeight: 250,
                    leftColWidth: 320,  
                    midColWidth: 256,   
                    dragTarget: null,   
                    
                    showSettingsModal: false,
                    settingsRoom: null,

                    showCellMenu: false,
                    cellMenuTop: 0,
                    cellMenuLeft: 0,
                    activeCellColor: '#1e293b'
                }
            },
            computed: {
                spaces() { return this.items.filter(i => i.type === 'space'); }
            },
            mounted() { 
                this.fetchData(); 
                this.fetchRooms();
                this.fetchTerminal();
                
                setInterval(() => {
                    this.silentAutoSave();
                    if(this.activeRoom) this.fetchRoomMessages();
                }, 5000);

                const updateActiveCell = (e) => {
                    if (!e.target || !e.target.closest) return;
                    const cell = e.target.closest('.tc-cell');
                    if (cell) {
                        window.currentActiveCell = cell;
                        const bg = window.getComputedStyle(cell).backgroundColor;
                        this.activeCellColor = this.rgbToHex(bg);
                        const wrapper = document.getElementById('editor-wrapper');
                        const wrapperRect = wrapper.getBoundingClientRect();
                        const cellRect = cell.getBoundingClientRect();
                        this.cellMenuTop = cellRect.top - wrapperRect.top + wrapper.scrollTop - 45;
                        this.cellMenuLeft = cellRect.left - wrapperRect.left + wrapper.scrollLeft;
                        this.showCellMenu = true;
                    } else {
                        this.showCellMenu = false;
                        window.currentActiveCell = null;
                    }
                };

                const wrapper = document.getElementById('editor-wrapper');
                if(wrapper) {
                    wrapper.addEventListener('click', updateActiveCell);
                    wrapper.addEventListener('keyup', updateActiveCell);
                }
            },
            methods: {
                linkify(text) {
                    if(!text) return '';
                    const urlRegex = /(https?:\/\/[^\s]+)/g;
                    return text.replace(urlRegex, (url) => {
                        return `<a href="${url}" target="_blank" class="chat-link">${url}</a>`;
                    });
                },

                applyCellColor(event) {
                    if (window.currentActiveCell) {
                        window.currentActiveCell.style.backgroundColor = event.target.value;
                        this.needsSave = true;
                    }
                },
                
                rgbToHex(rgb) {
                    if (!rgb || rgb === 'rgba(0, 0, 0, 0)' || rgb === 'transparent') return '#1e293b';
                    const rgbMatch = rgb.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
                    if (!rgbMatch) return rgb; 
                    function hex(x) { return ("0" + parseInt(x).toString(16)).slice(-2); }
                    return "#" + hex(rgbMatch[1]) + hex(rgbMatch[2]) + hex(rgbMatch[3]);
                },

                extractCellColors(outputData) {
                    const tableBlocks = document.querySelectorAll('.ce-block .tc-table');
                    let tableIndex = 0;
                    outputData.blocks.forEach(block => {
                        if (block.type === 'table') {
                            const domTable = tableBlocks[tableIndex++];
                            if (domTable) {
                                block.data.cellColors = [];
                                const rows = domTable.querySelectorAll('.tc-row');
                                rows.forEach(row => {
                                    const cellColorsRow = [];
                                    const cells = row.querySelectorAll('.tc-cell');
                                    cells.forEach(cell => {
                                        cellColorsRow.push(cell.style.backgroundColor || '');
                                    });
                                    block.data.cellColors.push(cellColorsRow);
                                });
                            }
                        }
                    });
                    return outputData;
                },

                startDrag(target) {
                    this.dragTarget = target;
                    document.body.style.userSelect = 'none'; 
                    document.body.style.cursor = target === 'admin' ? 'row-resize' : 'col-resize';
                    document.addEventListener('mousemove', this.onDrag);
                    document.addEventListener('mouseup', this.stopDrag);
                },
                onDrag(e) {
                    if (!this.dragTarget) return;
                    if (this.dragTarget === 'admin') {
                        let newHeight = window.innerHeight - e.clientY;
                        if (newHeight < 100) newHeight = 100; 
                        if (newHeight > window.innerHeight * 0.8) newHeight = window.innerHeight * 0.8; 
                        this.adminHeight = newHeight;
                    } 
                    else if (this.dragTarget === 'leftCol') {
                        let newWidth = e.clientX;
                        if (newWidth < 200) newWidth = 200; 
                        if (newWidth > 600) newWidth = 600; 
                        this.leftColWidth = newWidth;
                    }
                    else if (this.dragTarget === 'midCol') {
                        let newWidth = e.clientX - this.leftColWidth - 6; 
                        if (newWidth < 150) newWidth = 150;
                        if (newWidth > 600) newWidth = 600;
                        this.midColWidth = newWidth;
                    }
                },
                stopDrag() {
                    this.dragTarget = null;
                    document.body.style.userSelect = '';
                    document.body.style.cursor = '';
                    document.removeEventListener('mousemove', this.onDrag);
                    document.removeEventListener('mouseup', this.stopDrag);
                },

                async fetchTerminal() {
                    const res = await fetch('api.php?action=terminal');
                    this.adminMessages = await res.json();
                    this.$nextTick(() => {
                        const container = document.getElementById('admin-chat');
                        if(container) container.scrollTop = container.scrollHeight;
                    });
                },

                async postSystemMsg(content, colorClass) {
                    await fetch('api.php?action=terminal', { method: 'POST', body: JSON.stringify({ sender: 'System', content, colorClass }) });
                    this.fetchTerminal();
                },

                async sendAdminMessage() {
                    const msg = this.newAdminMsg.trim();
                    if(msg === '') return;
                    this.newAdminMsg = '';
                    
                    if (msg.startsWith('/')) {
                        const parts = msg.split(' ');
                        const cmd = parts[0].toLowerCase();
                        const arg = parts.slice(1).join(' ').trim();
                        
                        await fetch('api.php?action=terminal', { method: 'POST', body: JSON.stringify({ sender: 'Admin', content: msg, colorClass: 'text-purple-400' }) });
                        
                        if (cmd === '/create') {
                            if (!arg) { await this.postSystemMsg("Error: Use /create <name>", "text-red-400"); return; }
                            await fetch('api.php?action=rooms', { method: 'POST', body: JSON.stringify({ title: arg }) });
                            await this.fetchRooms();
                            await this.postSystemMsg(`Channel #${arg} successfully created!`, "text-green-400");
                        } 
                        else if (cmd === '/delete') {
                            if (!arg) { await this.postSystemMsg("Error: Use /delete <name>", "text-red-400"); return; }
                            const room = this.rooms.find(r => r.title.toLowerCase() === arg.toLowerCase());
                            if (!room) { await this.postSystemMsg(`Error: Channel #${arg} not found.`, "text-red-400"); return; }
                            await fetch(`api.php?action=rooms&id=${room.id}`, { method: 'DELETE' });
                            if(this.activeRoom?.id === room.id) { this.activeRoom = null; this.roomMessages = []; }
                            await this.fetchRooms();
                            await this.postSystemMsg(`Channel #${arg} permanently deleted.`, "text-amber-400");
                        }
                        else if (cmd === '/help') {
                            await this.postSystemMsg("Commands: /create <name>, /delete <name>, /help", "text-blue-400");
                        }
                        else {
                            await this.postSystemMsg(`Unknown command: ${cmd}.`, "text-red-400");
                        }
                        this.fetchTerminal();
                        return;
                    }
                    await fetch('api.php?action=terminal', { method: 'POST', body: JSON.stringify({ sender: 'Admin', content: msg, colorClass: 'text-purple-400' }) });
                    this.fetchTerminal();
                },

                async fetchRooms() {
                    const res = await fetch('api.php?action=rooms');
                    this.rooms = await res.json();
                },
                async createRoom() {
                    const title = prompt("Channel name?");
                    if(!title) return;
                    await fetch('api.php?action=rooms', { method: 'POST', body: JSON.stringify({ title }) });
                    await this.postSystemMsg(`Channel #${title} manually created.`, "text-green-400");
                    await this.fetchRooms();
                },
                selectRoom(room) {
                    this.activeRoom = room;
                    this.fetchRoomMessages();
                },
                async clearRoomMessages() {
                    if(!confirm(`Are you sure you want to clear all messages in stream #${this.activeRoom.title}?`)) return;
                    await fetch(`api.php?action=clear_messages&room_id=${this.activeRoom.id}`, { method: 'DELETE' });
                    this.roomMessages = [];
                    await this.postSystemMsg(`Stream #${this.activeRoom.title} has been cleared.`, "text-amber-400");
                },
                async fetchRoomMessages() {
                    if(!this.activeRoom) return;
                    try {
                        const res = await fetch(`api.php?action=webhook_messages&room_id=${this.activeRoom.id}`);
                        const newMsgs = await res.json();
                        const isNew = newMsgs.length > this.roomMessages.length;
                        this.roomMessages = newMsgs;
                        if(isNew) {
                            this.$nextTick(() => {
                                const container = document.getElementById('webhook-stream');
                                if(container) container.scrollTop = container.scrollHeight;
                            });
                        }
                    } catch(e) {}
                },
                openSettings(room) {
                    this.settingsRoom = room;
                    this.showSettingsModal = true;
                },
                getWebhookUrl(key) {
                    const baseUrl = window.location.origin + window.location.pathname.replace('index.php', '');
                    return `${baseUrl}webhook.php?key=${key}`;
                },
                async generateWebhook() {
                    const res = await fetch('api.php?action=webhook_key', { method: 'PUT', body: JSON.stringify({ id: this.settingsRoom.id }) });
                    const data = await res.json();
                    if(data.success) {
                        this.settingsRoom.webhook_key = data.key;
                        await this.fetchRooms();
                    }
                },
                async deleteWebhook() {
                    if(!confirm("Revoke this webhook? External services will fail to connect.")) return;
                    await fetch(`api.php?action=webhook_key&id=${this.settingsRoom.id}`, { method: 'DELETE' });
                    this.settingsRoom.webhook_key = null;
                    await this.fetchRooms();
                },
                async deleteRoom() {
                    if(!confirm(`Delete channel #${this.settingsRoom.title} and all its messages permanently?`)) return;
                    const title = this.settingsRoom.title;
                    await fetch(`api.php?action=rooms&id=${this.settingsRoom.id}`, { method: 'DELETE' });
                    if(this.activeRoom?.id === this.settingsRoom.id) {
                        this.activeRoom = null;
                        this.roomMessages = [];
                    }
                    this.showSettingsModal = false;
                    await this.postSystemMsg(`Channel #${title} manually deleted.`, "text-amber-400");
                    await this.fetchRooms();
                },

                getFormattedDateTime() {
                    const now = new Date();
                    return now.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
                },

                async fetchData() {
                    this.loading = true;
                    try {
                        const res = await fetch('api.php');
                        if (res.status === 401) { window.location.reload(); return; }
                        this.items = await res.json();
                        
                        const urlParams = new URLSearchParams(window.location.search);
                        const pageId = urlParams.get('p');
                        if(pageId && !this.activePage) {
                            const page = this.items.find(i => i.id == pageId);
                            if(page) this.selectDoc(page);
                        }
                    } catch (e) { }
                    this.loading = false;
                },

                getPages(spaceId) { return this.items.filter(i => i.type === 'page' && i.parent_id == spaceId); },

                async initEditor(dataBlocks) {
                    if (globalEditorInstance) {
                        try { await globalEditorInstance.isReady; globalEditorInstance.destroy(); } catch(e) { }
                        globalEditorInstance = null;
                    }
                    document.getElementById('editorjs').innerHTML = '';
                    const fullInlineToolbar = ['link', 'bold', 'italic', 'Color', 'Marker', 'inlineCode'];
                    globalEditorInstance = new EditorJS({
                        holder: 'editorjs', data: dataBlocks, autofocus: true, minHeight: 500, 
                        onChange: () => { this.needsSave = true; },
                        inlineToolbar: fullInlineToolbar,
                        tools: {
                            header: { class: Header, inlineToolbar: fullInlineToolbar, config: { levels: [1, 2, 3], defaultLevel: 2 } },
                            list: { class: EditorjsList, inlineToolbar: fullInlineToolbar }, 
                            checklist: { class: Checklist, inlineToolbar: fullInlineToolbar }, 
                            code: CodeTool,
                            table: { class: Table, inlineToolbar: fullInlineToolbar, config: { withHeadings: true } }, 
                            quote: { class: Quote, inlineToolbar: fullInlineToolbar },
                            warning: { class: Warning, inlineToolbar: fullInlineToolbar }, 
                            Color: { class: window.ColorPlugin, config: { colorCollections: ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#ffffff', '#cbd5e1', '#94a3b8', '#1e293b'], defaultColor: '#3b82f6', type: 'text', customPicker: true } },
                            Marker: { class: window.ColorPlugin, config: { colorCollections: ['#1e293b', '#334155', '#1e3a8a', '#7f1d1d', '#14532d', '#78350f', '#4c1d95', '#831843', '#f59e0b', '#3b82f6'], defaultColor: '#1e3a8a', type: 'marker', customPicker: true } },
                            delimiter: Delimiter, inlineCode: { class: InlineCode }, image: SimpleImage, embed: { class: Embed, inlineToolbar: true }
                        }
                    });

                    globalEditorInstance.isReady.then(() => {
                        setTimeout(() => {
                            const tableBlocks = document.querySelectorAll('.ce-block .tc-table');
                            let tableIndex = 0;
                            dataBlocks.blocks.forEach(block => {
                                if (block.type === 'table' && block.data.cellColors) {
                                    const domTable = tableBlocks[tableIndex++];
                                    if (domTable) {
                                        const rows = domTable.querySelectorAll('.tc-row');
                                        rows.forEach((row, rIdx) => {
                                            const cells = row.querySelectorAll('.tc-cell');
                                            cells.forEach((cell, cIdx) => {
                                                if (block.data.cellColors[rIdx] && block.data.cellColors[rIdx][cIdx]) {
                                                    cell.style.backgroundColor = block.data.cellColors[rIdx][cIdx];
                                                }
                                            });
                                        });
                                    }
                                } else if (block.type === 'table') tableIndex++;
                            });
                        }, 300); 
                    });
                },

                selectDoc(page) { 
                    const contentToLoad = page.has_draft ? page.draft_content : page.content;
                    this.activePage = { ...page, title: page.has_draft ? page.draft_title : page.title }; 
                    this.showCellMenu = false;
                    
                    const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?p=' + page.id;
                    window.history.pushState({ path: newUrl }, '', newUrl);

                    let parsedData = { blocks: [] };
                    if (contentToLoad) {
                        try { parsedData = JSON.parse(contentToLoad); } catch(e) { }
                    }
                    if (!parsedData.blocks || parsedData.blocks.length === 0) parsedData.blocks = [{ type: 'paragraph', data: { text: '' } }];

                    this.$nextTick(() => {
                        this.initEditor(parsedData);
                        this.lastSavedContent = JSON.stringify(parsedData);
                        this.lastSavedTitle = this.activePage.title;
                        this.lastSavedPublic = this.activePage.is_public;
                        this.lastSaveTime = null; 
                        this.needsSave = false;
                    });
                },

                async createItem(type, parentId = null) {
                    const title = prompt("Name?");
                    if (!title) return;
                    this.loading = true;
                    await fetch('api.php', { method: 'POST', body: JSON.stringify({ title, type, parent_id: parentId }) });
                    await this.fetchData();
                },
                
                async silentAutoSave() {
                    if (this.activePage && globalEditorInstance) {
                        let titleOrPublicChanged = (this.activePage.title !== this.lastSavedTitle || this.activePage.is_public !== this.lastSavedPublic);
                        
                        if (this.needsSave || titleOrPublicChanged) {
                            try {
                                const rawOutput = await globalEditorInstance.save();
                                const outputData = this.extractCellColors(rawOutput); 
                                const outputStr = JSON.stringify(outputData);
                                
                                await fetch('api.php', { method: 'PUT', body: JSON.stringify({ ...this.activePage, content: outputStr, action: 'draft' }) });
                                
                                this.lastSavedContent = outputStr; 
                                this.lastSavedTitle = this.activePage.title;
                                this.lastSavedPublic = this.activePage.is_public; 
                                this.lastSaveTime = this.getFormattedDateTime();
                                this.needsSave = false; 
                                this.activePage.has_draft = 1;
                                
                                const index = this.items.findIndex(i => i.id === this.activePage.id);
                                if(index !== -1) {
                                    this.items[index].has_draft = 1;
                                    this.items[index].draft_title = this.activePage.title;
                                    this.items[index].draft_content = outputStr;
                                }
                            } catch (e) { }
                        }
                    }
                },

                async manualPublish() {
                    this.loading = true; 
                    if (globalEditorInstance) {
                        try {
                            const rawOutput = await globalEditorInstance.save();
                            const outputData = this.extractCellColors(rawOutput); 
                            const outputStr = JSON.stringify(outputData);
                            
                            await fetch('api.php', { method: 'PUT', body: JSON.stringify({ ...this.activePage, content: outputStr, action: 'publish' }) });
                            
                            this.lastSavedContent = outputStr; 
                            this.lastSavedTitle = this.activePage.title;
                            this.lastSavedPublic = this.activePage.is_public; 
                            this.lastSaveTime = this.getFormattedDateTime();
                            this.needsSave = false; 
                            this.activePage.has_draft = 0; 
                        } catch (e) { }
                    }
                    await this.fetchData(); 
                },

                async deleteItem(id, type) {
                    if (!confirm("Delete?")) return;
                    this.loading = true;
                    await fetch(`api.php?id=${id}`, { method: 'DELETE' });
                    this.activePage = null;
                    await this.fetchData();
                }
            }
        }).mount('#app');
    </script>
</body>
</html>