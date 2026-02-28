// app.js
let globalEditorInstance = null;

function focusEditor() {
    if (globalEditorInstance) globalEditorInstance.focus();
}

const { createApp } = Vue;
createApp({
    data() {
        return {
            currentUser: null,
            items: [],
            activePage: null,
            loading: false,
            lastSavedContent: null,
            lastSavedTitle: null,
            lastSavedPublic: null,
            lastSavedCover: null,
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

            showPromptModal: false,
            promptTitle: '',
            promptInput: '',
            promptAction: null,
            promptPayload: null,

            // Account & User Management Modals
            showProfileModal: false,
            profileForm: { username: '', email: '', nickname: '', password: '' },

            showUsersModal: false,
            userList: [],
            editingUser: false,
            userForm: { id: null, username: '', email: '', nickname: '', password: '', role: 'user' },

            showCellMenu: false,
            cellMenuTop: 0,
            cellMenuLeft: 0,
            activeCellColor: '#1e293b',

            // Cropper & Beta Notice
            showCropModal: false,
            cropImageSrc: '',
            cropperInstance: null,
            
            showBetaNotice: false
        }
    },
    computed: {
        spaces() { return this.items.filter(i => i.type === 'space'); }
    },
    mounted() { 
        this.fetchProfile();
        this.fetchData(); 
        this.fetchRooms();
        this.fetchTerminal();
        
        if (!localStorage.getItem('lunardesk_beta_dismissed')) {
            this.showBetaNotice = true;
        }
        
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
                
                const mainContainer = document.querySelector('main');
                if (mainContainer) {
                    const mainRect = mainContainer.getBoundingClientRect();
                    const cellRect = cell.getBoundingClientRect();
                    
                    this.cellMenuTop = cellRect.top - mainRect.top - 45;
                    this.cellMenuLeft = cellRect.left - mainRect.left;
                    this.showCellMenu = true;
                }
            } else if (!e.target.closest('.floating-menu')) {
                this.showCellMenu = false;
                window.currentActiveCell = null;
            }
        };

        document.addEventListener('click', updateActiveCell);
        document.addEventListener('keyup', updateActiveCell);
        
        window.addEventListener('scroll', (e) => {
            if (e.target.id === 'editor-wrapper') {
                this.showCellMenu = false;
            }
        }, true);
    },
    methods: {
        dismissBetaNotice() {
            this.showBetaNotice = false;
            localStorage.setItem('lunardesk_beta_dismissed', '1');
        },
        
        // --- ACCOUNT & USER MANAGEMENT FUNCTIES ---
        async fetchProfile() {
            try {
                const res = await fetch('api.php?action=profile');
                this.currentUser = await res.json();
                this.profileForm = { ...this.currentUser, password: '' };
            } catch(e) {}
        },
        async updateProfile() {
            this.loading = true;
            await fetch('api.php?action=profile', { method: 'PUT', body: JSON.stringify(this.profileForm) });
            this.showProfileModal = false;
            await this.fetchProfile();
            this.loading = false;
        },
        async openUsersModal() {
            this.showUsersModal = true;
            await this.fetchUsers();
            this.resetUserForm();
        },
        async fetchUsers() {
            const res = await fetch('api.php?action=users');
            this.userList = await res.json();
        },
        resetUserForm() {
            this.editingUser = false;
            this.userForm = { id: null, username: '', email: '', nickname: '', password: '', role: 'user' };
        },
        editUser(user) {
            this.editingUser = true;
            this.userForm = { ...user, password: '' };
        },
        cancelEditUser() {
            this.resetUserForm();
        },
        async saveUser() {
            this.loading = true;
            if (this.editingUser) {
                await fetch('api.php?action=users', { method: 'PUT', body: JSON.stringify(this.userForm) });
            } else {
                await fetch('api.php?action=users', { method: 'POST', body: JSON.stringify(this.userForm) });
                alert("Gebruiker aangemaakt en uitnodiging verzonden!");
            }
            await this.fetchUsers();
            this.resetUserForm();
            this.loading = false;
        },
        async deleteUser(id) {
            if (!confirm("Are you sure you want to delete this user permanently?")) return;
            this.loading = true;
            await fetch(`api.php?action=users&id=${id}`, { method: 'DELETE' });
            await this.fetchUsers();
            this.loading = false;
        },

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
            if (!outputData || !outputData.blocks) return outputData;
            
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

        createRoom() {
            this.promptTitle = "Channel name?";
            this.promptInput = "";
            this.promptAction = 'createRoom';
            this.showPromptModal = true;
            this.$nextTick(() => { 
                if (this.$refs.promptInputRef) this.$refs.promptInputRef.focus(); 
            });
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
                this.fetchRooms();
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
        getSubpages(pageId) { return this.items.filter(i => i.type === 'subpage' && i.parent_id == pageId); },

        triggerCoverUpload() {
            document.getElementById('coverUpload').click();
        },
        
        // CROPPER LOGICA START
        handleCoverUpload(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = (event) => {
                this.cropImageSrc = event.target.result;
                this.showCropModal = true;
                this.$nextTick(() => {
                    this.initCropper();
                });
            };
            reader.readAsDataURL(file);
            e.target.value = ''; 
        },
        initCropper() {
            const image = document.getElementById('cropImage');
            if (this.cropperInstance) {
                this.cropperInstance.destroy();
            }
            this.cropperInstance = new Cropper(image, {
                aspectRatio: 21 / 9, 
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 1,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false,
            });
        },
        cancelCrop() {
            this.showCropModal = false;
            if (this.cropperInstance) {
                this.cropperInstance.destroy();
                this.cropperInstance = null;
            }
            this.cropImageSrc = '';
        },
        async applyCrop() {
            if (!this.cropperInstance) return;
            this.loading = true;
            
            this.cropperInstance.getCroppedCanvas({
                width: 1920,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            }).toBlob(async (blob) => {
                const formData = new FormData();
                formData.append('image', blob, 'banner.jpg');
                
                try {
                    const res = await fetch('api.php?action=upload', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();
                    if (data.success && data.file && data.file.url) {
                        this.activePage.cover_image = data.file.url;
                        this.needsSave = true;
                        this.cancelCrop();
                    } else {
                        alert("Upload failed.");
                    }
                } catch(err) {
                    console.error(err);
                    alert("Upload error.");
                }
                this.loading = false;
            }, 'image/jpeg', 0.85);
        },
        // CROPPER LOGICA EIND

        removeCover() {
            if (!confirm("Are you sure you want to remove the banner?")) return;
            this.activePage.cover_image = '';
            this.needsSave = true;
        },
        
        hasCover(page) {
            return page.cover_image && page.cover_image !== '';
        },
        getCoverStyle(page) {
            if (this.hasCover(page)) {
                return `background-image: url('${page.cover_image}'); background-size: cover; background-position: center;`;
            }
            return '';
        },

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
                    image: {
                        class: ImageTool,
                        config: {
                            endpoints: {
                                byFile: 'api.php?action=upload', 
                            }
                        }
                    },
                    Color: { class: window.ColorPlugin, config: { colorCollections: ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#ffffff', '#cbd5e1', '#94a3b8', '#1e293b'], defaultColor: '#3b82f6', type: 'text', customPicker: true } },
                    Marker: { class: window.ColorPlugin, config: { colorCollections: ['#1e293b', '#334155', '#1e3a8a', '#7f1d1d', '#14532d', '#78350f', '#4c1d95', '#831843', '#f59e0b', '#3b82f6'], defaultColor: '#1e3a8a', type: 'marker', customPicker: true } },
                    delimiter: Delimiter, inlineCode: { class: InlineCode }, embed: { class: Embed, inlineToolbar: true }
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
            
            this.activePage = { 
                ...page, 
                title: page.has_draft ? page.draft_title : page.title,
                cover_image: page.has_draft ? page.draft_cover_image : page.cover_image
            }; 
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
                this.lastSavedCover = this.activePage.cover_image;
                this.lastSaveTime = null; 
                this.needsSave = false;
            });
        },

        createItem(type, parentId = null) {
            if (type === 'space') {
                this.promptTitle = "Space Name?";
            } else if (type === 'page') {
                this.promptTitle = "Page Name?";
            } else if (type === 'subpage') {
                this.promptTitle = "Subpage Name?";
            }

            this.promptInput = "";
            this.promptAction = 'createItem';
            this.promptPayload = { type, parentId };
            this.showPromptModal = true;
            this.$nextTick(() => { 
                if (this.$refs.promptInputRef) this.$refs.promptInputRef.focus(); 
            });
        },

        async submitPrompt() {
            const title = this.promptInput.trim();
            if (!title) return;
            
            this.showPromptModal = false;

            if (this.promptAction === 'createRoom') {
                await fetch('api.php?action=rooms', { method: 'POST', body: JSON.stringify({ title }) });
                await this.postSystemMsg(`Channel #${title} manually created.`, "text-green-400");
                await this.fetchRooms();
            } 
            else if (this.promptAction === 'createItem') {
                this.loading = true;
                const type = this.promptPayload.type;
                const parentId = this.promptPayload.parentId;
                await fetch('api.php', { method: 'POST', body: JSON.stringify({ title, type, parent_id: parentId }) });
                await this.fetchData();
            }
        },
        
        async silentAutoSave() {
            if (this.activePage && globalEditorInstance) {
                let titleOrPublicChanged = (this.activePage.title !== this.lastSavedTitle || this.activePage.is_public !== this.lastSavedPublic);
                let coverChanged = (this.activePage.cover_image !== this.lastSavedCover);
                
                if (this.needsSave || titleOrPublicChanged || coverChanged) {
                    try {
                        const rawOutput = await globalEditorInstance.save();
                        const outputData = this.extractCellColors(rawOutput); 
                        const outputStr = JSON.stringify(outputData);
                        
                        await fetch('api.php', { method: 'PUT', body: JSON.stringify({ ...this.activePage, content: outputStr, action: 'draft' }) });
                        
                        this.lastSavedContent = outputStr; 
                        this.lastSavedTitle = this.activePage.title;
                        this.lastSavedPublic = this.activePage.is_public; 
                        this.lastSavedCover = this.activePage.cover_image;
                        this.lastSaveTime = this.getFormattedDateTime();
                        this.needsSave = false; 
                        this.activePage.has_draft = 1;
                        
                        const index = this.items.findIndex(i => i.id === this.activePage.id);
                        if(index !== -1) {
                            this.items[index].has_draft = 1;
                            this.items[index].draft_title = this.activePage.title;
                            this.items[index].draft_content = outputStr;
                            this.items[index].draft_cover_image = this.activePage.cover_image;
                        }
                    } catch (e) { 
                        console.error("AutoSave Error:", e);
                    }
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
                    this.lastSavedCover = this.activePage.cover_image;
                    this.lastSaveTime = this.getFormattedDateTime();
                    this.needsSave = false; 
                    this.activePage.has_draft = 0; 
                } catch (e) { 
                    console.error("Publish Error:", e);
                    alert("Er ging iets mis bij het publiceren! Controleer de developer console.");
                }
            }
            await this.fetchData(); 
            this.loading = false;
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