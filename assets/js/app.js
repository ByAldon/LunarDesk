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
            saveInFlight: false,
            pendingAutoSave: false,
            isPublishing: false,
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
            showTableSlashModal: false,
            tableSlashCell: null,
            tableSlashQuery: '',
            showRadioBuilderModal: false,
            radioBuilderCell: null,
            radioBuilderForm: { title: '', optionsText: '' },
            showPromptModal: false,
            promptTitle: '',
            promptInput: '',
            promptAction: null,
            showProfileModal: false,
            profileForm: { username: '', email: '', nickname: '', password: '' },
            showUsersModal: false,
            showManualModal: false,
            showHeaderMenu: false,
            showUpdateModal: false,
            updateNoticeVersion: '',
            updateNotes: [],
            userList: [],
            editingUser: false,
            userForm: { username: '', email: '', nickname: '', role: 'user' },
            showCropModal: false,
            cropImageSrc: null,
            cropper: null,
            alertDialog: { show: false, title: '', message: '' },
            confirmDialog: { show: false, title: '', message: '', onConfirm: null },
            editorHistory: [],
            editorHistoryIndex: -1,
            historyBusy: false,
            editorSessionKey: 0,
            editorBootstrapping: false,
            editorUserInteracted: false,
            silentRefreshTimer: null,
            silentRefreshInFlight: false
        }
    },
    async created() {
        await this.fetchUser();
        await this.fetchData();
        await this.fetchRooms();
        await this.fetchAdminMessages();
        await this.restoreUiState();
        this.evaluateUpdateNotice();
        
        setInterval(() => this.fetchData(), 10000);
        setInterval(() => this.fetchRooms(), 3000);
        setInterval(() => this.fetchAdminMessages(), 3000);

        this._historySnapshotTimer = null;
        this._isRestoringHistory = false;
        this._isSelectingTableRange = false;
        this._tableSelectionAnchorCell = null;

        window.addEventListener('mousemove', this.doDrag);
        window.addEventListener('mouseup', this.stopDrag);
        document.addEventListener('click', this.handleGlobalClick);
    },
    beforeUnmount() {
        document.removeEventListener('click', this.handleGlobalClick);
    },
    methods: {
        getAppVersion() {
            const root = document.getElementById('app');
            return root && root.dataset ? String(root.dataset.appVersion || '') : '';
        },
        getUpdateSeenStorageKey(version) {
            const uid = this.currentUser && Number.isFinite(Number(this.currentUser.id))
                ? Number(this.currentUser.id)
                : 'anon';
            return `lunardesk_update_seen_${version}_${uid}`;
        },
        getUpdateNotesForVersion(version) {
            const notesByVersion = {
                'v2.9.7.22': [
                    'Fixed table menus that could remain visible after clicking outside a table.',
                    'Outside-click now closes custom table menus and native row/column popovers.',
                    'Improved cleanup for table menu overlays in editor interactions.'
                ],
                'v2.9.7.21': [
                    'Manual table section updated to match current menu/search behavior.',
                    'Documented multi-word search in slash and right-click table menus.',
                    'Documented Row height... usage in right-click and native row menus.'
                ],
                'v2.9.7.20': [
                    'Fixed row-height mini adjust popup getting stuck after opening from the native row menu.',
                    'Outside-click handler now closes the numeric adjust popup even without the custom right-click menu.',
                    'Improved table menu cleanup behavior for native and custom menu flows.'
                ],
                'v2.9.7.19': [
                    'Fixed native table row/column custom action icons showing question marks.',
                    'Custom native menu actions now reuse the host menu icon style consistently.',
                    'Checked native row and column menu action rendering consistency.'
                ],
                'v2.9.7.18': [
                    'Removed row height +/- slash actions to reduce duplicate sizing entries.',
                    'Added Row height... action to the native row menu (4-dots).',
                    'Improved row sizing flow through the dedicated numeric adjust menu.'
                ],
                'v2.9.7.17': [
                    'Fixed native table row action click handling to prevent double execution.',
                    'Duplicate row from the row menu now creates exactly one duplicate row.',
                    'Updated startup release notice and version registration for this release.'
                ],
                'v2.9.7.16': [
                    'Numeric table actions now open a dedicated mini adjust menu.',
                    'Row height, column width, cell padding and border width support +/- plus manual number input.',
                    'Improved table sizing workflow from the right-click menu.'
                ],
                'v2.9.7.15': [
                    'Fixed native row-menu action handling so row color action is clickable reliably.',
                    'Added row color HEX input action for quick precise row coloring.',
                    'Improved native table menu behavior consistency.'
                ],
                'v2.9.7.14': [
                    'Native table menu actions are now split by menu type.',
                    'Row menu shows only row-related actions.',
                    'Column menu shows only column-related actions.'
                ],
                'v2.9.7.13': [
                    'Added table action: Duplicate row.',
                    'Duplicate row is now available in table menus and slash commands.',
                    'Table structure editing is faster for repeated row layouts.'
                ],
                'v2.9.7.12': [
                    'Native table 4-dots menu now includes: Row background color...',
                    'You can now color an entire row (left to right) in one action.',
                    'Adjusted table menu wording and behavior for row-first coloring.'
                ],
                'v2.9.7.11': [
                    'Native table 4-dots menu now includes: Column background color...',
                    'You can now color an entire table column in one action from that menu.',
                    'Small table workflow improvement for faster formatting.'
                ],
                'v2.9.7.10': [
                    'Table right-click menu actions are now sorted alphabetically.',
                    'Improved action scanability in long context menus.',
                    'Minor menu usability cleanup.'
                ],
                'v2.9.7.9': [
                    'Fixed native table menu injection targeting to avoid rendering actions in page content.',
                    'Added cleanup for stale custom native-menu entries from previous faulty injections.',
                    'Improved stability of custom actions inside the table 4-dots popup.'
                ],
                'v2.9.7.8': [
                    'Fixed duplicate entries in the native table 4-dots menu.',
                    'Native menu actions are now injected once per open menu, without stacking.',
                    'Stability improvements for custom table menu integration.'
                ],
                'v2.9.7.7': [
                    'Table right-click menu is now larger for easier reading and clicking.',
                    'Added a small search field at the top of the table right-click menu.',
                    'Menu actions can now be filtered live while typing.'
                ],
                'v2.9.7.4': [
                    'Table tools expanded: duplicate current column from the table menu/slash commands.',
                    'Table tools expanded: move current row up or down from the table menu/slash commands.',
                    'Improved table editing flow for faster structural changes.'
                ],
                'v2.9.7.6': [
                    'The native table 4-dots menu now includes: Duplicate column.',
                    'The native table 4-dots menu now includes: Move row up and Move row down.',
                    'Table structure actions are available directly in the built-in table menu.'
                ],
                'v2.9.7.3': [
                    'Updated the startup "System Updated" message content for this release.',
                    'Version handling remains one-time per user, per app version.',
                    'General maintenance and stability improvements.'
                ],
                'v2.9.4.11': [
                    'One-time "System Updated" popup added per user per version.',
                    'Popup now appears again automatically after each new version.',
                    'Release highlights are shown directly in-app on first visit.'
                ]
            };
            if (Array.isArray(notesByVersion[version]) && notesByVersion[version].length > 0) {
                return notesByVersion[version];
            }
            return [
                'This release contains improvements and fixes.',
                'Open Manual for detailed changes and usage notes.'
            ];
        },
        evaluateUpdateNotice() {
            const version = this.getAppVersion();
            if (!version) return;
            const key = this.getUpdateSeenStorageKey(version);
            if (localStorage.getItem(key) === '1') return;
            this.updateNoticeVersion = version;
            this.updateNotes = this.getUpdateNotesForVersion(version);
            this.showUpdateModal = true;
        },
        dismissUpdateNotice() {
            const version = this.updateNoticeVersion || this.getAppVersion();
            if (version) {
                localStorage.setItem(this.getUpdateSeenStorageKey(version), '1');
            }
            this.showUpdateModal = false;
        },
        queueSilentRefresh(delayMs = 300) {
            if (this.silentRefreshTimer) clearTimeout(this.silentRefreshTimer);
            this.silentRefreshTimer = setTimeout(() => {
                this.silentRefreshTimer = null;
                this.runSilentRefresh();
            }, delayMs);
        },
        async runSilentRefresh() {
            if (this.silentRefreshInFlight) return;
            if (this.historyBusy || this._isRestoringHistory) {
                this.queueSilentRefresh(200);
                return;
            }
            this.silentRefreshInFlight = true;
            try {
                await this.fetchData();
            } finally {
                this.silentRefreshInFlight = false;
            }
        },
        getUiStateStorageKey() {
            return 'lunardesk_ui_state_v1';
        },
        loadUiState() {
            try {
                const raw = localStorage.getItem(this.getUiStateStorageKey());
                if (!raw) return null;
                const parsed = JSON.parse(raw);
                return (parsed && typeof parsed === 'object') ? parsed : null;
            } catch (e) {
                return null;
            }
        },
        saveUiState() {
            try {
                const state = {
                    activeLeftTab: this.activeLeftTab === 'stream' ? 'stream' : 'terminal',
                    activeRoomId: this.activeRoom && Number.isFinite(Number(this.activeRoom.id)) ? Number(this.activeRoom.id) : null,
                    activePageId: this.activePage && Number.isFinite(Number(this.activePage.id)) ? Number(this.activePage.id) : null
                };
                localStorage.setItem(this.getUiStateStorageKey(), JSON.stringify(state));
            } catch (e) {}
        },
        async restoreUiState() {
            const state = this.loadUiState();
            if (!state) return;

            if (state.activeLeftTab === 'stream' || state.activeLeftTab === 'terminal') {
                this.activeLeftTab = state.activeLeftTab;
            }

            if (Number.isFinite(Number(state.activeRoomId))) {
                const room = this.rooms.find((r) => Number(r.id) === Number(state.activeRoomId));
                if (room) {
                    this.activeRoom = room;
                    await this.fetchMessages(room.id);
                }
            }

            if (Number.isFinite(Number(state.activePageId))) {
                const page = this.items.find((i) => Number(i.id) === Number(state.activePageId));
                if (page) {
                    await this.selectDoc(page);
                }
            }
        },
        handleUnauthorized() {
            this.showAlert('Session expired', 'Please log in again to continue editing.');
            setTimeout(() => { window.location.href = 'auth.php'; }, 900);
        },
        async fetchJson(url, options = {}) {
            const res = await fetch(url, options);
            if (res.status === 401) {
                this.handleUnauthorized();
                throw new Error('Unauthorized');
            }
            let data = null;
            try {
                data = await res.json();
            } catch (e) {
                throw new Error('Invalid server response');
            }
            if (!res.ok) {
                throw new Error((data && data.error) ? data.error : `Request failed (${res.status})`);
            }
            if (data && typeof data === 'object' && data.error) {
                if (String(data.error).toLowerCase().includes('unauthorized') || String(data.error).toLowerCase().includes('session expired')) {
                    this.handleUnauthorized();
                }
                throw new Error(data.error);
            }
            return data;
        },
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
            this.saveUiState();
        },
        toggleHeaderMenu() {
            this.showHeaderMenu = !this.showHeaderMenu;
        },
        closeHeaderMenu() {
            this.showHeaderMenu = false;
        },
        handleGlobalClick(e) {
            if (!this.showHeaderMenu) return;
            const root = this.$refs.headerMenuRoot;
            if (root && root.contains(e.target)) return;
            this.showHeaderMenu = false;
        },
        async fetchUser() {
            try {
                this.currentUser = await this.fetchJson('api.php?action=profile');
            } catch (e) {}
        },
        async fetchData() {
            try {
                const data = await this.fetchJson('api.php');
                if (!Array.isArray(data)) return;
                this.items = data;
                const canPatchActivePage =
                    this.activePage &&
                    !this.needsSave &&
                    !this.saveInFlight &&
                    !this.historyBusy &&
                    !this._isRestoringHistory;
                if (canPatchActivePage) {
                    const updated = data.find(i => i.id === this.activePage.id);
                    if (updated) {
                        const useDraft = Number(updated.has_draft) === 1;
                        const updatedHasDraft = useDraft ? 1 : 0;
                        const updatedTitle = useDraft ? (updated.draft_title || updated.title) : updated.title;
                        const updatedCover = useDraft
                            ? (updated.draft_cover_image || updated.cover_image || '')
                            : (updated.cover_image || '');
                        const activeCover = this.activePage.cover_image || '';
                        this.activePage.has_draft = updatedHasDraft;
                        this.activePage.draft_title = updated.draft_title || '';
                        this.activePage.draft_content = updated.draft_content || '';
                        this.activePage.draft_cover_image = updated.draft_cover_image || '';
                        if (updatedTitle !== this.activePage.title || updated.is_public !== this.activePage.is_public || updatedCover !== activeCover) {
                        this.activePage.title = updatedTitle;
                        this.activePage.is_public = updated.is_public;
                        this.activePage.cover_image = updatedCover;
                        }
                    }
                }
            } catch (e) {
                if (!Array.isArray(this.items)) this.items = [];
            }
        },
        async fetchRooms() {
            try {
                const data = await this.fetchJson('api.php?action=rooms');
                this.rooms = Array.isArray(data) ? data : [];
                if (this.activeRoom) {
                    await this.fetchMessages(this.activeRoom.id);
                }
            } catch (e) {
                this.rooms = [];
            }
        },
