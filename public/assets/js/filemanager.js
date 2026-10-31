/*!
 * File Manager — antarmuka ala elFinder (JavaScript murni, tanpa jQuery / Node).
 * Bergantung pada: Bootstrap 5 (bundle) dan endpoint /api/* aplikasi ini.
 */
(function () {
    'use strict';

    // ======================================================================
    // Konfigurasi & utilitas
    // ======================================================================
    const CFG = JSON.parse(document.getElementById('fm-config').textContent);
    // Selalu same-origin: ambil hanya path dari base URL agar tidak terkena masalah host/CORS.
    const BASE = (function () {
        try { return new URL(CFG.base, location.origin).pathname.replace(/\/+$/, ''); } catch (e) { return ''; }
    })();
    const can = (p) => CFG.perms.indexOf(p) !== -1;

    const $ = (sel, root) => (root || document).querySelector(sel);
    const $$ = (sel, root) => Array.prototype.slice.call((root || document).querySelectorAll(sel));

    /** Pembuat elemen aman (hanya textContent, tidak pernah innerHTML dari data). */
    function h(tag, props, ...kids) {
        const el = document.createElement(tag);
        if (props) {
            for (const k in props) {
                const v = props[k];
                if (v === undefined || v === null || v === false) continue;
                if (k === 'class') el.className = v;
                else if (k === 'text') el.textContent = v;
                else if (k === 'dataset') Object.assign(el.dataset, v);
                else if (k.slice(0, 2) === 'on' && typeof v === 'function') el.addEventListener(k.slice(2), v);
                else el.setAttribute(k, v === true ? '' : v);
            }
        }
        kids.flat().forEach((c) => { if (c !== null && c !== undefined && c !== false) el.append(c.nodeType ? c : document.createTextNode(String(c))); });
        return el;
    }
    const icon = (name, extra) => h('i', { class: 'bi ' + name + (extra ? ' ' + extra : '') });

    function fmtBytes(n) {
        n = Number(n) || 0;
        if (n < 1024) return n + ' B';
        const u = ['KB', 'MB', 'GB', 'TB'];
        let i = Math.min(Math.floor(Math.log(n) / Math.log(1024)), 4);
        const v = n / Math.pow(1024, i);
        return (v >= 100 || i < 2 ? Math.round(v) : v.toFixed(1)) + ' ' + u[i - 1];
    }
    function fmtDate(iso) {
        if (!iso) return '—';
        const d = new Date(iso);
        if (isNaN(d)) return '—';
        return d.toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }
    function randomHex(bytes) {
        const a = new Uint8Array(bytes);
        (window.crypto || window.msCrypto).getRandomValues(a);
        return Array.prototype.map.call(a, (b) => ('0' + b.toString(16)).slice(-2)).join('');
    }
    const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
    const store = {
        get(k, d) { try { const v = localStorage.getItem(k); return v === null ? d : v; } catch (e) { return d; } },
        set(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* abaikan */ } },
    };

    // ======================================================================
    // API
    // ======================================================================
    class ApiError extends Error {
        constructor(message, status, data) { super(message); this.status = status; this.data = data; }
    }

    async function api(method, path, body) {
        const headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', [CFG.csrfHeader]: CFG.csrfHash };
        const init = { method, headers, credentials: 'same-origin' };
        if (body instanceof FormData) {
            init.body = body;
        } else if (body !== undefined && body !== null) {
            headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(body);
        }
        let res;
        try {
            res = await fetch(BASE + path, init);
        } catch (e) {
            throw new ApiError('Tidak dapat terhubung ke server. Periksa koneksi Anda.', 0, null);
        }
        let data = null;
        try { data = await res.json(); } catch (e) { /* bukan JSON */ }
        if (res.status === 401) {
            location.href = BASE + '/login';
            throw new ApiError('Sesi berakhir.', 401, data);
        }
        if (res.status === 403 && data && data.code === 'password_change') {
            location.href = BASE + '/profile/password';
            throw new ApiError(data.message, 403, data);
        }
        if (!res.ok || !data || data.ok === false) {
            throw new ApiError((data && data.message) || ('Terjadi kesalahan (HTTP ' + res.status + ').'), res.status, data);
        }
        return data;
    }

    // ======================================================================
    // Toast, modal helper
    // ======================================================================
    function toast(message, type) {
        const cls = { success: 'text-bg-success', danger: 'text-bg-danger', info: 'text-bg-primary', warning: 'text-bg-warning' }[type || 'info'];
        const el = h('div', { class: 'toast align-items-center border-0 ' + cls, role: 'alert', 'aria-live': 'assertive' },
            h('div', { class: 'd-flex' },
                h('div', { class: 'toast-body', text: message }),
                h('button', { type: 'button', class: 'btn-close btn-close-white me-2 m-auto', 'data-bs-dismiss': 'toast', 'aria-label': 'Tutup' })));
        $('#fmToasts').append(el);
        const t = new bootstrap.Toast(el, { delay: type === 'danger' ? 7000 : 3500 });
        el.addEventListener('hidden.bs.toast', () => el.remove());
        t.show();
    }
    const fail = (e) => { if (e && e.status !== 401) toast(e.message || String(e), 'danger'); };
    const modal = (id) => bootstrap.Modal.getOrCreateInstance(document.getElementById(id));

    /** Dialog isian teks. Resolve string atau null. validate(value) boleh mengembalikan pesan galat. */
    function promptDialog(opts) {
        return new Promise((resolve) => {
            const m = document.getElementById('modalPrompt');
            const input = $('#promptInput'), form = $('#promptForm'), err = $('#promptError');
            $('#promptTitle').textContent = opts.title;
            $('#promptLabel').textContent = opts.label || 'Nama';
            $('#promptOk').textContent = opts.okText || 'Simpan';
            input.value = opts.value || '';
            input.classList.remove('is-invalid');
            err.textContent = '';
            let settled = false;
            const finish = (val) => { if (settled) return; settled = true; form.onsubmit = null; resolve(val); };
            form.onsubmit = (ev) => {
                ev.preventDefault();
                const v = input.value.trim();
                const problem = opts.validate ? opts.validate(v) : null;
                if (problem) { input.classList.add('is-invalid'); err.textContent = problem; return; }
                finish(v);
                modal('modalPrompt').hide();
            };
            m.addEventListener('hidden.bs.modal', () => finish(null), { once: true });
            m.addEventListener('shown.bs.modal', () => {
                input.focus();
                if (opts.selectBase) {
                    const dot = input.value.lastIndexOf('.');
                    input.setSelectionRange(0, dot > 0 ? dot : input.value.length);
                } else {
                    input.select();
                }
            }, { once: true });
            modal('modalPrompt').show();
        });
    }

    function confirmDialog(opts) {
        return new Promise((resolve) => {
            const m = document.getElementById('modalConfirm');
            $('#confirmTitle').textContent = opts.title || 'Konfirmasi';
            const body = $('#confirmBody');
            body.textContent = '';
            body.append(opts.message);
            const ok = $('#confirmOk');
            ok.textContent = opts.okText || 'Ya';
            ok.className = 'btn ' + (opts.danger === false ? 'btn-primary' : 'btn-danger');
            let settled = false;
            const finish = (v) => { if (settled) return; settled = true; ok.onclick = null; resolve(v); };
            ok.onclick = () => { finish(true); modal('modalConfirm').hide(); };
            m.addEventListener('hidden.bs.modal', () => finish(false), { once: true });
            modal('modalConfirm').show();
        });
    }

    // ======================================================================
    // State
    // ======================================================================
    const S = {
        mode: 'files',            // files | trash
        parent: null,             // id folder aktif (null = root)
        items: [],
        byId: new Map(),
        selected: new Set(),
        anchor: null,
        view: store.get('fm-view', 'list'),
        sort: store.get('fm-sort', 'name'),
        dir: store.get('fm-dir', 'asc'),
        q: '',
        crumbs: [],
        stats: null,
        folders: [],
        open: new Set(JSON.parse(store.get('fm-open', '[]'))),
        clip: null,               // {op:'copy'|'cut', ids:[...]}
        pendingSelect: null,
        loadSeq: 0,
    };

    const el = {
        list: $('#fmList'), crumbs: $('#fmCrumbs'), tree: $('#fmTree'), side: $('#fmSide'),
        ctx: $('#fmCtx'), drop: $('#fmDrop'), toolbar: $('#fmToolbar'),
    };

    const sel = () => Array.from(S.selected).map((id) => S.byId.get(id)).filter(Boolean);

    // ======================================================================
    // Ikon & tipe
    // ======================================================================
    function iconFor(it) {
        if (it.type === 'folder') return { cls: 'bi-folder-fill', tone: 'folder' };
        const ext = (it.ext || '').toLowerCase(), mime = it.mime || '';
        if (mime.indexOf('image/') === 0) return { cls: 'bi-file-earmark-image', tone: 'image' };
        if (mime.indexOf('video/') === 0) return { cls: 'bi-file-earmark-play', tone: 'video' };
        if (mime.indexOf('audio/') === 0) return { cls: 'bi-file-earmark-music', tone: 'audio' };
        if (ext === 'pdf') return { cls: 'bi-file-earmark-pdf', tone: 'pdf' };
        if (['doc', 'docx', 'odt', 'rtf'].includes(ext)) return { cls: 'bi-file-earmark-word', tone: 'doc' };
        if (['xls', 'xlsx', 'ods', 'csv'].includes(ext)) return { cls: 'bi-file-earmark-excel', tone: 'sheet' };
        if (['ppt', 'pptx', 'odp'].includes(ext)) return { cls: 'bi-file-earmark-ppt', tone: 'doc' };
        if (['zip', 'rar', '7z', 'tar', 'gz', 'bz2'].includes(ext)) return { cls: 'bi-file-earmark-zip', tone: 'archive' };
        if (['txt', 'md', 'log'].includes(ext)) return { cls: 'bi-file-earmark-text', tone: '' };
        if (['js', 'ts', 'css', 'html', 'json', 'xml', 'sql', 'py', 'java', 'c', 'cpp', 'go', 'rs', 'yml', 'yaml'].includes(ext)) return { cls: 'bi-file-earmark-code', tone: '' };
        return { cls: 'bi-file-earmark', tone: '' };
    }

    // ======================================================================
    // Pemuatan data
    // ======================================================================
    async function load(opts) {
        opts = opts || {};
        const seq = ++S.loadSeq;
        try {
            if (S.mode === 'trash') {
                const data = await api('GET', '/api/trash');
                if (seq !== S.loadSeq) return;
                S.items = data.items;
                S.crumbs = [{ id: null, name: 'Sampah', trash: true }];
                S.stats = null;
                S.trashDays = data.retention_days;
            } else {
                const qs = new URLSearchParams({ parent: S.parent || 0, sort: S.sort, dir: S.dir });
                if (S.q) qs.set('q', S.q);
                const data = await api('GET', '/api/list?' + qs.toString());
                if (seq !== S.loadSeq) return;
                S.items = data.items;
                S.crumbs = data.breadcrumb;
                S.stats = data.stats;
            }
        } catch (e) {
            if (seq === S.loadSeq) {
                fail(e);
                if (e.status === 404 && S.parent) { S.parent = null; location.hash = '#/'; }
            }
            return;
        }
        S.byId = new Map(S.items.map((i) => [i.id, i]));
        // pertahankan pilihan yang masih ada, atau pilih item baru
        const keep = new Set();
        (S.pendingSelect || Array.from(S.selected)).forEach((id) => { if (S.byId.has(id)) keep.add(id); });
        S.selected = keep;
        S.pendingSelect = null;
        render();
        renderCrumbs();
        renderTreeActive();
        updateUsage();
    }

    async function loadTree() {
        if (!can('files.view')) return;
        try {
            const data = await api('GET', '/api/tree');
            S.folders = data.folders;
            renderTree();
        } catch (e) { fail(e); }
    }

    function reload(withTree) {
        const p = load();
        if (withTree) loadTree();
        return p;
    }

    // ======================================================================
    // Navigasi (hash routing)
    // ======================================================================
    function hashFor(id) { return id ? '#/f/' + id : '#/'; }

    function go(id) {
        const target = id ? hashFor(id) : '#/';
        if (location.hash === target || (target === '#/' && (location.hash === '' || location.hash === '#'))) {
            S.mode = 'files'; S.parent = id || null; S.q = ''; $('#fmSearch').value = ''; S.selected.clear();
            load();
        } else {
            location.hash = target;
        }
    }
    function goTrash() { location.hash = '#/trash'; }

    function route() {
        const hash = location.hash || '#/';
        S.selected.clear();
        S.q = '';
        const si = $('#fmSearch'); if (si) si.value = '';
        let m;
        if (hash === '#/trash' && can('trash.manage')) {
            S.mode = 'trash'; S.parent = null;
        } else if ((m = /^#\/f\/(\d+)$/.exec(hash))) {
            S.mode = 'files'; S.parent = parseInt(m[1], 10);
        } else {
            S.mode = 'files'; S.parent = null;
        }
        S.view = store.get('fm-view', 'list');
        load();
        if (el.side) el.side.classList.remove('open');
    }

    // ======================================================================
    // Render: daftar
    // ======================================================================
    function buildHead() {
        const trash = S.mode === 'trash';
        const col = (key, label, cls) => {
            const sorted = !trash && S.sort === key && !S.q;
            return h('div', {
                class: 'col ' + (cls || '') + (sorted ? ' sorted' + (S.dir === 'desc' ? ' desc' : '') : ''),
                text: label,
                onclick: trash || S.q ? null : () => {
                    if (S.sort === key) S.dir = S.dir === 'asc' ? 'desc' : 'asc'; else { S.sort = key; S.dir = 'asc'; }
                    store.set('fm-sort', S.sort); store.set('fm-dir', S.dir);
                    load();
                },
            });
        };
        return h('div', { class: 'fm-head', role: 'row' },
            col('name', 'Nama', 'c-name'),
            col('size', trash ? 'Isi' : 'Ukuran', 'c-size'),
            col('date', trash ? 'Dihapus' : 'Diubah', 'c-date'),
            h('div', { class: 'col c-owner', text: trash ? 'Dihapus oleh' : 'Pemilik' }),
            h('div'));
    }

    function itemEl(it) {
        const trash = S.mode === 'trash';
        const ic = iconFor(it);
        const draggable = !trash && can('files.move') && it.editable;
        const nameBox = h('div', { class: 'fm-name' });

        if (S.view === 'grid' && it.thumb && !trash) {
            const img = h('img', { class: 'fm-thumb', loading: 'lazy', alt: '', src: BASE + '/files/thumb/' + it.id, draggable: 'false' });
            img.addEventListener('error', () => { img.replaceWith(icon(ic.cls, 'fm-icon ' + ic.tone)); });
            nameBox.append(img);
        } else {
            nameBox.append(icon(ic.cls, 'fm-icon ' + ic.tone));
        }

        const label = h('div', { class: 'min-w-0', style: 'min-width:0' },
            h('span', { class: 'label', text: it.name, title: it.name }));
        if (it.path && S.q) label.append(h('span', { class: 'sub', text: it.path }));
        nameBox.append(label);
        const badges = h('span', { class: 'fm-badges' });
        if (it.shared) badges.append(h('i', { class: 'bi bi-link-45deg text-primary', title: 'Memiliki tautan publik' }));
        if (!trash && !it.editable) badges.append(h('i', { class: 'bi bi-lock-fill text-secondary', title: 'Milik pengguna lain (hanya baca)' }));
        if (badges.childNodes.length) label.firstChild.after(badges);

        const sizeText = it.type === 'folder' ? (trash && it.count !== undefined ? it.count + ' item' : '—') : fmtBytes(it.size);
        const dateText = fmtDate(trash ? it.deleted_at : it.updated_at);
        const ownerText = trash ? (it.deleted_by_name || '—') : (it.owner_name || '—');

        const row = h('div', {
            class: 'fm-item' + (S.selected.has(it.id) ? ' selected' : '') + (S.clip && S.clip.op === 'cut' && S.clip.ids.indexOf(it.id) !== -1 ? ' cut' : ''),
            role: 'option', 'aria-selected': S.selected.has(it.id) ? 'true' : 'false', tabindex: '-1',
            dataset: { id: it.id }, draggable: draggable ? 'true' : null,
        },
            nameBox,
            h('div', { class: 'fm-cell c-size', text: sizeText }),
            h('div', { class: 'fm-cell c-date', text: dateText }),
            h('div', { class: 'fm-cell c-owner', text: ownerText }),
            h('button', { class: 'fm-more', type: 'button', title: 'Menu', 'aria-label': 'Menu item', dataset: { more: '1' } }, icon('bi-three-dots-vertical')));
        return row;
    }

    function render() {
        const list = el.list;
        list.className = 'fm-list ' + (S.view === 'grid' ? 'grid' : 'list');
        list.textContent = '';
        list.setAttribute('role', 'listbox');
        list.setAttribute('aria-multiselectable', 'true');
        if (S.view === 'list') list.append(buildHead());

        if (!S.items.length) {
            let msg = 'Folder ini kosong.', sub = can('files.upload') ? 'Seret berkas ke sini atau klik Unggah.' : '';
            if (S.mode === 'trash') { msg = 'Sampah kosong.'; sub = 'Item yang dihapus muncul di sini dan dihapus permanen otomatis setelah ' + (S.trashDays || 30) + ' hari.'; }
            if (S.q) { msg = 'Tidak ada hasil untuk “' + S.q + '”.'; sub = ''; }
            list.append(h('div', { class: 'fm-empty' }, icon(S.mode === 'trash' ? 'bi-trash3' : (S.q ? 'bi-search' : 'bi-folder2-open')), h('div', { text: msg }), sub ? h('small', { text: sub }) : null));
        } else {
            const frag = document.createDocumentFragment();
            S.items.forEach((it) => frag.append(itemEl(it)));
            list.append(frag);
        }
        updateToolbar();
        updateStatus();
        $$('[data-act="view-list"]').forEach((b) => b.classList.toggle('active', S.view === 'list'));
        $$('[data-act="view-grid"]').forEach((b) => b.classList.toggle('active', S.view === 'grid'));
        $('#navTrash') && $('#navTrash').classList.toggle('active', S.mode === 'trash');
        $$('[data-mode]').forEach((n) => { n.hidden = n.dataset.mode !== S.mode; });
    }

    function paintSelection() {
        $$('.fm-item', el.list).forEach((n) => {
            const on = S.selected.has(parseInt(n.dataset.id, 10));
            n.classList.toggle('selected', on);
            n.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        updateToolbar();
        updateStatus();
    }

    function renderCrumbs() {
        const box = el.crumbs;
        box.textContent = '';
        if (S.mode === 'trash') {
            box.append(h('span', { class: 'current' }, icon('bi-trash3'), ' Sampah'));
            return;
        }
        if (S.q) {
            box.append(h('a', { href: '#', onclick: (e) => { e.preventDefault(); $('#fmSearch').value = ''; S.q = ''; load(); } }, icon('bi-arrow-left'), ' Kembali'),
                h('span', { class: 'sep' }, ' | '), h('span', { class: 'current', text: 'Hasil pencarian “' + S.q + '” di seluruh folder' }));
            return;
        }
        S.crumbs.forEach((c, i) => {
            const last = i === S.crumbs.length - 1;
            if (i > 0) box.append(h('i', { class: 'bi bi-chevron-right sep' }));
            if (last) {
                box.append(h('span', { class: 'current' }, i === 0 ? icon('bi-house-door') : null, i === 0 ? ' ' : null, c.name));
            } else {
                const a = h('a', { href: BASE + '/files' + hashFor(c.id), dataset: { drop: c.id || 0 }, onclick: (e) => { e.preventDefault(); go(c.id); } },
                    i === 0 ? icon('bi-house-door') : null, i === 0 ? ' ' : null, c.name);
                box.append(a);
            }
        });
    }

    function updateStatus() {
        const st = S.stats;
        $('#stCount').textContent = S.mode === 'trash'
            ? S.items.length + ' item di sampah'
            : (st ? st.folders + ' folder, ' + st.files + ' berkas (' + fmtBytes(st.size) + ')' : '');
        const picked = sel();
        const size = picked.reduce((a, i) => a + (i.type === 'file' ? i.size : 0), 0);
        $('#stSel').textContent = picked.length ? picked.length + ' dipilih' + (size ? ' (' + fmtBytes(size) + ')' : '') : '';
        $('#stClip').textContent = S.clip ? (S.clip.op === 'cut' ? 'Dipotong: ' : 'Disalin: ') + S.clip.ids.length + ' item — siap ditempel' : '';
    }

    function updateUsage() {
        const u = $('#fmUsage');
        if (S.stats) u.textContent = 'Total penyimpanan terpakai: ' + fmtBytes(S.stats.usage);
    }

    // ======================================================================
    // Render: pohon folder
    // ======================================================================
    function renderTree() {
        const kids = new Map();
        S.folders.forEach((f) => {
            const k = f.parent_id || 0;
            if (!kids.has(k)) kids.set(k, []);
            kids.get(k).push(f);
        });
        const ancestors = new Set(S.crumbs.filter((c) => c.id).map((c) => c.id));

        const build = (pid) => {
            const ul = h('ul');
            (kids.get(pid) || []).forEach((f) => {
                const has = kids.has(f.id);
                const open = S.open.has(f.id) || ancestors.has(f.id);
                const node = h('div', { class: 'fm-tree-node', dataset: { id: f.id, drop: f.id }, tabindex: '0', role: 'treeitem' },
                    h('span', { class: 'fm-tree-toggle' + (has ? '' : ' empty'), dataset: { toggle: f.id } }, icon(open ? 'bi-chevron-down' : 'bi-chevron-right')),
                    icon(open ? 'bi-folder2-open' : 'bi-folder-fill', 'text-warning'),
                    h('span', { class: 'fm-tree-label', text: f.name, title: f.name }));
                const li = h('li', null, node);
                if (has && open) li.append(build(f.id));
                ul.append(li);
            });
            return ul;
        };

        el.tree.textContent = '';
        const root = h('li', null,
            h('div', { class: 'fm-tree-node', dataset: { id: 0, drop: 0 }, tabindex: '0', role: 'treeitem' },
                h('span', { class: 'fm-tree-toggle empty' }, icon('bi-chevron-down')),
                icon('bi-hdd-fill', 'text-primary'), h('span', { class: 'fm-tree-label', text: 'Beranda' })),
            build(0));
        el.tree.append(root);
        renderTreeActive();
    }

    function renderTreeActive() {
        const cur = S.mode === 'files' ? (S.parent || 0) : -1;
        $$('.fm-tree-node', el.tree).forEach((n) => n.classList.toggle('active', parseInt(n.dataset.id, 10) === cur));
        // buka otomatis rantai induk yang aktif
        const need = S.crumbs.filter((c) => c.id && !S.open.has(c.id));
        if (S.mode === 'files' && need.length && S.folders.length) {
            S.crumbs.forEach((c) => { if (c.id) S.open.add(c.id); });
            renderTree();
        }
    }

    el.tree.addEventListener('click', (e) => {
        const tg = e.target.closest('[data-toggle]');
        if (tg) {
            const id = parseInt(tg.dataset.toggle, 10);
            if (S.open.has(id)) S.open.delete(id); else S.open.add(id);
            store.set('fm-open', JSON.stringify(Array.from(S.open)));
            renderTree();
            e.stopPropagation();
            return;
        }
        const node = e.target.closest('.fm-tree-node');
        if (node) go(parseInt(node.dataset.id, 10) || null);
    });
    el.tree.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { const node = e.target.closest('.fm-tree-node'); if (node) go(parseInt(node.dataset.id, 10) || null); }
    });
    if ($('#navTrash')) {
        $('#navTrash').addEventListener('click', goTrash);
        $('#navTrash').addEventListener('keydown', (e) => { if (e.key === 'Enter') goTrash(); });
    }

    // ======================================================================
    // Ketersediaan aksi
    // ======================================================================
    function avail() {
        const s = sel(), n = s.length, one = n === 1;
        const files = S.mode === 'files', trash = S.mode === 'trash';
        const allEdit = n > 0 && s.every((i) => i.editable);
        const noSearch = files && !S.q;
        return {
            open: one,
            download: files && n >= 1 && can('files.download'),
            copy: files && n >= 1 && can('files.copy'),
            cut: files && n >= 1 && can('files.move') && allEdit,
            paste: files && noSearch && !!S.clip && can(S.clip.op === 'copy' ? 'files.copy' : 'files.move'),
            rename: files && one && can('files.rename') && allEdit,
            share: files && one && can('files.share') && allEdit,
            info: files && one,
            delete: files && n >= 1 && can('files.delete') && allEdit,
            upload: noSearch && can('files.upload'),
            mkdir: noSearch && can('files.create_folder'),
            restore: trash && n >= 1 && can('trash.manage'),
            purge: trash && n >= 1 && can('trash.manage'),
            'empty-trash': trash && S.items.length > 0 && can('trash.manage'),
            up: files && !S.q && S.parent !== null,
        };
    }

    function updateToolbar() {
        const a = avail();
        $$('[data-act]', el.toolbar).forEach((b) => {
            const act = b.dataset.act;
            if (act in a) b.disabled = !a[act];
        });
    }

    // ======================================================================
    // Seleksi, klik, buka
    // ======================================================================
    function selectOnly(id) { S.selected = new Set(id === null ? [] : [id]); S.anchor = id; }

    function onItemClick(e, id) {
        if (e.shiftKey && S.anchor !== null) {
            const ids = S.items.map((i) => i.id);
            const a = ids.indexOf(S.anchor), b = ids.indexOf(id);
            if (a !== -1 && b !== -1) {
                const [lo, hi] = a < b ? [a, b] : [b, a];
                S.selected = new Set(ids.slice(lo, hi + 1));
            }
        } else if (e.ctrlKey || e.metaKey) {
            if (S.selected.has(id)) S.selected.delete(id); else S.selected.add(id);
            S.anchor = id;
        } else {
            selectOnly(id);
        }
        paintSelection();
    }

    function openItem(it) {
        if (!it) return;
        if (S.mode === 'trash') { toast('Pulihkan item terlebih dahulu untuk membukanya.', 'info'); return; }
        if (it.type === 'folder') { S.q = ''; $('#fmSearch').value = ''; go(it.id); return; }
        if (it.kind) { openPreview(it); return; }
        if (can('files.download')) { download([it]); return; }
        showInfo(it);
    }

    el.list.addEventListener('click', (e) => {
        const more = e.target.closest('[data-more]');
        const row = e.target.closest('.fm-item');
        if (!row) { if (!e.target.closest('.fm-head')) { selectOnly(null); paintSelection(); } return; }
        const id = parseInt(row.dataset.id, 10);
        if (more) {
            e.stopPropagation();
            if (!S.selected.has(id)) { selectOnly(id); paintSelection(); }
            const r = more.getBoundingClientRect();
            openCtx(r.left, r.bottom);
            return;
        }
        onItemClick(e, id);
    });
    el.list.addEventListener('dblclick', (e) => {
        const row = e.target.closest('.fm-item');
        if (row) openItem(S.byId.get(parseInt(row.dataset.id, 10)));
    });
    el.list.addEventListener('contextmenu', (e) => {
        e.preventDefault();
        const row = e.target.closest('.fm-item');
        if (row) {
            const id = parseInt(row.dataset.id, 10);
            if (!S.selected.has(id)) { selectOnly(id); paintSelection(); }
        } else {
            selectOnly(null); paintSelection();
        }
        openCtx(e.clientX, e.clientY);
    });

    // ======================================================================
    // Menu konteks
    // ======================================================================
    function openCtx(x, y) {
        const a = avail();
        const one = sel().length === 1 ? sel()[0] : null;
        const row = (act, ic, label, kbd, danger) => a[act] ? h('button', { class: 'dropdown-item' + (danger ? ' danger' : ''), type: 'button', dataset: { ctx: act } }, icon(ic), h('span', { text: label }), kbd ? h('span', { class: 'kbd', text: kbd }) : null) : null;
        const items = [];
        if (S.selected.size === 0) {
            items.push(row('upload', 'bi-cloud-arrow-up', 'Unggah berkas'), row('mkdir', 'bi-folder-plus', 'Folder baru'),
                row('paste', 'bi-clipboard-check', 'Tempel', 'Ctrl+V'), row('empty-trash', 'bi-trash3', 'Kosongkan sampah', '', true));
            items.push(h('button', { class: 'dropdown-item', type: 'button', dataset: { ctx: 'refresh' } }, icon('bi-arrow-clockwise'), h('span', { text: 'Muat ulang' })));
        } else {
            items.push(row('open', one && one.type === 'folder' ? 'bi-folder2-open' : 'bi-eye', 'Buka', 'Enter'));
            items.push(row('download', 'bi-download', sel().length > 1 || (one && one.type === 'folder') ? 'Unduh sebagai ZIP' : 'Unduh'));
            if (one && one.type === 'folder' && a.paste) items.push(h('button', { class: 'dropdown-item', type: 'button', dataset: { ctx: 'paste-into' } }, icon('bi-clipboard-check'), h('span', { text: 'Tempel ke dalam folder' })));
            items.push(h('hr', { class: 'dropdown-divider' }));
            items.push(row('copy', 'bi-files', 'Salin', 'Ctrl+C'), row('cut', 'bi-scissors', 'Potong', 'Ctrl+X'), row('rename', 'bi-input-cursor-text', 'Ubah nama', 'F2'));
            items.push(row('share', 'bi-share', 'Bagikan tautan…'), row('info', 'bi-info-circle', 'Properti'));
            items.push(row('restore', 'bi-arrow-counterclockwise', 'Pulihkan'), row('purge', 'bi-x-octagon', 'Hapus permanen', '', true));
            if (a.delete) items.push(h('hr', { class: 'dropdown-divider' }), row('delete', 'bi-trash', 'Hapus', 'Del', true));
        }
        const clean = items.filter(Boolean);
        if (!clean.length) return;
        el.ctx.textContent = '';
        clean.forEach((n) => el.ctx.append(n));
        el.ctx.classList.add('show');
        const w = el.ctx.offsetWidth, hh = el.ctx.offsetHeight;
        el.ctx.style.left = Math.max(4, Math.min(x, window.innerWidth - w - 4)) + 'px';
        el.ctx.style.top = Math.max(4, Math.min(y, window.innerHeight - hh - 4)) + 'px';
    }
    function closeCtx() { el.ctx.classList.remove('show'); }
    el.ctx.addEventListener('click', (e) => {
        const b = e.target.closest('[data-ctx]');
        if (!b) return;
        closeCtx();
        const act = b.dataset.ctx;
        if (act === 'paste-into') { const f = sel()[0]; if (f) paste(f.id); return; }
        doAct(act);
    });
    document.addEventListener('click', (e) => { if (!e.target.closest('#fmCtx') && !e.target.closest('[data-more]')) closeCtx(); });
    document.addEventListener('scroll', closeCtx, true);
    window.addEventListener('resize', closeCtx);

    // ======================================================================
    // Aksi
    // ======================================================================
    function doAct(act) {
        const a = avail();
        if (act in a && !a[act] && act !== 'refresh') return;
        switch (act) {
            case 'toggle-tree': el.side.classList.toggle('open'); break;
            case 'up': goUp(); break;
            case 'refresh': reload(true); break;
            case 'view-list': S.view = 'list'; store.set('fm-view', 'list'); render(); break;
            case 'view-grid': S.view = 'grid'; store.set('fm-view', 'grid'); render(); break;
            case 'upload': $('#inpFiles').click(); break;
            case 'upload-folder': $('#inpDir').click(); break;
            case 'mkdir': actMkdir(); break;
            case 'open': openItem(sel()[0]); break;
            case 'download': download(sel()); break;
            case 'copy': setClip('copy'); break;
            case 'cut': setClip('cut'); break;
            case 'paste': paste(S.parent); break;
            case 'rename': actRename(sel()[0]); break;
            case 'share': openShare(sel()[0]); break;
            case 'info': showInfo(sel()[0]); break;
            case 'delete': actDelete(sel()); break;
            case 'restore': actRestore(sel()); break;
            case 'purge': actPurge(sel()); break;
            case 'empty-trash': actEmptyTrash(); break;
            default: break;
        }
    }
    el.toolbar.addEventListener('click', (e) => {
        const b = e.target.closest('[data-act]');
        if (b && !b.disabled) doAct(b.dataset.act);
    });

    function goUp() {
        if (S.parent === null) return;
        const crumbs = S.crumbs;
        const parent = crumbs.length >= 2 ? crumbs[crumbs.length - 2].id : null;
        go(parent);
    }

    function nameInUse(name, exceptId) {
        const low = name.toLowerCase();
        return S.items.some((i) => i.id !== exceptId && i.name.toLowerCase() === low);
    }

    async function actMkdir() {
        const name = await promptDialog({
            title: 'Folder baru', label: 'Nama folder', value: 'Folder baru', okText: 'Buat',
            validate: (v) => !v ? 'Nama wajib diisi.' : (/[\\/]/.test(v) ? 'Nama tidak boleh memuat / atau \\.' : (nameInUse(v) ? 'Nama sudah dipakai di folder ini.' : null)),
        });
        if (name === null) return;
        try {
            const r = await api('POST', '/api/folders', { parent_id: S.parent || 0, name });
            S.pendingSelect = [r.item.id];
            await reload(true);
            toast('Folder "' + name + '" dibuat.', 'success');
        } catch (e) { fail(e); }
    }

    async function actRename(it) {
        if (!it) return;
        const name = await promptDialog({
            title: 'Ubah nama', label: 'Nama baru', value: it.name, okText: 'Ubah', selectBase: it.type === 'file',
            validate: (v) => !v ? 'Nama wajib diisi.' : (/[\\/]/.test(v) ? 'Nama tidak boleh memuat / atau \\.' : (!S.q && nameInUse(v, it.id) ? 'Nama sudah dipakai di folder ini.' : null)),
        });
        if (name === null || name === it.name) return;
        try {
            await api('POST', '/api/rename', { id: it.id, name });
            S.pendingSelect = [it.id];
            await reload(it.type === 'folder');
            toast('Nama diubah.', 'success');
        } catch (e) { fail(e); }
    }

    async function actDelete(items) {
        if (!items.length) return;
        const msg = items.length === 1
            ? 'Pindahkan “' + items[0].name + '” ke Sampah?'
            : 'Pindahkan ' + items.length + ' item terpilih ke Sampah?';
        const extra = items.some((i) => i.type === 'folder') ? ' Seluruh isi folder ikut dipindahkan.' : '';
        const ok = await confirmDialog({ title: 'Hapus', message: msg + extra + (can('trash.manage') ? ' Anda masih dapat memulihkannya dari Sampah.' : ''), okText: 'Hapus' });
        if (!ok) return;
        try {
            await api('POST', '/api/delete', { ids: items.map((i) => i.id) });
            S.selected.clear();
            await reload(true);
            toast(items.length + ' item dipindahkan ke Sampah.', 'success');
        } catch (e) { fail(e); }
    }

    async function actRestore(items) {
        try {
            const r = await api('POST', '/api/trash/restore', { ids: items.map((i) => i.id) });
            S.selected.clear();
            await reload(true);
            toast(r.restored + ' item dipulihkan.', 'success');
        } catch (e) { fail(e); }
    }
    async function actPurge(items) {
        const ok = await confirmDialog({ title: 'Hapus permanen', message: items.length + ' item akan dihapus selamanya dan tidak dapat dipulihkan.', okText: 'Hapus permanen' });
        if (!ok) return;
        try {
            await api('POST', '/api/trash/purge', { ids: items.map((i) => i.id) });
            S.selected.clear();
            await reload(false);
            toast('Dihapus permanen.', 'success');
        } catch (e) { fail(e); }
    }
    async function actEmptyTrash() {
        const ok = await confirmDialog({ title: 'Kosongkan sampah', message: 'Seluruh isi Sampah akan dihapus selamanya dan tidak dapat dipulihkan.', okText: 'Kosongkan' });
        if (!ok) return;
        try {
            await api('POST', '/api/trash/empty');
            S.selected.clear();
            await reload(false);
            toast('Sampah dikosongkan.', 'success');
        } catch (e) { fail(e); }
    }

    // ---- salin / potong / tempel ----
    function setClip(op) {
        const items = sel();
        if (!items.length) return;
        S.clip = { op, ids: items.map((i) => i.id) };
        $$('.fm-item', el.list).forEach((n) => n.classList.toggle('cut', op === 'cut' && S.clip.ids.indexOf(parseInt(n.dataset.id, 10)) !== -1));
        updateStatus();
        updateToolbar();
        toast(items.length + ' item ' + (op === 'cut' ? 'dipotong' : 'disalin') + '. Buka folder tujuan lalu Tempel.', 'info');
    }

    async function paste(targetId) {
        if (!S.clip) return;
        const op = S.clip.op, ids = S.clip.ids.slice();
        try {
            const r = await api('POST', op === 'copy' ? '/api/copy' : '/api/move', { ids, target_id: targetId || 0 });
            if (op === 'cut') S.clip = null;
            S.pendingSelect = null;
            await reload(true);
            toast((op === 'copy' ? (r.copied + ' item disalin.') : (r.moved + ' item dipindahkan.')), 'success');
        } catch (e) { fail(e); }
    }

    async function moveTo(ids, targetId, copy) {
        try {
            const r = await api('POST', copy ? '/api/copy' : '/api/move', { ids, target_id: targetId || 0 });
            S.selected.clear();
            await reload(true);
            toast(copy ? (r.copied + ' item disalin.') : (r.moved + ' item dipindahkan.'), 'success');
        } catch (e) { fail(e); }
    }

    // ---- unduh ----
    function triggerDownload(url) {
        const a = h('a', { href: url, style: 'display:none' });
        document.body.append(a);
        a.click();
        setTimeout(() => a.remove(), 1000);
    }
    function download(items) {
        if (!items.length || !can('files.download')) return;
        if (items.length === 1 && items[0].type === 'file') triggerDownload(BASE + '/files/download/' + items[0].id);
        else triggerDownload(BASE + '/files/zip?ids=' + items.map((i) => i.id).join(','));
    }

    // ======================================================================
    // Drag & drop internal (pindah) + eksternal (unggah)
    // ======================================================================
    let dragIds = null;

    el.list.addEventListener('dragstart', (e) => {
        const row = e.target.closest('.fm-item');
        if (!row) return;
        const id = parseInt(row.dataset.id, 10);
        if (!S.selected.has(id)) { selectOnly(id); paintSelection(); }
        dragIds = Array.from(S.selected);
        e.dataTransfer.effectAllowed = 'copyMove';
        e.dataTransfer.setData('text/plain', 'fm-items');
        $$('.fm-item.selected', el.list).forEach((n) => n.classList.add('dragging'));
    });
    el.list.addEventListener('dragend', () => {
        dragIds = null;
        $$('.dragging, .drop-over').forEach((n) => n.classList.remove('dragging', 'drop-over'));
    });

    function dropTargetFrom(node) {
        const t = node.closest('[data-drop], .fm-item');
        if (!t) return null;
        if (t.dataset.drop !== undefined) return { el: t, id: parseInt(t.dataset.drop, 10) || null };
        const it = S.byId.get(parseInt(t.dataset.id, 10));
        return it && it.type === 'folder' && S.mode === 'files' ? { el: t, id: it.id } : null;
    }

    function dragOverHandler(e) {
        if (dragIds) { // pemindahan internal
            const t = dropTargetFrom(e.target);
            $$('.drop-over').forEach((n) => { if (!t || n !== t.el) n.classList.remove('drop-over'); });
            if (t && dragIds.indexOf(t.id) === -1) {
                e.preventDefault();
                e.dataTransfer.dropEffect = (e.ctrlKey || e.altKey) ? 'copy' : 'move';
                t.el.classList.add('drop-over');
            }
        }
    }
    function dropHandler(e) {
        if (!dragIds) return;
        const t = dropTargetFrom(e.target);
        $$('.drop-over').forEach((n) => n.classList.remove('drop-over'));
        if (!t || dragIds.indexOf(t.id) !== -1) return;
        e.preventDefault();
        e.stopPropagation();
        const copy = e.ctrlKey || e.altKey;
        if (copy ? !can('files.copy') : !can('files.move')) { toast('Anda tidak berhak melakukan aksi ini.', 'danger'); return; }
        moveTo(dragIds.slice(), t.id, copy);
    }
    [el.list, el.tree, el.crumbs].forEach((n) => { n.addEventListener('dragover', dragOverHandler); n.addEventListener('drop', dropHandler); });
    document.addEventListener('dragleave', (e) => { if (e.target.classList && e.target.classList.contains('drop-over')) e.target.classList.remove('drop-over'); });

    // unggah dengan seret dari luar browser
    let dragDepth = 0;
    const hasFiles = (e) => e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') !== -1;
    window.addEventListener('dragenter', (e) => {
        if (!hasFiles(e) || dragIds) return;
        dragDepth++;
        if (avail().upload) el.drop.hidden = false;
    });
    window.addEventListener('dragleave', (e) => {
        if (!hasFiles(e) || dragIds) return;
        dragDepth = Math.max(0, dragDepth - 1);
        if (dragDepth === 0) el.drop.hidden = true;
    });
    window.addEventListener('dragover', (e) => { if (hasFiles(e)) e.preventDefault(); });
    window.addEventListener('drop', async (e) => {
        if (!hasFiles(e)) return;
        e.preventDefault();
        dragDepth = 0;
        el.drop.hidden = true;
        if (!avail().upload) { toast('Unggah tidak tersedia di sini.', 'warning'); return; }
        // Tangkap entry secara sinkron sebelum ada await.
        const dt = e.dataTransfer;
        const entries = [];
        if (dt.items && dt.items.length && dt.items[0].webkitGetAsEntry) {
            for (let i = 0; i < dt.items.length; i++) {
                if (dt.items[i].kind === 'file') { const en = dt.items[i].webkitGetAsEntry(); if (en) entries.push(en); }
            }
        }
        let list = [];
        if (entries.length) {
            for (const en of entries) await walkEntry(en, '', list);
        } else {
            list = Array.prototype.map.call(dt.files, (f) => ({ file: f, rel: f.name }));
        }
        enqueue(list, S.parent);
    });

    function walkEntry(entry, path, out) {
        return new Promise((resolve) => {
            if (entry.isFile) {
                entry.file((f) => { out.push({ file: f, rel: path + entry.name }); resolve(); }, () => resolve());
            } else if (entry.isDirectory) {
                const reader = entry.createReader();
                const all = [];
                const readBatch = () => reader.readEntries(async (batch) => {
                    if (!batch.length) {
                        for (const child of all) await walkEntry(child, path + entry.name + '/', out);
                        resolve();
                    } else { all.push.apply(all, batch); readBatch(); }
                }, () => resolve());
                readBatch();
            } else resolve();
        });
    }

    // ======================================================================
    // Unggah (per-potongan, 2 berkas paralel, folder bersarang)
    // ======================================================================
    const U = { jobs: [], running: 0, MAX: 2, dirCache: new Map(), total: 0, done: 0, failed: 0, refreshTimer: null };

    $('#inpFiles').addEventListener('change', (e) => {
        enqueue(Array.prototype.map.call(e.target.files, (f) => ({ file: f, rel: f.name })), S.parent);
        e.target.value = '';
    });
    $('#inpDir').addEventListener('change', (e) => {
        enqueue(Array.prototype.map.call(e.target.files, (f) => ({ file: f, rel: f.webkitRelativePath || f.name })), S.parent);
        e.target.value = '';
    });

    function enqueue(list, parent) {
        if (!list.length) return;
        const hasDirs = list.some((x) => x.rel.indexOf('/') !== -1);
        if (hasDirs && !can('files.create_folder')) {
            toast('Anda tidak berhak membuat folder, sehingga folder tidak dapat diunggah.', 'danger');
            list = list.filter((x) => x.rel.indexOf('/') === -1);
            if (!list.length) return;
        }
        const panel = $('#upPanel');
        panel.hidden = false;
        panel.classList.remove('collapsed');
        list.forEach((x) => {
            const dirs = x.rel.split('/').slice(0, -1).filter(Boolean);
            const job = { id: randomHex(8), file: x.file, name: x.file.name, rel: x.rel, dirs, parent, status: 'queued', sent: 0, uid: null, xhr: null, cancelled: false, ui: null };
            job.ui = buildUploadRow(job);
            $('#upList').append(job.ui.row);
            U.jobs.push(job);
            U.total++;
        });
        updateUploadTitle();
        pump();
    }

    function buildUploadRow(job) {
        const bar = h('div', { class: 'progress-bar', style: 'width:0%' });
        const msg = h('span', { class: 'msg', text: 'Menunggu…' });
        const cancel = h('button', { class: 'btn btn-sm btn-link p-0 text-danger', type: 'button', title: 'Batalkan', onclick: () => cancelJob(job) }, icon('bi-x-circle'));
        const row = h('div', { class: 'fm-up-item' },
            h('div', { class: 'nm' }, h('span', { text: job.rel, title: job.rel }), cancel),
            h('div', { class: 'd-flex justify-content-between small text-body-secondary' }, msg, h('span', { text: fmtBytes(job.file.size) })),
            h('div', { class: 'progress' }, bar));
        return { row, bar, msg, cancel };
    }

    function updateUploadTitle() {
        const pending = U.jobs.filter((j) => j.status === 'queued' || j.status === 'uploading').length;
        $('#upTitle').textContent = pending
            ? 'Mengunggah ' + (U.done + U.failed + 1 > U.total ? U.total : U.done + U.failed + 1) + ' dari ' + U.total + '…'
            : 'Selesai: ' + U.done + ' berhasil' + (U.failed ? ', ' + U.failed + ' gagal' : '');
    }

    function pump() {
        while (U.running < U.MAX) {
            const job = U.jobs.find((j) => j.status === 'queued');
            if (!job) break;
            job.status = 'uploading';
            U.running++;
            runJob(job).finally(() => { U.running--; updateUploadTitle(); pump(); });
        }
        if (!U.running && !U.jobs.some((j) => j.status === 'queued')) finishUploads();
    }

    function ensureDirs(parent, dirs) {
        let chain = Promise.resolve(parent);
        let key = String(parent || 0);
        dirs.forEach((d) => {
            key += '/' + d;
            const k = key;
            chain = chain.then((cur) => {
                if (!U.dirCache.has(k)) {
                    U.dirCache.set(k, api('POST', '/api/folders', { parent_id: cur || 0, name: d, ensure: true }).then((r) => r.item.id));
                }
                return U.dirCache.get(k);
            });
        });
        return chain;
    }

    function sendChunk(job, uid, parent, offset, blob, total) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            job.xhr = xhr;
            xhr.open('POST', BASE + '/api/upload');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader(CFG.csrfHeader, CFG.csrfHash);
            xhr.upload.onprogress = (ev) => { if (ev.lengthComputable) { job.sent = offset + ev.loaded; paintJob(job); } };
            xhr.onload = () => {
                let data = null;
                try { data = JSON.parse(xhr.responseText); } catch (e) { /* bukan JSON */ }
                if (xhr.status === 401) { location.href = BASE + '/login'; return; }
                if (xhr.status >= 200 && xhr.status < 300 && data && data.ok) resolve(data);
                else {
                    const err = new ApiError((data && data.message) || ('Upload gagal (HTTP ' + xhr.status + ').'), xhr.status, data);
                    reject(err);
                }
            };
            xhr.onerror = () => reject(new ApiError('Koneksi terputus saat mengunggah.', 0, null));
            xhr.onabort = () => reject(new ApiError('Dibatalkan.', -1, null));
            const fd = new FormData();
            fd.append('upload_id', uid);
            fd.append('name', job.name);
            fd.append('size', String(total));
            fd.append('offset', String(offset));
            fd.append('parent_id', String(parent || 0));
            fd.append('chunk', blob, job.name);
            xhr.send(fd);
        });
    }

    async function runJob(job) {
        const total = job.file.size;
        if (total > CFG.maxFile) return failJob(job, 'Melebihi batas ukuran berkas (' + fmtBytes(CFG.maxFile) + ').');
        try {
            const parent = job.dirs.length ? await ensureDirs(job.parent, job.dirs) : job.parent;
            job.target = parent;
            job.uid = randomHex(16);
            let offset = 0;
            for (;;) {
                if (job.cancelled) throw new ApiError('Dibatalkan.', -1, null);
                const end = Math.min(offset + CFG.chunkSize, total);
                let res = null;
                for (let attempt = 1; attempt <= 3; attempt++) {
                    try { res = await sendChunk(job, job.uid, parent, offset, job.file.slice(offset, end), total); break; }
                    catch (err) {
                        const retryable = err.status === 0 || err.status >= 500;
                        if (!retryable || attempt === 3 || job.cancelled) throw err;
                        job.ui.msg.textContent = 'Mencoba ulang (' + attempt + ')…';
                        await new Promise((r) => setTimeout(r, 800 * attempt));
                    }
                }
                if (res.resync) { offset = res.received; continue; }
                if (res.done) break;
                offset = res.received;
            }
            job.status = 'done';
            job.sent = total;
            U.done++;
            paintJob(job);
            job.ui.row.classList.add('done');
            job.ui.msg.textContent = 'Selesai';
            job.ui.cancel.remove();
            scheduleRefresh();
        } catch (err) {
            failJob(job, err.status === -1 ? 'Dibatalkan' : err.message);
            if (job.uid) api('POST', '/api/upload/abort', { upload_id: job.uid }).catch(() => {});
        }
    }

    function failJob(job, message) {
        if (job.status === 'done') return;
        job.status = 'error';
        U.failed++;
        job.ui.row.classList.add('error');
        job.ui.msg.textContent = message;
        job.ui.cancel.remove();
        updateUploadTitle();
    }

    function cancelJob(job) {
        if (job.status === 'queued') { job.cancelled = true; failJob(job, 'Dibatalkan'); return; }
        if (job.status === 'uploading') { job.cancelled = true; if (job.xhr) job.xhr.abort(); }
    }

    function paintJob(job) {
        const pct = job.file.size ? Math.min(100, Math.round((job.sent / job.file.size) * 100)) : 100;
        job.ui.bar.style.width = pct + '%';
        if (job.status === 'uploading') job.ui.msg.textContent = pct + '%';
    }

    function scheduleRefresh() {
        clearTimeout(U.refreshTimer);
        U.refreshTimer = setTimeout(() => reload(true), 400);
    }

    function finishUploads() {
        if (!U.total) return;
        updateUploadTitle();
        scheduleRefresh();
        if (U.failed) toast(U.done + ' berkas terunggah, ' + U.failed + ' gagal.', U.done ? 'warning' : 'danger');
        else toast(U.done + ' berkas berhasil diunggah.', 'success');
        // reset hitungan untuk gelombang berikutnya (baris tetap terlihat sampai panel ditutup)
        U.total = 0; U.done = 0; U.failed = 0; U.jobs = U.jobs.filter((j) => j.status === 'uploading' || j.status === 'queued');
        U.dirCache.clear();
    }

    $('#upToggle').addEventListener('click', () => $('#upPanel').classList.toggle('collapsed'));
    $('#upClose').addEventListener('click', () => {
        if (U.running) { toast('Masih ada unggahan berjalan. Batalkan dulu bila ingin menutup.', 'warning'); return; }
        $('#upPanel').hidden = true; $('#upList').textContent = '';
    });
    window.addEventListener('beforeunload', (e) => { if (U.running) { e.preventDefault(); e.returnValue = ''; } });

    // ======================================================================
    // Pratinjau
    // ======================================================================
    const PV = { list: [], idx: 0 };

    function openPreview(it) {
        PV.list = S.items.filter((i) => i.type === 'file' && i.kind);
        PV.idx = Math.max(0, PV.list.findIndex((i) => i.id === it.id));
        renderPreview();
        modal('modalPreview').show();
    }

    async function renderPreview() {
        const it = PV.list[PV.idx];
        if (!it) return;
        const body = $('#pvBody');
        body.textContent = '';
        $('#pvTitle').textContent = it.name;
        $('#pvMeta').textContent = fmtBytes(it.size) + ' · ' + fmtDate(it.updated_at);
        const dl = $('#pvDownload'); if (dl) dl.href = BASE + '/files/download/' + it.id;
        $('#pvPrev').hidden = PV.list.length < 2;
        $('#pvNext').hidden = PV.list.length < 2;
        const url = BASE + '/files/preview/' + it.id;
        switch (it.kind) {
            case 'image': body.append(h('img', { src: url, alt: it.name })); break;
            case 'pdf': body.append(h('iframe', { src: url, title: it.name })); break;
            case 'video': body.append(h('video', { src: url, controls: true, autoplay: true, preload: 'metadata' })); break;
            case 'audio': body.append(h('audio', { src: url, controls: true, autoplay: true, style: 'width:90%' })); break;
            case 'text': {
                const pre = h('pre', { text: 'Memuat…' });
                body.append(pre);
                try {
                    const r = await api('GET', '/files/text/' + it.id);
                    if (PV.list[PV.idx] === it) pre.textContent = r.content + (r.truncated ? '\n\n… (dipotong; unduh untuk melihat selengkapnya)' : '');
                } catch (e) { pre.textContent = 'Pratinjau tidak tersedia: ' + e.message; }
                break;
            }
            default: body.append(h('div', { class: 'p-5 text-center text-body-secondary' }, icon('bi-file-earmark', 'display-3'), h('p', { text: 'Pratinjau tidak tersedia.' })));
        }
    }
    function stepPreview(d) {
        if (PV.list.length < 2) return;
        PV.idx = (PV.idx + d + PV.list.length) % PV.list.length;
        renderPreview();
    }
    $('#pvPrev').addEventListener('click', () => stepPreview(-1));
    $('#pvNext').addEventListener('click', () => stepPreview(1));
    document.getElementById('modalPreview').addEventListener('hidden.bs.modal', () => { $('#pvBody').textContent = ''; });
    document.getElementById('modalPreview').addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft') stepPreview(-1);
        if (e.key === 'ArrowRight') stepPreview(1);
    });

    // ======================================================================
    // Properti
    // ======================================================================
    async function showInfo(it) {
        if (!it) return;
        try {
            const r = (await api('GET', '/api/info/' + it.id)).item;
            const dl = $('#infoBody');
            dl.textContent = '';
            const row = (k, v) => { dl.append(h('dt', { class: 'col-4 text-body-secondary fw-normal', text: k }), h('dd', { class: 'col-8 text-break', text: v })); };
            row('Nama', r.name);
            row('Jenis', r.type === 'folder' ? 'Folder' : (r.mime || 'Berkas') + (r.ext ? ' (.' + r.ext + ')' : ''));
            row('Lokasi', r.path);
            if (r.type === 'folder' && r.stats) row('Isi', r.stats.folders + ' folder, ' + r.stats.files + ' berkas — ' + fmtBytes(r.stats.size));
            else row('Ukuran', fmtBytes(r.size) + ' (' + Number(r.size).toLocaleString('id-ID') + ' byte)');
            row('Pemilik', r.owner_name || '—');
            row('Dibuat', fmtDate(r.created_at));
            row('Diubah', fmtDate(r.updated_at));
            row('Tautan publik', r.share ? (r.share.active ? 'Aktif' : 'Nonaktif') + ' — ' + r.share.views + ' dilihat, ' + r.share.downloads + ' diunduh' : 'Tidak ada');
            modal('modalInfo').show();
        } catch (e) { fail(e); }
    }

    // ======================================================================
    // Bagikan
    // ======================================================================
    const SH = { item: null, share: null };

    async function openShare(it) {
        if (!it) return;
        SH.item = it;
        $('#shName').textContent = it.name;
        const ic = iconFor(it);
        $('#shIcon').className = 'bi ' + ic.cls + ' me-1';
        try {
            const r = await api('GET', '/api/share/' + it.id);
            renderShare(r.share);
            modal('modalShare').show();
        } catch (e) { fail(e); }
    }

    function renderShare(share) {
        SH.share = share;
        const has = !!share;
        $('#shEmpty').hidden = has;
        $('#shForm').hidden = !has;
        $('#shFooter').hidden = !has;
        if (!has) return;
        $('#shUrl').value = share.url;
        $('#shActive').checked = share.is_active;
        $('#shDownload').checked = share.allow_download;
        $('#shPassword').value = '';
        $('#shClearPw').checked = false;
        $('#shClearWrap').hidden = !share.has_password;
        $('#shPwState').textContent = share.has_password ? '(saat ini: dilindungi — isi untuk mengganti)' : '';
        $('#shExpires').value = share.expires_at || '';
        $('#shStats').textContent = share.view_count + ' kali dilihat · ' + share.download_count + ' kali diunduh' + (share.expired ? ' · TAUTAN SUDAH KEDALUWARSA' : '');
    }

    $('#shCreate').addEventListener('click', async () => {
        try {
            const r = await api('POST', '/api/share', { node_id: SH.item.id, allow_download: true, is_active: true });
            renderShare(r.share);
            reload(false);
        } catch (e) { fail(e); }
    });
    $('#shSave').addEventListener('click', async () => {
        const body = {
            node_id: SH.item.id,
            is_active: $('#shActive').checked,
            allow_download: $('#shDownload').checked,
            password: $('#shPassword').value,
            clear_password: $('#shClearPw').checked,
            expires_at: $('#shExpires').value,
        };
        try {
            const r = await api('POST', '/api/share', body);
            renderShare(r.share);
            toast('Pengaturan tautan disimpan.', 'success');
            reload(false);
        } catch (e) { fail(e); }
    });
    $('#shRemove').addEventListener('click', async () => {
        try {
            await api('POST', '/api/share/delete', { node_id: SH.item.id });
            renderShare(null);
            toast('Tautan dicabut.', 'success');
            reload(false);
        } catch (e) { fail(e); }
    });
    $('#shCopy').addEventListener('click', async () => {
        const input = $('#shUrl');
        try {
            await navigator.clipboard.writeText(input.value);
        } catch (e) {
            input.select(); document.execCommand('copy');
        }
        toast('Tautan disalin ke clipboard.', 'success');
    });
    $('#shUrl').addEventListener('focus', (e) => e.target.select());

    // ======================================================================
    // Pencarian
    // ======================================================================
    $('#fmSearch').addEventListener('input', debounce((e) => {
        const v = e.target.value.trim();
        if (v === S.q) return;
        S.q = v;
        S.selected.clear();
        load();
    }, 300));
    $('#fmSearch').addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { e.target.value = ''; if (S.q) { S.q = ''; load(); } e.target.blur(); }
    });

    // ======================================================================
    // Papan ketik
    // ======================================================================
    document.addEventListener('keydown', (e) => {
        if (document.querySelector('.modal.show')) return;
        const tag = (e.target.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable) return;
        const ctrl = e.ctrlKey || e.metaKey;
        const a = avail();

        if (e.key === 'Escape') { closeCtx(); selectOnly(null); paintSelection(); return; }
        if (ctrl && e.key.toLowerCase() === 'a') { e.preventDefault(); S.selected = new Set(S.items.map((i) => i.id)); paintSelection(); return; }
        if (ctrl && e.key.toLowerCase() === 'c' && a.copy) { e.preventDefault(); setClip('copy'); return; }
        if (ctrl && e.key.toLowerCase() === 'x' && a.cut) { e.preventDefault(); setClip('cut'); return; }
        if (ctrl && e.key.toLowerCase() === 'v' && a.paste) { e.preventDefault(); paste(S.parent); return; }
        if (e.key === 'Delete' && a.delete) { e.preventDefault(); actDelete(sel()); return; }
        if (e.key === 'Delete' && a.purge) { e.preventDefault(); actPurge(sel()); return; }
        if (e.key === 'F2' && a.rename) { e.preventDefault(); actRename(sel()[0]); return; }
        if (e.key === 'Enter' && a.open) { e.preventDefault(); openItem(sel()[0]); return; }
        if (e.key === 'Backspace' && a.up) { e.preventDefault(); goUp(); return; }

        if (['ArrowDown', 'ArrowUp', 'ArrowLeft', 'ArrowRight'].indexOf(e.key) !== -1 && S.items.length) {
            e.preventDefault();
            const ids = S.items.map((i) => i.id);
            let i = S.anchor !== null ? ids.indexOf(S.anchor) : -1;
            const grid = S.view === 'grid';
            const step = e.key === 'ArrowDown' ? (grid ? 4 : 1) : e.key === 'ArrowUp' ? (grid ? -4 : -1) : e.key === 'ArrowRight' ? 1 : -1;
            i = i === -1 ? 0 : Math.max(0, Math.min(ids.length - 1, i + step));
            if (e.shiftKey && S.anchor !== null) S.selected.add(ids[i]); else S.selected = new Set([ids[i]]);
            S.anchor = ids[i];
            paintSelection();
            const node = $('.fm-item[data-id="' + ids[i] + '"]', el.list);
            if (node) node.scrollIntoView({ block: 'nearest' });
        }
    });

    // ======================================================================
    // Mulai
    // ======================================================================
    window.addEventListener('hashchange', route);
    document.addEventListener('click', (e) => {
        if (el.side.classList.contains('open') && !e.target.closest('#fmSide') && !e.target.closest('[data-act="toggle-tree"]')) el.side.classList.remove('open');
    });

    loadTree().then(route);
})();
