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
            adminMessages: [], 
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
            showProfileModal: false,
            profileForm: { username: '', email: '', nickname: '', password: '' },
            showUsersModal: false,
            userList: [],
            editingUser: false,
            userForm: { username: '', email: '', nickname: '', role: 'user' },
            showCropModal: false,
            cropImageSrc: null,
            cropper: null,
            showBetaNotice: false,
            dismissBetaPermanently: false,
            alertDialog: { show: false, title: '', message: '' },
            confirmDialog: { show: false, title: '', message: '', onConfirm: null }
        }
    },
    async created() {
        const hideBeta = localStorage.getItem('hideBetaNotice');
        if (!hideBeta) this.showBetaNotice = true;
        await this.fetchUser();
        await this.fetchData();
        await this.fetchRooms();
        await this.fetchAdminMessages();
        
        setInterval(this.fetchData, 10000); 
        setInterval(this.fetchRooms, 3000); 
        setInterval(this.fetchAdminMessages, 3000); 

        window.addEventListener('mousemove', this.doDrag);
        window.addEventListener('mouseup', this.stopDrag);
    },
    methods: {
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
                if (updated && (updated.title !== this.activePage.title || updated.is_public !== this.activePage.is_public || updated.cover_image !== this.activePage.cover_image)) {
                    this.activePage.title = updated.title;
                    this.activePage.is_public = updated.is_public;
                    this.activePage.cover_image = updated.cover_image;
                }
            }
        },
        async fetchRooms() {
            const r = await fetch('api.php?action=rooms');
            this.rooms = await r.json();
            if (this.activeRoom) {
                const r2 = await fetch(`api.php?action=messages&room_id=${this.activeRoom.id}`);
                this.roomMessages = await r2.json();
            }
        },
        async fetchAdminMessages() {
            try {
                const r = await fetch('api.php?action=admin_terminal');
                const data = await r.json();
                
                const sysMsg = { id: 'sys-1', sender: "System", content: "Terminal Operational. Signals Syncing.", colorClass: "text-blue-400" };
                
                const currentLast = this.adminMessages.length > 1 ? this.adminMessages[this.adminMessages.length - 1].id : null;
                const newLast = data.length > 0 ? data[data.length - 1].id : null;
                
                if (this.adminMessages.length === 0 || currentLast !== newLast) {
                    this.adminMessages = [sysMsg, ...data];
                    this.$nextTick(() => {
                        const el = document.getElementById('admin-chat');
                        if (el) el.scrollTop = el.scrollHeight;
                    });
                }
            } catch(e) {}
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
                    await fetch('api.php', { method: 'PUT', body: JSON.stringify({ ...this.activePage, content: str, action: 'publish' }) });
                    this.lastSavedContent = str; this.lastSavedTitle = this.activePage.title; this.lastSavedPublic = this.activePage.is_public; 
                    this.lastSavedCover = this.activePage.cover_image; this.lastSaveTime = this.getFormattedDateTime(); this.needsSave = false; this.activePage.has_draft = 0; 
                    this.showAlert("Signal Active", "Live view updated.");
                } catch (e) { }
            }
            await this.fetchData(); 
            this.loading = false;
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
            return this.items.filter(i => i.type === 'page' && i.parent_id === spaceId);
        },
        getSubpages(pageId) {
            return this.items.filter(i => i.type === 'subpage' && i.parent_id === pageId);
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
        startDrag(target) { this.dragTarget = target; },
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
            }
        },
        stopDrag() { this.dragTarget = null; },
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
        },
        dismissBetaNotice() {
            if (this.dismissBetaPermanently) localStorage.setItem('hideBetaNotice', 'true');
            this.showBetaNotice = false;
        }
    },
    computed: {
        spaces() {
            return this.items.filter(i => i.type === 'space');
        }
    }
}).mount('#app');