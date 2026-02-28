// app.js
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
                if (wrapper) {
                    const wrapperRect = wrapper.getBoundingClientRect();
                    const cellRect = cell.getBoundingClientRect();
                    this.cellMenuTop = cellRect.top - wrapperRect.top + wrapper.scrollTop - 45;
                    this.cellMenuLeft = cellRect.left - wrapperRect.left + wrapper.scrollLeft;
                    this.showCellMenu = true;
                }
            } else if (!e.target.closest('.floating-menu')) {
                this.showCellMenu = false;
                window.currentActiveCell = null;
            }
        };

        document.addEventListener('click', updateActiveCell);
        document.addEventListener('keyup', updateActiveCell);
    },
    methods: {
        linkify(text) {
            if(!text) return '';
            const urlRegex = /(https?:\/\/[^\s]+)/g;
            return text.replace(urlRegex, (url) => {
                return `<a href="${url}" target="_blank" class="chat-link">${url}</a>`;
            });
        },

        setCellColor(color) {
            if (window.currentActiveCell) {
                window.currentActiveCell.style.backgroundColor = color;
                this.activeCellColor = color || '#1e293b';
                this.needsSave = true;
            }
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