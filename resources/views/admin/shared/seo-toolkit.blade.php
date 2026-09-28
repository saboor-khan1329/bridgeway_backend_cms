{{--
    SEO Studio — advanced, dependency-free schema / Open Graph builder.

    Attaches to:
      - textarea[name="schema"], textarea[name="og_tags"]  (per-page seoable forms)
      - textarea.js-json-field                              (SEO Configurator)

    Features:
      • Template picker (single source of truth: App\Support\SchemaTemplates)
      • Recursive VISUAL BUILDER with a true LOOP system — objects, and arrays
        with add / remove / drag-reorder of items at any depth
        (@graph, itemListElement, mainEntity, hasCredential, sameAs, knowsAbout…)
      • Live JSON-LD preview (sample-resolved) with @type detection + warnings
      • Open Graph social-card preview
      • Placeholder chips, JSON validation + formatting
      • Responsive two-pane (builder | preview) layout

    The underlying <textarea> stays the single source of truth, so existing
    saved JSON loads unchanged — purely additive, no data loss.
--}}
@once
@push('scripts')
<style>
    .seo-studio{border:1px solid #e3e6ef;border-radius:.5rem;padding:.65rem;margin-top:.4rem;background:#f8f9fc;}
    .seo-studio-bar{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;}
    .seo-studio-body{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:.6rem;}
    .seo-studio-main{flex:1 1 440px;min-width:0;}
    .seo-studio-preview{flex:1 1 320px;min-width:0;}
    @media (max-width:768px){.seo-studio-main,.seo-studio-preview{flex:1 1 100%;}}
    .seo-tree{font-size:.85rem;}
    .seo-node{border-left:2px solid #e3e6ef;padding-left:.55rem;margin:.25rem 0;}
    .seo-node-head{display:flex;align-items:center;gap:.35rem;margin-bottom:.2rem;}
    .seo-row{display:flex;gap:.35rem;align-items:flex-start;margin-bottom:.3rem;}
    .seo-grip{cursor:grab;color:#9aa2b1;line-height:31px;user-select:none;}
    .seo-key{max-width:200px;}
    .seo-idx{min-width:30px;text-align:center;color:#6c757d;line-height:31px;font-variant-numeric:tabular-nums;}
    .seo-type{max-width:96px;}
    .seo-chip{padding:0 .35rem;}
    .seo-preview-pre{max-height:340px;overflow:auto;background:#0d1117;color:#c9d1d9;border-radius:.4rem;padding:.6rem;font-size:.72rem;white-space:pre;}
    .seo-types-list .badge{margin:0 .25rem .25rem 0;}
    .og-card{border:1px solid #dfe1e6;border-radius:.5rem;overflow:hidden;background:#fff;max-width:520px;}
    .og-card-img{width:100%;height:170px;object-fit:cover;background:#eef0f4;display:block;}
    .og-card-body{padding:.55rem .7rem;}
    .og-card-site{font-size:.7rem;text-transform:uppercase;color:#8a8f98;letter-spacing:.02em;}
    .og-card-title{font-weight:600;font-size:.95rem;line-height:1.2;margin:.1rem 0;}
    .og-card-desc{font-size:.8rem;color:#5e6470;}
</style>
@endpush
@push('scripts')
<script>
window.SEO_TOOLKIT = {
    placeholders: @json(\App\Support\SchemaTemplates::PLACEHOLDERS),
    schemaTemplates: Object.assign(
        { sitewide_organization: @json(\App\Support\SchemaTemplates::sitewide()) },
        @json(\App\Support\SchemaTemplates::templates()),
        @json(\App\Support\SchemaTemplates::builderExtras())
    ),
    ogTemplates: @json(\App\Support\SchemaTemplates::ogTemplates()),
    siteName: @json(\App\Support\SchemaTemplates::orgName()),
    siteUrl: @json(\App\Support\SchemaTemplates::siteUrl()),
};

(function () {
    'use strict';

    var T = window.SEO_TOOLKIT;

    // Sample values used ONLY for the live preview, so the editor shows what a
    // real page will look like once SeoDefaults resolves the placeholders.
    var SAMPLE = {
        '{title}': 'Example Page Title',
        '{description}': 'Example meta description used for preview only.',
        '{url}': T.siteUrl + '/example-page',
        '{image}': T.siteUrl + '/images/og-sample.jpg',
        '{author}': 'Author Name',
        '{published_at}': '2024-01-01T09:00:00+00:00',
        '{modified_at}': '2024-01-02T09:00:00+00:00',
        '{category}': 'Example Category',
    };

    function el(tag, cls) { var n = document.createElement(tag); if (cls) n.className = cls; return n; }
    function btn(cls, html) { var b = el('button', cls); b.type = 'button'; b.innerHTML = html; return b; }

    function resolveSample(str) {
        return Object.keys(SAMPLE).reduce(function (s, k) { return s.split(k).join(SAMPLE[k]); }, str);
    }
    function resolveDeep(v) {
        if (typeof v === 'string') return resolveSample(v);
        if (Array.isArray(v)) return v.map(resolveDeep);
        if (v && typeof v === 'object') {
            var o = {}; Object.keys(v).forEach(function (k) { o[k] = resolveDeep(v[k]); }); return o;
        }
        return v;
    }
    function collectTypes(v, out) {
        out = out || [];
        if (Array.isArray(v)) v.forEach(function (i) { collectTypes(i, out); });
        else if (v && typeof v === 'object') {
            if (v['@type']) [].concat(v['@type']).forEach(function (t) { if (out.indexOf(t) < 0) out.push(t); });
            Object.keys(v).forEach(function (k) { collectTypes(v[k], out); });
        }
        return out;
    }
    function hasEmptyImage(v) {
        if (Array.isArray(v)) return v.some(hasEmptyImage);
        if (v && typeof v === 'object') {
            if ('image' in v && (v.image === '' || v.image === null)) return true;
            return Object.keys(v).some(function (k) { return hasEmptyImage(v[k]); });
        }
        return false;
    }

    function validate(textarea, statusEl) {
        var v = textarea.value.trim();
        if (v === '') { statusEl.textContent = ''; statusEl.className = 'small ms-1'; return true; }
        try {
            JSON.parse(v);
            statusEl.textContent = '✓ Valid JSON';
            statusEl.className = 'small ms-1 text-success';
            return true;
        } catch (e) {
            statusEl.textContent = '✗ ' + e.message;
            statusEl.className = 'small ms-1 text-danger';
            return false;
        }
    }
    function format(textarea, statusEl) {
        try {
            var v = textarea.value.trim();
            if (v !== '') textarea.value = JSON.stringify(JSON.parse(v), null, 2);
        } catch (e) { /* keep raw, validate will flag */ }
        validate(textarea, statusEl);
    }

    // ── Per-textarea studio ────────────────────────────────────────────────
    function enhance(textarea, kind) {
        if (textarea.dataset.seoToolkit) return;
        textarea.dataset.seoToolkit = '1';

        var studio = el('div', 'seo-studio');
        var bar = el('div', 'seo-studio-bar');
        var statusEl = el('span', 'small ms-1');
        var lastFocused = null;

        // Template picker
        var templates = (kind === 'og') ? T.ogTemplates : T.schemaTemplates;
        var sel = el('select', 'form-select form-select-sm');
        sel.style.maxWidth = '220px';
        sel.appendChild(new Option((kind === 'og' ? 'OG templates…' : 'Schema templates…'), ''));
        Object.keys(templates).forEach(function (k) { sel.appendChild(new Option(k.replace(/_/g, ' '), k)); });

        var insertBtn = btn('btn btn-outline-primary btn-sm', '<i class="fas fa-file-import"></i> Insert');
        insertBtn.addEventListener('click', function () {
            if (!sel.value) return;
            if (textarea.value.trim() !== '' && !confirm('Replace current JSON with the "' + sel.value + '" template?')) return;
            textarea.value = JSON.stringify(templates[sel.value], null, 2);
            afterTextareaChange();
            if (builderOpen()) loadBuilder();
        });

        var builderBtn = btn('btn btn-outline-success btn-sm', '<i class="fas fa-sitemap"></i> Visual builder');
        var formatBtn = btn('btn btn-outline-secondary btn-sm', '<i class="fas fa-code"></i> Format');
        formatBtn.addEventListener('click', function () { format(textarea, statusEl); afterTextareaChange(); });

        bar.appendChild(sel); bar.appendChild(insertBtn); bar.appendChild(builderBtn);
        bar.appendChild(formatBtn); bar.appendChild(statusEl);
        studio.appendChild(bar);

        // Placeholder chips → insert into the focused builder input, else textarea
        var chips = el('div', 'mt-2');
        var chipsLabel = el('span', 'small text-muted me-1'); chipsLabel.textContent = 'Placeholders:'; chips.appendChild(chipsLabel);
        T.placeholders.forEach(function (p) {
            var chip = btn('btn btn-light btn-sm border me-1 mb-1 py-0 font-monospace seo-chip', p);
            chip.title = 'Insert at cursor';
            chip.addEventListener('mousedown', function (e) { e.preventDefault(); });
            chip.addEventListener('click', function () {
                var target = (lastFocused && document.contains(lastFocused)) ? lastFocused : textarea;
                var s = target.selectionStart || 0, e = target.selectionEnd || 0;
                target.value = target.value.slice(0, s) + p + target.value.slice(e);
                target.dispatchEvent(new Event('input', { bubbles: true }));
                target.focus();
                target.selectionStart = target.selectionEnd = s + p.length;
            });
            chips.appendChild(chip);
        });
        studio.appendChild(chips);

        // Two-pane body. The builder column stays collapsed (display:none) until
        // toggled, so the preview takes the full width by default.
        var body = el('div', 'seo-studio-body');
        var main = el('div', 'seo-studio-main');
        main.style.display = 'none';
        var preview = el('div', 'seo-studio-preview');
        body.appendChild(main); body.appendChild(preview);
        studio.appendChild(body);

        // Builder panel (lazy / toggleable)
        var builderPanel = el('div', 'border rounded bg-white p-2');
        builderPanel.style.display = 'none';
        var tree = el('div', 'seo-tree');
        var builderActions = el('div', 'd-flex gap-2 mt-2 flex-wrap');
        var reloadBtn = btn('btn btn-outline-secondary btn-sm', '<i class="fas fa-sync"></i> Reload from JSON');
        reloadBtn.addEventListener('click', loadBuilder);
        var rootArrBtn = btn('btn btn-outline-secondary btn-sm', '<i class="fas fa-list"></i> Make root a list');
        rootArrBtn.title = 'Wrap into an array to emit separate <script> blocks';
        rootArrBtn.addEventListener('click', function () {
            if (!Array.isArray(holder.root)) { holder.root = [holder.root]; rerender(); }
        });
        builderActions.appendChild(reloadBtn); builderActions.appendChild(rootArrBtn);
        builderPanel.appendChild(el('div', 'small text-muted mb-2')).innerHTML =
            'Edit as a tree. Drag <b>&#8942;&#8942;</b> to reorder. Lists have <b>+ Add item</b>; objects have <b>+ Add property</b>. Changes write to the JSON live.';
        builderPanel.appendChild(tree);
        builderPanel.appendChild(builderActions);
        main.appendChild(builderPanel);

        // ── Builder model ──────────────────────────────────────────────────
        var holder = { root: {} };

        function builderOpen() { return builderPanel.style.display !== 'none'; }
        function sync() { textarea.value = JSON.stringify(holder.root, null, 2); validate(textarea, statusEl); updatePreview(); }

        function loadBuilder() {
            var v = textarea.value.trim();
            if (v === '') { holder.root = {}; rerender(); return true; }
            try { holder.root = JSON.parse(v); }
            catch (e) { alert('Fix invalid JSON before using the visual builder:\n' + e.message); return false; }
            rerender();
            return true;
        }

        function rerender() {
            tree.innerHTML = '';
            tree.appendChild(renderValue(holder, 'root'));
            sync();
        }

        function renderValue(parent, key) {
            var value = parent[key];
            if (Array.isArray(value)) return renderArray(parent, key);
            if (value !== null && typeof value === 'object') return renderObject(parent, key);
            return renderPrimitive(parent, key);
        }

        function typeOf(v) {
            if (Array.isArray(v)) return 'list';
            if (v === null) return 'null';
            if (typeof v === 'object') return 'object';
            if (typeof v === 'boolean') return 'boolean';
            if (typeof v === 'number') return 'number';
            return 'text';
        }
        function makeTypeSelect(parent, key) {
            var s = el('select', 'form-select form-select-sm seo-type');
            ['text', 'number', 'boolean', 'null', 'object', 'list'].forEach(function (t) { s.appendChild(new Option(t, t)); });
            s.value = typeOf(parent[key]);
            s.addEventListener('change', function () {
                var cur = parent[key];
                switch (s.value) {
                    case 'text': parent[key] = (cur == null || typeof cur === 'object') ? '' : String(cur); break;
                    case 'number': parent[key] = Number(cur) || 0; break;
                    case 'boolean': parent[key] = Boolean(cur) && cur !== 'false'; break;
                    case 'null': parent[key] = null; break;
                    case 'object': parent[key] = (cur && typeof cur === 'object' && !Array.isArray(cur)) ? cur : {}; break;
                    case 'list': parent[key] = Array.isArray(cur) ? cur : (cur === '' || cur == null ? [] : [cur]); break;
                }
                rerender();
            });
            return s;
        }

        function renderPrimitive(parent, key) {
            var node = el('div', 'd-flex gap-2 align-items-center flex-grow-1');
            node.appendChild(makeTypeSelect(parent, key));
            var v = parent[key];
            var input;
            if (typeof v === 'boolean') {
                input = el('select', 'form-select form-select-sm');
                input.appendChild(new Option('true', 'true')); input.appendChild(new Option('false', 'false'));
                input.value = String(v);
                input.addEventListener('change', function () { parent[key] = (input.value === 'true'); sync(); });
            } else if (v === null) {
                input = el('input', 'form-control form-control-sm');
                input.value = 'null'; input.disabled = true;
            } else {
                input = el('input', 'form-control form-control-sm');
                input.value = String(v);
                input.addEventListener('focus', function () { lastFocused = input; });
                input.addEventListener('input', function () {
                    parent[key] = (typeof v === 'number' && input.value !== '' && !isNaN(input.value))
                        ? Number(input.value) : input.value;
                    sync();
                });
            }
            node.appendChild(input);
            return node;
        }

        function attachDrag(row, container, commit) {
            row.draggable = true;
            row.addEventListener('dragstart', function () { row.classList.add('opacity-50'); container._drag = row; });
            row.addEventListener('dragend', function () { row.classList.remove('opacity-50'); container._drag = null; commit(); });
            row.addEventListener('dragover', function (e) {
                e.preventDefault();
                var dragging = container._drag;
                if (!dragging || dragging === row) return;
                var rect = row.getBoundingClientRect();
                container.insertBefore(dragging, (e.clientY - rect.top) > rect.height / 2 ? row.nextSibling : row);
            });
        }

        function renderObject(parent, key) {
            var obj = parent[key];
            var node = el('div', 'seo-node flex-grow-1');
            var head = el('div', 'seo-node-head');
            var tag = el('span', 'badge bg-light text-dark border'); tag.textContent = '{ } object';
            head.appendChild(tag);
            node.appendChild(head);

            var rows = el('div');
            function commitOrder() {
                var out = {};
                rows.querySelectorAll(':scope > .seo-row').forEach(function (r) { if (r.rowKey in obj) out[r.rowKey] = obj[r.rowKey]; });
                parent[key] = out; rerender();
            }
            Object.keys(obj).forEach(function (k) {
                var row = el('div', 'seo-row'); row.rowKey = k;
                var grip = el('span', 'seo-grip'); grip.innerHTML = '&#8942;&#8942;'; grip.title = 'Drag to reorder';
                var keyInput = el('input', 'form-control form-control-sm seo-key'); keyInput.value = k;
                keyInput.addEventListener('change', function () {
                    var nk = keyInput.value.trim();
                    if (!nk || nk === k) return;
                    var out = {}; Object.keys(obj).forEach(function (ok) { out[ok === k ? nk : ok] = obj[ok]; });
                    parent[key] = out; rerender();
                });
                var rm = btn('btn btn-outline-danger btn-sm', '<i class="fas fa-times"></i>');
                rm.addEventListener('click', function () { delete obj[k]; rerender(); });

                row.appendChild(grip); row.appendChild(keyInput);
                row.appendChild(renderValue(obj, k)); row.appendChild(rm);
                attachDrag(row, rows, commitOrder);
                rows.appendChild(row);
            });
            node.appendChild(rows);

            var add = btn('btn btn-outline-primary btn-sm mt-1', '<i class="fas fa-plus"></i> Add property');
            add.addEventListener('click', function () {
                var nk = 'key', i = 1; while (nk in obj) { nk = 'key' + (++i); }
                obj[nk] = ''; rerender();
            });
            node.appendChild(add);
            return node;
        }

        // Smart default for a new list item — clone the existing shape, else
        // decide string vs object from the property name (the "loop" intent).
        var STRING_LISTS = ['sameAs', 'knowsAbout', 'alternateName', 'keywords'];
        function newItemFor(key, arr) {
            if (arr.length) return JSON.parse(JSON.stringify(arr[arr.length - 1]));
            if (STRING_LISTS.indexOf(key) >= 0) return '';
            return {};
        }

        function renderArray(parent, key) {
            var arr = parent[key];
            var node = el('div', 'seo-node flex-grow-1');
            var head = el('div', 'seo-node-head');
            var tag = el('span', 'badge bg-info-subtle text-dark border'); tag.textContent = '[ ] list · ' + arr.length;
            head.appendChild(tag);
            node.appendChild(head);

            var rows = el('div');
            function commitOrder() {
                var out = []; rows.querySelectorAll(':scope > .seo-row').forEach(function (r) { out.push(arr[r.rowIndex]); });
                parent[key] = out; rerender();
            }
            arr.forEach(function (item, i) {
                var row = el('div', 'seo-row'); row.rowIndex = i;
                var grip = el('span', 'seo-grip'); grip.innerHTML = '&#8942;&#8942;'; grip.title = 'Drag to reorder';
                var idx = el('span', 'seo-idx'); idx.textContent = '#' + (i + 1);
                var rm = btn('btn btn-outline-danger btn-sm', '<i class="fas fa-times"></i>');
                rm.addEventListener('click', function () { arr.splice(i, 1); rerender(); });

                row.appendChild(grip); row.appendChild(idx);
                row.appendChild(renderValue(arr, i)); row.appendChild(rm);
                attachDrag(row, rows, commitOrder);
                rows.appendChild(row);
            });
            node.appendChild(rows);

            var add = btn('btn btn-outline-primary btn-sm mt-1', '<i class="fas fa-plus"></i> Add item');
            add.addEventListener('click', function () { arr.push(newItemFor(key, arr)); rerender(); });
            node.appendChild(add);
            return node;
        }

        builderBtn.addEventListener('click', function () {
            if (builderOpen()) {
                builderPanel.style.display = 'none';
                main.style.display = 'none';
                builderBtn.classList.replace('btn-success', 'btn-outline-success');
            } else {
                if (loadBuilder() === false) return;
                main.style.display = '';
                builderPanel.style.display = '';
                builderBtn.classList.replace('btn-outline-success', 'btn-success');
            }
        });

        // ── Preview pane ───────────────────────────────────────────────────
        function updatePreview() {
            preview.innerHTML = '';
            var raw = textarea.value.trim();
            var card = el('div', 'border rounded bg-white p-2 h-100');
            var title = el('div', 'small fw-semibold text-uppercase text-muted mb-2');
            title.textContent = (kind === 'og') ? 'Open Graph preview' : 'JSON-LD preview (sample-resolved)';
            card.appendChild(title);

            if (raw === '') { card.appendChild(el('div', 'text-muted small')).textContent = 'Empty — this field will fall through the cascade.'; preview.appendChild(card); return; }

            var data;
            try { data = JSON.parse(raw); }
            catch (e) {
                var err = el('div', 'text-danger small'); err.textContent = '✗ Invalid JSON: ' + e.message;
                card.appendChild(err); preview.appendChild(card); return;
            }
            var resolved = resolveDeep(data);

            if (kind === 'og') {
                card.appendChild(renderOgCard(resolved));
            } else {
                var types = collectTypes(data);
                if (types.length) {
                    var tl = el('div', 'seo-types-list mb-2');
                    types.forEach(function (t) { var b = el('span', 'badge bg-primary'); b.textContent = t; tl.appendChild(b); });
                    card.appendChild(tl);
                }
                var blocks = Array.isArray(data) ? data.length : 1;
                var info = el('div', 'small text-muted mb-2');
                info.textContent = blocks + ' <script> block' + (blocks === 1 ? '' : 's') + ' will render' +
                    (Array.isArray(data) ? ' (separate blocks)' : (data['@graph'] ? ' (one @graph block)' : ''));
                card.appendChild(info);
                if (hasEmptyImage(data)) {
                    var w = el('div', 'small text-warning mb-2'); w.textContent = '⚠ An "image" is empty — set {image} or a URL.';
                    card.appendChild(w);
                }
                var pre = el('pre', 'seo-preview-pre'); pre.textContent = JSON.stringify(resolved, null, 2);
                card.appendChild(pre);
            }
            preview.appendChild(card);
        }

        function renderOgCard(d) {
            d = d || {};
            var wrap = el('div', 'og-card');
            if (d.image) { var img = el('img', 'og-card-img'); img.src = d.image; img.alt = ''; img.onerror = function () { img.style.display = 'none'; }; wrap.appendChild(img); }
            var bodyEl = el('div', 'og-card-body');
            var site = el('div', 'og-card-site'); site.textContent = d.site_name || d.url || T.siteName;
            var ttl = el('div', 'og-card-title'); ttl.textContent = d.title || '(no title)';
            var desc = el('div', 'og-card-desc'); desc.textContent = d.description || '';
            bodyEl.appendChild(site); bodyEl.appendChild(ttl); bodyEl.appendChild(desc);
            wrap.appendChild(bodyEl);
            return wrap;
        }

        // Keep preview + validity in sync with manual textarea edits.
        function afterTextareaChange() { validate(textarea, statusEl); updatePreview(); }
        textarea.addEventListener('input', afterTextareaChange);

        textarea.parentNode.insertBefore(studio, textarea.nextSibling);
        validate(textarea, statusEl);
        updatePreview();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('textarea[name="schema"]').forEach(function (t) { enhance(t, 'schema'); });
        document.querySelectorAll('textarea[name="og_tags"]').forEach(function (t) { enhance(t, 'og'); });
        document.querySelectorAll('textarea.js-json-field').forEach(function (t) {
            enhance(t, /og_tags/.test(t.name || '') ? 'og' : 'schema');
        });
    });
})();
</script>
@endpush
@endonce
