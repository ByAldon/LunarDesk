<?php
// index.php
require_once 'auth.php';
include 'version.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LunarDesk <?php echo $app_version; ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHZpZXdCb3g9JzAgMCA1MTIgNTEyJz48cmVjdCB3aWR0aD0nNTEyJyBoZWlnaHQ9JzUxMicgZmlsbD0nIzI1NjNlYicgcng9JzExNScvPjxwYXRoIGQ9J00gMzUwIDI1NiBBIDExMCAxMTAgMCAxIDEgMjIwIDE0MCBBIDEzMCAxMzAgMCAwIDAgMzUwIDI1NiBaJyBmaWxsPScjOTNjNWZkJyBvcGFjaXR5PScwLjknLz48cGF0aCBkPSdNIDE5MCAxNzAgViAzMzAgSCAzMTAnIGZpbGw9J25vbmUnIHN0cm9rZT0nI2ZmZmZmZicgc3Ryb2tlLXdpZHRoPSc0OCcgc3Ryb2tlLWxpbmVjYXA9J3JvdW5kJyBzdHJva2UtbGluZWpvaW49J3JvdW5kJy8+PC9zdmc+">
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/editorjs@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/paragraph@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/header@2.8.8"></script>
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
    <link rel="stylesheet" href="assets/style.css">
    <style>[v-cloak]{display:none!important;}</style>
