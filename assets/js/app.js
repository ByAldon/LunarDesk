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
            publishNotice: '',
            publishNoticeTimer: null,
            needsSave: false,
            rooms: [],
            activeRoom: null,
            roomMessages: [],
            adminMessages: [], 
            activeLeftTab: 'stream',
            hasUnreadStream: false,
            hasUnreadTerminal: false,
            newAdminMsg: '',
            adminHeight: 250,
            channelsHeight: 250,
            leftColWidth: 320,  
            midColWidth: 280,   
            dragTarget: null,
            lastDragY: null,
            showSettingsModal: false,
            settingsRoom: null,
            showPromptModal: false,
            promptTitle: '',
            promptInput: '',
            promptAction: null,
            showProfileModal: false,
            profileForm: { username: '', email: '', nickname: '', password: '' },
            showUsersModal: false,
            userList: [],
            editingUser: false,
            userForm: { username: '', email: '', nickname: '', role: 'user' },
            showCropModal: false,
            cropImageSrc: null,
            cropper: null,
            alertDialog: { show: false, title: '', message: '' },
            confirmDialog: { show: false, title: '', message: '', onConfirm: null }
        }
    },
    async created() {
        await this.fetchUser();
        await this.fetchData();
        await this.fetchRooms();
        await this.fetchAdminMessages();
        
        setInterval(() => this.fetchData(), 10000);
        setInterval(() => this.fetchRooms(), 3000);
        setInterval(() => this.fetchAdminMessages(), 3000);

        window.addEventListener('mousemove', this.doDrag);
        window.addEventListener('mouseup', this.stopDrag);
    },
    methods: {
        switchTab(tab) {
            this.activeLeftTab = tab;
            if (tab === 'stream') {
                this.hasUnreadStream = false;
                this.$nextTick(() => {
                    const el = document.getElementById('webhook-stream');
                    if (el) el.scrollTop = el.scrollHeight;
                });
            } else if (tab === 'terminal') {
                this.hasUnreadTerminal = false;
                this.$nextTick(() => {
                    const el = document.getElementById('admin-chat');
                    if (el) el.scrollTop = el.scrollHeight;
                });
            }
        },
        async fetchUser() {
            const r = await fetch('api.php?action=profile');
            if (r.ok) this.currentUser = await r.json();
        },
        async fetchData() {
            const r = await fetch('api.php');
            const data = await r.json();
            this.items = data;
            if (this.activePage) {
                const updated = data.find(i => i.id === this.activePage.id);
                if (updated) {
                    const updatedCover = updated.draft_cover_image || updated.cover_image || '';
                    const activeCover = this.activePage.draft_cover_image || this.activePage.cover_image || '';
                    if (updated.title !== this.activePage.title || updated.is_public !== this.activePage.is_public || updatedCover !== activeCover) {
                    this.activePage.title = updated.title;
                    this.activePage.is_public = updated.is_public;
                    this.activePage.cover_image = updated.cover_image || '';
                    this.activePage.draft_cover_image = updated.draft_cover_image || '';
                    }
                }
            }
        },
        async fetchRooms() {
            const r = await fetch('api.php?action=rooms');
            this.rooms = await r.json();
            if (this.activeRoom) {
                await this.fetchMessages(this.activeRoom.id);
            }
        },
        async fetchMessages(roomId) {
            try {
                const res = await fetch(`api.php?action=messages&room_id=${roomId}`);
                const data = await res.json();
                const oldLen = this.roomMessages ? this.roomMessages.length : 0;
                this.roomMessages = data;

                if (data.length > oldLen && oldLen > 0 && this.activeLeftTab !== 'stream') {
                    this.hasUnreadStream = true;
                }

                if (this.activeLeftTab === 'stream') {
                    this.$nextTick(() => {
                        const el = document.getElementById('webhook-stream');
                        if (el) el.scrollTop = el.scrollHeight;
                    });
                }
            } catch (e) { console.error(e); }
        },
        async fetchAdminMessages() {
            try {
                const res = await fetch(`api.php?action=admin_terminal&t=${Date.now()}`, { cache: 'no-store' });
                if (!res.ok) return;
                const data = await res.json();
                const oldLen = this.adminMessages ? this.adminMessages.length : 0;
                this.adminMessages = data;

                if (data.length > oldLen && oldLen > 0 && this.activeLeftTab !== 'terminal') {
                    this.hasUnreadTerminal = true;
                }

                if (this.activeLeftTab === 'terminal') {
                    this.$nextTick(() => {
                        const el = document.getElementById('admin-chat');
                        if (el) el.scrollTop = el.scrollHeight;
                    });
                }
            } catch (e) { console.error(e); }
        },
        selectRoom(room) {
            this.activeRoom = room;
            this.fetchRooms();
        },
        async selectDoc(page) {
            this.loading = true;
            this.activePage = { ...page };
            this.lastSavedContent = page.draft_content || page.content;
            this.lastSavedTitle = page.title;
            this.lastSavedPublic = page.is_public;
            this.lastSavedCover = page.cover_image;
            this.lastSaveTime = null;
            this.needsSave = false;

            if (globalEditorInstance) {
                globalEditorInstance.destroy();
                globalEditorInstance = null;
            }

            setTimeout(() => {
                const initialData = this.lastSavedContent ? JSON.parse(this.lastSavedContent) : { blocks: [] };
                globalEditorInstance = new EditorJS({
                    holder: 'editorjs',
                    data: initialData,
                    placeholder: 'Begin your transmission...',
                    tools: {
                        header: Header,
                        list: { class: EditorjsList, inlineToolbar: true },
                        checklist: { class: Checklist, inlineToolbar: true },
                        code: CodeTool,
                        table: { class: Table, inlineToolbar: true },
                        quote: { class: Quote, inlineToolbar: true },
                        warning: Warning,
                        delimiter: Delimiter,
                        inlineCode: InlineCode,
                        simpleImage: SimpleImage,
                        image: {
                            class: ImageTool,
                            config: { endpoints: { byFile: 'api.php?action=upload' } }
                        },
                        embed: Embed,
                        Color: {
                            class: window.ColorPlugin,
                            config: {
                                colorCollections: ['#FF1300', '#EC78BB', '#007FFF', '#05FFA1', '#EBFF00', '#FFF'],
                                defaultColor: '#FF1300',
                                type: 'text',
                                customPicker: true
                            }
                        }
                    },
                    onChange: () => { this.needsSave = true; this.autoSave(); }
                });
            }, 100);
            this.loading = false;
        },
        async autoSave() {
            if (this.activePage && globalEditorInstance) {
                try {
                    const raw = await globalEditorInstance.save();
                    const out = this.extractCellColors(raw);
                    const str = JSON.stringify(out);
                    if (str !== this.lastSavedContent || this.activePage.title !== this.lastSavedTitle || this.activePage.is_public !== this.lastSavedPublic || this.activePage.cover_image !== this.lastSavedCover) {
                        await fetch('api.php', {
                            method: 'PUT',
                            body: JSON.stringify({ ...this.activePage, content: str, action: 'draft' })
                        });
                        this.lastSavedContent = str;
                        this.lastSavedTitle = this.activePage.title;
                        this.lastSavedPublic = this.activePage.is_public;
                        this.lastSavedCover = this.activePage.cover_image;
                        this.lastSaveTime = this.getFormattedDateTime();
                        this.needsSave = false;
                        this.activePage.has_draft = 1;
                        const idx = this.items.findIndex(i => i.id === this.activePage.id);
                        if (idx !== -1) { 
                            this.items[idx].has_draft = 1; 
                            this.items[idx].draft_title = this.activePage.title; 
                            this.items[idx].draft_content = str; 
                            this.items[idx].draft_cover_image = this.activePage.cover_image; 
                        }
                    }
                } catch (e) { }
            }
        },
        async manualPublish() {
            this.loading = true; 
            if (globalEditorInstance) {
                try {
                    const raw = await globalEditorInstance.save(); 
                    const out = this.extractCellColors(raw); 
                    const str = JSON.stringify(out);
                    const publishCover = this.activePage.draft_cover_image || this.activePage.cover_image || '';
                    await fetch('api.php', {
                        method: 'PUT',
                        body: JSON.stringify({ ...this.activePage, cover_image: publishCover, content: str, action: 'publish' })
                    });
                    this.activePage.cover_image = publishCover;
                    this.lastSavedContent = str; this.lastSavedTitle = this.activePage.title; this.lastSavedPublic = this.activePage.is_public; 
                    this.lastSavedCover = publishCover; this.lastSaveTime = this.getFormattedDateTime(); this.needsSave = false; this.activePage.has_draft = 0; 
                    this.showPublishNotice('Live page updated.');
                } catch (e) { }
            }
            await this.fetchData(); 
            this.loading = false;
        },
        showPublishNotice(message) {
            this.publishNotice = message;
            if (this.publishNoticeTimer) clearTimeout(this.publishNoticeTimer);
            this.publishNoticeTimer = setTimeout(() => {
                this.publishNotice = '';
                this.publishNoticeTimer = null;
            }, 3000);
        },
        copyPublicLink() {
            if (!this.activePage || !this.activePage.slug) return;
            const url = window.location.origin + window.location.pathname.replace('index.php', '') + 'p.php?s=' + this.activePage.slug;
            
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(() => {
                    this.showAlert("Link Copied", "The public link has been copied to your clipboard.");
                });
            } else {
                const el = document.createElement('textarea');
                el.value = url;
                el.style.position = 'absolute';
                el.style.left = '-9999px';
                document.body.appendChild(el);
                el.select();
                try {
                    document.execCommand('copy');
                    this.showAlert("Link Copied", "The public link has been copied to your clipboard.");
                } catch (err) {
                    this.showAlert("Error", "Could not copy the link automatically.");
                }
                document.body.removeChild(el);
            }
        },
        confirmDelete(id, type) {
            const msg = type === 'space' ? "Delete this partition?" : "Delete this node?";
            this.showConfirm("Signal Finalize", msg, async () => {
                this.loading = true; 
                await fetch(`api.php?id=${id}`, { method: 'DELETE' });
                if (this.activePage && this.activePage.id === id) this.activePage = null;
                await this.fetchData(); 
                this.loading = false;
            });
        },
        getPages(spaceId) {
            return this.items
                .filter(i => i.type === 'page' && i.parent_id === spaceId)
                .sort((a, b) => {
                    const ao = Number.isFinite(Number(a.sort_order)) ? Number(a.sort_order) : 0;
                    const bo = Number.isFinite(Number(b.sort_order)) ? Number(b.sort_order) : 0;
                    return ao - bo || a.id - b.id;
                });
        },
        getSubpages(pageId) {
            return this.items
                .filter(i => i.type === 'subpage' && i.parent_id === pageId)
                .sort((a, b) => {
                    const ao = Number.isFinite(Number(a.sort_order)) ? Number(a.sort_order) : 0;
                    const bo = Number.isFinite(Number(b.sort_order)) ? Number(b.sort_order) : 0;
                    return ao - bo || a.id - b.id;
                });
        },
        getNestedSubpages(rootId) {
            const out = [];
            const visited = new Set();
            const walk = (parentId, depth) => {
                const children = this.getSubpages(parentId);
                children.forEach(child => {
                    if (visited.has(child.id)) return;
                    visited.add(child.id);
                    out.push({ item: child, depth, parentId });
                    walk(child.id, depth + 1);
                });
            };
            walk(rootId, 0);
            return out;
        },
        async saveOrder(reorderedArray) {
            const payload = reorderedArray.map(item => ({
                id: item.id,
                sort_order: item.sort_order
            }));

            try {
                await fetch('api.php?action=reorder', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
            } catch (e) {
                console.error(e);
            }
        },
        async moveItem(item, direction, listType, parentId) {
            const expectedType = listType === 'subpage' ? 'subpage' : 'page';
            if (item.type !== expectedType || item.parent_id !== parentId) return;

            const currentList = expectedType === 'page'
                ? this.getPages(parentId)
                : this.getSubpages(parentId);

            const currentIndex = currentList.findIndex(i => i.id === item.id);
            if (currentIndex === -1) return;

            const targetIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1;
            if (targetIndex < 0 || targetIndex >= currentList.length) return;

            const reordered = [...currentList];
            const [moved] = reordered.splice(currentIndex, 1);
            reordered.splice(targetIndex, 0, moved);

            reordered.forEach((entry, idx) => {
                entry.sort_order = idx;
                const globalItem = this.items.find(i => i.id === entry.id);
                if (globalItem) globalItem.sort_order = idx;
            });

            await this.saveOrder(reordered);
        },
        createItem(type, parentId = null) {
            const label = type === 'space' ? "Partition Name" : "Node Name";
            this.showPrompt("New Sequence", label, async (val) => {
                this.loading = true;
                await fetch('api.php', {
                    method: 'POST',
                    body: JSON.stringify({ title: val, type, parent_id: parentId })
                });
                await this.fetchData();
                this.loading = false;
            });
        },
        createRoom() {
            this.showPrompt("Initialize Channel", "Channel Name", async (val) => {
                this.loading = true;
                await fetch('api.php?action=rooms', { method: 'POST', body: JSON.stringify({ title: val }) });
                await this.fetchRooms();
                this.loading = false;
            });
        },
        openSettings(room) {
            this.settingsRoom = { ...room };
            this.showSettingsModal = true;
        },
        async generateWebhook() {
            await fetch(`api.php?action=rooms&id=${this.settingsRoom.id}`, { method: 'PUT' });
            await this.fetchRooms();
            const updated = this.rooms.find(r => r.id === this.settingsRoom.id);
            if (updated) this.settingsRoom.webhook_key = updated.webhook_key;
        },
        async confirmDeleteWebhook() {
            this.showConfirm("Signal Terminate", "Revoke this webhook key?", async () => {
                await fetch(`api.php?action=rooms&id=${this.settingsRoom.id}&revoke=1`, { method: 'PUT' });
                await this.fetchRooms();
                this.settingsRoom.webhook_key = null;
            });
        },
        async confirmDeleteRoom() {
            this.showConfirm("Signal Terminate", "Delete this entire channel?", async () => {
                await fetch(`api.php?action=rooms&id=${this.settingsRoom.id}`, { method: 'DELETE' });
                this.showSettingsModal = false;
                if (this.activeRoom && this.activeRoom.id === this.settingsRoom.id) this.activeRoom = null;
                await this.fetchRooms();
            });
        },
        getWebhookUrl(key) {
            return window.location.origin + window.location.pathname.replace('index.php', '') + 'webhook.php?key=' + key;
        },
        async confirmClearMessages() {
            this.showConfirm("Signal Clear", "Wipe all messages in this stream?", async () => {
                await fetch(`api.php?action=messages&room_id=${this.activeRoom.id}`, { method: 'DELETE' });
                this.roomMessages = [];
            });
        },
        async sendAdminMessage() {
            if (!this.newAdminMsg.trim()) return;
            const msg = this.newAdminMsg;
            this.newAdminMsg = '';
            
            const senderName = this.currentUser ? (this.currentUser.nickname || this.currentUser.username) : "Me";

            // Voeg direct het bericht toe aan de interface (optimistisch laden voor snelle feel)
            this.adminMessages.push({ id: 'temp-'+Date.now(), sender: senderName, content: msg, colorClass: "text-slate-300" });
            this.$nextTick(() => {
                const el = document.getElementById('admin-chat');
                if (el) el.scrollTop = el.scrollHeight;
            });

            // Stuur het naar de api.php, daar gebeurt alle /delete en YES/NO logica
            await fetch('api.php?action=admin_terminal', {
                method: 'POST',
                body: JSON.stringify({ content: msg })
            });
            
            // Haal direct daarna de echte status weer op
            await this.fetchAdminMessages();
        },
        linkify(text) {
            if (!text) return '';
            const urlPattern = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
            return text.replace(urlPattern, '<a href="$1" target="_blank" class="text-blue-400 hover:underline">$1</a>');
        },
        formatItemDate(dateValue) {
            if (!dateValue) return '';
            const normalized = typeof dateValue === 'string' ? dateValue.replace(' ', 'T') : dateValue;
            const dt = new Date(normalized);
            if (Number.isNaN(dt.getTime())) return String(dateValue);
            return dt.toLocaleString();
        },
        getItemMetaLabel(item) {
            if (!item) return '';
            const hasCreated = !!item.created_at;
            const hasUpdated = !!item.updated_at;
            const changedByOtherUser = !!item.updated_by && !!item.created_by && Number(item.updated_by) !== Number(item.created_by);
            const changedTime = hasCreated && hasUpdated && item.updated_at !== item.created_at;
            const wasUpdated = changedByOtherUser || changedTime;
            const label = wasUpdated ? 'Updated' : 'Created';
            const rawDate = wasUpdated ? item.updated_at : item.created_at;
            const dateText = this.formatItemDate(rawDate);
            const actor = wasUpdated
                ? (item.updated_by_name || item.created_by_name || 'Unknown')
                : (item.created_by_name || item.updated_by_name || 'Unknown');
            const parts = [label];
            if (dateText) parts.push(dateText);
            if (actor) parts.push(`by ${actor}`);
            return parts.join(' ');
        },
        hasCover(page) {
            return !!(page.cover_image || page.draft_cover_image);
        },
        getCoverStyle(page) {
            const img = page.cover_image || page.draft_cover_image;
            return img ? { backgroundImage: `url(${img})`, backgroundSize: 'cover', backgroundPosition: 'center' } : {};
        },
        triggerCoverUpload() {
            document.getElementById('coverUpload').click();
        },
        handleCoverUpload(e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (event) => {
                this.cropImageSrc = event.target.result;
                this.showCropModal = true;
                this.$nextTick(() => {
                    const image = document.getElementById('cropImage');
                    if (this.cropper) this.cropper.destroy();
                    this.cropper = new Cropper(image, { aspectRatio: 16 / 5, viewMode: 1, background: false });
                });
            };
            reader.readAsDataURL(file);
        },
        cancelCrop() {
            this.showCropModal = false;
            if (this.cropper) { this.cropper.destroy(); this.cropper = null; }
        },
        async applyCrop() {
            const canvas = this.cropper.getCroppedCanvas({ width: 1500 });
            const base64 = canvas.toDataURL('image/jpeg', 0.8);
            this.activePage.cover_image = base64;
            this.activePage.draft_cover_image = base64;
            this.showCropModal = false;
            this.cropper.destroy();
            this.cropper = null;
            this.autoSave();
        },
        confirmRemoveBanner() {
            this.showConfirm("Signal Update", "Remove this banner?", () => {
                this.activePage.cover_image = '';
                this.autoSave();
            });
        },
        async updateProfile() {
            const r = await fetch('api.php?action=profile', { method: 'PUT', body: JSON.stringify(this.profileForm) });
            if (r.ok) {
                this.showAlert("Profile Active", "Identity parameters updated.");
                this.showProfileModal = false;
                this.fetchUser();
            }
        },
        async hardRefresh() {
            try {
                if ('caches' in window) {
                    const keys = await caches.keys();
                    await Promise.all(keys.map(k => caches.delete(k)));
                }
            } catch (e) {
                console.error(e);
            }

            const url = new URL(window.location.href);
            url.searchParams.set('refresh', Date.now().toString());
            window.location.replace(url.toString());
        },
        async openUsersModal() {
            const r = await fetch('api.php?action=users');
            this.userList = await r.json();
            this.showUsersModal = true;
        },
        async saveUser() {
            const method = this.editingUser ? 'PUT' : 'POST';
            const r = await fetch('api.php?action=users', { method, body: JSON.stringify(this.userForm) });
            if (r.ok) {
                const res = await r.json();
                if (method === 'POST') {
                    if (res.mailSent) {
                        this.showAlert("Invite Sent", `An invitation email was successfully sent to ${this.userForm.email}.`);
                    } else {
                        this.showAlert("Manual Invite Required", `The server could not send the email automatically. Please copy this link and send it manually:\n\n${res.link}`);
                    }
                } else {
                    this.showAlert("Profile Updated", "User access updated successfully.");
                }
                this.cancelEditUser();
                this.openUsersModal();
            }
        },
        editUser(user) {
            this.editingUser = true;
            this.userForm = { ...user };
        },
        cancelEditUser() {
            this.editingUser = false;
            this.userForm = { username: '', email: '', nickname: '', role: 'user' };
        },
        async confirmDeleteUser(id) {
            this.showConfirm("Identity Purge", "Remove this user access?", async () => {
                await fetch(`api.php?action=users&id=${id}`, { method: 'DELETE' });
                this.openUsersModal();
            });
        },
        startDrag(target, e = null) {
            this.dragTarget = target;
            if (target === 'channels') {
                if (e) e.preventDefault();
                this.lastDragY = e ? e.clientY : null;
            }
        },
        doDrag(e) {
            if (!this.dragTarget) return;
            if (this.dragTarget === 'leftCol') {
                this.leftColWidth = e.clientX;
            } else if (this.dragTarget === 'midCol') {
                const leftPart = this.leftColWidth;
                this.midColWidth = e.clientX - leftPart;
            } else if (this.dragTarget === 'admin') {
                const aside = document.querySelector('aside');
                const asideRect = aside.getBoundingClientRect();
                this.adminHeight = asideRect.bottom - e.clientY;
            } else if (this.dragTarget === 'channels') {
                const deltaY = this.lastDragY === null ? e.movementY : (e.clientY - this.lastDragY);
                this.channelsHeight += deltaY;
                if (this.channelsHeight < 100) this.channelsHeight = 100;
                this.lastDragY = e.clientY;
            }
        },
        stopDrag() {
            this.dragTarget = null;
            this.lastDragY = null;
        },
        getFormattedDateTime() {
            return new Date().toLocaleTimeString();
        },
        extractCellColors(data) {
            if (data.blocks) {
                data.blocks.forEach(block => {
                    if (block.type === 'table') {
                        block.data.content.forEach((row, rIdx) => {
                            row.forEach((cell, cIdx) => {
                                const m = cell.match(/style=\"background-color:\s*(#[a-fA-F0-9]{3,6}|rgb\([^\)]+\))\"/);
                                if (m) {
                                    if (!block.data.cellColors) block.data.cellColors = {};
                                    block.data.cellColors[`${rIdx}-${cIdx}`] = m[1];
                                }
                            });
                        });
                    }
                });
            }
            return data;
        },
        showAlert(title, message) {
            this.alertDialog = { show: true, title, message };
        },
        showConfirm(title, message, onConfirm) {
            this.confirmDialog = { show: true, title, message, onConfirm: onConfirm };
        },
        executeConfirm() {
            if (this.confirmDialog && typeof this.confirmDialog.onConfirm === 'function') {
                this.confirmDialog.onConfirm();
                this.confirmDialog.show = false;
            }
        },
        showPrompt(title, label, onConfirm) {
            this.promptTitle = title;
            this.promptInput = '';
            this.promptAction = onConfirm;
            this.showPromptModal = true;
            this.$nextTick(() => {
                if (this.$refs.promptInputRef) this.$refs.promptInputRef.focus();
            });
        },
        submitPrompt() {
            if (this.promptAction) this.promptAction(this.promptInput);
            this.showPromptModal = false;
        }
    },
    computed: {
        spaces() {
            return this.items.filter(i => i.type === 'space');
        }
    }
}).mount('#app');