async fetchMessages(roomId) {
            try {
                const data = await this.fetchJson(`api.php?action=messages&room_id=${roomId}`);
                if (!Array.isArray(data)) return;
                const oldLen = this.roomMessages ? this.roomMessages.length : 0;
                this.roomMessages = data;

                if (data.length > oldLen && oldLen > 0 && this.activeLeftTab !== 'stream') {
                    this.hasUnreadStream = true;
                }

                // FIX: Alleen naar beneden scrollen als er ECHT nieuwe berichten zijn
                if (this.activeLeftTab === 'stream' && data.length > oldLen) {
                    this.$nextTick(() => {
                        const el = document.getElementById('webhook-stream');
                        if (el) el.scrollTop = el.scrollHeight;
                    });
                }
            } catch (e) { console.error(e); }
        },
        
        async fetchAdminMessages() {
            try {
                const data = await this.fetchJson(`api.php?action=admin_terminal&t=${Date.now()}`, { cache: 'no-store' });
                if (!Array.isArray(data)) return;
                const oldLen = this.adminMessages ? this.adminMessages.length : 0;
                this.adminMessages = data;

                if (data.length > oldLen && oldLen > 0 && this.activeLeftTab !== 'terminal') {
                    this.hasUnreadTerminal = true;
                }

                // FIX: Alleen naar beneden scrollen als er ECHT nieuwe berichten zijn
                if (this.activeLeftTab === 'terminal' && data.length > oldLen) {
                    this.$nextTick(() => {
                        const el = document.getElementById('admin-chat');
                        if (el) el.scrollTop = el.scrollHeight;
                    });
                }
            } catch (e) { console.error(e); }
        },
        selectRoom(room) {
            this.activeRoom = room;
            this.saveUiState();
            this.fetchRooms();
        },
        async selectDoc(page) {
            const sessionKey = this.editorSessionKey + 1;
            this.editorSessionKey = sessionKey;
            this.editorBootstrapping = false;
            this.editorUserInteracted = false;
            this.loading = true;
            this.pendingAutoSave = false;
            this.activePage = { ...page };
            const useDraft = Number(page.has_draft) === 1;
            this.activePage.title = useDraft ? (page.draft_title || page.title) : page.title;
            this.activePage.cover_image = useDraft
                ? (page.draft_cover_image || page.cover_image || '')
                : (page.cover_image || '');
            this.activePage.draft_cover_image = page.draft_cover_image || '';
            this.lastSavedContent = page.draft_content || page.content;
            this.lastSavedTitle = this.activePage.title;
            this.lastSavedPublic = page.is_public;
            this.lastSavedCover = this.activePage.cover_image;
            this.lastSaveTime = null;
            this.needsSave = false;
            this.clearHistorySnapshotTimer();
            this.clearTableSelection();
            this.editorHistory = [];
            this.editorHistoryIndex = -1;
            this.historyBusy = false;

            if (globalEditorInstance) {
                this.unbindWordLikeTableBehavior();
                globalEditorInstance.destroy();
                globalEditorInstance = null;
            }

            setTimeout(() => {
                if (this.editorSessionKey !== sessionKey) return;
                const initialData = this.lastSavedContent ? JSON.parse(this.lastSavedContent) : { blocks: [] };
                const HeaderToolClass = window.Header || window.EditorjsHeader;
                const ParagraphToolClass = window.Paragraph;
                const TableToolClass = window.Table || window.EditorjsTable;
                this.editorBootstrapping = true;
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
                        ...(TableToolClass ? {
                            table: {
                                class: TableToolClass,
                                inlineToolbar: ['bold', 'italic', 'link', 'underline', 'inlineCode'],
                                config: {
                                    rows: 3,
                                    cols: 3,
                                    withHeadings: true
                                }
                            }
                        } : {}),
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
                        if (this.editorSessionKey !== sessionKey) return;
                        this.applyTableStylesFromData(initialData);
                        this.bindWordLikeTableBehavior();
                        const hydrated = this.captureTableStyles(JSON.parse(JSON.stringify(initialData)));
                        const serializedHydrated = JSON.stringify(hydrated);
                        this.lastSavedContent = serializedHydrated;
                        this.resetEditorHistory(hydrated);
                        this.needsSave = false;
                        setTimeout(() => {
                            if (this.editorSessionKey !== sessionKey) return;
                            this.editorBootstrapping = false;
                        }, 0);
                    },
                    onChange: () => {
                        if (this.editorSessionKey !== sessionKey) return;
                        if (this.editorBootstrapping) return;
                        if (!this.editorUserInteracted) return;
                        if (this._isRestoringHistory) return;
                        this.needsSave = true;
                        this.autoSave();
                        this.queueHistorySnapshot();
                    }
                });
            }, 100);
            this.loading = false;
            this.saveUiState();
            if (this.silentRefreshTimer) {
                clearTimeout(this.silentRefreshTimer);
                this.silentRefreshTimer = null;
            }
        },
        async autoSave() {
            if (!this.activePage || !globalEditorInstance || this.isPublishing) return;
            if (this.saveInFlight) {
                this.pendingAutoSave = true;
                return;
            }

            const sessionKey = this.editorSessionKey;
            const pageSnapshot = { ...this.activePage };
            const pageId = Number(pageSnapshot.id);
            this.saveInFlight = true;
            try {
                const raw = await this.getEditorOutput();
                if (this.editorSessionKey !== sessionKey || !this.activePage || Number(this.activePage.id) !== pageId) return;
                const str = JSON.stringify(raw);
                const hasChanges =
                    str !== this.lastSavedContent ||
                    pageSnapshot.title !== this.lastSavedTitle ||
                    pageSnapshot.is_public !== this.lastSavedPublic ||
                    pageSnapshot.cover_image !== this.lastSavedCover;
                if (hasChanges) {
                    await this.fetchJson('api.php', {
                        method: 'PUT',
                        body: JSON.stringify({ ...pageSnapshot, content: str, action: 'draft' })
                    });
                    if (this.editorSessionKey !== sessionKey || !this.activePage || Number(this.activePage.id) !== pageId) return;
                    this.lastSavedContent = str;
                    this.lastSavedTitle = pageSnapshot.title;
                    this.lastSavedPublic = pageSnapshot.is_public;
                    this.lastSavedCover = pageSnapshot.cover_image;
                    this.lastSaveTime = this.getFormattedDateTime();
                    this.needsSave = false;
                    this.activePage.has_draft = 1;
                    const idx = this.items.findIndex(i => i.id === this.activePage.id);
                    if (idx !== -1) {
                        this.items[idx].has_draft = 1;
                        this.items[idx].draft_title = pageSnapshot.title;
                        this.items[idx].draft_content = str;
                        this.items[idx].draft_cover_image = pageSnapshot.cover_image;
                    }
                    this.queueSilentRefresh(350);
                } else {
                    this.needsSave = false;
                }
            } catch (e) {
                this.needsSave = true;
            } finally {
                this.saveInFlight = false;
                if (this.pendingAutoSave && !this.isPublishing && this.editorSessionKey === sessionKey) {
                    this.pendingAutoSave = false;
                    this.autoSave();
                } else if (this.editorSessionKey !== sessionKey) {
                    this.pendingAutoSave = false;
                }
            }
        },
        async waitForSaveIdle() {
            while (this.saveInFlight) {
                await new Promise(resolve => setTimeout(resolve, 25));
            }
        },
        async manualPublish() {
            this.loading = true;
            this.isPublishing = true;
            const sessionKey = this.editorSessionKey;
            const pageId = this.activePage ? Number(this.activePage.id) : null;
            try {
                await this.waitForSaveIdle();
                if (this.editorSessionKey !== sessionKey || !this.activePage || Number(this.activePage.id) !== pageId) return;
                if (globalEditorInstance) {
                    const pageSnapshot = { ...this.activePage };
                    const raw = await this.getEditorOutput();
                    if (this.editorSessionKey !== sessionKey || !this.activePage || Number(this.activePage.id) !== pageId) return;
                    const str = JSON.stringify(raw);
                    const publishCover = pageSnapshot.draft_cover_image || pageSnapshot.cover_image || '';
                    await this.fetchJson('api.php', {
                        method: 'PUT',
                        body: JSON.stringify({ ...pageSnapshot, cover_image: publishCover, content: str, action: 'publish' })
                    });
                    if (this.editorSessionKey !== sessionKey || !this.activePage || Number(this.activePage.id) !== pageId) return;
                    this.activePage.cover_image = publishCover;
                    this.lastSavedContent = str; this.lastSavedTitle = pageSnapshot.title; this.lastSavedPublic = pageSnapshot.is_public; 
                    this.lastSavedCover = publishCover; this.lastSaveTime = this.getFormattedDateTime(); this.needsSave = false; this.activePage.has_draft = 0; 
                    const idx = this.items.findIndex(i => i.id === this.activePage.id);
                    if (idx !== -1) this.items[idx].has_draft = 0;
                    this.showPublishNotice('Live page updated.');
                }
            } catch (e) {
                this.showAlert('Publish Error', 'Publishing failed. Try again and check if Live is enabled.');
            } finally {
                this.isPublishing = false;
            }
            await this.runSilentRefresh();
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
                this.saveUiState();
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
            let expectedType = 'page';
            let currentList = [];
            if (listType === 'space') {
                expectedType = 'space';
                currentList = [...this.spaces];
            } else if (listType === 'subpage') {
                expectedType = 'subpage';
                if (item.parent_id !== parentId) return;
                currentList = this.getSubpages(parentId);
            } else {
                expectedType = 'page';
                if (item.parent_id !== parentId) return;
                currentList = this.getPages(parentId);
            }
            if (item.type !== expectedType) return;

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
        renameSpace(space) {
            if (!space || Number(space.id) <= 0) return;
            this.showPrompt("Rename Space", "Space Name", async (val) => {
                const nextTitle = String(val || '').trim();
                if (!nextTitle) return;
                this.loading = true;
                try {
                    await this.fetchJson('api.php', {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            id: Number(space.id),
                            title: nextTitle,
                            content: String(space.content || ''),
                            cover_image: String(space.cover_image || ''),
                            is_public: Number(space.is_public) === 1 ? 1 : 0,
                            action: 'publish'
                        })
                    });
                    await this.fetchData();
                } catch (e) {
                    this.showAlert('Rename Error', 'Could not rename this space.');
                } finally {
                    this.loading = false;
                }
            }, String(space.title || ''));
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
        clearHistorySnapshotTimer() {
            if (!this._historySnapshotTimer) return;
            clearTimeout(this._historySnapshotTimer);
            this._historySnapshotTimer = null;
        },
        resetEditorHistory(initialData) {
            const safeData = initialData && Array.isArray(initialData.blocks) ? initialData : { blocks: [] };
            this.editorHistory = [JSON.stringify(safeData)];
            this.editorHistoryIndex = 0;
            this.historyBusy = false;
            this.clearHistorySnapshotTimer();
        },
        queueHistorySnapshot() {
            this.clearHistorySnapshotTimer();
            this._historySnapshotTimer = setTimeout(() => {
                this.captureHistorySnapshot();
            }, 350);
        },
        async captureHistorySnapshot() {
            if (!globalEditorInstance || this._isRestoringHistory) return;
            try {
                const output = await this.getEditorOutput();
                const serialized = JSON.stringify(output);
                this.pushHistorySnapshot(serialized);
            } catch (e) {
            }
        },
        pushHistorySnapshot(serialized) {
            if (!serialized) return;
            const current = this.editorHistory[this.editorHistoryIndex] || null;
            if (current === serialized) return;
            if (this.editorHistoryIndex < this.editorHistory.length - 1) {
                this.editorHistory = this.editorHistory.slice(0, this.editorHistoryIndex + 1);
            }
            this.editorHistory.push(serialized);
            const maxHistory = 100;
            if (this.editorHistory.length > maxHistory) {
                this.editorHistory.shift();
            }
            this.editorHistoryIndex = this.editorHistory.length - 1;
        },
        async restoreHistoryAt(index) {
            if (!globalEditorInstance) return;
            if (index < 0 || index >= this.editorHistory.length) return;
            const serialized = this.editorHistory[index];
            if (!serialized) return;
            let parsed = null;
            try {
                parsed = JSON.parse(serialized);
            } catch (e) {
                return;
            }

            this.historyBusy = true;
            this._isRestoringHistory = true;
            this.clearTableSelection();
            try {
                await globalEditorInstance.render(parsed);
                this.applyTableStylesFromData(parsed);
                this.editorHistoryIndex = index;
                this.needsSave = true;
                this.autoSave();
            } finally {
                this._isRestoringHistory = false;
                this.historyBusy = false;
            }
        },
        async undoEditor() {
            if (this.historyBusy) return;
            if (this.editorHistoryIndex <= 0) return;
            await this.restoreHistoryAt(this.editorHistoryIndex - 1);
        },
        async redoEditor() {
            if (this.historyBusy) return;
            if (this.editorHistoryIndex >= this.editorHistory.length - 1) return;
            await this.restoreHistoryAt(this.editorHistoryIndex + 1);
        },
        getTableSlashCommands() {
            return [
                {
                    id: 'radiobutton',
                    label: 'radiobutton',
                    hint: 'Insert radio buttons with text',
                    run: (cell) => this.openTableRadioBuilder(cell)
                },
                {
                    id: 'insert-row-above',
                    label: 'insert row above',
                    hint: 'Insert a row above current cell',
                    run: (cell) => { this.insertTableRow(cell, 'above'); this.markTableChanged(); }
                },
                {
                    id: 'insert-row-below',
                    label: 'insert row below',
                    hint: 'Insert a row below current cell',
                    run: (cell) => { this.insertTableRow(cell, 'below'); this.markTableChanged(); }
                },
                {
                    id: 'insert-column-left',
                    label: 'insert column left',
                    hint: 'Insert a column to the left',
                    run: (cell) => { this.insertTableColumn(cell, 'left'); this.markTableChanged(); }
                },
                {
                    id: 'insert-column-right',
                    label: 'insert column right',
                    hint: 'Insert a column to the right',
                    run: (cell) => { this.insertTableColumn(cell, 'right'); this.markTableChanged(); }
                },
                {
                    id: 'delete-row',
                    label: 'delete row',
                    hint: 'Delete current row',
                    run: (cell) => { this.deleteTableRow(cell); this.markTableChanged(); }
                },
                {
                    id: 'delete-column',
                    label: 'delete column',
                    hint: 'Delete current column',
                    run: (cell) => { this.deleteTableColumn(cell); this.markTableChanged(); }
                },
                {
                    id: 'duplicate-column',
                    label: 'duplicate column',
                    hint: 'Duplicate current column to the right',
                    run: (cell) => this.duplicateTableColumn(cell)
                },
                {
                    id: 'duplicate-row',
                    label: 'duplicate row',
                    hint: 'Duplicate current row below',
                    run: (cell) => this.duplicateTableRow(cell)
                },
                {
                    id: 'move-row-up',
                    label: 'move row up',
                    hint: 'Move current row one step up',
                    run: (cell) => this.moveTableRow(cell, 'up')
                },
                {
                    id: 'move-row-down',
                    label: 'move row down',
                    hint: 'Move current row one step down',
                    run: (cell) => this.moveTableRow(cell, 'down')
                },
                {
                    id: 'merge-selected-cells',
                    label: 'merge selected cells',
                    hint: 'Merge Alt-selected cell range',
                    run: () => this.mergeSelectedTableCells()
                },
                {
                    id: 'clear-selected-cells',
                    label: 'clear selected cells',
                    hint: 'Clear Alt-selection highlight',
                    run: () => this.clearTableSelection()
                },
                {
                    id: 'merge-right',
                    label: 'merge with right cell',
                    hint: 'Merge current cell with right cell',
                    run: (cell) => this.mergeCellWithRight(cell)
                },
                {
                    id: 'merge-below',
                    label: 'merge with cell below',
                    hint: 'Merge current cell with cell below',
                    run: (cell) => this.mergeCellWithBelow(cell)
                },
                {
                    id: 'split-merged-cell',
                    label: 'split merged cell',
                    hint: 'Split merged cell into normal cells',
                    run: (cell) => this.splitMergedCell(cell)
                },
                {
                    id: 'duplicate-right',
                    label: 'duplicate right',
                    hint: 'Duplicate current cell to the right',
                    run: (cell) => this.duplicateCellContent(cell, 'right')
                },
                {
                    id: 'duplicate-below',
                    label: 'duplicate below',
                    hint: 'Duplicate current cell below',
                    run: (cell) => this.duplicateCellContent(cell, 'below')
                },
                {
                    id: 'cell-background-color',
                    label: 'cell background color',
                    hint: 'Pick cell background color',
                    run: (cell) => this.pickTableCellColor(cell)
                },
                {
                    id: 'cell-background-hex',
                    label: 'cell background hex',
                    hint: 'Set cell background using hex',
                    run: (cell) => this.promptTableCellColor(cell)
                },
                {
                    id: 'clear-cell-background',
                    label: 'clear cell background',
                    hint: 'Remove cell background color',
                    run: (cell) => this.clearTableCellColor(cell)
                },
                {
                    id: 'column-width-plus',
                    label: 'column width +',
                    hint: 'Increase column width',
                    run: (cell) => this.adjustTableColumnWidth(cell, 20)
                },
                {
                    id: 'column-width-minus',
                    label: 'column width -',
                    hint: 'Decrease column width',
                    run: (cell) => this.adjustTableColumnWidth(cell, -20)
                },
                {
                    id: 'cell-padding-plus',
                    label: 'cell padding +',
                    hint: 'Increase cell padding',
                    run: (cell) => this.adjustTableCellPadding(cell, 2)
                },
                {
                    id: 'cell-padding-minus',
                    label: 'cell padding -',
                    hint: 'Decrease cell padding',
                    run: (cell) => this.adjustTableCellPadding(cell, -2)
                },
                {
                    id: 'row-background-color',
                    label: 'row background color',
                    hint: 'Pick row background color',
                    run: (cell) => this.pickTableRowColor(cell)
                },
                {
                    id: 'column-background-color',
                    label: 'column background color',
                    hint: 'Pick column background color',
                    run: (cell) => this.pickTableColumnColor(cell)
                },
                {
                    id: 'table-background-color',
                    label: 'table background color',
                    hint: 'Pick whole table background color',
                    run: (cell) => this.pickWholeTableColor(cell)
                },
                {
                    id: 'text-align-left',
                    label: 'text align left',
                    hint: 'Align cell text left',
                    run: (cell) => this.setTableCellTextAlign(cell, 'left')
                },
                {
                    id: 'text-align-center',
                    label: 'text align center',
                    hint: 'Align cell text center',
                    run: (cell) => this.setTableCellTextAlign(cell, 'center')
                },
                {
                    id: 'text-align-right',
                    label: 'text align right',
                    hint: 'Align cell text right',
                    run: (cell) => this.setTableCellTextAlign(cell, 'right')
                },
                {
                    id: 'align-row-center',
                    label: 'align whole row center',
                    hint: 'Center text for full row',
                    run: (cell) => this.setTableRowTextAlign(cell, 'center')
                },
                {
                    id: 'align-column-center',
                    label: 'align whole column center',
                    hint: 'Center text for full column',
                    run: (cell) => this.setTableColumnTextAlign(cell, 'center')
                },
                {
                    id: 'vertical-align-top',
                    label: 'vertical align top',
                    hint: 'Set vertical alignment to top',
                    run: (cell) => this.setTableCellVerticalAlign(cell, 'top')
                },
                {
                    id: 'vertical-align-middle',
                    label: 'vertical align middle',
                    hint: 'Set vertical alignment to middle',
                    run: (cell) => this.setTableCellVerticalAlign(cell, 'middle')
                },
                {
                    id: 'vertical-align-bottom',
                    label: 'vertical align bottom',
                    hint: 'Set vertical alignment to bottom',
                    run: (cell) => this.setTableCellVerticalAlign(cell, 'bottom')
                },
                {
                    id: 'border-color',
                    label: 'border color',
                    hint: 'Pick border color',
                    run: (cell) => this.pickTableBorderColor(cell)
                },
                {
                    id: 'border-width-plus',
                    label: 'border width +',
                    hint: 'Increase border width',
                    run: (cell) => this.adjustTableAllBorders(cell, 1)
                },
                {
                    id: 'border-width-minus',
                    label: 'border width -',
                    hint: 'Decrease border width',
                    run: (cell) => this.adjustTableAllBorders(cell, -1)
                },
                {
                    id: 'distribute-columns-evenly',
                    label: 'distribute columns evenly',
                    hint: 'Make all columns equal width',
                    run: (cell) => this.distributeTableColumns(cell)
                },
                {
                    id: 'table-layout-fixed',
                    label: 'table layout fixed',
                    hint: 'Set fixed table layout',
                    run: (cell) => this.setTableLayoutFixed(cell, true)
                },
                {
                    id: 'table-layout-auto',
                    label: 'table layout auto',
                    hint: 'Set automatic table layout',
                    run: (cell) => this.setTableLayoutFixed(cell, false)
                },
                {
                    id: 'delete-table',
                    label: 'delete table',
                    hint: 'Remove entire table block',
                    run: (cell) => this.deleteTable(cell)
                }
            ];
        },
        getFilteredTableSlashCommands() {
            const q = String(this.tableSlashQuery || '').trim().toLowerCase();
            const list = this.getTableSlashCommands();
            if (!q) return list;

            const terms = q.split(/\s+/).filter(Boolean);
            return list.filter((cmd) => {
                const haystack = [cmd.label, cmd.hint, cmd.id]
                    .map((v) => String(v || '').toLowerCase())
                    .join(' ');
                return terms.every((term) => haystack.includes(term));
            });
        },
        openTableSlashHelper(cell, initialQuery = '') {
            if (!cell) return;
            this.tableSlashCell = cell;
            this.tableSlashQuery = String(initialQuery || '');
            this.showTableSlashModal = true;
            this.$nextTick(() => {
                if (this.$refs.tableSlashInputRef) this.$refs.tableSlashInputRef.focus();
            });
        },
        closeTableSlashHelper() {
            this.showTableSlashModal = false;
            this.tableSlashCell = null;
            this.tableSlashQuery = '';
        },
        rememberActiveTableCell(target) {
            const cell = this.getEditorTableCellFromTarget(target);
            if (!cell) return;
            this._tableActiveCell = cell;
        },
        getActiveTableCell() {
            if (this._tableActiveCell && document.body.contains(this._tableActiveCell)) return this._tableActiveCell;
            const active = this.getEditorTableCellFromTarget(document.activeElement);
            return active || null;
        },
        getNativeTableMenuType(menuEl) {
            if (!menuEl || !menuEl.textContent || !menuEl.querySelectorAll) return null;
            const cls = String(menuEl.className || '').toLowerCase();
            if (!cls.includes('popover')) return null;
            const actions = this.getNativeMenuActionNodes(menuEl);
            if (actions.length < 3 || actions.length > 20) return null;
            const labels = actions.map((el) => String(el.textContent || '').trim().toLowerCase());
            const isRowMenu = labels.some((t) => t.includes('add row above'))
                && labels.some((t) => t.includes('add row below'))
                && labels.some((t) => t.includes('delete row'));
            const isColumnMenu = labels.some((t) => t.includes('add column left'))
                && labels.some((t) => t.includes('add column right'))
                && labels.some((t) => t.includes('delete column'));
            if (isRowMenu) return 'row';
            if (isColumnMenu) return 'column';
            return null;
        },
        getNativeMenuActionNodes(menuEl) {
            if (!menuEl) return [];
            const candidates = Array.from(menuEl.querySelectorAll(':scope > button, :scope > [role="button"], :scope > .ce-popover-item, :scope > .tc-popover__item, :scope > li'));
            return candidates.filter((el) => {
                const t = String(el.textContent || '').trim().toLowerCase();
                if (!t) return false;
                const cls = String(el.className || '').toLowerCase();
                const role = String(el.getAttribute && el.getAttribute('role') || '').toLowerCase();
                const tag = String(el.tagName || '').toLowerCase();
                return tag === 'button' || role === 'button' || cls.includes('popover') || tag === 'li';
            });
        },
        addNativeTableMenuAction(menuEl, actionId, label, onRun) {
            const existing = menuEl.querySelector(`[data-ld-native-table-action-id="${actionId}"]`);
            if (existing) return;

            const template = this.getNativeMenuActionNodes(menuEl)[0] || null;
            const tag = template && template.tagName ? template.tagName.toLowerCase() : 'button';
            const item = document.createElement(tag);
            if (tag === 'button') item.type = 'button';
            item.setAttribute('data-ld-native-table-action', '1');
            item.setAttribute('data-ld-native-table-action-id', actionId);
            if (template) {
                item.className = template.className || '';
                const role = template.getAttribute('role');
                if (role) item.setAttribute('role', role);
            } else {
                item.className = 'ce-popover-item';
            }

            const templateIcon = template
                ? (template.querySelector('.ce-popover-item__icon, .tc-popover__item-icon, svg') || null)
                : null;
            const icon = templateIcon ? templateIcon.cloneNode(true) : document.createElement('span');
            if (!templateIcon) {
                icon.className = 'ce-popover-item__icon';
                icon.textContent = '+';
                icon.style.marginRight = '8px';
            }

            const title = document.createElement('span');
            title.className = 'ce-popover-item__title';
            title.textContent = label;

            item.appendChild(icon);
            item.appendChild(title);
            const runAction = (event) => {
                event.preventDefault();
                event.stopPropagation();
                const cell = this.getActiveTableCell();
                if (!cell) return;
                onRun(cell);
            };
            item.addEventListener('mousedown', runAction);
            menuEl.appendChild(item);
        },
        removeStaleNativeTableActions() {
            const stale = document.querySelectorAll('[data-ld-native-table-action="1"]');
            stale.forEach((el) => {
                const hostMenu = el.closest('.ce-popover, .tc-popover');
                if (!hostMenu || !this.getNativeTableMenuType(hostMenu)) {
                    el.remove();
                }
            });
        },
        closeNativeTableMenus() {
            const openMenus = Array.from(document.querySelectorAll('.ce-popover, .tc-popover'));
            openMenus.forEach((menuEl) => {
                if (!this.getNativeTableMenuType(menuEl)) return;
                menuEl.remove();
            });
            this.removeStaleNativeTableActions();
        },
        enhanceNativeTableDotsMenu(menuEl) {
            const menuType = this.getNativeTableMenuType(menuEl);
            if (!menuType) return;
            const stale = menuEl.querySelectorAll('[data-ld-native-table-action="1"]');
            stale.forEach((el) => el.remove());
            if (menuType === 'row') {
                this.addNativeTableMenuAction(menuEl, 'duplicate-row', 'Duplicate row', (cell) => this.duplicateTableRow(cell));
                this.addNativeTableMenuAction(menuEl, 'move-row-up', 'Move row up', (cell) => this.moveTableRow(cell, 'up'));
                this.addNativeTableMenuAction(menuEl, 'move-row-down', 'Move row down', (cell) => this.moveTableRow(cell, 'down'));
                this.addNativeTableMenuAction(menuEl, 'row-height', 'Row height...', (cell) => this.openTableNumberAdjustMenu(cell, {
                    title: 'Row height',
                    unit: 'px',
                    step: 10,
                    min: 24,
                    max: 600,
                    get: (refCell) => {
                        const pos = this.getTableCellPosition(refCell);
                        if (!pos) return 42;
                        const row = pos.rows[pos.rIdx] || [];
                        return this.getCellPixelSize(row[0], 'height', 42);
                    },
                    set: (refCell, value) => this.setTableRowHeight(refCell, value)
                }));
                this.addNativeTableMenuAction(menuEl, 'row-background-color', 'Row background color...', (cell) => this.pickTableRowColor(cell));
                this.addNativeTableMenuAction(menuEl, 'row-background-hex', 'Row background hex...', (cell) => this.promptTableRowColor(cell));
            } else if (menuType === 'column') {
                this.addNativeTableMenuAction(menuEl, 'duplicate-column', 'Duplicate column', (cell) => this.duplicateTableColumn(cell));
                this.addNativeTableMenuAction(menuEl, 'column-background-color', 'Column background color...', (cell) => this.pickTableColumnColor(cell));
            }
        },
        ensureNativeTableMenuEnhancer() {
            if (this._nativeTableMenuObserver) return;
            this.removeStaleNativeTableActions();
            this._nativeTableMenuObserver = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if (!node || node.nodeType !== 1) return;
                        const el = node;
                        if (this.getNativeTableMenuType(el)) this.enhanceNativeTableDotsMenu(el);
                        const nestedMenus = Array.from(el.querySelectorAll ? el.querySelectorAll('.ce-popover, .tc-popover') : []);
                        nestedMenus.forEach((menu) => this.enhanceNativeTableDotsMenu(menu));
                    });
                });
            });
            this._nativeTableMenuObserver.observe(document.body, { childList: true, subtree: true });
        },
        runTableSlashCommand(commandId = '') {
            const cell = this.tableSlashCell;
            if (!cell) {
                this.closeTableSlashHelper();
                return;
            }
            const id = String(commandId || '').trim().toLowerCase();
            const filtered = this.getFilteredTableSlashCommands();
            const cmd = id
                ? filtered.find((entry) => entry.id === id || entry.label === id)
                : filtered[0];
            if (!cmd || typeof cmd.run !== 'function') return;
            this.closeTableSlashHelper();
            cmd.run(cell);
        },
        bindWordLikeTableBehavior() {
            const holder = document.getElementById('editorjs');
            if (!holder) return;
            this.unbindWordLikeTableBehavior();

            this._editorInteractionHandler = (event) => {
                if (!event || !event.target || !holder.contains(event.target)) return;
                this.editorUserInteracted = true;
            };

            this._tableWordKeydownHandler = (event) => {
                const key = String(event.key || '').toLowerCase();
                const hasCtrl = !!(event.ctrlKey || event.metaKey);
                this.rememberActiveTableCell(event.target);
                if (this.showTableSlashModal) {
                    if (key === 'escape') {
                        event.preventDefault();
                        this.closeTableSlashHelper();
                    }
                    return;
                }
                if (hasCtrl && !event.altKey && key === 'z') {
                    event.preventDefault();
                    if (event.shiftKey) this.redoEditor();
                    else this.undoEditor();
                    return;
                }
                if (hasCtrl && !event.altKey && key === 'y') {
                    event.preventDefault();
                    this.redoEditor();
                    return;
                }
                if (key === 'escape') {
                    this.clearTableSelection();
                    return;
                }
                const cell = this.getEditorTableCellFromTarget(event.target);
                if (!hasCtrl && !event.altKey && event.key === '/' && cell) {
                    event.preventDefault();
                    this.openTableSlashHelper(cell);
                    return;
                }
                if (event.key !== 'Tab') return;
                if (!cell) return;
                event.preventDefault();
                const moved = this.focusAdjacentTableCell(cell, event.shiftKey ? -1 : 1);
                if (!moved && !event.shiftKey) {
                    this.insertTableRow(cell, 'below');
                    const pos = this.getTableCellPosition(cell);
                    if (pos) {
                        const nextRows = this.getEditorTableRows(pos.tableEl);
                        const nextRow = nextRows[pos.rIdx + 1];
                        const target = nextRow && nextRow[pos.cIdx] ? nextRow[pos.cIdx] : null;
                        if (target) this.focusTableCell(target);
                    }
                }
                this.markTableChanged();
            };

            this._tableContextMenuHandler = (event) => {
                this.rememberActiveTableCell(event.target);
                const cell = this.getEditorTableCellFromTarget(event.target);
                if (!cell) return;
                event.preventDefault();
                this.openTableContextMenu(cell, event.clientX, event.clientY);
            };

            this._tableContextMenuOutsideHandler = (event) => {
                const hasContextMenu = !!this._tableContextMenuEl;
                const hasNumberAdjust = !!this._tableNumberAdjustEl;
                if (!hasContextMenu && !hasNumberAdjust) return;
                const inNativeMenu = !!(event.target && event.target.closest && event.target.closest('.ce-popover, .tc-popover'));
                if (inNativeMenu) return;
                if (hasContextMenu && this._tableContextMenuEl.contains(event.target)) return;
                if (hasNumberAdjust && this._tableNumberAdjustEl.contains(event.target)) return;
                this.closeTableContextMenu();
                this.closeTableNumberAdjustMenu();
                this.closeNativeTableMenus();
            };

            this._tableCellSelectStartHandler = (event) => {
                if (event.button !== 0 || !event.altKey) return;
                const cell = this.getEditorTableCellFromTarget(event.target);
                if (!cell) return;
                event.preventDefault();
                this._isSelectingTableRange = true;
                this._tableSelectionAnchorCell = cell;
                this.selectTableRange(cell, cell);
            };

            this._tableCellSelectMoveHandler = (event) => {
                if (!this._isSelectingTableRange || !this._tableSelectionAnchorCell) return;
                const cell = this.getEditorTableCellFromTarget(event.target);
                if (!cell) return;
                this.selectTableRange(this._tableSelectionAnchorCell, cell);
            };

            this._tableCellSelectEndHandler = () => {
                this._isSelectingTableRange = false;
                this._tableSelectionAnchorCell = null;
            };

            this._tableInputChangeHandler = (event) => {
                this.rememberActiveTableCell(event.target);
                const target = event.target;
                if (!target || !target.matches) return;
                if (!target.matches('.ld-table-radio, .ld-table-checkbox')) return;
                if (!this.getEditorTableCellFromTarget(target)) return;
                this.markTableChanged();
            };
            this._tableTrackCellHandler = (event) => this.rememberActiveTableCell(event.target);

            holder.addEventListener('keydown', this._tableWordKeydownHandler, true);
            holder.addEventListener('contextmenu', this._tableContextMenuHandler);
            holder.addEventListener('mousedown', this._tableCellSelectStartHandler);
            holder.addEventListener('mouseover', this._tableTrackCellHandler);
            holder.addEventListener('focusin', this._tableTrackCellHandler);
            holder.addEventListener('mouseover', this._tableCellSelectMoveHandler);
            holder.addEventListener('change', this._tableInputChangeHandler, true);
            holder.addEventListener('keydown', this._editorInteractionHandler, true);
            holder.addEventListener('mousedown', this._editorInteractionHandler, true);
            holder.addEventListener('input', this._editorInteractionHandler, true);
            holder.addEventListener('paste', this._editorInteractionHandler, true);
            document.addEventListener('mousedown', this._tableContextMenuOutsideHandler);
            document.addEventListener('mouseup', this._tableCellSelectEndHandler);
            this.ensureNativeTableMenuEnhancer();
        },
        unbindWordLikeTableBehavior() {
            const holder = document.getElementById('editorjs');
            if (holder && this._tableWordKeydownHandler) {
                holder.removeEventListener('keydown', this._tableWordKeydownHandler, true);
            }
            if (holder && this._tableContextMenuHandler) {
                holder.removeEventListener('contextmenu', this._tableContextMenuHandler);
            }
            if (holder && this._tableCellSelectStartHandler) {
                holder.removeEventListener('mousedown', this._tableCellSelectStartHandler);
            }
            if (holder && this._tableTrackCellHandler) {
                holder.removeEventListener('mouseover', this._tableTrackCellHandler);
                holder.removeEventListener('focusin', this._tableTrackCellHandler);
            }
            if (holder && this._tableCellSelectMoveHandler) {
                holder.removeEventListener('mouseover', this._tableCellSelectMoveHandler);
            }
            if (holder && this._tableInputChangeHandler) {
                holder.removeEventListener('change', this._tableInputChangeHandler, true);
            }
            if (holder && this._editorInteractionHandler) {
                holder.removeEventListener('keydown', this._editorInteractionHandler, true);
                holder.removeEventListener('mousedown', this._editorInteractionHandler, true);
                holder.removeEventListener('input', this._editorInteractionHandler, true);
                holder.removeEventListener('paste', this._editorInteractionHandler, true);
            }
            if (this._tableContextMenuOutsideHandler) {
                document.removeEventListener('mousedown', this._tableContextMenuOutsideHandler);
            }
            if (this._tableCellSelectEndHandler) {
                document.removeEventListener('mouseup', this._tableCellSelectEndHandler);
            }
            if (this._nativeTableMenuObserver) {
                this._nativeTableMenuObserver.disconnect();
                this._nativeTableMenuObserver = null;
            }
            this._tableWordKeydownHandler = null;
            this._tableContextMenuHandler = null;
            this._tableContextMenuOutsideHandler = null;
            this._tableCellSelectStartHandler = null;
            this._tableCellSelectMoveHandler = null;
            this._tableCellSelectEndHandler = null;
            this._tableInputChangeHandler = null;
            this._editorInteractionHandler = null;
            this._tableColorPickHandler = null;
            this._tableTrackCellHandler = null;
            this._tableActiveCell = null;
            this._isSelectingTableRange = false;
            this._tableSelectionAnchorCell = null;
            this.closeTableSlashHelper();
            this.clearTableSelection();
            this.closeTableContextMenu();
            this.closeTableNumberAdjustMenu();
            this.closeNativeTableMenus();
        },
        getEditorTableCellFromTarget(target) {
            const holder = document.getElementById('editorjs');
            if (!holder || !target || !target.closest) return null;
            const cell = target.closest('.tc-cell, td, th');
            if (!cell || !holder.contains(cell)) return null;
            if (!cell.closest('.tc-table, table')) return null;
            return cell;
        },
        getEditorTableRows(tableEl) {
            if (!tableEl) return [];
            const tcRows = Array.from(tableEl.querySelectorAll('.tc-row'));
            if (tcRows.length > 0) {
                return tcRows.map((row) => Array.from(row.querySelectorAll('.tc-cell')));
            }
            const trRows = Array.from(tableEl.querySelectorAll('tr'));
            return trRows.map((row) => Array.from(row.querySelectorAll('td, th')));
        },
        getTableCellPosition(cell) {
            const tableEl = cell ? cell.closest('.tc-table, table') : null;
            if (!tableEl) return null;
            const rows = this.getEditorTableRows(tableEl);
            for (let rIdx = 0; rIdx < rows.length; rIdx++) {
                const cIdx = rows[rIdx].indexOf(cell);
                if (cIdx !== -1) return { tableEl, rows, rIdx, cIdx };
            }
            return null;
        },
        focusTableCell(cell) {
            if (!cell) return;
            cell.focus();
            const selection = window.getSelection();
            if (!selection) return;
            const range = document.createRange();
            range.selectNodeContents(cell);
            range.collapse(false);
            selection.removeAllRanges();
            selection.addRange(range);
        },
        focusAdjacentTableCell(currentCell, step) {
            const pos = this.getTableCellPosition(currentCell);
            if (!pos) return false;
            const flat = [];
            pos.rows.forEach((row) => row.forEach((c) => flat.push(c)));
            const idx = flat.indexOf(currentCell);
            if (idx === -1) return false;
            const next = flat[idx + step];
            if (!next) return false;
            this.focusTableCell(next);
            return true;
        },
        markTableChanged() {
            if (this._isRestoringHistory) return;
            this.editorUserInteracted = true;
            this.needsSave = true;
            this.autoSave();
            this.queueHistorySnapshot();
        },
        ensureTableSelectionStyles() {
            if (this._tableSelectionStyleEl) return;
            const style = document.createElement('style');
            style.textContent = `
                #editorjs .ld-table-selected {
                    box-shadow: inset 0 0 0 2px #38bdf8;
                    background-color: rgba(56, 189, 248, 0.18) !important;
                }
            `;
            document.head.appendChild(style);
            this._tableSelectionStyleEl = style;
        },
        isTableCellSelected(cell) {
            return !!(cell && cell.classList && cell.classList.contains('ld-table-selected'));
        },
        clearTableSelection() {
            const selected = document.querySelectorAll('#editorjs .ld-table-selected');
            selected.forEach((cell) => cell.classList.remove('ld-table-selected'));
        },
        getSelectedTableCells() {
            return Array.from(document.querySelectorAll('#editorjs .ld-table-selected'));
        },
        selectTableRange(anchorCell, targetCell) {
            const anchor = this.getTableCellPosition(anchorCell);
            const target = this.getTableCellPosition(targetCell);
            if (!anchor || !target) return;
            if (anchor.tableEl !== target.tableEl) return;
            this.ensureTableSelectionStyles();
            this.clearTableSelection();
            const minR = Math.min(anchor.rIdx, target.rIdx);
            const maxR = Math.max(anchor.rIdx, target.rIdx);
            const minC = Math.min(anchor.cIdx, target.cIdx);
            const maxC = Math.max(anchor.cIdx, target.cIdx);
            for (let r = minR; r <= maxR; r++) {
                const row = anchor.rows[r] || [];
                for (let c = minC; c <= maxC; c++) {
                    const cell = row[c];
                    if (cell) cell.classList.add('ld-table-selected');
                }
            }
        },
        mergeSelectedTableCells() {
            const selected = this.getSelectedTableCells();
            if (selected.length < 2) {
                this.showAlert('Merge Cells', 'Selecteer minimaal 2 cellen met Alt + slepen.');
                return;
            }

            const positions = selected
                .map((cell) => ({ cell, pos: this.getTableCellPosition(cell) }))
                .filter((entry) => entry.pos);
            if (positions.length !== selected.length) {
                this.showAlert('Merge Cells', 'Kon de geselecteerde cellen niet lezen.');
                return;
            }
            const tableEl = positions[0].pos.tableEl;
            if (positions.some((entry) => entry.pos.tableEl !== tableEl)) {
                this.showAlert('Merge Cells', 'Selecteer cellen binnen dezelfde tabel.');
                return;
            }
            if (positions.some((entry) => {
                const colspan = parseInt(entry.cell.getAttribute('colspan') || '1', 10);
                const rowspan = parseInt(entry.cell.getAttribute('rowspan') || '1', 10);
                return colspan > 1 || rowspan > 1;
            })) {
                this.showAlert('Merge Cells', 'Split eerst bestaande merged cells in deze selectie.');
                return;
            }

            const minR = Math.min(...positions.map((entry) => entry.pos.rIdx));
            const maxR = Math.max(...positions.map((entry) => entry.pos.rIdx));
            const minC = Math.min(...positions.map((entry) => entry.pos.cIdx));
            const maxC = Math.max(...positions.map((entry) => entry.pos.cIdx));
            const expectedCount = (maxR - minR + 1) * (maxC - minC + 1);
            if (selected.length !== expectedCount) {
                this.showAlert('Merge Cells', 'Selecteer een aaneengesloten rechthoek van cellen.');
                return;
            }

            const rows = this.getEditorTableRows(tableEl);
            const selectedSet = new Set(selected);
            for (let r = minR; r <= maxR; r++) {
                for (let c = minC; c <= maxC; c++) {
                    const cell = rows[r] && rows[r][c];
                    if (!cell || !selectedSet.has(cell)) {
                        this.showAlert('Merge Cells', 'Selecteer een aaneengesloten rechthoek van cellen.');
                        return;
                    }
                }
            }

            const origin = rows[minR] && rows[minR][minC];
            if (!origin) return;
            const mergedContent = [];
            for (let r = minR; r <= maxR; r++) {
                for (let c = minC; c <= maxC; c++) {
                    const cell = rows[r][c];
                    const html = String(cell.innerHTML || '').trim();
                    if (html && html !== '<br>') mergedContent.push(html);
                }
            }

            origin.innerHTML = mergedContent.length > 0 ? mergedContent.join('<br>') : '<br>';
            const colspan = maxC - minC + 1;
            const rowspan = maxR - minR + 1;
            if (colspan > 1) origin.setAttribute('colspan', String(colspan));
            else origin.removeAttribute('colspan');
            if (rowspan > 1) origin.setAttribute('rowspan', String(rowspan));
            else origin.removeAttribute('rowspan');

            for (let r = minR; r <= maxR; r++) {
                for (let c = minC; c <= maxC; c++) {
                    const cell = rows[r][c];
                    if (cell !== origin && cell.parentNode) {
                        cell.parentNode.removeChild(cell);
                    }
                }
            }

            this.clearTableSelection();
            this.focusTableCell(origin);
            this.markTableChanged();
        },
        ensureTableColorInput() {
            if (this._tableColorInput) return this._tableColorInput;
            const input = document.createElement('input');
            input.type = 'color';
            input.style.position = 'fixed';
            input.style.left = '8px';
            input.style.top = '8px';
            input.style.width = '1px';
            input.style.height = '1px';
            input.style.opacity = '0';
            input.style.pointerEvents = 'none';
            input.style.zIndex = '-1';
            const emitPickedColor = () => {
                if (typeof this._tableColorPickHandler === 'function') {
                    this._tableColorPickHandler(input.value);
                }
            };
            input.addEventListener('input', emitPickedColor);
            input.addEventListener('change', emitPickedColor);
            document.body.appendChild(input);
            this._tableColorInput = input;
            return input;
        },
        pickColor(initialHex, onPick) {
            const input = this.ensureTableColorInput();
            this._tableColorPickHandler = onPick;
            input.value = this.toHexColor(initialHex || '#ffffff');
            try {
                if (typeof input.showPicker === 'function') {
                    input.showPicker();
                } else {
                    input.click();
                }
            } catch (e) {
                input.click();
            }
        },
        pickTableCellColor(cell) {
            if (!cell) return;
            this.pickColor(cell.style.backgroundColor || '#ffffff', (hex) => {
                this.setCellBackgroundColor(cell, hex);
                this.markTableChanged();
            });
        },
        setCellBackgroundColor(cell, color) {
            if (!cell) return;
            cell.style.setProperty('background-color', color, 'important');
            cell.style.setProperty('background', color, 'important');
        },
        normalizeHexColor(value) {
            const raw = String(value || '').trim();
            if (!raw) return null;
            const withHash = raw.startsWith('#') ? raw : `#${raw}`;
            if (!/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(withHash)) return null;
            if (withHash.length === 4) {
                return `#${withHash[1]}${withHash[1]}${withHash[2]}${withHash[2]}${withHash[3]}${withHash[3]}`.toLowerCase();
            }
            return withHash.toLowerCase();
        },
        promptTableCellColor(cell) {
            if (!cell) return;
            this.showPrompt('Cell color (hex)', 'Hex color', (val) => {
                const hex = this.normalizeHexColor(val);
                if (!hex) {
                    this.showAlert('Invalid color', 'Use a hex value like #ffcc00 or ffcc00.');
                    return;
                }
                this.setCellBackgroundColor(cell, hex);
                this.markTableChanged();
            });
        },
        clearTableCellColor(cell) {
            if (!cell) return;
            cell.style.removeProperty('background');
            cell.style.removeProperty('background-color');
            this.markTableChanged();
        },
        escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },
        serializeTableCellRichHtml(cell) {
            if (!cell) return '';
            const clone = cell.cloneNode(true);
            const inputs = Array.from(clone.querySelectorAll('.ld-table-checkbox, .ld-table-radio'));
            inputs.forEach((input) => {
                if (input && input.removeAttribute) {
                    input.removeAttribute('checked');
                }
                if ('checked' in input) {
                    input.checked = false;
                }
            });
            return String(clone.innerHTML || '').trim();
        },
        openTableRadioBuilder(cell) {
            if (!cell) return;
            this.radioBuilderCell = cell;
            this.radioBuilderForm = {
                title: '',
                optionsText: 'Option 1\nOption 2'
            };
            this.showRadioBuilderModal = true;
            this.$nextTick(() => {
                if (this.$refs.radioOptionsInputRef) this.$refs.radioOptionsInputRef.focus();
            });
        },
        cancelTableRadioBuilder() {
            this.showRadioBuilderModal = false;
            this.radioBuilderCell = null;
            this.radioBuilderForm = { title: '', optionsText: '' };
        },
        submitTableRadioBuilder() {
            const cell = this.radioBuilderCell;
            if (!cell) {
                this.cancelTableRadioBuilder();
                return;
            }

            const lines = String(this.radioBuilderForm.optionsText || '')
                .split(/\r?\n/)
                .map(v => v.trim())
                .filter(Boolean);
            if (lines.length === 0) {
                this.showAlert('Radio buttons', 'Add at least one option.');
                return;
            }

            const groupName = `ld-radio-${Date.now()}-${Math.floor(Math.random() * 100000)}`;
            const title = String(this.radioBuilderForm.title || '').trim();
            const titleHtml = title
                ? `<div class="ld-table-radio-title">${this.escapeHtml(title)}</div>`
                : '';
            const optionsHtml = lines.map((label, idx) => (
                `<label class="ld-table-radio-row"><input type="radio" class="ld-table-radio" name="${groupName}" value="${idx}"><span>${this.escapeHtml(label)}</span></label>`
            )).join('');
            const groupHtml = `<div class="ld-table-radio-group">${titleHtml}${optionsHtml}</div>`;

            const current = String(cell.innerHTML || '').trim();
            if (!current || current === '<br>') {
                cell.innerHTML = groupHtml;
            } else {
                cell.innerHTML = `${current}<div><br></div>${groupHtml}`;
            }

            this.cancelTableRadioBuilder();
            this.markTableChanged();
        },
        removeTableRadioGroups(cell) {
            if (!cell) return;
            const wrapper = document.createElement('div');
            wrapper.innerHTML = String(cell.innerHTML || '');
            const groups = Array.from(wrapper.querySelectorAll('.ld-table-radio-group'));
            if (groups.length === 0) {
                this.showAlert('Radio buttons', 'No radio group found in this cell.');
                return;
            }
            groups.forEach((group) => {
                const prev = group.previousElementSibling;
                group.remove();
                if (
                    prev &&
                    prev.tagName === 'DIV' &&
                    String(prev.innerHTML || '').trim().toLowerCase() === '<br>'
                ) {
                    prev.remove();
                }
            });
            const cleaned = wrapper.innerHTML.trim();
            cell.innerHTML = cleaned ? cleaned : '<br>';
            this.markTableChanged();
        },
        getCellPixelSize(cell, axis, fallback) {
            if (!cell) return fallback;
            const styled = parseFloat(axis === 'width' ? cell.style.width : cell.style.height);
            if (Number.isFinite(styled) && styled > 0) return styled;
            const rect = cell.getBoundingClientRect();
            const measured = axis === 'width' ? rect.width : rect.height;
            return Number.isFinite(measured) && measured > 0 ? measured : fallback;
        },
        getCellPadding(cell) {
            if (!cell) return 8;
            const styled = parseFloat(cell.style.padding);
            if (Number.isFinite(styled) && styled >= 0) return styled;
            const computed = window.getComputedStyle(cell);
            const top = parseFloat(computed.paddingTop);
            return Number.isFinite(top) && top >= 0 ? top : 8;
        },
        toHexColor(color) {
            if (!color) return '#ffffff';
            if (color.startsWith('#')) {
                if (color.length === 4) {
                    return `#${color[1]}${color[1]}${color[2]}${color[2]}${color[3]}${color[3]}`;
                }
                return color;
            }
            const m = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
            if (!m) return '#ffffff';
            const r = Number(m[1]).toString(16).padStart(2, '0');
            const g = Number(m[2]).toString(16).padStart(2, '0');
            const b = Number(m[3]).toString(16).padStart(2, '0');
            return `#${r}${g}${b}`;
        },
        adjustTableColumnWidth(referenceCell, delta) {
            const pos = this.getTableCellPosition(referenceCell);
            if (!pos) return;
            const current = this.getCellPixelSize(pos.rows[0] && pos.rows[0][pos.cIdx], 'width', 140);
            const next = Math.max(60, Math.min(1200, current + delta));
            this.setTableColumnWidth(referenceCell, next);
        },
        setTableColumnWidth(referenceCell, width) {
            const pos = this.getTableCellPosition(referenceCell);
            if (!pos) return;
            const next = Math.max(60, Math.min(1200, Number(width) || 140));
            pos.rows.forEach((row) => {
                const cell = row[pos.cIdx];
                if (!cell) return;
                cell.style.width = `${next}px`;
                cell.style.minWidth = `${next}px`;
                cell.style.maxWidth = `${next}px`;
            });
            this.markTableChanged();
        },
        adjustTableRowHeight(referenceCell, delta) {
            const pos = this.getTableCellPosition(referenceCell);
            if (!pos) return;
            const row = pos.rows[pos.rIdx] || [];
            const current = this.getCellPixelSize(row[0], 'height', 42);
            const next = Math.max(24, Math.min(600, current + delta));
            this.setTableRowHeight(referenceCell, next);
        },
        setTableRowHeight(referenceCell, height) {
            const pos = this.getTableCellPosition(referenceCell);
            if (!pos) return;
            const row = pos.rows[pos.rIdx] || [];
            const next = Math.max(24, Math.min(600, Number(height) || 42));
            row.forEach((cell) => {
                if (!cell) return;
                cell.style.height = `${next}px`;
                cell.style.minHeight = `${next}px`;
            });
            this.markTableChanged();
        },
        adjustTableCellPadding(referenceCell, delta) {
            if (!referenceCell) return;
            const current = this.getCellPadding(referenceCell);
            const next = Math.max(0, Math.min(40, current + delta));
            this.setTableCellPadding(referenceCell, next);
        },
        setTableCellPadding(referenceCell, value) {
            if (!referenceCell) return;
            const next = Math.max(0, Math.min(40, Number(value) || 0));
            referenceCell.style.padding = `${next}px`;
            this.markTableChanged();
        },
        getTableBorderWidth(referenceCell) {
            const pos = this.getTableCellPosition(referenceCell);
            if (!pos || !pos.rows[0] || !pos.rows[0][0]) return 1;
            const sample = pos.rows[0][0];
            const styled = parseFloat(sample.style.borderWidth || '1');
            if (Number.isFinite(styled) && styled >= 0) return styled;
            const computed = parseFloat(window.getComputedStyle(sample).borderWidth || '1');
            return Number.isFinite(computed) && computed >= 0 ? computed : 1;
        },
        setTableBorderWidth(referenceCell, width) {
            const pos = this.getTableCellPosition(referenceCell);
            if (!pos || !pos.rows[0] || !pos.rows[0][0]) return;
            const sample = pos.rows[0][0];
            const next = Math.max(0, Math.min(12, Number(width) || 1));
            const color = sample.style.borderColor || '#334155';
            this.setTableAllBorders(referenceCell, next, color);
        },
        ensureTableNumberAdjustMenu() {
            if (this._tableNumberAdjustEl) return this._tableNumberAdjustEl;
            const wrap = document.createElement('div');
            wrap.style.position = 'fixed';
            wrap.style.zIndex = '10000';
            wrap.style.display = 'none';
            wrap.style.background = '#0f172a';
            wrap.style.border = '1px solid #334155';
            wrap.style.borderRadius = '8px';
            wrap.style.boxShadow = '0 12px 30px rgba(0,0,0,0.35)';
            wrap.style.padding = '10px';
            wrap.style.minWidth = '240px';

            const title = document.createElement('div');
            title.style.fontSize = '11px';
            title.style.fontWeight = '700';
            title.style.color = '#cbd5e1';
            title.style.marginBottom = '8px';

            const row = document.createElement('div');
            row.style.display = 'flex';
            row.style.gap = '8px';
            row.style.alignItems = 'center';

            const mkBtn = (txt) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.textContent = txt;
                b.style.width = '30px';
                b.style.height = '30px';
                b.style.background = '#1e293b';
                b.style.color = '#e2e8f0';
                b.style.border = '1px solid #334155';
                b.style.borderRadius = '6px';
                b.style.cursor = 'pointer';
                return b;
            };
            const minus = mkBtn('-');
            const plus = mkBtn('+');
            const input = document.createElement('input');
            input.type = 'number';
            input.step = '1';
            input.style.flex = '1';
            input.style.height = '30px';
            input.style.padding = '4px 8px';
            input.style.background = '#111827';
            input.style.color = '#e2e8f0';
            input.style.border = '1px solid #334155';
            input.style.borderRadius = '6px';
            input.style.outline = 'none';

            const unit = document.createElement('span');
            unit.style.fontSize = '11px';
            unit.style.color = '#94a3b8';
            unit.textContent = 'px';

            row.appendChild(minus);
            row.appendChild(input);
            row.appendChild(plus);
            row.appendChild(unit);
            wrap.appendChild(title);
            wrap.appendChild(row);
            document.body.appendChild(wrap);

            this._tableNumberAdjustEl = wrap;
            this._tableNumberAdjustTitleEl = title;
            this._tableNumberAdjustInputEl = input;
            this._tableNumberAdjustUnitEl = unit;
            this._tableNumberAdjustMinusEl = minus;
            this._tableNumberAdjustPlusEl = plus;
            return wrap;
        },
        closeTableNumberAdjustMenu() {
            if (!this._tableNumberAdjustEl) return;
            this._tableNumberAdjustEl.style.display = 'none';
            this._tableNumberAdjustConfig = null;
            this._tableNumberAdjustCell = null;
        },
        openTableNumberAdjustMenu(referenceCell, config) {
            if (!referenceCell || !config) return;
            const wrap = this.ensureTableNumberAdjustMenu();
            const title = this._tableNumberAdjustTitleEl;
            const input = this._tableNumberAdjustInputEl;
            const unit = this._tableNumberAdjustUnitEl;
            const minus = this._tableNumberAdjustMinusEl;
            const plus = this._tableNumberAdjustPlusEl;
            if (!wrap || !title || !input || !unit || !minus || !plus) return;

            this._tableNumberAdjustCell = referenceCell;
            this._tableNumberAdjustConfig = config;

            const clamp = (v) => Math.max(config.min, Math.min(config.max, v));
            const read = () => clamp(Number(config.get(referenceCell)));
            const apply = (raw) => {
                const num = Number(raw);
                if (!Number.isFinite(num)) return;
                const value = clamp(num);
                config.set(referenceCell, value);
                input.value = String(Math.round(value * 100) / 100);
            };

            title.textContent = config.title;
            unit.textContent = config.unit || 'px';
            input.step = String(config.step || 1);
            input.min = String(config.min);
            input.max = String(config.max);
            input.value = String(Math.round(read() * 100) / 100);

            minus.onclick = () => apply(read() - config.step);
            plus.onclick = () => apply(read() + config.step);
            input.onchange = () => apply(input.value);
            input.onkeydown = (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    apply(input.value);
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    this.closeTableNumberAdjustMenu();
                }
            };

            const x = Number.isFinite(this._tableContextMenuLastX) ? this._tableContextMenuLastX : Math.round(window.innerWidth * 0.5);
            const y = Number.isFinite(this._tableContextMenuLastY) ? this._tableContextMenuLastY : Math.round(window.innerHeight * 0.5);
            wrap.style.display = 'block';
            const rect = wrap.getBoundingClientRect();
            const maxX = Math.max(8, window.innerWidth - rect.width - 8);
            const maxY = Math.max(8, window.innerHeight - rect.height - 8);
            wrap.style.left = `${Math.max(8, Math.min(x, maxX))}px`;
            wrap.style.top = `${Math.max(8, Math.min(y, maxY))}px`;
            input.focus();
            input.select();
        },
        mergeCellWithRight(referenceCell) {
            const pos = this.getTableCellPosition(referenceCell);
            if (!pos) return;
            const row = pos.rows[pos.rIdx] || [];
            const rightCell = row[pos.cIdx + 1];
            if (!rightCell) return;

            const leftSpan = parseInt(referenceCell.getAttribute('colspan') || '1', 10);
            const rightSpan = parseInt(rightCell.getAttribute('colspan') || '1', 10);
            const newSpan = Math.max(1, leftSpan) + Math.max(1, rightSpan);
            referenceCell.setAttribute('colspan', String(newSpan));

            const rightHtml = (rightCell.innerHTML || '').trim();
            const leftHtml = (referenceCell.innerHTML || '').trim();
            if (rightHtml) {
                referenceCell.innerHTML = leftHtml ? `${leftHtml}<br>${rightHtml}` : rightHtml;
            }

            if (rightCell.parentNode) rightCell.parentNode.removeChild(rightCell);
            this.markTableChanged();
        },
        mergeCellWithBelow(referenceCell) {
            const pos = this.getTableCellPosition(referenceCell);
            if (!pos) return;
            const nextRow = pos.rows[pos.rIdx + 1];
            if (!nextRow) return;
            const belowCell = nextRow[pos.cIdx];
            if (!belowCell) return;

            const topSpan = parseInt(referenceCell.getAttribute('rowspan') || '1', 10);
            const belowSpan = parseInt(belowCell.getAttribute('rowspan') || '1', 10);
            const newSpan = Math.max(1, topSpan) + Math.max(1, belowSpan);
            referenceCell.setAttribute('rowspan', String(newSpan));

            const belowHtml = (belowCell.innerHTML || '').trim();
            const topHtml = (referenceCell.innerHTML || '').trim();
            if (belowHtml) {
                referenceCell.innerHTML = topHtml ? `${topHtml}<br>${belowHtml}` : belowHtml;
            }

            if (belowCell.parentNode) belowCell.parentNode.removeChild(belowCell);
            this.markTableChanged();
        },
        splitMergedCell(referenceCell) {
            const pos = this.getTableCellPosition(referenceCell);
            if (!pos) return;
            const colspan = parseInt(referenceCell.getAttribute('colspan') || '1', 10);
            const rowspan = parseInt(referenceCell.getAttribute('rowspan') || '1', 10);
            if (colspan <= 1 && rowspan <= 1) return;

            const rowEl = referenceCell.closest('.tc-row, tr');
            if (!rowEl) return;
            const tag = (referenceCell.tagName || 'td').toLowerCase() === 'th' ? 'th' : (referenceCell.tagName || 'td').toLowerCase();

            if (colspan > 1) {
                for (let i = 1; i < colspan; i++) {
                    const newCell = document.createElement(tag);
                    newCell.innerHTML = '<br>';
                    rowEl.insertBefore(newCell, referenceCell.nextSibling);
                }
            }
            referenceCell.removeAttribute('colspan');

            if (rowspan > 1) {
                for (let r = 1; r < rowspan; r++) {
                    const freshRows = this.getEditorTableRows(pos.tableEl);
                    const targetRow = freshRows[pos.rIdx + r];
                    if (!targetRow || targetRow.length === 0) continue;
                    const targetRowEl = targetRow[0].closest('.tc-row, tr');
                    if (!targetRowEl) continue;
                    const newCell = document.createElement(tag);
                    newCell.innerHTML = '<br>';
                    const before = targetRow[pos.cIdx] || null;
                    targetRowEl.insertBefore(newCell, before);
                }
            }
            referenceCell.removeAttribute('rowspan');
            this.markTableChanged();
        },
        applyStoredTableMerges(tableEl, cellMerges) {
            if (!tableEl || !cellMerges || typeof cellMerges !== 'object') return;
            const keys = Object.keys(cellMerges).sort((a, b) => {
                const [ar, ac] = a.split('-').map(Number);
                const [br, bc] = b.split('-').map(Number);
                if (ar !== br) return ar - br;
                return ac - bc;
            });
            keys.forEach((key) => {
                const merge = cellMerges[key] || {};
                const targetColspan = Math.max(1, parseInt(merge.colspan || '1', 10));
                const targetRowspan = Math.max(1, parseInt(merge.rowspan || '1', 10));
                const [rIdx, cIdx] = key.split('-').map(Number);
                if (!Number.isFinite(rIdx) || !Number.isFinite(cIdx)) return;

                const rows = this.getEditorTableRows(tableEl);
                const origin = rows[rIdx] && rows[rIdx][cIdx];
                if (!origin) return;

                if (targetColspan > 1) {
                    for (let i = 1; i < targetColspan; i++) {
                        const freshRows = this.getEditorTableRows(tableEl);
                        const row = freshRows[rIdx] || [];
                        const right = row[cIdx + 1];
                        if (!right) break;
                        if (right.parentNode) right.parentNode.removeChild(right);
                    }
                    origin.setAttribute('colspan', String(targetColspan));
                }
                if (targetRowspan > 1) {
                    for (let i = 1; i < targetRowspan; i++) {
                        const freshRows = this.getEditorTableRows(tableEl);
                        const row = freshRows[rIdx + i] || [];
                        const below = row[cIdx];
                        if (!below) continue;
                        if (below.parentNode) below.parentNode.removeChild(below);
                    }
                    origin.setAttribute('rowspan', String(targetRowspan));
                }
            });
        },
        applyStyleToTableColumn(referenceCell, applyFn) {
            const pos = this.getTableCellPosition(referenceCell);
            if (!pos) return;
            pos.rows.forEach((row) => {
                const cell = row[pos.cIdx];
                if (cell) applyFn(cell);
            });
        },
        applyStyleToTableRow(referenceCell, applyFn) {
            const pos = this.getTableCellPosition(referenceCell);
            if (!pos) return;
            (pos.rows[pos.rIdx] || []).forEach((cell) => {
                if (cell) applyFn(cell);
            });
        },
        applyStyleToWholeTable(referenceCell, applyFn) {
            const pos = this.getTableCellPosition(referenceCell);
            if (!pos) return;
            pos.rows.forEach((row) => row.forEach((cell) => cell && applyFn(cell)));
        },
        setTableCellTextAlign(referenceCell, align) {
            if (!referenceCell) return;
            referenceCell.style.textAlign = align;
            this.markTableChanged();
        },
        setTableColumnTextAlign(referenceCell, align) {
            this.applyStyleToTableColumn(referenceCell, (cell) => { cell.style.textAlign = align; });
            this.markTableChanged();
        },
        setTableRowTextAlign(referenceCell, align) {
            this.applyStyleToTableRow(referenceCell, (cell) => { cell.style.textAlign = align; });
            this.markTableChanged();
        },
        setTableCellVerticalAlign(referenceCell, align) {
            if (!referenceCell) return;
            referenceCell.style.verticalAlign = align;
            this.markTableChanged();
        },
        setTableAllBorders(referenceCell, width, color) {
            this.applyStyleToWholeTable(referenceCell, (cell) => {
                cell.style.borderStyle = 'solid';
                cell.style.borderWidth = `${Math.max(0, width)}px`;
                cell.style.borderColor = color;
            });
            this.markTableChanged();
        },
        adjustTableAllBorders(referenceCell, delta) {
            const pos = this.getTableCellPosition(referenceCell);
            if (!pos || !pos.rows[0] || !pos.rows[0][0]) return;
            const sample = pos.rows[0][0];
            const current = parseFloat(sample.style.borderWidth || '1');
            const next = Math.max(0, Math.min(12, (Number.isFinite(current) ? current : 1) + delta));
            const color = sample.style.borderColor || '#334155';
            this.setTableAllBorders(referenceCell, next, color);
        },
        pickTableBorderColor(referenceCell) {
            const pos = this.getTableCellPosition(referenceCell);
            if (!pos || !pos.rows[0] || !pos.rows[0][0]) return;
            const sample = pos.rows[0][0];
            this.pickColor(sample.style.borderColor || '#334155', (hex) => {
                const width = parseFloat(sample.style.borderWidth || '1');
                this.setTableAllBorders(referenceCell, Number.isFinite(width) ? width : 1, hex);
            });
        },
        pickTableRowColor(referenceCell) {
            if (!referenceCell) return;
            this.pickColor(referenceCell.style.backgroundColor || '#ffffff', (hex) => {
                this.applyStyleToTableRow(referenceCell, (cell) => { this.setCellBackgroundColor(cell, hex); });
                this.markTableChanged();
            });
        },
        promptTableRowColor(referenceCell) {
            if (!referenceCell) return;
            this.showPrompt('Row color (hex)', 'Hex color', (val) => {
                const hex = this.normalizeHexColor(val);
                if (!hex) {
                    this.showAlert('Invalid color', 'Use a hex value like #ffcc00 or ffcc00.');
                    return;
                }
                this.applyStyleToTableRow(referenceCell, (cell) => { this.setCellBackgroundColor(cell, hex); });
                this.markTableChanged();
            });
        },
        pickTableColumnColor(referenceCell) {
            if (!referenceCell) return;
            this.pickColor(referenceCell.style.backgroundColor || '#ffffff', (hex) => {
                this.applyStyleToTableColumn(referenceCell, (cell) => { this.setCellBackgroundColor(cell, hex); });
                this.markTableChanged();
            });
        },
        pickWholeTableColor(referenceCell) {
            if (!referenceCell) return;
            this.pickColor(referenceCell.style.backgroundColor || '#ffffff', (hex) => {
                this.applyStyleToWholeTable(referenceCell, (cell) => { this.setCellBackgroundColor(cell, hex); });
                this.markTableChanged();
            });
        },
        setTableLayoutFixed(referenceCell, fixed) {
            const pos = this.getTableCellPosition(referenceCell);
            if (!pos) return;
            pos.tableEl.style.tableLayout = fixed ? 'fixed' : 'auto';
            if (fixed) pos.tableEl.style.width = '100%';
            this.markTableChanged();
        },
        distributeTableColumns(referenceCell) {
            const pos = this.getTableCellPosition(referenceCell);
            if (!pos) return;
            const cols = pos.rows[0] ? pos.rows[0].length : 0;
            if (cols <= 0) return;
            const tableWidth = Math.max(300, pos.tableEl.getBoundingClientRect().width || 900);
            const per = Math.round(tableWidth / cols);
            for (let cIdx = 0; cIdx < cols; cIdx++) {
                pos.rows.forEach((row) => {
                    const cell = row[cIdx];
                    if (!cell) return;
                    cell.style.width = `${per}px`;
                    cell.style.minWidth = `${per}px`;
                    cell.style.maxWidth = `${per}px`;
                });
            }
            this.markTableChanged();
        },
        deleteTable(referenceCell) {
            if (!referenceCell) return;
            const tableEl = referenceCell.closest('.tc-table, table');
            if (!tableEl) return;
            const block = tableEl.closest('.ce-block');
            if (!block || !block.parentNode) return;
            block.parentNode.removeChild(block);
            this.markTableChanged();
        },
        insertTableRow(referenceCell, where) {
            const rowEl = referenceCell ? referenceCell.closest('.tc-row, tr') : null;
            if (!rowEl || !rowEl.parentNode) return;
            if (rowEl.tagName && rowEl.tagName.toLowerCase() === 'tr') {
                const sourceCells = Array.from(rowEl.querySelectorAll('th, td'));
                if (sourceCells.length === 0) return;
                const newRow = rowEl.cloneNode(false);
                sourceCells.forEach((src) => {
                    const tag = src.tagName.toLowerCase() === 'th' ? 'th' : 'td';
                    const cell = document.createElement(tag);
                    cell.innerHTML = '<br>';
                    newRow.appendChild(cell);
                });
                if (where === 'above') rowEl.parentNode.insertBefore(newRow, rowEl);
                else rowEl.parentNode.insertBefore(newRow, rowEl.nextSibling);
                return;
            }
            const sourceCells = Array.from(rowEl.children).filter((el) => el.classList && el.classList.contains('tc-cell'));
            if (sourceCells.length === 0) return;
            const newRow = rowEl.cloneNode(false);
            sourceCells.forEach(() => {
                const cell = document.createElement('div');
                cell.className = 'tc-cell';
                cell.innerHTML = '<br>';
                newRow.appendChild(cell);
            });
            if (where === 'above') rowEl.parentNode.insertBefore(newRow, rowEl);
            else rowEl.parentNode.insertBefore(newRow, rowEl.nextSibling);
        },
        insertTableColumn(referenceCell, where) {
            const pos = this.getTableCellPosition(referenceCell);
            if (!pos) return;
            const insertAt = where === 'left' ? pos.cIdx : pos.cIdx + 1;
            pos.rows.forEach((row) => {
                const before = row[insertAt] || null;
                const sample = row[0] || referenceCell;
                if (!sample || !sample.parentNode) return;
                if (sample.tagName && (sample.tagName.toLowerCase() === 'td' || sample.tagName.toLowerCase() === 'th')) {
                    const tag = sample.tagName.toLowerCase() === 'th' ? 'th' : 'td';
                    const cell = document.createElement(tag);
                    cell.innerHTML = '<br>';
                    sample.parentNode.insertBefore(cell, before);
                } else {
                    const cell = document.createElement('div');
                    cell.className = 'tc-cell';
                    cell.innerHTML = '<br>';
                    sample.parentNode.insertBefore(cell, before);
                }
            });
        },
        deleteTableRow(referenceCell) {
            const pos = this.getTableCellPosition(referenceCell);
            if (!pos || pos.rows.length <= 1) return;
            const rowEl = referenceCell.closest('.tc-row, tr');
            if (rowEl && rowEl.parentNode) rowEl.parentNode.removeChild(rowEl);
        },
        deleteTableColumn(referenceCell) {
            const pos = this.getTableCellPosition(referenceCell);
            if (!pos) return;
            const colCount = pos.rows[0] ? pos.rows[0].length : 0;
            if (colCount <= 1) return;
            pos.rows.forEach((row) => {
                const cell = row[pos.cIdx];
                if (cell && cell.parentNode) cell.parentNode.removeChild(cell);
            });
        },
        duplicateTableColumn(referenceCell) {
            const pos = this.getTableCellPosition(referenceCell);
            if (!pos) return;
            pos.rows.forEach((row) => {
                const source = row[pos.cIdx];
                if (!source || !source.parentNode) return;
                const clone = source.cloneNode(true);
                source.parentNode.insertBefore(clone, source.nextSibling);
            });
            this.markTableChanged();
        },
        duplicateTableRow(referenceCell) {
            const pos = this.getTableCellPosition(referenceCell);
            if (!pos) return;
            const rowEl = referenceCell ? referenceCell.closest('.tc-row, tr') : null;
            if (!rowEl || !rowEl.parentNode) return;

            const clone = rowEl.cloneNode(true);
            rowEl.parentNode.insertBefore(clone, rowEl.nextSibling);

            const freshRows = this.getEditorTableRows(pos.tableEl);
            const targetRow = freshRows[pos.rIdx + 1] || [];
            const focusCell = targetRow[pos.cIdx] || targetRow[0] || null;
            if (focusCell) this.focusTableCell(focusCell);
            this.markTableChanged();
        },
        moveTableRow(referenceCell, direction) {
            const pos = this.getTableCellPosition(referenceCell);
            if (!pos) return;
            const rowEl = referenceCell ? referenceCell.closest('.tc-row, tr') : null;
            if (!rowEl || !rowEl.parentNode) return;

            const findSiblingRow = (start, step) => {
                let cursor = start;
                while (cursor) {
                    if (cursor.matches && cursor.matches('.tc-row, tr')) return cursor;
                    cursor = step < 0 ? cursor.previousElementSibling : cursor.nextElementSibling;
                }
                return null;
            };

            if (direction === 'up') {
                const target = findSiblingRow(rowEl.previousElementSibling, -1);
                if (!target) return;
                rowEl.parentNode.insertBefore(rowEl, target);
            } else if (direction === 'down') {
                const target = findSiblingRow(rowEl.nextElementSibling, 1);
                if (!target) return;
                rowEl.parentNode.insertBefore(target, rowEl);
            } else {
                return;
            }

            const freshRows = this.getEditorTableRows(pos.tableEl);
            const nextRowIdx = direction === 'up' ? pos.rIdx - 1 : pos.rIdx + 1;
            const focusCell = freshRows[nextRowIdx] && freshRows[nextRowIdx][pos.cIdx]
                ? freshRows[nextRowIdx][pos.cIdx]
                : null;
            if (focusCell) this.focusTableCell(focusCell);
            this.markTableChanged();
        },
        duplicateCellContent(referenceCell, direction) {
            const pos = this.getTableCellPosition(referenceCell);
            if (!pos) return;

            let target = null;
            if (direction === 'right') {
                const row = pos.rows[pos.rIdx] || [];
                target = row[pos.cIdx + 1] || null;
            } else if (direction === 'below') {
                const row = pos.rows[pos.rIdx + 1] || [];
                target = row[pos.cIdx] || null;
            }
            if (!target) return;

            target.innerHTML = referenceCell.innerHTML;
            target.style.cssText = referenceCell.style.cssText;
            this.markTableChanged();
        },
        ensureTableContextMenu() {
            if (this._tableContextMenuEl) return;
            const menu = document.createElement('div');
            menu.style.position = 'fixed';
            menu.style.zIndex = '9999';
            menu.style.display = 'none';
            menu.style.width = '300px';
            menu.style.maxWidth = '320px';
            menu.style.maxHeight = '420px';
            menu.style.overflowY = 'auto';
            menu.style.overflowX = 'hidden';
            menu.style.scrollbarWidth = 'thin';
            menu.style.background = '#0f172a';
            menu.style.border = '1px solid #334155';
            menu.style.borderRadius = '8px';
            menu.style.boxShadow = '0 12px 30px rgba(0,0,0,0.35)';
            menu.style.padding = '8px';

            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.placeholder = 'Search actions...';
            searchInput.style.width = '100%';
            searchInput.style.height = '30px';
            searchInput.style.padding = '6px 10px';
            searchInput.style.marginBottom = '8px';
            searchInput.style.fontSize = '12px';
            searchInput.style.fontWeight = '600';
            searchInput.style.color = '#e2e8f0';
            searchInput.style.background = '#111827';
            searchInput.style.border = '1px solid #334155';
            searchInput.style.borderRadius = '6px';
            searchInput.style.outline = 'none';
            menu.appendChild(searchInput);

            const makeButton = (label, onClick) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = label;
                btn.dataset.tableAction = '1';
                btn.dataset.searchLabel = String(label || '').toLowerCase();
                btn.style.display = 'block';
                btn.style.width = '100%';
                btn.style.textAlign = 'left';
                btn.style.padding = '8px 10px';
                btn.style.fontSize = '12px';
                btn.style.fontWeight = '600';
                btn.style.color = '#e2e8f0';
                btn.style.background = 'transparent';
                btn.style.border = '0';
                btn.style.borderRadius = '6px';
                btn.style.cursor = 'pointer';
                btn.addEventListener('mouseenter', () => { btn.style.background = '#1e293b'; });
                btn.addEventListener('mouseleave', () => { btn.style.background = 'transparent'; });
                btn.addEventListener('click', onClick);
                return btn;
            };

            const applyActionFilter = () => {
                const q = String(searchInput.value || '').trim().toLowerCase();
                const terms = q.split(/\s+/).filter(Boolean);
                const items = Array.from(menu.querySelectorAll('button[data-table-action="1"]'));
                items.forEach((btn) => {
                    const label = String(btn.dataset.searchLabel || '');
                    const visible = terms.length === 0 || terms.every((term) => label.includes(term));
                    btn.style.display = visible ? 'block' : 'none';
                });
            };
            searchInput.addEventListener('input', applyActionFilter);

            menu.appendChild(makeButton('Insert row above', () => {
                if (!this._tableMenuCell) return;
                this.insertTableRow(this._tableMenuCell, 'above');
                this.markTableChanged();
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Insert row below', () => {
                if (!this._tableMenuCell) return;
                this.insertTableRow(this._tableMenuCell, 'below');
                this.markTableChanged();
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Insert column left', () => {
                if (!this._tableMenuCell) return;
                this.insertTableColumn(this._tableMenuCell, 'left');
                this.markTableChanged();
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Insert column right', () => {
                if (!this._tableMenuCell) return;
                this.insertTableColumn(this._tableMenuCell, 'right');
                this.markTableChanged();
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Delete row', () => {
                if (!this._tableMenuCell) return;
                this.deleteTableRow(this._tableMenuCell);
                this.markTableChanged();
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Delete column', () => {
                if (!this._tableMenuCell) return;
                this.deleteTableColumn(this._tableMenuCell);
                this.markTableChanged();
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Duplicate column', () => {
                if (!this._tableMenuCell) return;
                this.duplicateTableColumn(this._tableMenuCell);
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Duplicate row', () => {
                if (!this._tableMenuCell) return;
                this.duplicateTableRow(this._tableMenuCell);
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Move row up', () => {
                if (!this._tableMenuCell) return;
                this.moveTableRow(this._tableMenuCell, 'up');
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Move row down', () => {
                if (!this._tableMenuCell) return;
                this.moveTableRow(this._tableMenuCell, 'down');
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Merge selected cells (manual)', () => {
                this.mergeSelectedTableCells();
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Clear selected cells', () => {
                this.clearTableSelection();
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Merge with right cell', () => {
                if (!this._tableMenuCell) return;
                this.mergeCellWithRight(this._tableMenuCell);
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Merge with cell below', () => {
                if (!this._tableMenuCell) return;
                this.mergeCellWithBelow(this._tableMenuCell);
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Split merged cell', () => {
                if (!this._tableMenuCell) return;
                this.splitMergedCell(this._tableMenuCell);
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Duplicate cell to right', () => {
                if (!this._tableMenuCell) return;
                this.duplicateCellContent(this._tableMenuCell, 'right');
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Duplicate cell below', () => {
                if (!this._tableMenuCell) return;
                this.duplicateCellContent(this._tableMenuCell, 'below');
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Cell background color...', () => {
                if (!this._tableMenuCell) return;
                this.pickTableCellColor(this._tableMenuCell);
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Insert radio group...', () => {
                if (!this._tableMenuCell) return;
                this.openTableRadioBuilder(this._tableMenuCell);
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Remove radio group(s)', () => {
                if (!this._tableMenuCell) return;
                this.removeTableRadioGroups(this._tableMenuCell);
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Cell background hex...', () => {
                if (!this._tableMenuCell) return;
                this.promptTableCellColor(this._tableMenuCell);
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Clear cell background', () => {
                if (!this._tableMenuCell) return;
                this.clearTableCellColor(this._tableMenuCell);
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Column width...', () => {
                if (!this._tableMenuCell) return;
                this.openTableNumberAdjustMenu(this._tableMenuCell, {
                    title: 'Column width',
                    unit: 'px',
                    step: 20,
                    min: 60,
                    max: 1200,
                    get: (cell) => {
                        const pos = this.getTableCellPosition(cell);
                        if (!pos) return 140;
                        return this.getCellPixelSize(pos.rows[0] && pos.rows[0][pos.cIdx], 'width', 140);
                    },
                    set: (cell, value) => this.setTableColumnWidth(cell, value)
                });
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Row height...', () => {
                if (!this._tableMenuCell) return;
                this.openTableNumberAdjustMenu(this._tableMenuCell, {
                    title: 'Row height',
                    unit: 'px',
                    step: 10,
                    min: 24,
                    max: 600,
                    get: (cell) => {
                        const pos = this.getTableCellPosition(cell);
                        if (!pos) return 42;
                        const row = pos.rows[pos.rIdx] || [];
                        return this.getCellPixelSize(row[0], 'height', 42);
                    },
                    set: (cell, value) => this.setTableRowHeight(cell, value)
                });
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Cell padding...', () => {
                if (!this._tableMenuCell) return;
                this.openTableNumberAdjustMenu(this._tableMenuCell, {
                    title: 'Cell padding',
                    unit: 'px',
                    step: 2,
                    min: 0,
                    max: 40,
                    get: (cell) => this.getCellPadding(cell),
                    set: (cell, value) => this.setTableCellPadding(cell, value)
                });
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Row background color...', () => {
                if (!this._tableMenuCell) return;
                this.pickTableRowColor(this._tableMenuCell);
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Row background hex...', () => {
                if (!this._tableMenuCell) return;
                this.promptTableRowColor(this._tableMenuCell);
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Column background color...', () => {
                if (!this._tableMenuCell) return;
                this.pickTableColumnColor(this._tableMenuCell);
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Table background color...', () => {
                if (!this._tableMenuCell) return;
                this.pickWholeTableColor(this._tableMenuCell);
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Text align left', () => {
                if (!this._tableMenuCell) return;
                this.setTableCellTextAlign(this._tableMenuCell, 'left');
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Text align center', () => {
                if (!this._tableMenuCell) return;
                this.setTableCellTextAlign(this._tableMenuCell, 'center');
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Text align right', () => {
                if (!this._tableMenuCell) return;
                this.setTableCellTextAlign(this._tableMenuCell, 'right');
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Align whole row center', () => {
                if (!this._tableMenuCell) return;
                this.setTableRowTextAlign(this._tableMenuCell, 'center');
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Align whole column center', () => {
                if (!this._tableMenuCell) return;
                this.setTableColumnTextAlign(this._tableMenuCell, 'center');
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Vertical align top', () => {
                if (!this._tableMenuCell) return;
                this.setTableCellVerticalAlign(this._tableMenuCell, 'top');
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Vertical align middle', () => {
                if (!this._tableMenuCell) return;
                this.setTableCellVerticalAlign(this._tableMenuCell, 'middle');
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Vertical align bottom', () => {
                if (!this._tableMenuCell) return;
                this.setTableCellVerticalAlign(this._tableMenuCell, 'bottom');
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Border color...', () => {
                if (!this._tableMenuCell) return;
                this.pickTableBorderColor(this._tableMenuCell);
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Border width...', () => {
                if (!this._tableMenuCell) return;
                this.openTableNumberAdjustMenu(this._tableMenuCell, {
                    title: 'Border width',
                    unit: 'px',
                    step: 1,
                    min: 0,
                    max: 12,
                    get: (cell) => this.getTableBorderWidth(cell),
                    set: (cell, value) => this.setTableBorderWidth(cell, value)
                });
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Distribute columns evenly', () => {
                if (!this._tableMenuCell) return;
                this.distributeTableColumns(this._tableMenuCell);
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Table layout fixed', () => {
                if (!this._tableMenuCell) return;
                this.setTableLayoutFixed(this._tableMenuCell, true);
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Table layout auto', () => {
                if (!this._tableMenuCell) return;
                this.setTableLayoutFixed(this._tableMenuCell, false);
                this.closeTableContextMenu();
            }));
            menu.appendChild(makeButton('Delete table', () => {
                if (!this._tableMenuCell) return;
                this.deleteTable(this._tableMenuCell);
                this.closeTableContextMenu();
            }));

            const sortedActionButtons = Array.from(menu.querySelectorAll('button[data-table-action="1"]'))
                .sort((a, b) => String(a.textContent || '').localeCompare(String(b.textContent || ''), undefined, { sensitivity: 'base' }));
            sortedActionButtons.forEach((btn) => menu.appendChild(btn));

            document.body.appendChild(menu);
            this._tableContextMenuEl = menu;
            this._tableContextMenuSearchInput = searchInput;
            this._tableContextMenuApplyFilter = applyActionFilter;
        },
        openTableContextMenu(cell, x, y) {
            this.ensureTableContextMenu();
            this._tableMenuCell = cell;
            this._tableContextMenuLastX = x;
            this._tableContextMenuLastY = y;
            this.closeTableNumberAdjustMenu();
            const menu = this._tableContextMenuEl;
            if (!menu) return;
            if (this._tableContextMenuSearchInput) {
                this._tableContextMenuSearchInput.value = '';
            }
            if (typeof this._tableContextMenuApplyFilter === 'function') {
                this._tableContextMenuApplyFilter();
            }
            menu.style.display = 'block';
            const menuRect = menu.getBoundingClientRect();
            const maxX = Math.max(8, window.innerWidth - menuRect.width - 8);
            const maxY = Math.max(8, window.innerHeight - menuRect.height - 8);
            menu.style.left = `${Math.max(8, Math.min(x, maxX))}px`;
            menu.style.top = `${Math.max(8, Math.min(y, maxY))}px`;
        },
        closeTableContextMenu() {
            if (!this._tableContextMenuEl) return;
            this._tableContextMenuEl.style.display = 'none';
            this._tableMenuCell = null;
        },
        applyTableStylesFromData(data) {
            if (!data || !Array.isArray(data.blocks)) return;
            const tableBlocks = data.blocks.filter((b) => b && b.type === 'table');
            const domTables = Array.from(document.querySelectorAll('#editorjs .tc-table, #editorjs table'));
            tableBlocks.forEach((block, tableIdx) => {
                const tableEl = domTables[tableIdx];
                if (!tableEl) return;
                const rows = this.getEditorTableRows(tableEl);
                const styles = (block.data && block.data.ld_styles) ? block.data.ld_styles : {};
                const colors = styles.cellColors || {};
                const widths = styles.columnWidths || {};
                const heights = styles.rowHeights || {};
                const paddings = styles.cellPaddings || {};
                const textAligns = styles.cellTextAlign || {};
                const verticalAligns = styles.cellVerticalAlign || {};
                const borderColors = styles.cellBorderColor || {};
                const borderWidths = styles.cellBorderWidth || {};
                const tableOptions = styles.tableOptions || {};
                const cellMerges = styles.cellMerges || {};
                const cellRichHtml = styles.cellRichHtml || {};
                const cellInputStates = styles.cellInputStates || {};

                if (tableOptions.tableLayout) tableEl.style.tableLayout = String(tableOptions.tableLayout);
                if (tableOptions.width) tableEl.style.width = String(tableOptions.width);

                rows.forEach((row, rIdx) => {
                    row.forEach((cell, cIdx) => {
                        const colorKey = `${rIdx}-${cIdx}`;
                        const richHtml = cellRichHtml[colorKey];
                        if (richHtml) {
                            cell.innerHTML = String(richHtml);
                        }
                        const color = colors[colorKey];
                        if (color) this.setCellBackgroundColor(cell, color);

                        const pad = paddings[colorKey];
                        if (pad) cell.style.padding = String(pad);

                        const ta = textAligns[colorKey];
                        if (ta) cell.style.textAlign = String(ta);

                        const va = verticalAligns[colorKey];
                        if (va) cell.style.verticalAlign = String(va);

                        const bc = borderColors[colorKey];
                        if (bc) {
                            cell.style.borderStyle = 'solid';
                            cell.style.borderColor = String(bc);
                        }
                        const bw = borderWidths[colorKey];
                        if (bw) {
                            cell.style.borderStyle = 'solid';
                            cell.style.borderWidth = String(bw);
                        }

                        const states = Array.isArray(cellInputStates[colorKey]) ? cellInputStates[colorKey] : [];
                        if (states.length > 0) {
                            const inputs = Array.from(cell.querySelectorAll('.ld-table-checkbox, .ld-table-radio'));
                            inputs.forEach((input, idx) => {
                                input.checked = !!states[idx];
                            });
                        }
                    });
                });

                Object.keys(widths).forEach((key) => {
                    const cIdx = Number(key);
                    const width = Number(widths[key]);
                    if (!Number.isFinite(cIdx) || !Number.isFinite(width)) return;
                    rows.forEach((row) => {
                        const cell = row[cIdx];
                        if (!cell) return;
                        cell.style.width = `${width}px`;
                        cell.style.minWidth = `${width}px`;
                        cell.style.maxWidth = `${width}px`;
                    });
                });

                Object.keys(heights).forEach((key) => {
                    const rIdx = Number(key);
                    const height = Number(heights[key]);
                    if (!Number.isFinite(rIdx) || !Number.isFinite(height)) return;
                    const row = rows[rIdx] || [];
                    row.forEach((cell) => {
                        cell.style.height = `${height}px`;
                        cell.style.minHeight = `${height}px`;
                    });
                });

                this.applyStoredTableMerges(tableEl, cellMerges);
            });
        },
        captureTableStyles(data) {
            if (!data || !Array.isArray(data.blocks)) return data;
            const tableBlocks = data.blocks.filter((b) => b && b.type === 'table');
            const domTables = Array.from(document.querySelectorAll('#editorjs .tc-table, #editorjs table'));
            tableBlocks.forEach((block, tableIdx) => {
                const tableEl = domTables[tableIdx];
                if (!tableEl) return;
                const rows = this.getEditorTableRows(tableEl);
                const cellColors = {};
                const cellPaddings = {};
                const columnWidths = {};
                const rowHeights = {};
                const cellTextAlign = {};
                const cellVerticalAlign = {};
                const cellBorderColor = {};
                const cellBorderWidth = {};
                const tableOptions = {};
                const cellMerges = {};
                const cellRichHtml = {};
                const cellInputStates = {};

                if (tableEl.style.tableLayout) tableOptions.tableLayout = tableEl.style.tableLayout;
                if (tableEl.style.width) tableOptions.width = tableEl.style.width;

                rows.forEach((row, rIdx) => {
                    row.forEach((cell, cIdx) => {
                        const key = `${rIdx}-${cIdx}`;
                        const color = (cell.style.backgroundColor || '').trim();
                        if (color && color !== 'transparent') cellColors[key] = color;

                        const padding = (cell.style.padding || '').trim();
                        if (padding) cellPaddings[key] = padding;

                        const textAlign = (cell.style.textAlign || '').trim();
                        if (textAlign) cellTextAlign[key] = textAlign;

                        const verticalAlign = (cell.style.verticalAlign || '').trim();
                        if (verticalAlign) cellVerticalAlign[key] = verticalAlign;

                        const borderColor = (cell.style.borderColor || '').trim();
                        if (borderColor) cellBorderColor[key] = borderColor;

                        const borderWidth = (cell.style.borderWidth || '').trim();
                        if (borderWidth) cellBorderWidth[key] = borderWidth;

                        const colspan = parseInt(cell.getAttribute('colspan') || '1', 10);
                        const rowspan = parseInt(cell.getAttribute('rowspan') || '1', 10);
                        if ((Number.isFinite(colspan) && colspan > 1) || (Number.isFinite(rowspan) && rowspan > 1)) {
                            cellMerges[key] = {
                                colspan: Number.isFinite(colspan) && colspan > 1 ? colspan : 1,
                                rowspan: Number.isFinite(rowspan) && rowspan > 1 ? rowspan : 1
                            };
                        }

                        const inputs = Array.from(cell.querySelectorAll('.ld-table-checkbox, .ld-table-radio'));
                        if (inputs.length > 0) {
                            const richHtml = this.serializeTableCellRichHtml(cell);
                            if (richHtml) {
                                cellRichHtml[key] = richHtml;
                            }
                            cellInputStates[key] = inputs.map((input) => !!input.checked);
                        }
                    });
                });

                const firstRow = rows[0] || [];
                firstRow.forEach((cell, cIdx) => {
                    // Persist only explicit user-set widths, not measured layout widths.
                    const styled = parseFloat(cell.style.width || '');
                    if (Number.isFinite(styled) && styled > 0) {
                        columnWidths[cIdx] = Math.round(styled);
                    }
                });

                rows.forEach((row, rIdx) => {
                    const first = row[0];
                    if (!first) return;
                    // Persist only explicit user-set heights, not measured layout heights.
                    const styled = parseFloat(first.style.height || '');
                    if (Number.isFinite(styled) && styled > 0) {
                        rowHeights[rIdx] = Math.round(styled);
                    }
                });

                if (!block.data) block.data = {};
                const out = {};
                if (Object.keys(cellColors).length > 0) out.cellColors = cellColors;
                if (Object.keys(cellPaddings).length > 0) out.cellPaddings = cellPaddings;
                if (Object.keys(columnWidths).length > 0) out.columnWidths = columnWidths;
                if (Object.keys(rowHeights).length > 0) out.rowHeights = rowHeights;
                if (Object.keys(cellTextAlign).length > 0) out.cellTextAlign = cellTextAlign;
                if (Object.keys(cellVerticalAlign).length > 0) out.cellVerticalAlign = cellVerticalAlign;
                if (Object.keys(cellBorderColor).length > 0) out.cellBorderColor = cellBorderColor;
                if (Object.keys(cellBorderWidth).length > 0) out.cellBorderWidth = cellBorderWidth;
                if (Object.keys(tableOptions).length > 0) out.tableOptions = tableOptions;
                if (Object.keys(cellMerges).length > 0) out.cellMerges = cellMerges;
                if (Object.keys(cellRichHtml).length > 0) out.cellRichHtml = cellRichHtml;
                if (Object.keys(cellInputStates).length > 0) out.cellInputStates = cellInputStates;
                if (Object.keys(out).length > 0) block.data.ld_styles = out;
                else delete block.data.ld_styles;
            });
            return data;
        },
        async getEditorOutput() {
            const raw = await globalEditorInstance.save();
            return this.captureTableStyles(raw);
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
        showPrompt(title, label, onConfirm, initialValue = '') {
            this.promptTitle = title;
            this.promptInput = String(initialValue || '');
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
        canPublish() {
            if (!this.activePage) return false;
            return !this.isPublishing && (this.needsSave || Number(this.activePage.has_draft) === 1);
        },
        canUndo() {
            return this.editorHistoryIndex > 0 && !this.historyBusy;
        },
        canRedo() {
            return this.editorHistoryIndex >= 0 && this.editorHistoryIndex < (this.editorHistory.length - 1) && !this.historyBusy;
        },
        spaces() {
            return this.items
                .filter(i => i.type === 'space')
                .sort((a, b) => {
                    const ao = Number.isFinite(Number(a.sort_order)) ? Number(a.sort_order) : 0;
                    const bo = Number.isFinite(Number(b.sort_order)) ? Number(b.sort_order) : 0;
                    return ao - bo || a.id - b.id;
                });
        }
    }
}).mount('#app');

