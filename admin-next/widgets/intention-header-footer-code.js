/**
 * Header Footer Code — Admin Next autoLoad injector (no FAB).
 *
 * Admin2 autoLoad imports this module on every admin page, but with
 * showFab:false it may never mount the custom element. Run injection at
 * module load so backend snippets still apply.
 */
const TAG = window.__GRAV_WIDGET_TAG || 'grav-intention-header-footer-code--widget';
const MARKER_COMMENT = 'Added by Intention Header Footer Code plugin';

function apiBase() {
    return (window.__GRAV_API_SERVER_URL || '') + (window.__GRAV_API_PREFIX || '/api/v1');
}

function apiHeaders() {
    const headers = { Accept: 'application/json' };
    const token = window.__GRAV_API_TOKEN;
    if (token) headers['X-API-Token'] = token;
    return headers;
}

function appendWithMarker(target, node) {
    target.appendChild(document.createComment(MARKER_COMMENT));
    target.appendChild(node);
}

async function injectBackendSnippets() {
    if (window.__HFC_BACKEND_INJECTED__) {
        return;
    }
    // Wait briefly for auth token globals if the script raced ahead of shell setup.
    for (let i = 0; i < 40 && !window.__GRAV_API_TOKEN; i++) {
        await new Promise((r) => setTimeout(r, 50));
    }
    if (!window.__GRAV_API_TOKEN) {
        return;
    }
    window.__HFC_BACKEND_INJECTED__ = true;

    try {
        const res = await fetch(apiBase() + '/intention-header-footer-code/active?target=backend', {
            headers: apiHeaders(),
        });
        if (!res.ok) return;
        const json = await res.json();
        const snippets = json?.data?.snippets || [];
        for (const snippet of snippets) {
            applySnippet(snippet);
        }
    } catch (err) {
        console.warn('[intention-header-footer-code] backend inject failed', err);
        window.__HFC_BACKEND_INJECTED__ = false;
    }
}

function applySnippet(snippet) {
    const code = String(snippet.code || '');
    if (!code.trim()) return;

    const location = snippet.location === 'footer' ? 'footer' : 'header';
    const type = snippet.type;
    const target = (type === 'css' || location === 'header')
        ? document.head
        : (document.body || document.documentElement);

    if (type === 'css') {
        const style = document.createElement('style');
        style.setAttribute('data-hfc-id', snippet.id);
        style.textContent = code;
        appendWithMarker(target, style);
        return;
    }

    if (type === 'js') {
        const script = document.createElement('script');
        script.setAttribute('data-hfc-id', snippet.id);
        if (snippet.async) script.async = true;
        if (snippet.defer) script.defer = true;
        script.text = code;
        appendWithMarker(target, script);
        return;
    }

    if (type === 'html') {
        const tpl = document.createElement('template');
        tpl.innerHTML = code;
        const nodes = Array.from(tpl.content.childNodes);
        // One marker above the whole HTML snippet group.
        target.appendChild(document.createComment(MARKER_COMMENT));
        for (const node of nodes) {
            if (node.nodeType === Node.ELEMENT_NODE && node.tagName === 'SCRIPT') {
                const script = document.createElement('script');
                for (const attr of node.attributes) {
                    script.setAttribute(attr.name, attr.value);
                }
                script.text = node.textContent || '';
                target.appendChild(script);
            } else {
                target.appendChild(node.cloneNode(true));
            }
        }
    }
}

class IntentionHeaderFooterCodeInjector extends HTMLElement {
    connectedCallback() {
        this.style.display = 'none';
        injectBackendSnippets();
    }
}

if (!customElements.get(TAG)) {
    customElements.define(TAG, IntentionHeaderFooterCodeInjector);
}

// Kick off as soon as the autoLoad module is evaluated.
injectBackendSnippets();
