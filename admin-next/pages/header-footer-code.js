/**
 * Header Footer Code — Admin Next page (list + edit).
 * Tag: window.__GRAV_PAGE_TAG
 */
const TAG = window.__GRAV_PAGE_TAG || 'grav-header-footer-code--page';

const TYPES = [
    { value: 'html', label: 'HTML' },
    { value: 'css', label: 'CSS' },
    { value: 'js', label: 'JavaScript' },
];
const LOCATIONS = [
    { value: 'header', label: 'Header' },
    { value: 'footer', label: 'Footer' },
];
const TARGETS = [
    { value: 'frontend', label: 'Frontend' },
    { value: 'backend', label: 'Backend (Admin)' },
    { value: 'both', label: 'Both' },
];

function apiBase() {
    return (window.__GRAV_API_SERVER_URL || '') + (window.__GRAV_API_PREFIX || '/api/v1');
}

function apiHeaders(extra = {}) {
    const headers = { Accept: 'application/json', ...extra };
    const token = window.__GRAV_API_TOKEN;
    if (token) headers['X-API-Token'] = token;
    return headers;
}

async function api(path, options = {}) {
    const res = await fetch(apiBase() + path, {
        ...options,
        headers: apiHeaders(options.headers || {}),
    });
    if (res.status === 204) return null;
    const json = await res.json().catch(() => ({}));
    if (!res.ok) {
        const msg = json?.error?.message || `Request failed (${res.status})`;
        throw new Error(msg);
    }
    return json.data;
}

function emptyForm() {
    return {
        id: null,
        title: '',
        enabled: true,
        type: 'html',
        location: 'header',
        target: 'frontend',
        defer: false,
        async: false,
        code: '',
    };
}

class HeaderFooterCodePage extends HTMLElement {
    constructor() {
        super();
        this._view = 'list'; // list | edit
        this._snippets = [];
        this._form = emptyForm();
        this._status = '';
        this._error = '';
        this._loading = true;
        this._saving = false;
        this._cmView = null;
        this._cmModule = null;
    }

    connectedCallback() {
        this._ensureStyles();
        this._loadList();
    }

    disconnectedCallback() {
        this._destroyEditor();
    }

    _ensureStyles() {
        if (document.getElementById('hfc-admin-css')) return;
        const link = document.createElement('link');
        link.id = 'hfc-admin-css';
        link.rel = 'stylesheet';
        link.href = (window.__GRAV_API_SERVER_URL || '') + '/user/plugins/header-footer-code/assets/admin/page.css';
        document.head.appendChild(link);
    }

    async _loadList() {
        this._loading = true;
        this._error = '';
        this._render();
        try {
            const data = await api('/header-footer-code/snippets');
            this._snippets = data?.snippets || [];
            this._view = 'list';
        } catch (err) {
            this._error = err.message || String(err);
        } finally {
            this._loading = false;
            this._render();
        }
    }

