// assets/js/app.js
let globalEditorInstance = null;

function focusEditor() {
    if (globalEditorInstance) globalEditorInstance.focus();
}

class UnderlineTool {
    static get isInline() { return true; }
    static get sanitize() { return { u: {} }; }

    constructor({ api }) {
        this.api = api;
        this.button = null;
        this.tag = 'U';
    }

    render() {
        this.button = document.createElement('button');
        this.button.type = 'button';
        this.button.classList.add(this.api.styles.inlineToolButton);
        this.button.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3v8a6 6 0 0 0 12 0V3h-2v8a4 4 0 0 1-8 0V3H6zm-1 16v2h14v-2H5z" fill="currentColor"/></svg>';
        return this.button;
    }

    surround() {
        document.execCommand('underline');
    }

    checkState() {
        if (!this.button) return;
        this.button.classList.toggle(this.api.styles.inlineToolButtonActive, document.queryCommandState('underline'));
    }
}

class FilteredListTool extends EditorjsList {
    static get toolbox() {
        const base = EditorjsList.toolbox;
        if (!Array.isArray(base)) return base;
        return base.filter((entry) => {
            const title = String(entry && entry.title ? entry.title : '').toLowerCase();
            const style = entry && entry.data && entry.data.style ? String(entry.data.style).toLowerCase() : '';
            return title !== 'checklist' && style !== 'checklist';
        });
    }
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
            activeLeftTab: 'terminal',
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
        focusTitleInput() {
            this.$nextTick(() => {
                const input = this.$refs.pageTitleInput;
                if (input && typeof input.focus === 'function') input.focus();
            });
        },
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
                const HeaderToolClass = window.Header || window.EditorjsHeader;
                const ParagraphToolClass = window.Paragraph;
                globalEditorInstance = new EditorJS({
                    holder: 'editorjs',
                    data: initialData,
                    placeholder: 'Begin your transmission...',
                    inlineToolbar: ['bold', 'italic', 'link', 'underline', 'inlineCode'],
                    tools: {
                        paragraph: { class: ParagraphToolClass, inlineToolbar: ['bold', 'italic', 'link', 'underline', 'inlineCode'] },
                        header: {
                            class: HeaderToolClass,
                            inlineToolbar: ['bold', 'italic', 'link', 'underline', 'inlineCode'],
                            config: {
                                levels: [1, 2, 3, 4],
                                defaultLevel: 2
                            }
                        },
                        list: { class: FilteredListTool, inlineToolbar: ['bold', 'italic', 'link', 'underline', 'inlineCode'] },
                        checklist: {
                            class: Checklist,
                            inlineToolbar: ['bold', 'italic', 'link', 'underline', 'inlineCode'],
                            toolbox: {
                                title: 'Checkboxes'
                            }
                        },
                        code: CodeTool,
                        table: { class: Table, inlineToolbar: ['bold', 'italic', 'link', 'underline', 'inlineCode'] },
                        quote: { class: Quote, inlineToolbar: ['bold', 'italic', 'link', 'underline', 'inlineCode'] },
                        warning: Warning,
                        delimiter: Delimiter,
                        inlineCode: InlineCode,
                        underline: UnderlineTool,
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
                    onReady: () => {
                        this.bindTableCellColorPicker();
                        this.applyTableCellColorsFromData(initialData);
                        this.applyTableCellSizesFromData(initialData);
                        this.applyTableCellPaddingsFromData(initialData);
                        this.normalizeRadioGroupsByCell();
                    },
                    onChange: () => {
                        this.needsSave = true;
                        this.autoSave();
                    }
                });
            }, 100);
            this.loading = false;
        },
        async autoSave() {
            if (this.activePage && globalEditorInstance) {
                try {
                    const raw = await globalEditorInstance.save();
                    const withTableColors = this.captureTableCellColors(raw);
                    const withTableSizes = this.captureTableCellSizes(withTableColors);
                    const withTablePaddings = this.captureTableCellPaddings(withTableSizes);
                    const out = this.extractCellColors(withTablePaddings);
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
                    const withTableColors = this.captureTableCellColors(raw);
                    const withTableSizes = this.captureTableCellSizes(withTableColors);
                    const withTablePaddings = this.captureTableCellPaddings(withTableSizes);
                    const out = this.extractCellColors(withTablePaddings); 
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
            if (!this.currentUser || this.currentUser.role !== 'admin') return;
            this.showConfirm("Signal Clear", "Wipe all messages in this stream?", async () => {
                await fetch(`api.php?action=messages&room_id=${this.activeRoom.id}`, { method: 'DELETE' });
                this.roomMessages = [];
            });
        },
        async confirmDeleteMessage(msg) {
            if (!this.currentUser || this.currentUser.role !== 'admin') return;
            if (!this.activeRoom || !msg || !msg.id) return;
            this.showConfirm("Delete Message", "Delete this message?", async () => {
                await fetch(`api.php?action=messages&room_id=${this.activeRoom.id}&id=${msg.id}`, { method: 'DELETE' });
                this.roomMessages = this.roomMessages.filter((m) => Number(m.id) !== Number(msg.id));
            });
        },
        async confirmDeleteAdminMessage(chat) {
            if (!this.currentUser || this.currentUser.role !== 'admin') return;
            if (!chat || !chat.id) return;
            this.showConfirm("Delete Message", "Delete this terminal message?", async () => {
                await fetch(`api.php?action=admin_terminal&id=${chat.id}`, { method: 'DELETE' });
                this.adminMessages = this.adminMessages.filter((m) => Number(m.id) !== Number(chat.id));
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
        bindTableCellColorPicker() {
            const holder = document.getElementById('editorjs');
            if (!holder) return;

            if (!this._tableColorInput) {
                const input = document.createElement('input');
                input.type = 'color';
                input.style.position = 'fixed';
                input.style.left = '-9999px';
                input.style.top = '0';
                input.addEventListener('input', () => {
                    if (!this._activeTableCell) return;
                    this._activeTableCell.style.backgroundColor = input.value;
                });
                input.addEventListener('change', () => {
                    this.needsSave = true;
                    this.autoSave();
                });
                document.body.appendChild(input);
                this._tableColorInput = input;
            }

            if (!this._tableCellColorMenu) {
                const menu = document.createElement('div');
                menu.style.position = 'fixed';
                menu.style.display = 'none';
                menu.style.zIndex = '9999';
                menu.style.padding = '8px';
                menu.style.background = '#0f172a';
                menu.style.border = '1px solid #334155';
                menu.style.borderRadius = '10px';
                menu.style.boxShadow = '0 10px 30px rgba(0,0,0,0.45)';
                menu.style.minWidth = '220px';

                const title = document.createElement('div');
                title.textContent = 'Cell background';
                title.style.fontSize = '10px';
                title.style.fontWeight = '700';
                title.style.letterSpacing = '0.08em';
                title.style.textTransform = 'uppercase';
                title.style.color = '#94a3b8';
                title.style.marginBottom = '8px';
                menu.appendChild(title);

                const swatchWrap = document.createElement('div');
                swatchWrap.style.display = 'grid';
                swatchWrap.style.gridTemplateColumns = 'repeat(6, 1fr)';
                swatchWrap.style.gap = '6px';
                swatchWrap.style.marginBottom = '8px';
                const colors = ['#0f172a', '#1e293b', '#334155', '#475569', '#0369a1', '#166534', '#7c2d12', '#9f1239', '#1d4ed8', '#6d28d9', '#b45309', '#be123c'];
                colors.forEach((hex) => {
                    const swatch = document.createElement('button');
                    swatch.type = 'button';
                    swatch.style.width = '22px';
                    swatch.style.height = '22px';
                    swatch.style.borderRadius = '6px';
                    swatch.style.border = '1px solid #475569';
                    swatch.style.background = hex;
                    swatch.style.cursor = 'pointer';
                    swatch.title = hex;
                    swatch.addEventListener('click', () => {
                        if (!this._activeTableCell) return;
                        this._activeTableCell.style.backgroundColor = hex;
                        this.needsSave = true;
                        this.autoSave();
                    });
                    swatchWrap.appendChild(swatch);
                });
                menu.appendChild(swatchWrap);

                const formatTitle = document.createElement('div');
                formatTitle.textContent = 'Cell elements';
                formatTitle.style.fontSize = '10px';
                formatTitle.style.fontWeight = '700';
                formatTitle.style.letterSpacing = '0.08em';
                formatTitle.style.textTransform = 'uppercase';
                formatTitle.style.color = '#94a3b8';
                formatTitle.style.marginBottom = '6px';
                menu.appendChild(formatTitle);

                const formatButtons = document.createElement('div');
                formatButtons.style.display = 'grid';
                formatButtons.style.gridTemplateColumns = 'repeat(4, 1fr)';
                formatButtons.style.gap = '6px';
                formatButtons.style.marginBottom = '8px';

                const makeFormatBtn = (label, onClick) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.textContent = label;
                    btn.style.height = '24px';
                    btn.style.borderRadius = '6px';
                    btn.style.background = '#1e293b';
                    btn.style.border = '1px solid #334155';
                    btn.style.color = '#e2e8f0';
                    btn.style.fontSize = '11px';
                    btn.style.fontWeight = '700';
                    btn.style.cursor = 'pointer';
                    btn.addEventListener('click', onClick);
                    return btn;
                };

                formatButtons.appendChild(makeFormatBtn('B', () => this.applyCellCommand('bold')));
                formatButtons.appendChild(makeFormatBtn('I', () => this.applyCellCommand('italic')));
                formatButtons.appendChild(makeFormatBtn('U', () => this.applyCellCommand('underline')));
                formatButtons.appendChild(makeFormatBtn('Link', () => {
                    const url = window.prompt('Link URL', 'https://');
                    if (!url) return;
                    this.applyCellCommand('createLink', url);
                }));
                menu.appendChild(formatButtons);

                const insertButtons = document.createElement('div');
                insertButtons.style.display = 'grid';
                insertButtons.style.gridTemplateColumns = '1fr 1fr';
                insertButtons.style.gap = '6px';
                insertButtons.style.marginBottom = '8px';
                insertButtons.appendChild(makeFormatBtn('Checkbox', () => this.insertCheckboxIntoActiveCell()));
                insertButtons.appendChild(makeFormatBtn('Radio', () => this.insertRadioIntoActiveCell()));
                insertButtons.appendChild(makeFormatBtn('Bullet', () => this.insertIntoActiveCell('\u2022 ')));
                menu.appendChild(insertButtons);

                const sizeTitle = document.createElement('div');
                sizeTitle.textContent = 'Cell size';
                sizeTitle.style.fontSize = '10px';
                sizeTitle.style.fontWeight = '700';
                sizeTitle.style.letterSpacing = '0.08em';
                sizeTitle.style.textTransform = 'uppercase';
                sizeTitle.style.color = '#94a3b8';
                sizeTitle.style.marginBottom = '6px';
                menu.appendChild(sizeTitle);

                const sizeControls = document.createElement('div');
                sizeControls.style.display = 'grid';
                sizeControls.style.gridTemplateColumns = '1fr 1fr';
                sizeControls.style.gap = '6px';
                sizeControls.style.marginBottom = '8px';

                const makeSizeGroup = (labelText, minusFn, plusFn) => {
                    const wrap = document.createElement('div');
                    wrap.style.display = 'grid';
                    wrap.style.gridTemplateColumns = '24px 1fr 24px';
                    wrap.style.alignItems = 'center';
                    wrap.style.gap = '4px';

                    const minus = document.createElement('button');
                    minus.type = 'button';
                    minus.textContent = '-';
                    minus.style.height = '22px';
                    minus.style.borderRadius = '6px';
                    minus.style.background = '#1e293b';
                    minus.style.border = '1px solid #334155';
                    minus.style.color = '#e2e8f0';
                    minus.style.cursor = 'pointer';
                    minus.addEventListener('click', minusFn);

                    const label = document.createElement('div');
                    label.textContent = labelText;
                    label.style.textAlign = 'center';
                    label.style.fontSize = '10px';
                    label.style.color = '#cbd5e1';
                    label.style.fontWeight = '700';

                    const plus = document.createElement('button');
                    plus.type = 'button';
                    plus.textContent = '+';
                    plus.style.height = '22px';
                    plus.style.borderRadius = '6px';
                    plus.style.background = '#1e293b';
                    plus.style.border = '1px solid #334155';
                    plus.style.color = '#e2e8f0';
                    plus.style.cursor = 'pointer';
                    plus.addEventListener('click', plusFn);

                    wrap.appendChild(minus);
                    wrap.appendChild(label);
                    wrap.appendChild(plus);
                    return { wrap, label };
                };

                const widthGroup = makeSizeGroup('W', () => this.adjustActiveCellSize('width', -20), () => this.adjustActiveCellSize('width', 20));
                const heightGroup = makeSizeGroup('H', () => this.adjustActiveCellSize('height', -12), () => this.adjustActiveCellSize('height', 12));
                sizeControls.appendChild(widthGroup.wrap);
                sizeControls.appendChild(heightGroup.wrap);
                menu.appendChild(sizeControls);
                this._tableCellWidthLabel = widthGroup.label;
                this._tableCellHeightLabel = heightGroup.label;

                const paddingControls = document.createElement('div');
                paddingControls.style.display = 'grid';
                paddingControls.style.gridTemplateColumns = '24px 1fr 24px';
                paddingControls.style.alignItems = 'center';
                paddingControls.style.gap = '4px';
                paddingControls.style.marginBottom = '8px';
                const padMinus = document.createElement('button');
                padMinus.type = 'button';
                padMinus.textContent = '-';
                padMinus.style.height = '22px';
                padMinus.style.borderRadius = '6px';
                padMinus.style.background = '#1e293b';
                padMinus.style.border = '1px solid #334155';
                padMinus.style.color = '#e2e8f0';
                padMinus.style.cursor = 'pointer';
                padMinus.addEventListener('click', () => this.adjustActiveCellPadding(-2));
                const padLabel = document.createElement('div');
                padLabel.textContent = 'P 14';
                padLabel.style.textAlign = 'center';
                padLabel.style.fontSize = '10px';
                padLabel.style.color = '#cbd5e1';
                padLabel.style.fontWeight = '700';
                const padPlus = document.createElement('button');
                padPlus.type = 'button';
                padPlus.textContent = '+';
                padPlus.style.height = '22px';
                padPlus.style.borderRadius = '6px';
                padPlus.style.background = '#1e293b';
                padPlus.style.border = '1px solid #334155';
                padPlus.style.color = '#e2e8f0';
                padPlus.style.cursor = 'pointer';
                padPlus.addEventListener('click', () => this.adjustActiveCellPadding(2));
                paddingControls.appendChild(padMinus);
                paddingControls.appendChild(padLabel);
                paddingControls.appendChild(padPlus);
                menu.appendChild(paddingControls);
                this._tableCellPaddingLabel = padLabel;

                const actions = document.createElement('div');
                actions.style.display = 'flex';
                actions.style.gap = '6px';

                const customBtn = document.createElement('button');
                customBtn.type = 'button';
                customBtn.textContent = 'Custom';
                customBtn.style.flex = '1';
                customBtn.style.padding = '6px 8px';
                customBtn.style.fontSize = '10px';
                customBtn.style.fontWeight = '700';
                customBtn.style.textTransform = 'uppercase';
                customBtn.style.letterSpacing = '0.06em';
                customBtn.style.color = '#e2e8f0';
                customBtn.style.background = '#1e293b';
                customBtn.style.border = '1px solid #334155';
                customBtn.style.borderRadius = '7px';
                customBtn.style.cursor = 'pointer';
                customBtn.addEventListener('click', () => {
                    if (!this._activeTableCell || !this._tableColorInput) return;
                    this._tableColorInput.value = this.toHexColor(this._activeTableCell.style.backgroundColor || '#1e293b');
                    this._tableColorInput.click();
                });
                actions.appendChild(customBtn);

                const resetBtn = document.createElement('button');
                resetBtn.type = 'button';
                resetBtn.textContent = 'Reset';
                resetBtn.style.flex = '1';
                resetBtn.style.padding = '6px 8px';
                resetBtn.style.fontSize = '10px';
                resetBtn.style.fontWeight = '700';
                resetBtn.style.textTransform = 'uppercase';
                resetBtn.style.letterSpacing = '0.06em';
                resetBtn.style.color = '#fecaca';
                resetBtn.style.background = '#3f1d1d';
                resetBtn.style.border = '1px solid #7f1d1d';
                resetBtn.style.borderRadius = '7px';
                resetBtn.style.cursor = 'pointer';
                resetBtn.addEventListener('click', () => {
                    if (!this._activeTableCell) return;
                    this._activeTableCell.style.backgroundColor = '';
                    this.needsSave = true;
                    this.autoSave();
                });
                actions.appendChild(resetBtn);

                menu.appendChild(actions);
                document.body.appendChild(menu);
                this._tableCellColorMenu = menu;
            }

            if (!this._tableColorOutsideHandler) {
                this._tableColorOutsideHandler = (event) => {
                    if (!this._tableCellColorMenu || this._tableCellColorMenu.style.display === 'none') return;
                    if (this._tableCellColorMenu.contains(event.target)) return;
                    const cell = event.target.closest ? event.target.closest('.tc-cell, td, th') : null;
                    if (cell && cell === this._activeTableCell) return;
                    this.hideTableCellColorMenu();
                };
                document.addEventListener('mousedown', this._tableColorOutsideHandler);
            }

            if (!this._tableColorRepositionHandler) {
                this._tableColorRepositionHandler = () => {
                    if (!this._activeTableCell) return;
                    if (!this._tableCellColorMenu || this._tableCellColorMenu.style.display === 'none') return;
                    this.positionTableCellColorMenu(this._activeTableCell);
                };
                window.addEventListener('resize', this._tableColorRepositionHandler);
                window.addEventListener('scroll', this._tableColorRepositionHandler, true);
            }

            if (!this._tableCheckboxChangeHandler) {
                this._tableCheckboxChangeHandler = (event) => {
                    const input = event.target && event.target.closest ? event.target.closest('.ld-table-checkbox, .ld-table-radio') : null;
                    if (!input || !holder.contains(input)) return;
                    this.needsSave = true;
                    this.autoSave();
                };
                holder.addEventListener('change', this._tableCheckboxChangeHandler);
            }

            if (this._tableCellClickHandler) {
                holder.removeEventListener('click', this._tableCellClickHandler);
            }

            this._tableCellClickHandler = (event) => {
                if (event.target && event.target.closest && event.target.closest('.ld-table-checkbox, .ld-table-radio')) {
                    return;
                }
                const cell = event.target.closest('.tc-cell, td, th');
                if (!cell || !holder.contains(cell)) return;
                this._activeTableCell = cell;
                this.positionTableCellColorMenu(cell);
                this.refreshActiveCellSizeLabels();
            };

            holder.addEventListener('click', this._tableCellClickHandler);
        },
        positionTableCellColorMenu(cell) {
            if (!this._tableCellColorMenu || !cell) return;
            const rect = cell.getBoundingClientRect();
            const menuW = 230;
            const menuH = 260;
            const maxX = Math.max(8, window.innerWidth - menuW - 8);
            const maxY = Math.max(8, window.innerHeight - menuH - 8);
            const left = Math.max(8, Math.min(rect.left, maxX));
            const top = Math.max(8, Math.min(rect.bottom + 6, maxY));
            this._tableCellColorMenu.style.left = `${left}px`;
            this._tableCellColorMenu.style.top = `${top}px`;
            this._tableCellColorMenu.style.display = 'block';
        },
        hideTableCellColorMenu() {
            if (!this._tableCellColorMenu) return;
            this._tableCellColorMenu.style.display = 'none';
        },
        applyCellCommand(command, value = null) {
            if (!this._activeTableCell) return;
            this._activeTableCell.focus();
            this.ensureCaretInActiveCell();
            document.execCommand(command, false, value);
            this.needsSave = true;
            this.autoSave();
        },
        insertIntoActiveCell(text) {
            if (!this._activeTableCell) return;
            this._activeTableCell.focus();
            this.ensureCaretInActiveCell();
            document.execCommand('insertText', false, text);
            this.needsSave = true;
            this.autoSave();
        },
        insertCheckboxIntoActiveCell() {
            if (!this._activeTableCell) return;
            this._activeTableCell.focus();
            this.ensureCaretInActiveCell();
            document.execCommand(
                'insertHTML',
                false,
                '<input type="checkbox" class="ld-table-checkbox" contenteditable="false"> '
            );
            this.needsSave = true;
            this.autoSave();
        },
        insertRadioIntoActiveCell() {
            if (!this._activeTableCell) return;
            this._activeTableCell.focus();
            this.ensureCaretInActiveCell();
            const group = this.getRadioGroupNameForActiveCell();
            document.execCommand(
                'insertHTML',
                false,
                `<input type="radio" class="ld-table-radio" name="${group}" contenteditable="false"> `
            );
            this.needsSave = true;
            this.autoSave();
        },
        getRadioGroupNameForActiveCell() {
            const cell = this._activeTableCell;
            if (!cell) return `ld_radio_${Date.now()}_${Math.floor(Math.random() * 1000)}`;
            if (!cell.dataset.ldRadioGroup) {
                const pageId = this.activePage && this.activePage.id ? String(this.activePage.id) : 'draft';
                cell.dataset.ldRadioGroup = `ld_radio_${pageId}_${Date.now()}_${Math.floor(Math.random() * 100000)}`;
            }
            return cell.dataset.ldRadioGroup;
        },
        normalizeRadioGroupsByCell() {
            const holder = document.getElementById('editorjs');
            if (!holder) return;
            const cells = Array.from(holder.querySelectorAll('.tc-cell, td, th'));
            cells.forEach((cell, idx) => {
                const radios = Array.from(cell.querySelectorAll('.ld-table-radio'));
                if (radios.length === 0) return;
                if (!cell.dataset.ldRadioGroup) {
                    const pageId = this.activePage && this.activePage.id ? String(this.activePage.id) : 'draft';
                    cell.dataset.ldRadioGroup = `ld_radio_${pageId}_cell_${idx}`;
                }
                const group = cell.dataset.ldRadioGroup;
                radios.forEach((radio) => {
                    radio.name = group;
                });
            });
        },
        ensureCaretInActiveCell() {
            if (!this._activeTableCell) return;
            const selection = window.getSelection();
            const range = selection && selection.rangeCount > 0 ? selection.getRangeAt(0) : null;
            const inside = range && this._activeTableCell.contains(range.startContainer);
            if (inside) return;
            const newRange = document.createRange();
            newRange.selectNodeContents(this._activeTableCell);
            newRange.collapse(false);
            selection.removeAllRanges();
            selection.addRange(newRange);
        },
        adjustActiveCellSize(type, delta) {
            if (!this._activeTableCell) return;
            const tableEl = this._activeTableCell.closest('.tc-table, table');
            if (!tableEl) return;
            const rows = this.getTableRows(tableEl);
            const pos = this.getCellPosition(rows, this._activeTableCell);
            if (!pos) return;

            if (type === 'width') {
                const current = this.getCellPixelSize(rows[0] && rows[0][pos.cIdx], 'width', 140);
                const next = Math.max(60, Math.min(900, current + delta));
                rows.forEach((cells) => {
                    const cell = cells[pos.cIdx];
                    if (!cell) return;
                    cell.style.width = `${next}px`;
                    cell.style.minWidth = `${next}px`;
                    cell.style.maxWidth = `${next}px`;
                });
            } else {
                const current = this.getCellPixelSize(rows[pos.rIdx] && rows[pos.rIdx][0], 'height', 44);
                const next = Math.max(28, Math.min(360, current + delta));
                const targetRow = rows[pos.rIdx] || [];
                targetRow.forEach((cell) => {
                    cell.style.height = `${next}px`;
                    cell.style.minHeight = `${next}px`;
                });
            }

            this.refreshActiveCellSizeLabels();
            this.needsSave = true;
            this.autoSave();
        },
        adjustActiveCellPadding(delta) {
            if (!this._activeTableCell) return;
            const current = this.getActiveCellPadding();
            const next = Math.max(4, Math.min(40, current + delta));
            this._activeTableCell.style.padding = `${next}px`;
            this.refreshActiveCellSizeLabels();
            this.needsSave = true;
            this.autoSave();
        },
        refreshActiveCellSizeLabels() {
            if (!this._activeTableCell) return;
            const tableEl = this._activeTableCell.closest('.tc-table, table');
            if (!tableEl) return;
            const rows = this.getTableRows(tableEl);
            const pos = this.getCellPosition(rows, this._activeTableCell);
            if (!pos) return;

            if (this._tableCellWidthLabel) {
                const width = this.getCellPixelSize(rows[0] && rows[0][pos.cIdx], 'width', 140);
                this._tableCellWidthLabel.textContent = `W ${Math.round(width)}`;
            }
            if (this._tableCellHeightLabel) {
                const height = this.getCellPixelSize(rows[pos.rIdx] && rows[pos.rIdx][0], 'height', 44);
                this._tableCellHeightLabel.textContent = `H ${Math.round(height)}`;
            }
            if (this._tableCellPaddingLabel) {
                const pad = this.getActiveCellPadding();
                this._tableCellPaddingLabel.textContent = `P ${Math.round(pad)}`;
            }
        },
        applyTableCellColorsFromData(data) {
            if (!data || !Array.isArray(data.blocks)) return;
            const tableBlocks = data.blocks.filter(b => b.type === 'table');
            const domTables = Array.from(document.querySelectorAll('#editorjs .tc-table, #editorjs table'));

            tableBlocks.forEach((block, tableIdx) => {
                const colorMap = (block.data && block.data.cellColors) ? block.data.cellColors : {};
                const tableEl = domTables[tableIdx];
                if (!tableEl) return;
                const rows = this.getTableRows(tableEl);
                rows.forEach((cells, rIdx) => {
                    cells.forEach((cell, cIdx) => {
                        const key = `${rIdx}-${cIdx}`;
                        cell.style.backgroundColor = colorMap[key] || '';
                    });
                });
            });
        },
        captureTableCellColors(data) {
            if (!data || !Array.isArray(data.blocks)) return data;
            const tableBlocks = data.blocks.filter(b => b.type === 'table');
            const domTables = Array.from(document.querySelectorAll('#editorjs .tc-table, #editorjs table'));

            tableBlocks.forEach((block, tableIdx) => {
                const tableEl = domTables[tableIdx];
                if (!tableEl) return;

                const colors = {};
                const rows = this.getTableRows(tableEl);
                rows.forEach((cells, rIdx) => {
                    cells.forEach((cell, cIdx) => {
                        const color = (cell.style.backgroundColor || '').trim();
                        if (color && color !== 'transparent') {
                            colors[`${rIdx}-${cIdx}`] = color;
                        }
                    });
                });

                if (!block.data) block.data = {};
                if (Object.keys(colors).length > 0) {
                    block.data.cellColors = colors;
                } else {
                    delete block.data.cellColors;
                }
            });

            return data;
        },
        applyTableCellSizesFromData(data) {
            if (!data || !Array.isArray(data.blocks)) return;
            const tableBlocks = data.blocks.filter(b => b.type === 'table');
            const domTables = Array.from(document.querySelectorAll('#editorjs .tc-table, #editorjs table'));

            tableBlocks.forEach((block, tableIdx) => {
                const tableEl = domTables[tableIdx];
                if (!tableEl) return;
                const rows = this.getTableRows(tableEl);
                const colWidths = (block.data && block.data.columnWidths) ? block.data.columnWidths : {};
                const rowHeights = (block.data && block.data.rowHeights) ? block.data.rowHeights : {};

                Object.keys(colWidths).forEach((colKey) => {
                    const cIdx = Number(colKey);
                    const width = Number(colWidths[colKey]);
                    if (!Number.isFinite(cIdx) || !Number.isFinite(width)) return;
                    rows.forEach((cells) => {
                        const cell = cells[cIdx];
                        if (!cell) return;
                        cell.style.width = `${width}px`;
                        cell.style.minWidth = `${width}px`;
                        cell.style.maxWidth = `${width}px`;
                    });
                });

                Object.keys(rowHeights).forEach((rowKey) => {
                    const rIdx = Number(rowKey);
                    const height = Number(rowHeights[rowKey]);
                    if (!Number.isFinite(rIdx) || !Number.isFinite(height)) return;
                    const targetRow = rows[rIdx] || [];
                    targetRow.forEach((cell) => {
                        cell.style.height = `${height}px`;
                        cell.style.minHeight = `${height}px`;
                    });
                });
            });
        },
        captureTableCellSizes(data) {
            if (!data || !Array.isArray(data.blocks)) return data;
            const tableBlocks = data.blocks.filter(b => b.type === 'table');
            const domTables = Array.from(document.querySelectorAll('#editorjs .tc-table, #editorjs table'));

            tableBlocks.forEach((block, tableIdx) => {
                const tableEl = domTables[tableIdx];
                if (!tableEl) return;
                const rows = this.getTableRows(tableEl);
                const columnWidths = {};
                const rowHeights = {};

                const firstRow = rows[0] || [];
                firstRow.forEach((cell, cIdx) => {
                    const width = this.getCellPixelSize(cell, 'width', 0);
                    const styled = parseFloat(cell && cell.style && cell.style.width ? cell.style.width : '');
                    if (styled > 0 || width > 0) {
                        columnWidths[cIdx] = Math.round(styled > 0 ? styled : width);
                    }
                });

                rows.forEach((cells, rIdx) => {
                    const firstCell = cells[0];
                    if (!firstCell) return;
                    const height = this.getCellPixelSize(firstCell, 'height', 0);
                    const styled = parseFloat(firstCell.style && firstCell.style.height ? firstCell.style.height : '');
                    if (styled > 0 || height > 0) {
                        rowHeights[rIdx] = Math.round(styled > 0 ? styled : height);
                    }
                });

                if (!block.data) block.data = {};
                if (Object.keys(columnWidths).length > 0) block.data.columnWidths = columnWidths;
                else delete block.data.columnWidths;
                if (Object.keys(rowHeights).length > 0) block.data.rowHeights = rowHeights;
                else delete block.data.rowHeights;
            });

            return data;
        },
        applyTableCellPaddingsFromData(data) {
            if (!data || !Array.isArray(data.blocks)) return;
            const tableBlocks = data.blocks.filter(b => b.type === 'table');
            const domTables = Array.from(document.querySelectorAll('#editorjs .tc-table, #editorjs table'));
            tableBlocks.forEach((block, tableIdx) => {
                const tableEl = domTables[tableIdx];
                if (!tableEl) return;
                const rows = this.getTableRows(tableEl);
                const paddings = (block.data && block.data.cellPaddings) ? block.data.cellPaddings : {};
                Object.keys(paddings).forEach((key) => {
                    const parts = key.split('-');
                    const rIdx = Number(parts[0]);
                    const cIdx = Number(parts[1]);
                    if (!Number.isFinite(rIdx) || !Number.isFinite(cIdx)) return;
                    const cell = rows[rIdx] && rows[rIdx][cIdx];
                    if (!cell) return;
                    cell.style.padding = String(paddings[key]);
                });
            });
        },
        captureTableCellPaddings(data) {
            if (!data || !Array.isArray(data.blocks)) return data;
            const tableBlocks = data.blocks.filter(b => b.type === 'table');
            const domTables = Array.from(document.querySelectorAll('#editorjs .tc-table, #editorjs table'));
            tableBlocks.forEach((block, tableIdx) => {
                const tableEl = domTables[tableIdx];
                if (!tableEl) return;
                const rows = this.getTableRows(tableEl);
                const paddings = {};
                rows.forEach((cells, rIdx) => {
                    cells.forEach((cell, cIdx) => {
                        const value = (cell.style.padding || '').trim();
                        if (value) paddings[`${rIdx}-${cIdx}`] = value;
                    });
                });
                if (!block.data) block.data = {};
                if (Object.keys(paddings).length > 0) block.data.cellPaddings = paddings;
                else delete block.data.cellPaddings;
            });
            return data;
        },
        getTableRows(tableEl) {
            if (!tableEl) return [];
            const tcRows = Array.from(tableEl.querySelectorAll('.tc-row'));
            if (tcRows.length > 0) {
                return tcRows.map((row) => Array.from(row.querySelectorAll('.tc-cell')));
            }
            const trRows = Array.from(tableEl.querySelectorAll('tr'));
            return trRows.map((row) => Array.from(row.querySelectorAll('td, th')));
        },
        getCellPosition(rows, targetCell) {
            for (let rIdx = 0; rIdx < rows.length; rIdx++) {
                const cIdx = rows[rIdx].indexOf(targetCell);
                if (cIdx !== -1) return { rIdx, cIdx };
            }
            return null;
        },
        getCellPixelSize(cell, axis, fallback) {
            if (!cell) return fallback;
            const styled = parseFloat(axis === 'width' ? cell.style.width : cell.style.height);
            if (Number.isFinite(styled) && styled > 0) return styled;
            const rect = cell.getBoundingClientRect();
            const measured = axis === 'width' ? rect.width : rect.height;
            return Number.isFinite(measured) && measured > 0 ? measured : fallback;
        },
        getActiveCellPadding() {
            if (!this._activeTableCell) return 14;
            const styled = parseFloat(this._activeTableCell.style.padding);
            if (Number.isFinite(styled) && styled > 0) return styled;
            const computed = window.getComputedStyle(this._activeTableCell);
            const top = parseFloat(computed.paddingTop);
            return Number.isFinite(top) && top > 0 ? top : 14;
        },
        toHexColor(color) {
            if (!color) return '#1e293b';
            if (color.startsWith('#')) {
                if (color.length === 4) {
                    return `#${color[1]}${color[1]}${color[2]}${color[2]}${color[3]}${color[3]}`;
                }
                return color;
            }
            const m = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
            if (!m) return '#1e293b';
            const r = Number(m[1]).toString(16).padStart(2, '0');
            const g = Number(m[2]).toString(16).padStart(2, '0');
            const b = Number(m[3]).toString(16).padStart(2, '0');
            return `#${r}${g}${b}`;
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
