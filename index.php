<?php
// index.php
require_once 'auth.php';

// Version update
$app_version = "v1.1.11";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LunarDesk <?php echo $app_version; ?></title>
    
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><rect width='512' height='512' fill='%232563eb' rx='115'/><path d='M 350 256 A 110 110 0 1 1 220 140 A 130 130 0 0 0 350 256 Z' fill='%2393c5fd' opacity='0.9'/><path d='M 190 170 V 330 H 310' fill='none' stroke='%23ffffff' stroke-width='48' stroke-linecap='round' stroke-linejoin='round'/></svg>">
    
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
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/image@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/embed@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/list@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/editorjs-text-color-plugin@2.0.4/dist/bundle.js"></script>

    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-slate-950 h-screen overflow-hidden text-slate-300">
    
    <div id="app" class="flex flex-col h-full w-full">
        
        <header class="bg-slate-900 border-b border-slate-800 h-14 shrink-0 flex items-center justify-between px-6 shadow-sm z-30">
            <div class="flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" class="w-8 h-8 shadow-md rounded-lg">
                    <rect width="512" height="512" fill="#2563eb"/>
                    <path d="M 350 256 A 110 110 0 1 1 220 140 A 130 130 0 0 0 350 256 Z" fill="#93c5fd" opacity="0.9"/>
                    <path d="M 190 170 V 330 H 310" fill="none" stroke="#ffffff" stroke-width="48" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span class="font-black text-white uppercase tracking-widest text-sm">LunarDesk</span>
            </div>
            <div class="flex items-center gap-4">
                <button v-if="currentUser" @click="showProfileModal = true" class="text-[10px] text-slate-400 hover:text-white font-bold uppercase tracking-widest hidden sm:inline-block transition" title="My Profile">{{ currentUser.nickname || currentUser.username }}</button>
                <button v-if="currentUser?.role === 'admin'" @click="openUsersModal" class="bg-blue-600/20 hover:bg-blue-500 text-blue-400 hover:text-white border border-blue-700/50 px-3 py-1.5 rounded transition text-[10px] font-bold uppercase tracking-widest">Users</button>
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
                    <header class="relative border-b border-slate-800 flex flex-col justify-end shrink-0 transition-all duration-300"
                            :class="hasCover(activePage) ? 'h-64' : 'h-28 bg-slate-900'"
                            :style="getCoverStyle(activePage)">
                        
                        <div v-if="hasCover(activePage)" class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-900/50 to-transparent z-0"></div>

                        <div class="absolute top-4 right-4 z-20 flex gap-2">
                            <input type="file" id="coverUpload" accept="image/*" class="hidden" @change="handleCoverUpload">
                            <button @click="triggerCoverUpload" class="bg-slate-900/80 hover:bg-slate-800 text-white px-3 py-1.5 rounded text-xs font-bold transition shadow-lg backdrop-blur-md border border-slate-700">
                                <span v-if="hasCover(activePage)">Change Banner</span>
                                <span v-else>+ Add Banner</span>
                            </button>
                            <button v-if="hasCover(activePage)" @click="removeCover" class="bg-red-900/80 hover:bg-red-800 text-white px-3 py-1.5 rounded text-xs font-bold transition shadow-lg backdrop-blur-md border border-red-800">Remove</button>
                        </div>

                        <div class="relative z-10 p-6 pb-4 flex justify-between items-end w-full mt-auto">
                            <div class="w-full">
                                <input v-model="activePage.title" placeholder="Page Title..." class="text-3xl font-black bg-transparent text-white outline-none w-full min-w-[100px] drop-shadow-md placeholder-slate-500">
                            </div>
                            <div class="flex items-center space-x-4 shrink-0 mb-1 ml-4">
                                <span v-if="lastSaveTime" class="text-[11px] text-slate-300 italic mr-2 whitespace-nowrap hidden sm:inline drop-shadow-md font-bold">Draft saved: {{ lastSaveTime }}</span>
                                <label class="flex items-center text-xs text-slate-300 gap-2 cursor-pointer hover:text-white transition drop-shadow-md font-bold">
                                    <span>Public:</span>
                                    <input type="checkbox" v-model="activePage.is_public" :true-value="1" :false-value="0" class="accent-blue-600 shadow-lg">
                                </label>
                                <button @click="manualPublish" class="bg-green-600 text-white px-4 py-1.5 rounded text-sm font-bold hover:bg-green-500 transition shadow-lg whitespace-nowrap">Publish</button>
                                <button @click="deleteItem(activePage.id, 'page')" class="text-red-400 text-xs font-bold hover:text-red-300 hover:underline transition drop-shadow-md">Delete</button>
                            </div>
                        </div>
                    </header>

                    <div class="bg-slate-900 border-b border-slate-800 p-2 px-6 flex justify-between items-center text-xs shrink-0 overflow-hidden shadow-inner">
                        <span class="text-slate-400 truncate">
                            <span class="text-blue-400 font-bold">ℹ️ Info:</span> Try adding a Banner Image! Or type / to use the Image tool.
                        </span>
                        <span v-if="activePage.has_draft == 1" class="text-amber-500 font-bold animate-pulse uppercase tracking-wider text-[10px] ml-4 shrink-0">Unpublished Draft</span>
                    </div>
                    
                    <div v-if="activePage.is_public == 1" class="bg-blue-900/20 text-[11px] text-blue-300 border-b border-slate-800 p-2 px-6 flex justify-between shrink-0">
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

        <div v-if="showPromptModal" class="fixed inset-0 z-[150] bg-black/80 flex items-center justify-center backdrop-blur-sm">
            <div class="bg-slate-900 p-6 rounded-xl border border-slate-700 w-full max-w-sm shadow-2xl">
                <h2 class="text-lg font-bold text-white mb-4">{{ promptTitle }}</h2>
                <form @submit.prevent="submitPrompt">
                    <input type="text" v-model="promptInput" ref="promptInputRef" class="w-full bg-slate-950 border border-slate-700 rounded p-3 text-sm text-white outline-none focus:border-blue-500 mb-6" required placeholder="Type a name...">
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="showPromptModal = false" class="text-slate-400 hover:text-white px-4 py-2 rounded text-sm transition">Cancel</button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-2 rounded text-sm font-bold transition shadow-lg">Confirm</button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showProfileModal" class="fixed inset-0 z-[100] bg-black/80 flex items-center justify-center backdrop-blur-sm">
            <div class="bg-slate-900 p-6 rounded-xl border border-slate-700 w-full max-w-sm shadow-2xl">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-white flex items-center gap-2">My Profile</h2>
                    <button @click="showProfileModal = false" class="text-slate-500 hover:text-white text-xl leading-none">&times;</button>
                </div>
                
                <form @submit.prevent="updateProfile" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Username</label>
                        <input type="text" v-model="profileForm.username" disabled class="w-full bg-slate-950 border border-slate-800 rounded p-2 text-xs text-slate-500 outline-none cursor-not-allowed">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Display Name</label>
                        <input type="text" v-model="profileForm.nickname" required class="w-full bg-slate-950 border border-slate-700 rounded p-2 text-xs text-white outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Email</label>
                        <input type="email" v-model="profileForm.email" required class="w-full bg-slate-950 border border-slate-700 rounded p-2 text-xs text-white outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">New Password (Optional)</label>
                        <input type="password" v-model="profileForm.password" placeholder="Leave blank to keep current" class="w-full bg-slate-950 border border-slate-700 rounded p-2 text-xs text-white outline-none focus:border-blue-500">
                    </div>
                    
                    <div class="pt-4 flex justify-end">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-2 rounded text-xs font-bold transition shadow-lg">Save Profile</button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showUsersModal" class="fixed inset-0 z-[100] bg-black/80 flex items-center justify-center backdrop-blur-sm">
            <div class="bg-slate-900 p-6 rounded-xl border border-slate-700 w-full max-w-3xl shadow-2xl flex flex-col max-h-[90vh]">
                <div class="flex justify-between items-center mb-6 shrink-0">
                    <h2 class="text-xl font-bold text-white flex items-center gap-2">Manage Users</h2>
                    <button @click="showUsersModal = false" class="text-slate-500 hover:text-white text-xl leading-none">&times;</button>
                </div>
                
                <div class="flex-1 overflow-y-auto mb-4 border border-slate-800 rounded bg-slate-950 p-2">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead>
                            <tr class="border-b border-slate-800 uppercase text-slate-500 font-bold">
                                <th class="p-2">Username</th>
                                <th class="p-2">Display Name</th>
                                <th class="p-2">Email</th>
                                <th class="p-2">Role</th>
                                <th class="p-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in userList" :key="user.id" class="border-b border-slate-800/50 hover:bg-slate-900/50 transition">
                                <td class="p-2">{{ user.username }}</td>
                                <td class="p-2">{{ user.nickname }}</td>
                                <td class="p-2">{{ user.email }}</td>
                                <td class="p-2">
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase" :class="user.role === 'admin' ? 'bg-purple-900/50 text-purple-400 border border-purple-800' : 'bg-slate-800 text-slate-400 border border-slate-700'">{{ user.role }}</span>
                                </td>
                                <td class="p-2 flex justify-end gap-3">
                                    <button @click="editUser(user)" class="text-blue-400 hover:text-blue-300 font-bold">Edit</button>
                                    <button @click="deleteUser(user.id)" class="text-red-400 hover:text-red-300 font-bold" v-if="user.id !== currentUser.id">Delete</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="bg-slate-950 border border-slate-800 p-4 rounded-lg shrink-0">
                    <h3 class="text-sm font-bold text-white mb-3">{{ editingUser ? 'Edit User' : 'Add New User' }}</h3>
                    <form @submit.prevent="saveUser" class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        <input type="text" v-model="userForm.username" placeholder="Username (Login)" required class="bg-slate-900 border border-slate-700 rounded p-2 text-xs text-white outline-none focus:border-blue-500">
                        <input type="email" v-model="userForm.email" placeholder="Email" required class="bg-slate-900 border border-slate-700 rounded p-2 text-xs text-white outline-none focus:border-blue-500">
                        <input type="text" v-model="userForm.nickname" placeholder="Display Name" required class="bg-slate-900 border border-slate-700 rounded p-2 text-xs text-white outline-none focus:border-blue-500">
                        <input type="password" v-model="userForm.password" :placeholder="editingUser ? 'New Password (leave blank to keep)' : 'Password'" :required="!editingUser" class="bg-slate-900 border border-slate-700 rounded p-2 text-xs text-white outline-none focus:border-blue-500">
                        <select v-model="userForm.role" class="bg-slate-900 border border-slate-700 rounded p-2 text-xs text-white outline-none focus:border-blue-500">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                        <div class="flex justify-end gap-2 items-center">
                            <button type="button" v-if="editingUser" @click="cancelEditUser" class="text-slate-400 hover:text-white text-xs px-3">Cancel</button>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded text-xs font-bold transition w-full">{{ editingUser ? 'Update User' : 'Create & Invite' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>