    async _toggleEnabled(id, enabled) {
        try {
            const updated = await api(`/header-footer-code/snippets/${encodeURIComponent(id)}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ enabled }),
            });
            const idx = this._snippets.findIndex((s) => s.id === id);
            if (idx >= 0) this._snippets[idx] = updated;
            this._status = enabled ? 'Snippet enabled.' : 'Snippet disabled.';
            this._render();
        } catch (err) {
            this._error = err.message || String(err);
            this._render();
        }
    }

    async _deleteSnippet(id, title) {
        const ok = await window.__GRAV_DIALOGS?.confirm({
            title: 'Delete snippet?',
            message: `Delete “${title || id}”? This cannot be undone.`,
            confirmLabel: 'Delete',
            variant: 'destructive',
        });
        if (!ok) return;

        try {
            await api(`/header-footer-code/snippets/${encodeURIComponent(id)}`, { method: 'DELETE' });
            this._snippets = this._snippets.filter((s) => s.id !== id);
            this._status = 'Snippet deleted.';
            this._render();
        } catch (err) {
            this._error = err.message || String(err);
            this._render();
        }
    }

    _openCreate() {
        this._destroyEditor();
        this._form = emptyForm();
        this._view = 'edit';
        this._status = '';
        this._error = '';
        this._render();
        this._mountEditorSoon();
    }

    _openEdit(snippet) {
        this._destroyEditor();
        this._form = {
            id: snippet.id,
            title: snippet.title || '',
            enabled: !!snippet.enabled,
            type: snippet.type || 'html',
            location: snippet.location || 'header',
            target: snippet.target || 'frontend',
            defer: !!snippet.defer,
            async: !!snippet.async,
            code: snippet.code || '',
        };
        this._view = 'edit';
        this._status = '';
        this._error = '';
        this._render();
        this._mountEditorSoon();
    }

    _backToList() {
        this._destroyEditor();
        this._view = 'list';
        this._status = '';
        this._error = '';
        this._render();
    }

    _readFormFromDom() {
        const title = this.querySelector('#hfc-title')?.value ?? this._form.title;
        const type = this.querySelector('#hfc-type')?.value ?? this._form.type;
        const location = this.querySelector('#hfc-location')?.value ?? this._form.location;
        const target = this.querySelector('#hfc-target')?.value ?? this._form.target;
        const enabled = !!this.querySelector('#hfc-enabled')?.checked;
        const defer = !!this.querySelector('#hfc-defer')?.checked;
        const asyncAttr = !!this.querySelector('#hfc-async')?.checked;
        let code = this._form.code;
        if (this._cmView) {
            code = this._cmView.state.doc.toString();
        } else {
            code = this.querySelector('#hfc-code')?.value ?? code;
        }
        this._form = {
            ...this._form,
            title,
            type,
            location,
            target,
            enabled,
            defer,
            async: asyncAttr,
            code,
        };
    }

    async _save() {
        this._readFormFromDom();
        if (!this._form.title.trim()) {
            this._error = 'Title is required.';
            this._render();
            this._mountEditorSoon();
            return;
        }

        this._saving = true;
        this._error = '';
        this._status = '';
        this._render();
        this._mountEditorSoon();

        const payload = {
            title: this._form.title.trim(),
            enabled: this._form.enabled,
            type: this._form.type,
            location: this._form.location,
            target: this._form.target,
            defer: this._form.type === 'js' ? this._form.defer : false,
            async: this._form.type === 'js' ? this._form.async : false,
            code: this._form.code,
        };

        try {
            let saved;
            if (this._form.id) {
                saved = await api(`/header-footer-code/snippets/${encodeURIComponent(this._form.id)}`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
            } else {
                saved = await api('/header-footer-code/snippets', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
            }
            this._destroyEditor();
            await this._loadList();
            this._status = `Saved “${saved.title}”.`;
            this._render();
        } catch (err) {
            this._error = err.message || String(err);
            this._saving = false;
            this._render();
            this._mountEditorSoon();
        } finally {
            this._saving = false;
        }
    }

    _mountEditorSoon() {
        requestAnimationFrame(() => this._mountEditor());
    }

    async _mountEditor() {
        const host = this.querySelector('#hfc-editor-host');
        if (!host || this._view !== 'edit') return;

        try {
            if (!this._cmModule) {
                const [
                    { basicSetup, EditorView },
                    { EditorState },
                    { css },
                    { html },
                    { javascript },
                ] = await Promise.all([
                    import('https://esm.sh/codemirror@6'),
                    import('https://esm.sh/@codemirror/state@6'),
                    import('https://esm.sh/@codemirror/lang-css@6'),
                    import('https://esm.sh/@codemirror/lang-html@6'),
                    import('https://esm.sh/@codemirror/lang-javascript@6'),
                ]);
                this._cmModule = { basicSetup, EditorView, EditorState, css, html, javascript };
            }

            const { basicSetup, EditorView, EditorState, css, html, javascript } = this._cmModule;
            const lang = this._form.type === 'css'
                ? css()
                : this._form.type === 'js'
                    ? javascript()
                    : html();

            this._destroyEditor();
            host.innerHTML = '';
            this._cmView = new EditorView({
                parent: host,
                state: EditorState.create({
                    doc: this._form.code || '',
                    extensions: [
                        basicSetup,
                        lang,
                        EditorView.theme({
                            '&': { minHeight: '280px', fontSize: '13px' },
                            '.cm-scroller': { overflow: 'auto', minHeight: '280px' },
                        }),
                        EditorView.updateListener.of((update) => {
                            if (update.docChanged) {
                                this._form.code = update.state.doc.toString();
                            }
                        }),
                    ],
                }),
            });
        } catch (err) {
            console.warn('[header-footer-code] CodeMirror failed, using textarea', err);
            host.innerHTML = '';
            const ta = document.createElement('textarea');
            ta.id = 'hfc-code';
            ta.className = 'hfc-textarea';
            ta.value = this._form.code || '';
            ta.addEventListener('input', () => {
                this._form.code = ta.value;
            });
            host.appendChild(ta);
        }
    }

    _destroyEditor() {
        if (this._cmView) {
            this._cmView.destroy();
            this._cmView = null;
        }
    }

    _onTypeChange() {
        this._readFormFromDom();
        this._render();
        this._mountEditorSoon();
    }

    _render() {
        if (this._view === 'edit') {
            this.innerHTML = this._renderEdit();
            this._bindEdit();
            return;
        }
        this.innerHTML = this._renderList();
        this._bindList();
    }

    _renderList() {
        const rows = this._snippets.map((s) => `
            <tr data-id="${this._esc(s.id)}">
                <td class="hfc-title-cell">
                    <button type="button" class="hfc-link" data-action="edit">${this._esc(s.title)}</button>
                </td>
                <td><span class="hfc-badge">${this._esc(s.type)}</span></td>
                <td>${this._esc(s.location)}</td>
                <td>${this._esc(s.target)}</td>
                <td>
                    <label class="hfc-switch">
                        <input type="checkbox" data-action="toggle" ${s.enabled ? 'checked' : ''} />
                        <span>${s.enabled ? 'On' : 'Off'}</span>
                    </label>
                </td>
                <td class="hfc-actions">
                    <button type="button" class="hfc-btn hfc-btn-ghost" data-action="edit">Edit</button>
                    <button type="button" class="hfc-btn hfc-btn-danger" data-action="delete">Delete</button>
                </td>
            </tr>
        `).join('');

        return `
            <div class="hfc-page">
                <div class="hfc-toolbar">
                    <div>
                        <h2 class="hfc-heading">Header Footer Code</h2>
                        <p class="hfc-muted">Sitewide HTML, CSS, and JS inserts for frontend and/or Admin.</p>
                    </div>
                    <button type="button" class="hfc-btn hfc-btn-primary" data-action="add">Add snippet</button>
                </div>
                ${this._statusHtml()}
                ${this._loading ? '<p class="hfc-muted">Loading…</p>' : ''}
                ${!this._loading && this._snippets.length === 0 ? `
                    <div class="hfc-empty">
                        <p>No snippets yet.</p>
                        <button type="button" class="hfc-btn hfc-btn-primary" data-action="add">Add snippet</button>
                    </div>
                ` : ''}
                ${!this._loading && this._snippets.length > 0 ? `
                    <div class="hfc-table-wrap">
                        <table class="hfc-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Target</th>
                                    <th>Enabled</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>${rows}</tbody>
                        </table>
                    </div>
                ` : ''}
            </div>
        `;
    }

    _renderEdit() {
        const f = this._form;
        const isJs = f.type === 'js';
        return `
            <div class="hfc-page">
                <div class="hfc-toolbar">
                    <div>
                        <h2 class="hfc-heading">${f.id ? 'Edit snippet' : 'Add snippet'}</h2>
                        <p class="hfc-warn">Snippets run as-is. Only add trusted HTML, CSS, and JavaScript.</p>
                    </div>
                    <button type="button" class="hfc-btn hfc-btn-ghost" data-action="back">Back to list</button>
                </div>
                ${this._statusHtml()}
                <form class="hfc-form" id="hfc-form">
                    <div class="hfc-grid">
                        <label class="hfc-field">
                            <span>Title</span>
                            <input id="hfc-title" type="text" value="${this._escAttr(f.title)}" required />
                        </label>
                        <label class="hfc-field">
                            <span>Type</span>
                            <select id="hfc-type">
                                ${TYPES.map((t) => `<option value="${t.value}" ${f.type === t.value ? 'selected' : ''}>${t.label}</option>`).join('')}
                            </select>
                        </label>
                        <label class="hfc-field">
                            <span>Location</span>
                            <select id="hfc-location">
                                ${LOCATIONS.map((t) => `<option value="${t.value}" ${f.location === t.value ? 'selected' : ''}>${t.label}</option>`).join('')}
                            </select>
                            <small class="hfc-muted">CSS is always injected in &lt;head&gt;.</small>
                        </label>
                        <label class="hfc-field">
                            <span>Insert on</span>
                            <select id="hfc-target">
                                ${TARGETS.map((t) => `<option value="${t.value}" ${f.target === t.value ? 'selected' : ''}>${t.label}</option>`).join('')}
                            </select>
                        </label>
                    </div>
                    <div class="hfc-checks">
                        <label class="hfc-check"><input id="hfc-enabled" type="checkbox" ${f.enabled ? 'checked' : ''} /> Enabled</label>
                        ${isJs ? `
                            <label class="hfc-check"><input id="hfc-defer" type="checkbox" ${f.defer ? 'checked' : ''} /> Defer</label>
                            <label class="hfc-check"><input id="hfc-async" type="checkbox" ${f.async ? 'checked' : ''} /> Async</label>
                        ` : ''}
                    </div>
                    <label class="hfc-field hfc-field-code">
                        <span>Code</span>
                        <div id="hfc-editor-host" class="hfc-editor-host"></div>
                    </label>
                    <div class="hfc-form-actions">
                        <button type="submit" class="hfc-btn hfc-btn-primary" ${this._saving ? 'disabled' : ''}>
                            ${this._saving ? 'Saving…' : 'Save snippet'}
                        </button>
                        <button type="button" class="hfc-btn hfc-btn-ghost" data-action="back">Cancel</button>
                    </div>
                </form>
            </div>
        `;
    }

    _statusHtml() {
        return `
            ${this._error ? `<div class="hfc-alert hfc-alert-error">${this._esc(this._error)}</div>` : ''}
            ${this._status ? `<div class="hfc-alert hfc-alert-ok">${this._esc(this._status)}</div>` : ''}
        `;
    }

    _bindList() {
        this.querySelectorAll('[data-action="add"]').forEach((btn) => {
            btn.addEventListener('click', () => this._openCreate());
        });
        this.querySelectorAll('tbody tr').forEach((row) => {
            const id = row.getAttribute('data-id');
            const snippet = this._snippets.find((s) => s.id === id);
            if (!snippet) return;
            row.querySelectorAll('[data-action="edit"]').forEach((btn) => {
                btn.addEventListener('click', () => this._openEdit(snippet));
            });
            row.querySelector('[data-action="delete"]')?.addEventListener('click', () => {
                this._deleteSnippet(snippet.id, snippet.title);
            });
            row.querySelector('[data-action="toggle"]')?.addEventListener('change', (e) => {
                this._toggleEnabled(snippet.id, !!e.target.checked);
            });
        });
    }

    _bindEdit() {
        this.querySelectorAll('[data-action="back"]').forEach((btn) => {
            btn.addEventListener('click', () => this._backToList());
        });
        this.querySelector('#hfc-type')?.addEventListener('change', () => this._onTypeChange());
        this.querySelector('#hfc-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this._save();
        });
    }

    _esc(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    _escAttr(str) {
        return this._esc(str).replace(/'/g, '&#39;');
    }
}

customElements.define(TAG, HeaderFooterCodePage);
