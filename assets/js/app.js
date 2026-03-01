// assets/js/app.js
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
            adminMessages: [{ id: 1, sender: "System", content: "Flat Design Core active. Signals connected.", colorClass: "text-blue-700" }],
            newAdminMsg: '',
            adminHeight: 250,
            leftColWidth: 320,  
            midColWidth: 280,   
            dragTarget: null,   
            showSettingsModal: false,
            settingsRoom: null,
            showPromptModal: false,
            promptTitle: '',
            promptInput: '',
            promptAction: null,
            promptPayload: null,
            showProfileModal: false,
            profileForm: { username: '', email: '', nickname: '', password: '' },
            showUsersModal: false,
            userList: [],
            editingUser: false,
            userForm: { id: null, username: '', email: '', nickname: '', password: '', role: 'user' },
            showCropModal: false,
            cropImageSrc: '',
            cropperInstance: null,
            showBetaNotice: false,
            dismissBetaPermanently: false,
            confirmDialog: { show: false, title: '', message: '', onConfirm: null },
            alertDialog: { show: false, title: '', message: '' }
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
        if (!localStorage.getItem('lunardesk_hide_beta_v3')) { this.showBetaNotice = true; }
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
    },
    methods: {
        showConfirm(title, message, callback) {
            this.confirmDialog = { show: true, title, message, onConfirm: () => { callback(); this.confirmDialog.show = false; } };
        },
        showAlert(title, message) {
            this.alertDialog = { show: true, title, message };
        },
        dismissBetaNotice() {
            this.showBetaNotice = false;
            if (this.dismissBetaPermanently) { localStorage.setItem('lunardesk_hide_beta_v3', '1'); }
        },
        async fetchProfile() {
            const res = await fetch('api.php?action=profile');
            this.currentUser = await res.json();
            this.profileForm = { ...this.currentUser, password: '' };
        },
        async updateProfile() {
            this.loading = true;
            const res = await fetch('api.php?action=profile', { method: 'PUT', body: JSON.stringify(this.profileForm) });
            const data = await res.json();
            if(data.success) { this.showProfileModal = false; await this.fetchProfile(); this.showAlert("Sync", "Identity updated."); }
            this.loading = false;
        },
        async openUsersModal() { await this.fetchUsers(); this.resetUserForm(); this.showUsersModal = true; },
        async fetchUsers() {
            const res = await fetch('api.php?action=users');
            if (res.status === 403) return;
            this.userList = await res.json();
        },
        resetUserForm() { this.editingUser = false; this.userForm = { id: null, username: '', email: '', nickname: '', password: '', role: 'user' }; },
        editUser(user) { this.editingUser = true; this.userForm = { ...user, password: '' }; },
        cancelEditUser() { this.resetUserForm(); },
        async saveUser() {
            this.loading = true;
            const res = await fetch('api.php?action=users', { method: this.editingUser ? 'PUT' : 'POST', body: JSON.stringify(this.userForm) });
            const data = await res.json();
            if (data.success) { await this.fetchUsers(); this.resetUserForm(); }
            this.loading = false;
        },
        confirmDeleteUser(id) {
            this.showConfirm("Wipe User", "Terminate this access identity?", async () => {
                this.loading = true; await fetch(`api.php?action=users&id=${id}`, { method: 'DELETE' }); await this.fetchUsers(); this.loading = false;
            });
        },
        linkify(text) {
            if(!text) return '';
            const urlRegex = /(https?:\/\/[^\s]+)/g;
            return text.replace(urlRegex, (url) => `<a href="${url}" target="_blank" class="chat-link">${url}</a>`);
        },
        rgbToHex(rgb) {
            if (!rgb || rgb === 'rgba(0, 0, 0, 0)' || rgb === 'transparent') return '#1e293b';
            const m = rgb.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
            if (!m) return rgb; 
            const h = (x) => ("0" + parseInt(x).toString(16)).slice(-2);
            return "#" + h(m[1]) + h(m[2]) + h(m[3]);
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
                            cells.forEach(cell => { cellColorsRow.push(cell.style.backgroundColor || ''); });
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
                let h = window.innerHeight - e.clientY;
                this.adminHeight = Math.max(100, Math.min(h, window.innerHeight * 0.8));
            } else if (this.dragTarget === 'leftCol') {
                this.leftColWidth = Math.max(200, Math.min(e.clientX, 600));
            } else if (this.dragTarget === 'midCol') {
                this.midColWidth = Math.max(150, Math.min(e.clientX - this.leftColWidth - 6, 600));
            }
        },
        stopDrag() {
            this.dragTarget = null;
            document.body.style.userSelect = ''; document.body.style.cursor = '';
            document.removeEventListener('mousemove', this.onDrag);
            document.removeEventListener('mouseup', this.stopDrag);
        },
        async fetchTerminal() {
            const res = await fetch('api.php?action=terminal');
            this.adminMessages = await res.json();
            this.$nextTick(() => { const c = document.getElementById('admin-chat'); if(c) c.scrollTop = c.scrollHeight; });
        },
        async postSystemMsg(content, colorClass) {
            await fetch('api.php?action=terminal', { method: 'POST', body: JSON.stringify({ sender: 'System', content, colorClass }) });
            this.fetchTerminal();
        },
        async sendAdminMessage() {
            const msg = this.newAdminMsg.trim();
            if(msg === '') return;
            this.newAdminMsg = '';
            const senderName = this.currentUser?.nickname || this.currentUser?.username || 'Member';
            if (msg.startsWith('/')) {
                const parts = msg.split(' '); const cmd = parts[0].toLowerCase(); const arg = parts.slice(1).join(' ').trim();
                await fetch('api.php?action=terminal', { method: 'POST', body: JSON.stringify({ sender: senderName, content: msg, colorClass: 'text-blue-500' }) });
                if (cmd === '/create') {
                    if (!arg) { await this.postSystemMsg("Signal Error: /create <name>", "text-red-700"); return; }
                    await fetch('api.php?action=rooms', { method: 'POST', body: JSON.stringify({ title: arg }) });
                    await this.fetchRooms(); await this.postSystemMsg(`#${arg} partition ready.`, "text-green-700");
                } else if (cmd === '/delete') {
                    if (!arg) { await this.postSystemMsg("Signal Error: /delete <name>", "text-red-700"); return; }
                    const r = this.rooms.find(room => room.title.toLowerCase() === arg.toLowerCase());
                    if (!r) { await this.postSystemMsg(`Signal Error: #${arg} unknown.`, "text-red-700"); return; }
                    await fetch(`api.php?action=rooms&id=${r.id}`, { method: 'DELETE' });
                    if(this.activeRoom?.id === r.id) { this.activeRoom = null; this.roomMessages = []; }
                    await this.fetchRooms(); await this.postSystemMsg(`#${arg} partition wiped.`, "text-amber-700");
                } else if (cmd === '/help') { await this.postSystemMsg("/create <name>, /delete <name>, /help", "text-blue-500");
                } else { await this.postSystemMsg(`Unknown signal: ${cmd}`, "text-red-700"); }
                this.fetchTerminal(); return;
            }
            await fetch('api.php?action=terminal', { method: 'POST', body: JSON.stringify({ sender: senderName, content: msg, colorClass: 'text-slate-400' }) });
            this.fetchTerminal();
        },
        async fetchRooms() { const res = await fetch('api.php?action=rooms'); this.rooms = await res.json(); },
        createRoom() { this.promptTitle = "Initialize Channel"; this.promptInput = ""; this.promptAction = 'createRoom'; this.showPromptModal = true; this.$nextTick(() => { if (this.$refs.promptInputRef) this.$refs.promptInputRef.focus(); }); },
        selectRoom(room) { this.activeRoom = room; this.fetchRoomMessages(); },
        confirmClearMessages() {
            this.showConfirm("Wipe Signal History", `Erase all data in #${this.activeRoom.title}?`, async () => {
                await fetch(`api.php?action=clear_messages&room_id=${this.activeRoom.id}`, { method: 'DELETE' });
                this.roomMessages = []; await this.postSystemMsg(`#${this.activeRoom.title} signal wipe complete.`, "text-amber-700");
            });
        },
        async fetchRoomMessages() {
            if(!this.activeRoom) return;
            try {
                const res = await fetch(`api.php?action=webhook_messages&room_id=${this.activeRoom.id}`);
                const msgs = await res.json();
                const isNew = msgs.length > this.roomMessages.length;
                this.roomMessages = msgs;
                if (isNew) this.$nextTick(() => { const c = document.getElementById('webhook-stream'); if(c) c.scrollTop = c.scrollHeight; });
            } catch(e) {}
        },
        openSettings(room) { this.settingsRoom = room; this.showSettingsModal = true; },
        getWebhookUrl(key) { return `${window.location.origin}${window.location.pathname.replace('index.php', '')}webhook.php?key=${key}`; },
        async generateWebhook() {
            const res = await fetch('api.php?action=webhook_key', { method: 'PUT', body: JSON.stringify({ id: this.settingsRoom.id }) });
            const d = await res.json(); if(d.success) { this.settingsRoom.webhook_key = d.key; this.fetchRooms(); }
        },
        confirmDeleteWebhook() {
            this.showConfirm("Revoke Link", "Disable this external signal input?", async () => {
                await fetch(`api.php?action=webhook_key&id=${this.settingsRoom.id}`, { method: 'DELETE' });
                this.settingsRoom.webhook_key = null; await this.fetchRooms();
            });
        },
        confirmDeleteRoom() {
            this.showConfirm("Destroy Channel", "Erase channel and its documentation forever?", async () => {
                await fetch(`api.php?action=rooms&id=${this.settingsRoom.id}`, { method: 'DELETE' });
                if(this.activeRoom?.id === this.settingsRoom.id) { this.activeRoom = null; this.roomMessages = []; }
                this.showSettingsModal = false; await this.postSystemMsg(`#${this.settingsRoom.title} wiped from existence.`, "text-amber-700"); await this.fetchRooms();
            });
        },
        getFormattedDateTime() { return new Date().toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit' }); },
        async fetchData() {
            this.loading = true;
            try {
                const res = await fetch('api.php'); if (res.status === 401) { window.location.reload(); return; }
                this.items = await res.json();
                const pId = new URLSearchParams(window.location.search).get('p');
                if(pId && !this.activePage) { const p = this.items.find(i => i.id == pId); if(p) this.selectDoc(p); }
            } catch (e) { }
            this.loading = false;
        },
        getPages(sId) { return this.items.filter(i => i.type === 'page' && i.parent_id == sId); },
        getSubpages(pId) { return this.items.filter(i => i.type === 'subpage' && i.parent_id == pId); },
        triggerCoverUpload() { document.getElementById('coverUpload').click(); },
        handleCoverUpload(e) {
            const f = e.target.files[0]; if (!f) return;
            const r = new FileReader();
            r.onload = (ev) => { this.cropImageSrc = ev.target.result; this.showCropModal = true; this.$nextTick(() => { this.initCropper(); }); };
            r.readAsDataURL(f); e.target.value = ''; 
        },
        initCropper() {
            const img = document.getElementById('cropImage'); if (this.cropperInstance) this.cropperInstance.destroy();
            this.cropperInstance = new Cropper(img, { aspectRatio: 21 / 9, viewMode: 1, dragMode: 'move', autoCropArea: 1 });
        },
        cancelCrop() { this.showCropModal = false; if (this.cropperInstance) { this.cropperInstance.destroy(); this.cropperInstance = null; } this.cropImageSrc = ''; },
        async applyCrop() {
            if (!this.cropperInstance) return; this.loading = true;
            this.cropperInstance.getCroppedCanvas({ width: 1920 }).toBlob(async (blob) => {
                const fd = new FormData(); fd.append('image', blob, 'banner.jpg');
                try {
                    const res = await fetch('api.php?action=upload', { method: 'POST', body: fd });
                    const d = await res.json();
                    if (d.success) { this.activePage.cover_image = d.file.url; this.needsSave = true; this.cancelCrop(); }
                } catch(err) { this.showAlert("Error", "Signal Lost."); }
                this.loading = false;
            }, 'image/jpeg', 0.85);
        },
        confirmRemoveBanner() {
            this.showConfirm("Wipe Identity", "Clear cover banner?", () => { this.activePage.cover_image = ''; this.needsSave = true; });
        },
        hasCover(p) { return p.cover_image && p.cover_image !== ''; },
        getCoverStyle(p) { return this.hasCover(p) ? `background-image: url('${p.cover_image}'); background-size: cover; background-position: center;` : ''; },
        async initEditor(dataBlocks) {
            if (globalEditorInstance) { try { await globalEditorInstance.isReady; globalEditorInstance.destroy(); } catch(e) { } globalEditorInstance = null; }
            document.getElementById('editorjs').innerHTML = '';
            const bar = ['link', 'bold', 'italic', 'Color', 'Marker', 'inlineCode'];
            globalEditorInstance = new EditorJS({
                holder: 'editorjs', data: dataBlocks, autofocus: true, minHeight: 500, onChange: () => { this.needsSave = true; },
                inlineToolbar: bar,
                tools: {
                    header: { class: Header, inlineToolbar: bar, config: { levels: [1, 2, 3], defaultLevel: 2 } },
                    list: { class: EditorjsList, inlineToolbar: bar }, 
                    checklist: { class: Checklist, inlineToolbar: bar }, 
                    code: CodeTool, table: { class: Table, inlineToolbar: bar, config: { withHeadings: true } }, 
                    quote: { class: Quote, inlineToolbar: bar }, warning: { class: Warning, inlineToolbar: bar }, 
                    image: { class: ImageTool, config: { endpoints: { byFile: 'api.php?action=upload' } } },
                    Color: { class: window.ColorPlugin, config: { colorCollections: ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#ffffff', '#cbd5e1', '#94a3b8', '#1e293b'], defaultColor: '#3b82f6', type: 'text', customPicker: true } },
                    Marker: { class: window.ColorPlugin, config: { colorCollections: ['#1e293b', '#334155', '#1e3a8a', '#7f1d1d', '#14532d', '#78350f', '#4c1d95', '#831843', '#f59e0b', '#3b82f6'], defaultColor: '#1e3a8a', type: 'marker', customPicker: true } },
                    delimiter: Delimiter, inlineCode: { class: InlineCode }, embed: { class: Embed, inlineToolbar: true }
                }
            });
            globalEditorInstance.isReady.then(() => {
                setTimeout(() => {
                    const blocks = document.querySelectorAll('.ce-block .tc-table'); let idx = 0;
                    dataBlocks.blocks.forEach(b => {
                        if (b.type === 'table' && b.data.cellColors) {
                            const dom = blocks[idx++];
                            if (dom) {
                                const rows = dom.querySelectorAll('.tc-row');
                                rows.forEach((r, ri) => {
                                    const cells = r.querySelectorAll('.tc-cell');
                                    cells.forEach((c, ci) => { if (b.data.cellColors[ri] && b.data.cellColors[ri][ci]) c.style.backgroundColor = b.data.cellColors[ri][ci]; });
                                });
                            }
                        } else if (block.type === 'table') idx++;
                    });
                }, 300); 
            });
        },
        selectDoc(p) { 
            const content = p.has_draft ? p.draft_content : p.content;
            this.activePage = { ...p, title: p.has_draft ? p.draft_title : p.title, cover_image: p.has_draft ? p.draft_cover_image : p.cover_image }; 
            const url = `${window.location.protocol}//${window.location.host}${window.location.pathname}?p=${p.id}`;
            window.history.pushState({ path: url }, '', url);
            let d = { blocks: [] }; if (content) { try { d = JSON.parse(content); } catch(e) { } }
            if (!d.blocks || d.blocks.length === 0) d.blocks = [{ type: 'paragraph', data: { text: '' } }];
            this.$nextTick(() => {
                this.initEditor(d); this.lastSavedContent = JSON.stringify(d); this.lastSavedTitle = this.activePage.title;
                this.lastSavedPublic = this.activePage.is_public; this.lastSavedCover = this.activePage.cover_image; this.lastSaveTime = null; this.needsSave = false;
            });
        },
        createItem(type, pId = null) {
            this.promptTitle = type === 'space' ? "New Partition" : "New Node";
            this.promptInput = ""; this.promptAction = 'createItem'; this.promptPayload = { type, pId };
            this.showPromptModal = true; this.$nextTick(() => { if (this.$refs.promptInputRef) this.$refs.promptInputRef.focus(); });
        },
        async submitPrompt() {
            const t = this.promptInput.trim(); if (!t) return; this.showPromptModal = false;
            if (this.promptAction === 'createRoom') {
                await fetch('api.php?action=rooms', { method: 'POST', body: JSON.stringify({ title: t }) });
                await this.postSystemMsg(`#${t} signals initialized.`, "text-blue-500"); await this.fetchRooms();
            } else if (this.promptAction === 'createItem') {
                this.loading = true; await fetch('api.php', { method: 'POST', body: JSON.stringify({ title: t, type: this.promptPayload.type, parent_id: this.promptPayload.pId }) });
                await this.fetchData();
            }
        },
        async silentAutoSave() {
            if (this.activePage && globalEditorInstance) {
                let changed = (this.activePage.title !== this.lastSavedTitle || this.activePage.is_public !== this.lastSavedPublic || this.activePage.cover_image !== this.lastSavedCover);
                if (this.needsSave || changed) {
                    try {
                        const raw = await globalEditorInstance.save(); const out = this.extractCellColors(raw); const str = JSON.stringify(out);
                        await fetch('api.php', { method: 'PUT', body: JSON.stringify({ ...this.activePage, content: str, action: 'draft' }) });
                        this.lastSavedContent = str; this.lastSavedTitle = this.activePage.title; this.lastSavedPublic = this.activePage.is_public; 
                        this.lastSavedCover = this.activePage.cover_image; this.lastSaveTime = this.getFormattedDateTime(); this.needsSave = false; this.activePage.has_draft = 1;
                        const idx = this.items.findIndex(i => i.id === this.activePage.id);
                        if(idx !== -1) { this.items[idx].has_draft = 1; this.items[idx].draft_title = this.activePage.title; this.items[idx].draft_content = str; this.items[idx].draft_cover_image = this.activePage.cover_image; }
                    } catch (e) { }
                }
            }
        },
        async manualPublish() {
            this.loading = true; 
            if (globalEditorInstance) {
                try {
                    const raw = await globalEditorInstance.save(); const out = this.extractCellColors(raw); const str = JSON.stringify(out);
                    await fetch('api.php', { method: 'PUT', body: JSON.stringify({ ...this.activePage, content: str, action: 'publish' }) });
                    this.lastSavedContent = str; this.lastSavedTitle = this.activePage.title; this.lastSavedPublic = this.activePage.is_public; 
                    this.lastSavedCover = this.activePage.cover_image; this.lastSaveTime = this.getFormattedDateTime(); this.needsSave = false; this.activePage.has_draft = 0; 
                    this.showAlert("Documentation Active", "The live view has been synchronized.");
                } catch (e) { }
            }
            await this.fetchData(); this.loading = false;
        },
        confirmDelete(id, type) {
            this.showConfirm("Final Wipe", "Permanently erase this data node?", async () => {
                this.loading = true; await fetch(`api.php?id=${id}`, { method: 'DELETE' });
                this.activePage = null; await this.fetchData();
            });
        }
    }
}).mount('#app');