<?php
// index.php
require_once 'auth.php';

// Version update
$app_version = "v1.0.7";
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

    <link rel="stylesheet" href="style.css">
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
                         class="floating-menu absolute z-50 bg-slate-800 border border-slate-600 shadow-xl rounded-md px-3 py-2 flex items-center gap-2 transition-opacity duration-200"
                         :style="{ top: cellMenuTop + 'px', left: cellMenuLeft + 'px' }">
                        
                        <button @click="setCellColor('')" class="w-5 h-5 rounded-full border border-slate-500 flex items-center justify-center hover:bg-slate-700 transition" title="Clear Color">
                            <span class="text-slate-300 text-sm leading-none mt-[-2px]">&times;</span>
                        </button>
                        
                        <div class="w-px h-4 bg-slate-600 mx-1"></div>
                        
                        <button @click="setCellColor('#4c1d95')" class="w-5 h-5 rounded-full hover:scale-110 transition bg-[#4c1d95] shadow-inner" title="Purple"></button>
                        <button @click="setCellColor('#14532d')" class="w-5 h-5 rounded-full hover:scale-110 transition bg-[#14532d] shadow-inner" title="Green"></button>
                        <button @click="setCellColor('#1e3a8a')" class="w-5 h-5 rounded-full hover:scale-110 transition bg-[#1e3a8a] shadow-inner" title="Blue"></button>
                        <button @click="setCellColor('#7f1d1d')" class="w-5 h-5 rounded-full hover:scale-110 transition bg-[#7f1d1d] shadow-inner" title="Red"></button>
                        <button @click="setCellColor('#78350f')" class="w-5 h-5 rounded-full hover:scale-110 transition bg-[#78350f] shadow-inner" title="Amber"></button>
                        <button @click="setCellColor('#334155')" class="w-5 h-5 rounded-full hover:scale-110 transition bg-[#334155] shadow-inner" title="Grey Light"></button>
                        <button @click="setCellColor('#1e293b')" class="w-5 h-5 rounded-full hover:scale-110 transition bg-[#1e293b] shadow-inner" title="Grey Dark"></button>
                        
                        <div class="w-px h-4 bg-slate-600 mx-1"></div>

                        <label class="w-5 h-5 rounded-full border border-slate-500 overflow-hidden cursor-pointer flex items-center justify-center hover:scale-110 transition relative" title="Custom Color">
                            <input type="color" v-model="activeCellColor" @input="applyCellColor" class="absolute -inset-2 w-10 h-10 cursor-pointer p-0 m-0 border-0 bg-transparent">
                        </label>
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

    <script src="assets/js/app.js"></script>
</body>
</html>