</head>
<body class="bg-slate-800 h-screen overflow-hidden text-slate-300 selection:bg-blue-500/30">
    <div id="app" data-app-version="<?php echo htmlspecialchars($app_version, ENT_QUOTES, 'UTF-8'); ?>" class="flex flex-col h-full w-full relative" v-cloak @click="closeHeaderMenu">
        
        <header class="h-20 shrink-0 flex items-center justify-between px-8 z-30 relative bg-transparent">
            <div class="flex items-center gap-4">
                <div class="bg-blue-600 p-2 rounded-xl shadow-lg shadow-blue-500/20">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" class="w-6 h-6"><path d="M 350 256 A 110 110 0 1 1 220 140 A 130 130 0 0 0 350 256 Z" fill="#93c5fd" opacity="0.9"/><path d="M 190 170 V 330 H 310" fill='none' stroke='#ffffff' stroke-width='48' stroke-linecap='round' stroke-linejoin='round'/></svg>
                </div>
                <span class="font-black text-white uppercase tracking-widest text-sm drop-shadow-md">LunarDesk</span>
            </div>
            <div class="relative flex items-center gap-3" @click.stop>
                <button
                    v-if="currentUser"
                    @click="profileForm = { username: currentUser.username, nickname: currentUser.nickname, email: currentUser.email, password: '' }; showProfileModal = true"
                    class="text-[10px] text-slate-300 hover:text-white font-black uppercase tracking-widest transition-colors"
                >
                    {{ currentUser.nickname || currentUser.username }}
                </button>
                <button
                    @click.stop="toggleHeaderMenu"
                    class="flex items-center justify-center w-11 h-11 bg-slate-800 border border-slate-700 rounded-2xl shadow-xl text-slate-300 hover:text-white hover:bg-slate-700 transition-colors"
                    aria-label="Open menu"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 4.75h3l.5 1.8a5.9 5.9 0 011.27.74l1.72-.68 2.12 2.12-.68 1.72c.28.4.53.83.74 1.27l1.8.5v3l-1.8.5a5.9 5.9 0 01-.74 1.27l.68 1.72-2.12 2.12-1.72-.68a5.9 5.9 0 01-1.27.74l-.5 1.8h-3l-.5-1.8a5.9 5.9 0 01-1.27-.74l-1.72.68-2.12-2.12.68-1.72a5.9 5.9 0 01-.74-1.27l-1.8-.5v-3l1.8-.5c.2-.44.46-.87.74-1.27l-.68-1.72 2.12-2.12 1.72.68c.4-.28.83-.53 1.27-.74l.5-1.8z" />
                        <circle cx="12" cy="12" r="3.1" stroke-width="2" />
                    </svg>
                </button>
                <div
                    v-if="showHeaderMenu"
                    class="absolute right-0 top-full mt-3 min-w-[220px] bg-slate-800 border border-slate-700 rounded-2xl shadow-2xl p-2 flex flex-col gap-1 z-50"
                >
                    <button v-if="currentUser?.role === 'admin'" @click="openUsersModal(); showHeaderMenu = false" class="text-left px-4 py-3 text-[10px] text-slate-300 hover:text-white hover:bg-slate-700 rounded-xl font-black uppercase tracking-widest transition-colors">Users</button>
                    <button @click="showManualModal = true; showHeaderMenu = false" class="text-left px-4 py-3 text-[10px] text-slate-300 hover:text-white hover:bg-slate-700 rounded-xl font-black uppercase tracking-widest transition-colors">Manual</button>
                    <button @click="hardRefresh(); showHeaderMenu = false" class="text-left px-4 py-3 text-[10px] text-slate-300 hover:text-white hover:bg-slate-700 rounded-xl font-black uppercase tracking-widest transition-colors">Hard Refresh</button>
                    <a href="?action=logout" @click="showHeaderMenu = false" class="text-left px-4 py-3 text-[10px] text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded-xl font-black uppercase tracking-widest transition-colors">Logout</a>
                </div>
            </div>
        </header>

        <div class="flex-1 flex overflow-hidden w-full relative px-6 pb-6 gap-2">
            <aside class="bg-slate-700/80 border border-slate-600 shadow-2xl flex flex-col shrink-0 z-10 relative rounded-3xl overflow-hidden" :style="{ width: leftColWidth + 'px' }">
                
                <div class="flex items-center border-b border-slate-600/50 bg-slate-800/60 shrink-0">
                    <button @click="switchTab('stream')" class="flex-1 py-4 px-4 text-[10px] font-black uppercase tracking-widest transition-colors relative flex justify-center items-center gap-2" :class="activeLeftTab === 'stream' ? 'text-blue-400 bg-slate-800/80 shadow-[inset_0_2px_0_0_#3b82f6]' : 'text-slate-500 hover:text-slate-300 hover:bg-slate-800/40'">
                        Stream
                        <span v-if="hasUnreadStream" class="w-2 h-2 rounded-full bg-red-500 animate-pulse shadow-lg shadow-red-500/50"></span>
                    </button>
                    <div class="w-px h-6 bg-slate-700/50"></div>
                    <button @click="switchTab('terminal')" class="flex-1 py-4 px-4 text-[10px] font-black uppercase tracking-widest transition-colors relative flex justify-center items-center gap-2" :class="activeLeftTab === 'terminal' ? 'text-amber-500 bg-slate-800/80 shadow-[inset_0_2px_0_0_#f59e0b]' : 'text-slate-500 hover:text-slate-300 hover:bg-slate-800/40'">
                        Terminal
                        <span v-if="hasUnreadTerminal" class="w-2 h-2 rounded-full bg-red-500 animate-pulse shadow-lg shadow-red-500/50"></span>
                    </button>
                </div>

                <div v-show="activeLeftTab === 'stream'" class="flex-1 flex flex-col overflow-hidden">
                    
                    <div class="flex flex-col shrink-0 border-b border-slate-600/50 bg-slate-800/20" :style="{ height: channelsHeight + 'px' }">
                        <div class="p-6 flex justify-between items-center"><span class="font-black text-[10px] uppercase tracking-[0.2em] text-slate-400">Channels</span><button @click="createRoom" class="text-slate-400 hover:text-white transition-colors">+</button></div>
                        <div class="flex-1 overflow-y-auto px-4 space-y-1 pb-4">
                            <div v-for="room in rooms" :key="room.id" @click="selectRoom(room)" class="nav-item px-4 py-3 rounded-xl cursor-pointer text-sm flex justify-between items-center group transition-all" :class="activeRoom?.id === room.id ? 'bg-blue-600/20 text-blue-400 font-bold nav-item-active shadow-inner' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white'">
                                <div class="nav-indicator"></div>
                                <span class="truncate"># {{ room.title }}</span>
                                <button @click.stop="openSettings(room)" class="opacity-0 group-hover:opacity-100 text-slate-400 hover:text-white transition-opacity">⚙️</button>
                            </div>
                        </div>
                    </div>
                    
                    <div @mousedown="startDrag('channels', $event)" class="h-2 w-full bg-transparent hover:bg-blue-500/20 cursor-row-resize z-50 transition-colors shrink-0"></div>
                    
                    <div class="flex-1 flex flex-col overflow-hidden relative bg-slate-900/40">
                        <div class="absolute top-2 right-4 z-10">
                            <button v-if="currentUser?.role === 'admin' && activeRoom && roomMessages.length > 0" @click="confirmClearMessages" class="text-[9px] uppercase font-bold text-slate-400 hover:text-red-400 transition-colors bg-slate-800/80 px-2 py-1 rounded-lg border border-slate-700 backdrop-blur-sm">Wipe</button>
                        </div>
                        <div class="flex-1 overflow-y-auto p-4 space-y-4" id="webhook-stream">
                            <div v-for="msg in roomMessages" :key="msg.id" class="group relative">
                                <button v-if="currentUser?.role === 'admin'" @click.stop="confirmDeleteMessage(msg)" class="absolute right-1 top-1 opacity-0 group-hover:opacity-100 text-slate-400 hover:text-red-400 text-xs font-black leading-none transition-opacity bg-slate-800/70 px-1.5 py-1 rounded-md border border-slate-600" aria-label="Delete message">&times;</button>
                                <div class="flex items-baseline gap-2 mb-1 pr-8">
                                    <span class="text-[10px] font-black text-blue-400 uppercase tracking-tighter">{{ msg.sender }}</span>
                                    <span class="text-[9px] text-slate-500 font-medium uppercase ml-2">{{ new Date(msg.created_at).toLocaleTimeString() }}</span>
                                </div>
                                <div class="font-mono text-slate-200 text-xs leading-relaxed break-words bg-slate-700/95 p-4 rounded-xl border border-slate-500 shadow-[0_0_0_1px_rgba(148,163,184,0.25),0_6px_16px_rgba(2,6,23,0.35)]" v-html="linkify(msg.content)"></div>
                            </div>
                            <div v-if="!activeRoom" class="text-center text-slate-600 text-[10px] uppercase font-bold mt-10 tracking-widest">Select a channel</div>
                            <div v-else-if="roomMessages.length === 0" class="text-center text-slate-600 text-[10px] uppercase font-bold mt-10 tracking-widest">No messages yet</div>
                        </div>
                    </div>
                </div>

                <div v-show="activeLeftTab === 'terminal'" class="flex-1 flex flex-col overflow-hidden bg-slate-700/40">
                    <div class="flex-1 overflow-y-auto px-4 py-4 space-y-2" id="admin-chat">
                        <div v-for="chat in adminMessages" :key="chat.id" class="group relative text-xs leading-relaxed">
                            <button v-if="currentUser?.role === 'admin'" @click.stop="confirmDeleteAdminMessage(chat)" class="absolute right-1 top-0 opacity-0 group-hover:opacity-100 text-slate-400 hover:text-red-400 text-xs font-black leading-none transition-opacity bg-slate-800/70 px-1.5 py-1 rounded-md border border-slate-600" aria-label="Delete terminal message">&times;</button>
                            <div class="pr-8"><span :class="[chat.colorClass, 'font-black mr-2 uppercase tracking-tighter text-[10px]']">{{ chat.sender }}:</span><span class="text-slate-300" v-html="linkify(chat.content)"></span></div>
                        </div>
                    </div>
                    <div class="p-4 border-t border-slate-600/50 bg-slate-700/30">
                        <form @submit.prevent="sendAdminMessage">
                            <input v-model="newAdminMsg" type="text" placeholder="Enter command..." class="w-full bg-slate-600/95 border border-slate-400 rounded-xl px-5 py-3 text-xs text-white outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400/60 font-mono transition-all placeholder-slate-300 shadow-[inset_0_0_0_1px_rgba(148,163,184,0.25),0_6px_14px_rgba(2,6,23,0.3)]">
                        </form>
                    </div>
                </div>

            </aside>

            <div @mousedown="startDrag('leftCol')" class="w-2 cursor-col-resize z-50 shrink-0 flex items-center justify-center group"><div class="w-1 h-12 rounded-full bg-slate-700 group-hover:bg-blue-500/50 transition-colors"></div></div>

            <aside class="bg-slate-700/80 border border-slate-600 shadow-2xl flex flex-col shrink-0 z-20 relative rounded-3xl overflow-hidden" :style="{ width: midColWidth + 'px' }">
                <div class="flex flex-col h-full p-6">
                    <button @click="createItem('space')" class="bg-blue-600 hover:bg-blue-500 text-white py-4 rounded-xl text-xs font-black uppercase tracking-widest mb-8 shadow-lg shadow-blue-500/20 transition-all">+ New Space</button>
                    <div class="flex-1 overflow-y-auto space-y-8 pr-2">
                        <div v-for="space in spaces" :key="space.id">
                            <div class="flex justify-between items-center group mb-4 px-2">
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">{{ space.title }}</span>
                                <div class="hidden group-hover:flex items-center gap-2">
                                    <button @click.stop="moveItem(space, 'up', 'space')" class="text-slate-400 hover:text-blue-400 text-base leading-none transition-colors px-1">↑</button>
                                    <button @click.stop="moveItem(space, 'down', 'space')" class="text-slate-400 hover:text-blue-400 text-base leading-none transition-colors px-1">↓</button>
                                    <button @click.stop="renameSpace(space)" class="text-slate-400 hover:text-white transition-colors px-1">✎</button>
                                    <button @click.stop="createItem('page', space.id)" class="text-slate-200 hover:text-white bg-slate-700/70 hover:bg-blue-600/30 transition-colors text-lg leading-none px-2 rounded-md">+</button>
                                    <button @click.stop="confirmDelete(space.id, 'space')" class="text-slate-400 hover:text-red-400 transition-colors">x</button>
                                </div>
                            </div>
                            <ul class="space-y-1">
                                <template v-for="page in getPages(space.id)" :key="page.id">
                                    <li @click="selectDoc(page)" class="nav-item group flex flex-col cursor-pointer">
                                        <div class="flex items-center justify-between pl-4 pr-3 py-2.5 rounded-xl transition-all" :class="activePage?.id == page.id ? 'bg-blue-600/20 text-blue-400 font-bold nav-item-active shadow-inner' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white'">
                                            <div class="nav-indicator"></div>
                                            <div class="min-w-0 flex-1">
                                                <span class="truncate text-sm block">{{ page.has_draft ? page.draft_title : page.title }}</span>
                                                <span class="truncate text-[9px] block text-slate-500">{{ getItemMetaLabel(page) }}</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span v-if="page.is_public == 1" class="text-[8px] bg-green-500/20 text-green-400 px-2 py-0.5 rounded-full font-black uppercase">Live</span>
                                                <button @click.stop="moveItem(page, 'up', 'page', space.id)" class="hidden group-hover:block text-slate-400 hover:text-blue-400 text-base leading-none transition-colors px-1">↑</button>
                                                <button @click.stop="moveItem(page, 'down', 'page', space.id)" class="hidden group-hover:block text-slate-400 hover:text-blue-400 text-base leading-none transition-colors px-1">↓</button>
                                                <button @click.stop="createItem('subpage', page.id)" class="hidden group-hover:block text-slate-200 hover:text-white bg-slate-700/70 hover:bg-blue-600/30 text-2xl font-light leading-none transition-colors px-2 rounded-md">+</button>
                                            </div>
                                        </div>
                                        <ul class="mt-2 ml-4 space-y-1 border-l border-slate-700 pl-2">
                                            <li v-for="node in getNestedSubpages(page.id)" :key="node.item.id" @click.stop="selectDoc(node.item)" class="nav-item group pl-4 pr-3 py-2 rounded-xl text-xs transition-all flex items-center justify-between" :class="activePage?.id == node.item.id ? 'bg-blue-600/20 text-blue-400 font-bold nav-item-active shadow-inner' : 'text-slate-400 hover:bg-slate-700/50 hover:text-white'">
                                                <div class="flex items-center min-w-0 gap-2" :style="{ marginLeft: (node.depth * 16) + 'px' }">
                                                    <div class="nav-indicator"></div>
                                                    <div class="min-w-0 flex-1">
                                                        <span class="truncate block">{{ node.item.has_draft ? node.item.draft_title : node.item.title }}</span>
                                                        <span class="truncate text-[9px] block text-slate-500">{{ getItemMetaLabel(node.item) }}</span>
                                                    </div>
                                                </div>
                                                <div class="hidden group-hover:flex items-center gap-1">
                                                    <button @click.stop="moveItem(node.item, 'up', 'subpage', node.parentId)" class="text-slate-400 hover:text-blue-400 text-base leading-none transition-colors px-1">↑</button>
                                                    <button @click.stop="moveItem(node.item, 'down', 'subpage', node.parentId)" class="text-slate-400 hover:text-blue-400 text-base leading-none transition-colors px-1">↓</button>
                                                    <button @click.stop="createItem('subpage', node.item.id)" class="text-slate-200 hover:text-white bg-slate-700/70 hover:bg-blue-600/30 text-lg leading-none transition-colors px-2 rounded-md">+</button>
                                                </div>
                                            </li>
                                        </ul>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </div>
            </aside>

            <div @mousedown="startDrag('midCol')" class="w-2 cursor-col-resize z-50 shrink-0 flex items-center justify-center group"><div class="w-1 h-12 rounded-full bg-slate-700 group-hover:bg-blue-500/50 transition-colors"></div></div>

            <main class="bg-slate-800/80 border border-slate-700 shadow-2xl flex-1 flex flex-col relative overflow-y-auto rounded-3xl scroll-smooth">
                <div v-if="loading" class="fixed top-0 left-0 right-0 h-1 bg-blue-500 animate-pulse z-50 rounded-t-3xl"></div>
                
                <template v-if="activePage">
                    <div class="flex-1 flex flex-col min-h-max relative">
                        
                        <header class="relative shrink-0 transition-all duration-700 ease-in-out h-64 overflow-hidden" :class="hasCover(activePage) ? '' : 'bg-transparent'" :style="getCoverStyle(activePage)">
                            <div v-if="hasCover(activePage)" class="absolute inset-0 bg-gradient-to-t from-slate-800 to-transparent"></div>
                            <div class="absolute top-8 right-8 z-20 flex gap-3">
                                <input type="file" id="coverUpload" accept="image/*" class="hidden" @change="handleCoverUpload">
                                <button v-if="hasCover(activePage)" @click="confirmRemoveBanner" class="bg-red-500/80 backdrop-blur-md hover:bg-red-500 text-white px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-xl transition-all">Remove</button>
                                <button @click="triggerCoverUpload" class="bg-slate-800/90 border border-slate-600 backdrop-blur-md hover:bg-slate-700 text-white px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-2xl transition-all">
                                    {{ hasCover(activePage) ? 'Change Banner' : 'Add Banner' }}
                                </button>
                            </div>
                            <div class="absolute bottom-8 left-12 z-20">
                                <div class="inline-flex items-center gap-3">
                                    <input
                                        ref="pageTitleInput"
                                        v-model="activePage.title"
                                        placeholder="Untitled Page"
                                        class="text-4xl md:text-5xl font-black bg-transparent text-white outline-none w-[min(55vw,32rem)] placeholder-slate-300 transition-all border-none focus:ring-0 drop-shadow-2xl"
                                    >
                                </div>
                            </div>
                        </header>

                        <div class="sticky top-0 bg-slate-800/95 backdrop-blur-md z-30 p-4 px-12 flex justify-between items-center border-b border-slate-700 shadow-md">
                            <div class="flex flex-col gap-2">
                                <div class="flex items-center gap-6">
                                    <span v-if="lastSaveTime" class="text-blue-400 font-mono text-[10px] tracking-widest uppercase">Signal: {{ lastSaveTime }}</span>
                                    <span v-if="activePage.has_draft == 1" class="text-amber-500 font-black animate-pulse uppercase text-[10px] tracking-widest bg-amber-500/10 px-3 py-1 rounded-md">Unpublished</span>
                                </div>
                                <span class="text-slate-400 font-mono text-[10px] tracking-widest uppercase">{{ getItemMetaLabel(activePage) }}</span>
                            </div>
                            
                            <div class="flex items-center gap-3">
                                <button
                                    @click="undoEditor"
                                    :disabled="!canUndo"
                                    class="bg-slate-700 hover:bg-slate-600 disabled:hover:bg-slate-700 text-white px-4 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-md border border-slate-600 disabled:opacity-40 disabled:cursor-not-allowed"
                                >
                                    Undo
                                </button>
                                <button
                                    @click="redoEditor"
                                    :disabled="!canRedo"
                                    class="bg-slate-700 hover:bg-slate-600 disabled:hover:bg-slate-700 text-white px-4 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-md border border-slate-600 disabled:opacity-40 disabled:cursor-not-allowed"
                                >
                                    Redo
                                </button>
                                <label class="flex items-center text-[10px] font-black uppercase text-slate-300 gap-3 cursor-pointer hover:text-white transition-colors bg-slate-700/60 px-4 py-3 rounded-xl border border-slate-600">
                                    <span>Live</span>
                                    <input type="checkbox" v-model="activePage.is_public" @change="autoSave" :true-value="1" :false-value="0" class="accent-blue-500 w-5 h-5 rounded-md cursor-pointer">
                                </label>
                                <span v-if="publishNotice" class="text-emerald-400 font-mono text-[10px] tracking-widest uppercase">{{ publishNotice }}</span>
                                <template v-if="activePage.is_public == 1">
                                    <button @click="copyPublicLink" class="bg-slate-700 hover:bg-slate-600 text-white px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-md flex items-center gap-2 border border-slate-600">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" /></svg>
                                        Copy Link
                                    </button>
                                    <a :href="'p.php?s=' + activePage.slug" target="_blank" class="bg-slate-700 hover:bg-slate-600 text-white px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-md border border-slate-600">Open Public</a>
                                </template>

                                <button
                                    @click="manualPublish"
                                    :disabled="!canPublish"
                                    class="text-white px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all"
                                    :class="canPublish
                                        ? 'bg-blue-600 hover:bg-blue-500 shadow-lg shadow-blue-500/20 active:scale-95'
                                        : 'bg-slate-600 cursor-not-allowed opacity-70'"
                                >Publish/Save</button>
                                <button @click.stop="confirmDelete(activePage.id, 'page')" class="text-slate-400 hover:text-red-400 transition-colors bg-slate-700 p-2.5 rounded-xl hover:bg-red-500/20 ml-2 border border-slate-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                        </div>

                        <div class="p-12 px-20 max-w-5xl mx-auto w-full pb-32" @click.self="focusEditor">
                            <div id="editorjs" class="w-full"></div>
                        </div>
                    </div>
                </template>
                
                <div v-else class="flex-1 flex flex-col items-center justify-center text-slate-500 selection:bg-transparent h-full min-h-full">
                    <p class="font-black uppercase tracking-[0.5em] text-[10px] drop-shadow-lg">LunarDesk Ready</p>
                </div>
            </main>
        </div>

        <transition name="modal">
            <div v-if="confirmDialog.show" class="fixed inset-0 z-[500] bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-slate-800 border border-slate-700 p-10 rounded-3xl w-full max-w-md shadow-2xl">
                    <h2 class="text-2xl font-black text-white mb-3 uppercase tracking-tighter">{{ confirmDialog.title }}</h2>
                    <p class="text-slate-300 text-sm mb-10 leading-relaxed">{{ confirmDialog.message }}</p>
                    <div class="flex gap-4">
                        <button @click="confirmDialog.show = false" class="flex-1 bg-slate-700 hover:bg-slate-600 text-white font-black uppercase py-4 rounded-xl text-xs transition-all border border-slate-600">NO</button>
                        <button @click="executeConfirm" class="flex-1 bg-red-500 hover:bg-red-400 text-white font-black uppercase py-4 rounded-xl text-xs shadow-xl shadow-red-500/20 transition-all">YES</button>
                    </div>
                </div>
            </div>
        </transition>

        <transition name="modal">
            <div v-if="alertDialog.show" class="fixed inset-0 z-[600] bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-slate-800 border border-slate-700 p-10 rounded-3xl w-full max-w-md shadow-2xl text-center">
                    <h2 class="text-2xl font-black text-white mb-3 uppercase tracking-tighter">{{ alertDialog.title }}</h2>
                    <p class="text-slate-300 text-sm mb-10 leading-relaxed">{{ alertDialog.message }}</p>
                    <button @click="alertDialog.show = false" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-black uppercase py-4 rounded-xl text-xs shadow-lg shadow-blue-500/20 transition-all">Continue</button>
                </div>
            </div>
        </transition>

        <transition name="modal">
            <div v-if="showUpdateModal" class="fixed inset-0 z-[610] bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-slate-800 border border-slate-700 p-10 rounded-3xl w-full max-w-2xl shadow-2xl">
                    <h2 class="text-2xl font-black text-white mb-2 uppercase tracking-tighter">System Updated</h2>
                    <p class="text-slate-400 text-xs uppercase tracking-[0.2em] mb-6">Version {{ updateNoticeVersion }}</p>
                    <p class="text-slate-300 text-sm mb-5">LunarDesk has been updated. What's new:</p>
                    <ul class="list-disc pl-6 text-slate-300 text-sm space-y-2 mb-8">
                        <li v-for="note in updateNotes" :key="note">{{ note }}</li>
                    </ul>
                    <div class="flex justify-end">
                        <button @click="dismissUpdateNotice" class="bg-blue-600 hover:bg-blue-500 text-white font-black uppercase py-3 px-8 rounded-xl text-xs shadow-lg shadow-blue-500/20 transition-all">Continue</button>
                    </div>
                </div>
            </div>
        </transition>

        <transition name="modal">
            <div v-if="showManualModal" class="fixed inset-0 z-[120] bg-black/80 backdrop-blur-md flex items-center justify-center p-6">
                <div class="bg-slate-800 border border-slate-700 p-10 rounded-3xl w-full max-w-6xl shadow-2xl max-h-[92vh] overflow-y-auto">
                    <div class="flex justify-between items-start mb-8 gap-6">
                        <div>
                            <h2 class="text-3xl font-black text-white uppercase tracking-tighter">LunarDesk Manual</h2>
                            <p class="text-slate-400 text-xs uppercase tracking-[0.2em] mt-2">Editor, pages, publishing and table workflows</p>
                        </div>
                        <button @click="showManualModal = false" class="text-slate-500 hover:text-white transition-all duration-300 hover:rotate-90 p-2 text-xl font-bold outline-none">&times;</button>
                    </div>

                    <div class="space-y-8 text-slate-300 text-sm leading-relaxed">
                        <section id="manual-index" class="bg-slate-900/50 border border-slate-700 rounded-2xl p-6">
                            <h3 class="text-white font-black uppercase text-xs tracking-[0.2em] mb-4">Index</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                                <a href="#manual-1" class="text-blue-300 hover:text-blue-200 transition-colors">1. Workspace Layout</a>
                                <a href="#manual-2" class="text-blue-300 hover:text-blue-200 transition-colors">2. Structure: Spaces, Pages, Subpages</a>
                                <a href="#manual-3" class="text-blue-300 hover:text-blue-200 transition-colors">3. Page Header Controls</a>
                                <a href="#manual-4" class="text-blue-300 hover:text-blue-200 transition-colors">4. Editor Blocks and Formatting</a>
                                <a href="#manual-5" class="text-blue-300 hover:text-blue-200 transition-colors">5. Autosave, Drafts and Publish Flow</a>
                                <a href="#manual-6" class="text-blue-300 hover:text-blue-200 transition-colors">6. Table Editing: Core Actions</a>
                                <a href="#manual-7" class="text-blue-300 hover:text-blue-200 transition-colors">7. Manual Multi-Cell Merge</a>
                                <a href="#manual-8" class="text-blue-300 hover:text-blue-200 transition-colors">8. Keyboard Shortcuts</a>
                                <a href="#manual-9" class="text-blue-300 hover:text-blue-200 transition-colors">9. Stream, Terminal and Admin Utilities</a>
                                <a href="#manual-10" class="text-blue-300 hover:text-blue-200 transition-colors">10. Update Check and Content Recovery</a>
                            </div>
                        </section>

                        <section id="manual-1">
                            <h3 class="text-white font-black uppercase text-xs tracking-[0.2em] mb-3">1. Workspace Layout</h3>
                            <p>The interface is split into three primary columns. The left column contains Stream and Terminal, the middle column contains your Spaces and page tree, and the main panel contains the active page editor.</p>
                            <p class="mt-2">You can resize the columns by dragging the vertical handles between panels. Inside Stream, you can also resize the Channels area with the horizontal drag handle.</p>
                        </section>

                        <section id="manual-2">
                            <h3 class="text-white font-black uppercase text-xs tracking-[0.2em] mb-3">2. Structure: Spaces, Pages, Subpages</h3>
                            <p>Use <strong>+ New Space</strong> to create a top-level container. Inside each space, create pages and nested subpages from the plus buttons shown on hover. Use the up/down controls to reorder items.</p>
                            <p class="mt-2">Every item can hold draft data. A page marked as <strong>Unpublished</strong> has changes saved in draft that are not yet pushed to the public page.</p>
                        </section>

                        <section id="manual-3">
                            <h3 class="text-white font-black uppercase text-xs tracking-[0.2em] mb-3">3. Page Header Controls</h3>
                            <p>At the top of each page, you can edit title and cover banner. In the sticky action bar, the controls work as follows:</p>
                            <ul class="list-disc pl-6 mt-2 space-y-1">
                                <li><strong>Undo / Redo:</strong> Step backward or forward through editor history.</li>
                                <li><strong>Live toggle:</strong> Sets public visibility on or off.</li>
                                <li><strong>Copy Link / Open Public:</strong> Available when the page is public.</li>
                                <li><strong>Publish:</strong> Pushes current draft to the public version.</li>
                                <li><strong>Delete icon:</strong> Removes the current page.</li>
                            </ul>
                        </section>

                        <section id="manual-4">
                            <h3 class="text-white font-black uppercase text-xs tracking-[0.2em] mb-3">4. Editor Blocks and Formatting</h3>
                            <p>LunarDesk uses block-based editing. Add content blocks using EditorJS tools such as Paragraph, Header, List, Checkboxes, Quote, Warning, Code, Delimiter, Image, Embed and Table.</p>
                            <p class="mt-2">Inline formatting supports bold, italic, links, underline and inline code. Most blocks can be edited directly and reordered with the standard block controls.</p>
                        </section>

                        <section id="manual-5">
                            <h3 class="text-white font-black uppercase text-xs tracking-[0.2em] mb-3">5. Autosave, Drafts and Publish Flow</h3>
                            <p>Changes are auto-saved to draft while you edit. The signal timestamp confirms the latest successful save. Draft saves do not automatically update the public page.</p>
                            <p class="mt-2">To push your latest draft to public output, use <strong>Publish</strong>. Public viewers only see published content from <code>p.php?s=slug</code>.</p>
                        </section>

                        <section id="manual-6">
                            <h3 class="text-white font-black uppercase text-xs tracking-[0.2em] mb-3">6. Table Editing: Core Actions</h3>
                            <p>Right-click any table cell to open the table context menu. You can also type <strong>/</strong> inside a table cell to open the Slash Commands popup with search.</p>
                            <p class="mt-2">Both menus provide the same actions: row/column insert and delete, merge/split, duplicate cell content, row height and column width adjustments, cell padding, background colors, alignment, border styling, table layout mode and table deletion.</p>
                            <p class="mt-2">Example slash queries: <code>/radiobutton</code>, <code>/duplicate right</code>, <code>/insert row below</code>.</p>
                            <p class="mt-2">Use <strong>Tab</strong> to move to the next cell. At the end of a row, Tab can create a new row automatically.</p>
                        </section>

                        <section id="manual-7">
                            <h3 class="text-white font-black uppercase text-xs tracking-[0.2em] mb-3">7. Manual Multi-Cell Merge (New)</h3>
                            <p>You can manually merge a selected range of cells into a single cell:</p>
                            <ul class="list-disc pl-6 mt-2 space-y-1">
                                <li>Hold <strong>Alt</strong> and drag across cells to select a rectangular range.</li>
                                <li>Right-click inside the table and choose <strong>Merge selected cells (manual)</strong>.</li>
                                <li>Use <strong>Clear selected cells</strong> or press <strong>Esc</strong> to clear the selection highlight.</li>
                            </ul>
                            <p class="mt-2">Validation rules: selection must be one continuous rectangle in the same table. If merged cells already exist in that range, split them first, then merge again.</p>
                        </section>

                        <section id="manual-8">
                            <h3 class="text-white font-black uppercase text-xs tracking-[0.2em] mb-3">8. Keyboard Shortcuts</h3>
                            <ul class="list-disc pl-6 mt-2 space-y-1">
                                <li><strong>Ctrl/Cmd + Z:</strong> Undo</li>
                                <li><strong>Ctrl/Cmd + Shift + Z:</strong> Redo</li>
                                <li><strong>Ctrl/Cmd + Y:</strong> Redo</li>
                                <li><strong>/ (inside table cell):</strong> Open Table Slash Commands popup</li>
                                <li><strong>Tab / Shift + Tab:</strong> Move between table cells</li>
                                <li><strong>Esc:</strong> Clear current manual table selection</li>
                            </ul>
                        </section>

                        <section id="manual-9">
                            <h3 class="text-white font-black uppercase text-xs tracking-[0.2em] mb-3">9. Stream, Terminal and Admin Utilities</h3>
                            <p><strong>Stream</strong> shows channel messages and webhook output. <strong>Terminal</strong> is an admin command/message stream. Admin actions include room settings, webhook generation, message cleanup, and user access management.</p>
                        </section>

                        <section id="manual-10">
                            <h3 class="text-white font-black uppercase text-xs tracking-[0.2em] mb-3">10. Update Check and Content Recovery</h3>
                            <p>After an update, it is possible that existing content does not render correctly in some pages. Always check your important pages manually after updating.</p>
                            <p class="mt-2">If content is broken after an update, verify the page state and recreate the affected content manually.</p>
                            <p class="mt-2 text-slate-300"><strong>This guidance applies to all users.</strong></p>
                            <p class="mt-2"><strong>Admin note:</strong> In most cases, deleting the database file is not required. If problems continue, check carefully first. Only if it is truly necessary, remove the database file so the system can create a new one.</p>
                            <p class="mt-2 text-slate-400">The database maintenance note above is intended for administrators and is generally not needed for regular users.</p>
                        </section>
                    </div>

                    <div class="pt-8">
                        <button @click="showManualModal = false" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-black py-4 rounded-xl text-xs uppercase tracking-widest transition-all shadow-lg shadow-blue-500/20">Close Manual</button>
                    </div>
                </div>
            </div>
        </transition>

        <transition name="modal">
            <div v-if="showUsersModal" class="fixed inset-0 z-[100] bg-black/80 backdrop-blur-md flex items-center justify-center p-6">
                <div class="bg-slate-800 border border-slate-700 p-12 rounded-3xl w-full max-w-6xl shadow-2xl flex flex-col max-h-[90vh]">
                    <div class="flex justify-between items-center mb-10">
                        <h2 class="text-4xl font-black text-white uppercase tracking-tighter">Access</h2>
                        <button @click="showUsersModal = false" class="text-slate-500 hover:text-white transition-all duration-300 hover:rotate-90 p-2 text-xl font-bold outline-none">&times;</button>
                    </div>
                    <div class="flex flex-col md:flex-row gap-12 overflow-hidden">
                        <div class="w-full md:w-96 bg-slate-900/50 border border-slate-700 p-8 rounded-2xl shadow-inner">
                            <h3 class="text-[10px] font-black text-blue-400 uppercase tracking-[0.3em] mb-8">{{ editingUser ? 'Update Profile' : 'Invite New' }}</h3>
                            <form @submit.prevent="saveUser" class="space-y-5">
                                <div v-if="editingUser"><label class="block text-[9px] font-black text-slate-400 uppercase mb-2">Username</label><input v-model="userForm.username" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-4 text-sm text-white outline-none focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50 transition-all placeholder-slate-500" required></div>
                                <div v-if="editingUser"><label class="block text-[9px] font-black text-slate-400 uppercase mb-2">Display Name</label><input v-model="userForm.nickname" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-4 text-sm text-white outline-none focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50 transition-all placeholder-slate-500" required></div>
                                <div><label class="block text-[9px] font-black text-slate-400 uppercase mb-2">Email</label><input v-model="userForm.email" type="email" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-4 text-sm text-white outline-none focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50 transition-all placeholder-slate-500" required></div>
                                <div><label class="block text-[9px] font-black text-slate-400 uppercase mb-2">Role</label>
                                    <select v-model="userForm.role" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-4 text-sm text-white outline-none focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50 appearance-none transition-all">
                                        <option value="user">Collaborator</option>
                                        <option value="admin">Administrator</option>
                                    </select>
                                </div>
                                <div class="flex gap-3 pt-6">
                                    <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-4 rounded-xl text-xs font-black uppercase tracking-widest w-full shadow-lg shadow-blue-500/20 transition-all">{{ editingUser ? 'Apply' : 'Invite' }}</button>
                                    <button v-if="editingUser" type="button" @click="cancelEditUser" class="bg-slate-700 hover:bg-slate-600 text-white px-6 py-4 rounded-xl text-xs font-bold transition-all border border-slate-600">Cancel</button>
                                </div>
                            </form>
                        </div>
                        <div class="flex-1 overflow-y-auto bg-slate-900/50 border border-slate-700 rounded-2xl shadow-inner">
                            <table class="w-full text-left text-sm border-separate border-spacing-0">
                                <thead class="sticky top-0 bg-slate-800/95 backdrop-blur-sm text-[10px] font-black uppercase text-slate-400 tracking-[0.2em] z-10">
                                    <tr><th class="p-6 border-b border-slate-700">Identity</th><th class="p-6 border-b border-slate-700">Access</th><th class="p-6 border-b border-slate-700 text-right">Actions</th></tr>
                                </thead>
                                <tbody class="divide-y divide-slate-700">
                                    <tr v-for="user in userList" :key="user.id" class="hover:bg-slate-800 transition-colors">
                                        <td class="p-6"><span class="font-black text-white tracking-tight uppercase">{{ user.nickname || 'Pending' }}</span><span class="block text-[10px] text-slate-400 font-mono mt-1">{{ user.email }}</span></td>
                                        <td class="p-6"><span class="px-3 py-1 border border-slate-600 rounded-full text-[9px] font-black uppercase tracking-widest" :class="user.role === 'admin' ? 'text-blue-400 bg-blue-500/10' : 'text-slate-300 bg-slate-700'">{{ user.role }}</span></td>
                                        <td class="p-6 text-right">
                                            <button @click="editUser(user)" class="text-blue-400 hover:text-blue-300 mr-6 text-[10px] font-black uppercase tracking-widest bg-blue-500/10 px-3 py-1.5 rounded-lg transition-colors">Edit</button>
                                            <button v-if="user.id !== currentUser.id" @click="confirmDeleteUser(user.id)" class="text-red-400 hover:text-red-300 text-[10px] font-black uppercase tracking-widest bg-red-500/10 px-3 py-1.5 rounded-lg transition-colors">Delete</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <transition name="modal">
            <div v-if="showProfileModal" class="fixed inset-0 z-[100] bg-black/80 backdrop-blur-md flex items-center justify-center p-6">
                <div class="bg-slate-800 border border-slate-700 p-12 rounded-3xl w-full max-w-md shadow-2xl">
                    <div class="flex justify-between items-center mb-10">
                        <h2 class="text-3xl font-black text-white uppercase tracking-tighter">Your Profile</h2>
                        <button @click="showProfileModal = false" class="text-slate-500 hover:text-white transition-all duration-300 hover:rotate-90 p-2 text-xl font-bold outline-none">&times;</button>
                    </div>
                    <form @submit.prevent="updateProfile" class="space-y-6">
                        <div><label class="block text-[9px] font-black text-slate-400 uppercase mb-2 ml-1">Username</label><input v-model="profileForm.username" type="text" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-4 text-sm text-white outline-none focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50 transition-all" required></div>
                        <div><label class="block text-[9px] font-black text-slate-400 uppercase mb-2 ml-1">Nickname</label><input v-model="profileForm.nickname" type="text" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-4 text-sm text-white outline-none focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50 transition-all" required></div>
                        <div><label class="block text-[9px] font-black text-slate-400 uppercase mb-2 ml-1">Email</label><input v-model="profileForm.email" type="email" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-4 text-sm text-white outline-none focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50 transition-all" required></div>
                        <div><label class="block text-[9px] font-black text-slate-400 uppercase mb-2 ml-1">New Password</label><input v-model="profileForm.password" type="password" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-4 text-sm text-white outline-none focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50 transition-all placeholder-slate-600" placeholder="Empty to keep"></div>
                        <div class="pt-6"><button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-black py-4 rounded-xl text-xs uppercase tracking-widest transition-all shadow-lg shadow-blue-500/20">Save Changes</button></div>
                    </form>
                </div>
            </div>
        </transition>

        <div v-if="showTableSlashModal" class="fixed inset-0 z-[175] bg-black/70 backdrop-blur-sm flex items-center justify-center p-6">
            <div class="bg-slate-800 border border-slate-700 p-8 rounded-3xl w-full max-w-xl shadow-2xl">
                <h2 class="text-xl font-black text-white mb-4 uppercase tracking-tighter">Table Slash Commands</h2>
                <p class="text-slate-400 text-xs uppercase tracking-[0.2em] mb-4">Type to search command</p>
                <form @submit.prevent="runTableSlashCommand()" class="space-y-4">
                    <input
                        type="text"
                        v-model="tableSlashQuery"
                        ref="tableSlashInputRef"
                        class="w-full bg-slate-900 border border-slate-700 rounded-xl p-4 text-sm text-white outline-none focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50 transition-all placeholder-slate-600"
                        placeholder="radiobutton"
                    >
                    <div class="max-h-64 overflow-y-auto space-y-2">
                        <button
                            v-for="cmd in getFilteredTableSlashCommands()"
                            :key="cmd.id"
                            type="button"
                            @click="runTableSlashCommand(cmd.id)"
                            class="w-full text-left bg-slate-900/70 hover:bg-slate-700/70 border border-slate-700 rounded-xl p-3 transition-colors"
                        >
                            <div class="text-white font-black text-sm">{{ cmd.label }}</div>
                            <div class="text-slate-400 text-xs mt-1">{{ cmd.hint }}</div>
                        </button>
                    </div>
                    <div class="flex gap-4 pt-2">
                        <button type="button" @click="closeTableSlashHelper" class="flex-1 bg-slate-700 hover:bg-slate-600 text-white font-black uppercase py-4 rounded-xl text-xs transition-all border border-slate-600">Cancel</button>
                        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-500 text-white py-4 rounded-xl text-xs font-black uppercase tracking-widest shadow-lg shadow-blue-500/20 transition-all">Run</button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showRadioBuilderModal" class="fixed inset-0 z-[170] bg-black/80 backdrop-blur-md flex items-center justify-center p-6">
            <div class="bg-slate-800 border border-slate-700 p-10 rounded-3xl w-full max-w-lg shadow-2xl">
                <h2 class="text-2xl font-black text-white mb-8 uppercase tracking-tighter">Insert Radio Group</h2>
                <form @submit.prevent="submitTableRadioBuilder" class="space-y-5">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">Group Title (optional)</label>
                        <input type="text" v-model="radioBuilderForm.title" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-4 text-sm text-white outline-none focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50 transition-all placeholder-slate-600" placeholder="Choose one">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">Options (one per line)</label>
                        <textarea v-model="radioBuilderForm.optionsText" ref="radioOptionsInputRef" rows="7" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-4 text-sm text-white outline-none focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50 transition-all placeholder-slate-600 resize-y" placeholder="Option 1&#10;Option 2" required></textarea>
                    </div>
                    <div class="flex gap-4 pt-2">
                        <button type="button" @click="cancelTableRadioBuilder" class="flex-1 bg-slate-700 hover:bg-slate-600 text-white font-black uppercase py-4 rounded-xl text-xs transition-all border border-slate-600">Cancel</button>
                        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-500 text-white py-4 rounded-xl text-xs font-black uppercase tracking-widest shadow-lg shadow-blue-500/20 transition-all">Insert</button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showPromptModal" class="fixed inset-0 z-[150] bg-black/80 backdrop-blur-md flex items-center justify-center p-6">
            <div class="bg-slate-800 border border-slate-700 p-12 rounded-3xl w-full max-w-md shadow-2xl">
                <h2 class="text-2xl font-black text-white mb-8 uppercase tracking-tighter">{{ promptTitle }}</h2>
                <form @submit.prevent="submitPrompt">
                    <input type="text" v-model="promptInput" ref="promptInputRef" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-5 text-sm text-white outline-none mb-10 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50 transition-all placeholder-slate-600" required placeholder="...">
                    <div class="flex gap-4">
                        <button type="button" @click="showPromptModal = false" class="flex-1 bg-slate-700 hover:bg-slate-600 text-white font-black uppercase py-4 rounded-xl text-xs transition-all border border-slate-600">Cancel</button>
                        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-500 text-white py-4 rounded-xl text-xs font-black uppercase tracking-widest shadow-lg shadow-blue-500/20 transition-all">Confirm</button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showSettingsModal" class="fixed inset-0 z-[100] bg-black/80 backdrop-blur-md flex items-center justify-center p-6">
            <div class="bg-slate-800 border border-slate-700 p-12 rounded-3xl w-full max-w-lg shadow-2xl text-center">
                <h2 class="text-2xl font-black text-white mb-8 uppercase tracking-tighter">Configuration</h2>
                <div class="bg-slate-900/50 border border-slate-700 p-8 mb-10 rounded-2xl">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">Webhook Link</label>
                    <div v-if="settingsRoom.webhook_key" class="flex flex-col gap-4">
                        <input type="text" readonly :value="getWebhookUrl(settingsRoom.webhook_key)" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-4 text-[10px] text-green-400 font-mono text-center shadow-inner">
                        <button @click="confirmDeleteWebhook" class="text-red-400 hover:text-red-300 text-[10px] font-black uppercase tracking-widest transition-colors bg-red-500/10 py-2 rounded-lg">Revoke Link</button>
                    </div>
                    <button v-else @click="generateWebhook" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-4 rounded-xl text-xs font-black uppercase tracking-widest w-full shadow-lg shadow-blue-500/20 transition-all">Enable Webhook</button>
                </div>
                <div class="flex justify-between items-center px-4">
                    <button @click="confirmDeleteRoom" class="text-red-400 hover:text-red-300 text-[10px] font-black uppercase tracking-widest transition-colors bg-red-500/10 px-4 py-2 rounded-lg">Delete Room</button>
                    <button @click="showSettingsModal = false" class="bg-slate-700 hover:bg-slate-600 text-white px-10 py-3 rounded-xl text-xs font-black uppercase tracking-widest transition-all border border-slate-600">Done</button>
                </div>
            </div>
        </div>

        <div v-if="showCropModal" class="fixed inset-0 z-[200] bg-black/95 backdrop-blur-xl flex items-center justify-center p-4">
            <div class="bg-slate-800 border border-slate-700 rounded-3xl w-full max-w-5xl flex flex-col shadow-2xl overflow-hidden">
                <div class="p-8 border-b border-slate-700 flex justify-between items-center bg-slate-900/50">
                    <h2 class="text-2xl font-black text-white uppercase tracking-tighter">Format Identity</h2>
                    <button @click="cancelCrop" class="text-slate-500 hover:text-white transition-all duration-300 hover:rotate-90 p-2 text-xl font-bold outline-none">&times;</button>
                </div>
                <div class="p-1 bg-black/50 flex justify-center items-center" style="height: 60vh;"><img id="cropImage" :src="cropImageSrc" class="max-w-full max-h-full block rounded-xl"></div>
                <div class="p-8 border-t border-slate-700 flex justify-end gap-6 bg-slate-900/50">
                    <button @click="cancelCrop" class="text-slate-300 hover:text-white font-black uppercase tracking-widest py-2 px-6 transition-colors">Discard</button>
                    <button @click="applyCrop" class="bg-blue-600 hover:bg-blue-500 text-white px-12 py-4 rounded-xl text-xs font-black uppercase tracking-widest shadow-xl transition-all">Apply Changes</button>
                </div>
            </div>
        </div>

        <footer class="shrink-0 h-14 flex items-center justify-between px-10 bg-slate-900 border-t border-slate-800 z-30 relative">
            <span class="text-[10px] text-slate-400 font-black uppercase tracking-[0.3em]">LunarDesk &bull; <?php echo $app_version; ?> <br> Timezone is <a href="https://time.is/UTC" target="_blank">UTC</a></span>
            <span class="text-[10px] text-slate-400 font-black uppercase tracking-[0.3em]">2026 &copy; Ported by <a href="https://github.com/ByAldon" target="_blank" class="hover:text-blue-400 transition-colors">Aldon</a></span>
        </footer>

    </div>
    <script src="assets/js/app.js?v=<?php echo urlencode($app_version); ?>"></script>
</body>
</html>
