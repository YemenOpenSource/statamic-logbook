/*!
 * Statamic Logbook — Control Panel runtime
 * ----------------------------------------------------------
 * Self-contained JS registered via $scripts on
 * LogbookServiceProvider and auto-injected by Statamic into
 * the CP <head>.
 *
 * Why this file exists
 * --------------------
 * Statamic 6's dashboard renders addon widget HTML through a
 * DynamicHtmlRenderer that does:
 *
 *     defineComponent({ template: widget.html })
 *
 * i.e. each widget's raw HTML is handed to the Vue template
 * compiler at runtime. Vue's compiler strips any <script>
 * tags from templates before compilation, regardless of
 * `v-pre`. That means filter/command JS embedded in Blade
 * widget views simply never executes on v6. Shipping this
 * file via $scripts puts the handlers in the page once, at
 * CP boot, where they can listen for events bubbling up from
 * the Vue-mounted widget DOM via document-level delegation.
 *
 * All handlers here are idempotent (guarded by `window.__logbook*`
 * flags) so we survive HMR reloads and repeated injections.
 *
 * No framework dependencies — this is vanilla browser JS.
 */
(function () {
    'use strict';

    if (window.__logbookLoaded) return;
    window.__logbookLoaded = true;

    // ------------------------------------------------------
    // 1. Pulse widget filter pills (dashboard)
    // ------------------------------------------------------
    // The pulse widget renders its rows with data-lb-type and
    // data-lb-sev attributes, and its filter buttons with a
    // data-lb-filter attribute. We listen at document level,
    // so the listener is immune to Vue re-mounting the widget
    // subtree.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('[data-lb-filter]');
        if (!btn) return;

        var root = btn.closest('.logbook-pulse-root');
        if (!root) return;

        var mode = btn.getAttribute('data-lb-filter') || 'all';
        var rows = root.querySelectorAll('.logbook-pulse-row');
        var btns = root.querySelectorAll('[data-lb-filter]');

        rows.forEach(function (el) {
            var t = el.getAttribute('data-lb-type');
            var s = el.getAttribute('data-lb-sev');
            var show = false;
            if (mode === 'all') show = true;
            else if (mode === 'errors') show = (s === 'error');
            else if (mode === 'audit') show = (t === 'audit');
            else if (mode === 'info') show = (t === 'system' && s === 'info');
            el.classList.toggle('lb-hidden', !show);
        });

        btns.forEach(function (b) {
            b.classList.toggle('lb-pill--active', b === btn);
        });
    });

    // ------------------------------------------------------
    // 2. Utility page: Prune / Flush Spool command CTAs
    // ------------------------------------------------------
    // Buttons on the utility page carry:
    //   data-lb-command="prune" | "flush-spool"
    //   data-lb-command-url="<cp_route url>"
    //   data-lb-command-label="Prune" | "Flush Spool"
    //   data-lb-csrf="<csrf token>"
    //
    // Posts a URL-encoded form so Laravel's VerifyCsrfToken
    // middleware is happy, surfaces toasts via the Statamic
    // global when available and falls back to `alert()`.
    var commandState = Object.create(null);

    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('[data-lb-command]');
        if (!btn) return;
        e.preventDefault();
        runCommand(btn);
    });

    function runCommand(button) {
        var url = button.getAttribute('data-lb-command-url');
        var key = button.getAttribute('data-lb-command') || '';
        var label = button.getAttribute('data-lb-command-label') || 'Command';
        var token = button.getAttribute('data-lb-csrf') || getCsrfToken();
        if (!url || commandState[key]) return;

        var originalText = button.textContent;
        commandState[key] = true;
        button.disabled = true;
        button.setAttribute('aria-disabled', 'true');
        button.textContent = label + '…';
        toast('info', label + ': in-progress');

        var body = new URLSearchParams();
        body.append('_token', token || '');

        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.toString()
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                if (response.ok && data && data.ok) {
                    toast('success', label + ': done');
                } else {
                    var message = (data && data.message) ? data.message : ('HTTP ' + response.status);
                    toast('error', label + ': failed (' + message + ')');
                }
            });
        }).catch(function (err) {
            toast('error', label + ': failed (' + ((err && err.message) || 'request error') + ')');
        }).finally(function () {
            commandState[key] = false;
            button.disabled = false;
            button.removeAttribute('aria-disabled');
            button.textContent = originalText;
        });
    }

    // ------------------------------------------------------
    // 3. Utility page: modal viewer for context / changes /
    //    request details
    // ------------------------------------------------------
    // Triggers: elements with `data-lb-modal-title`,
    //           `data-lb-modal-payload` (base64-utf8),
    //           `data-lb-modal-subtitle`.
    document.addEventListener('click', function (e) {
        var opener = e.target.closest && e.target.closest('[data-lb-modal-payload]');
        if (!opener) return;
        e.preventDefault();
        openModal(
            opener.getAttribute('data-lb-modal-title') || 'Details',
            opener.getAttribute('data-lb-modal-payload') || '',
            opener.getAttribute('data-lb-modal-subtitle') || ''
        );
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest) return;
        if (e.target.closest('[data-lb-modal-close]')) {
            e.preventDefault();
            closeModal();
        } else if (e.target.closest('[data-lb-modal-copy]')) {
            e.preventDefault();
            copyModal();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });

    // ------------------------------------------------------
    // 4. Utility page: density toggle (Compact / Cozy / Spacious)
    // ------------------------------------------------------
    // Persists user's preference in localStorage and re-applies
    // on page load. Buttons carry `data-lb-density="<mode>"`.
    var DENSITY_KEY = 'statamic-logbook.density';
    var DENSITY_CLASSES = ['lb-table--compact', 'lb-table--spacious'];

    function applyDensity(mode) {
        var tables = document.querySelectorAll('.lb-page .lb-table');
        tables.forEach(function (t) {
            DENSITY_CLASSES.forEach(function (c) { t.classList.remove(c); });
            if (mode === 'compact')  t.classList.add('lb-table--compact');
            if (mode === 'spacious') t.classList.add('lb-table--spacious');
        });
        var btns = document.querySelectorAll('[data-lb-density]');
        btns.forEach(function (b) {
            b.setAttribute('aria-pressed', b.getAttribute('data-lb-density') === mode ? 'true' : 'false');
        });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('[data-lb-density]');
        if (!btn) return;
        var mode = btn.getAttribute('data-lb-density') || 'comfortable';
        try { localStorage.setItem(DENSITY_KEY, mode); } catch (_) { /* ignore quota / private mode */ }
        applyDensity(mode);
    });

    // Re-apply stored preference on DOMContentLoaded
    function initDensity() {
        var mode = 'comfortable';
        try { mode = localStorage.getItem(DENSITY_KEY) || 'comfortable'; } catch (_) {}
        applyDensity(mode);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDensity);
    } else {
        initDensity();
    }

    // ------------------------------------------------------
    // 5. Utility page: keyboard shortcuts
    // ------------------------------------------------------
    // `/`      → focus primary filter search input on the page
    // `g s`    → go to system logs
    // `g a`    → go to audit logs
    // Shortcuts are suppressed while typing in form fields.
    var gLeaderUntil = 0;

    function isEditable(el) {
        if (!el) return false;
        if (el.isContentEditable) return true;
        var tag = (el.tagName || '').toLowerCase();
        return tag === 'input' || tag === 'textarea' || tag === 'select';
    }

    document.addEventListener('keydown', function (e) {
        if (isEditable(e.target)) return;
        if (e.metaKey || e.ctrlKey || e.altKey) return;

        // `/` → focus search
        if (e.key === '/') {
            var search = document.querySelector('.lb-page .lb-filter__search');
            if (search) {
                e.preventDefault();
                search.focus();
                search.select && search.select();
            }
            return;
        }

        // `g <k>` leader sequence
        var now = Date.now();
        if (e.key === 'g') {
            gLeaderUntil = now + 1200;
            return;
        }
        if (now < gLeaderUntil) {
            var systemLink = document.querySelector('.lb-tabs a.lb-tab[href*="/logbook/system"]');
            var auditLink  = document.querySelector('.lb-tabs a.lb-tab[href*="/logbook/audit"]');
            if (e.key === 's' && systemLink) { e.preventDefault(); window.location.href = systemLink.getAttribute('href'); }
            if (e.key === 'a' && auditLink)  { e.preventDefault(); window.location.href = auditLink.getAttribute('href'); }
            gLeaderUntil = 0;
        }
    });

    function openModal(title, payloadB64, subtitle) {
        var modal = document.getElementById('logbook-modal');
        if (!modal) return;
        setText('logbook-modal-title', title);
        setText('logbook-modal-subtitle', subtitle);
        var text = payloadB64 ? decodeB64(payloadB64) : '—';
        setText('logbook-modal-body', text);
        modal.classList.remove('lb-hidden');
        document.body.classList.add('lb-no-scroll');
    }

    function closeModal() {
        var modal = document.getElementById('logbook-modal');
        if (!modal) return;
        modal.classList.add('lb-hidden');
        document.body.classList.remove('lb-no-scroll');
    }

    function copyModal() {
        var body = document.getElementById('logbook-modal-body');
        if (!body) return;
        var text = body.textContent || '';
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(
                function () { toast('info', 'Copied to clipboard'); },
                function () { legacyCopy(text); }
            );
        } else {
            legacyCopy(text);
        }
    }

    function legacyCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'absolute';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            toast('info', 'Copied to clipboard');
        } catch (_) {
            toast('error', 'Copy failed');
        }
        document.body.removeChild(ta);
    }

    // ------------------------------------------------------
    // Helpers
    // ------------------------------------------------------
    function setText(id, text) {
        var el = document.getElementById(id);
        if (el) el.textContent = text || '';
    }

    function decodeB64(b64) {
        try {
            var binary = atob(b64);
            var bytes = Uint8Array.from(binary, function (c) { return c.charCodeAt(0); });
            return new TextDecoder('utf-8').decode(bytes);
        } catch (_) {
            return '';
        }
    }

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function toast(type, text) {
        try {
            if (window.Statamic && window.Statamic.$toast) {
                var t = window.Statamic.$toast;
                if (type === 'success' && typeof t.success === 'function') return t.success(text);
                if (type === 'error' && typeof t.error === 'function') return t.error(text);
                if (typeof t.info === 'function') return t.info(text);
                if (typeof t.show === 'function') return t.show(text);
            }
            if (window.$toast) {
                var u = window.$toast;
                if (type === 'success' && typeof u.success === 'function') return u.success(text);
                if (type === 'error' && typeof u.error === 'function') return u.error(text);
                if (typeof u.info === 'function') return u.info(text);
            }
        } catch (_) {
            // fall through
        }
        if (type === 'error') {
            // Only interrupt the user for errors; info/success fall back to console.
            try { alert(text); } catch (_) { /* noop */ }
        } else {
            try { console.info('[logbook]', text); } catch (_) { /* noop */ }
        }
    }
})();
