/* Jelajahi arsip backup (baca-saja) + ekstrak ke folder khusus. JS murni; teks selalu lewat textContent. */
(function () {
    'use strict';
    var CFG = JSON.parse(document.getElementById('ar-config').textContent);
    var $ = function (s) { return document.querySelector(s); };
    var S = { parent: null, q: '', trash: false, items: [], sel: new Set(), qTimer: null };

    function fmtBytes(n) {
        n = Number(n) || 0;
        if (n < 1024) return n + ' B';
        var u = ['KB', 'MB', 'GB', 'TB'], i = -1;
        do { n /= 1024; i++; } while (n >= 1024 && i < u.length - 1);
        return n.toLocaleString('id-ID', { maximumFractionDigits: n < 10 ? 2 : 1 }) + ' ' + u[i];
    }
    function fmtDate(s) { return s ? new Date(s).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '—'; }
    function h(tag, attrs) {
        var e = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            if (k === 'text') e.textContent = attrs[k]; else if (k === 'class') e.className = attrs[k]; else e.setAttribute(k, attrs[k]);
        });
        for (var i = 2; i < arguments.length; i++) { var c = arguments[i]; if (c != null) e.append(c); }
        return e;
    }
    function icon(c) { return h('i', { class: 'bi ' + c }); }

    function api(method, path, body) {
        var opt = { method: method, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };
        if (method !== 'GET') { opt.headers['Content-Type'] = 'application/json'; opt.headers[CFG.csrfHeader] = CFG.csrfHash; opt.body = JSON.stringify(body || {}); }
        return fetch(CFG.base + path, opt).then(function (r) {
            return r.json().catch(function () { return { ok: false, message: 'Respons tidak valid (HTTP ' + r.status + ').' }; })
                .then(function (d) { if (!d.ok) { var e = new Error(d.message || ('Gagal (HTTP ' + r.status + ')')); e.status = r.status; throw e; } return d; });
        });
    }

    // ---------- daftar ----------
    function load() {
        var qs = '?parent=' + (S.parent || 0) + '&q=' + encodeURIComponent(S.q) + (S.trash ? '&trash=1' : '');
        $('#arBody').replaceChildren(h('tr', {}, h('td', { colspan: 6, class: 'text-center text-body-secondary py-4', text: 'Memuat…' })));
        api('GET', '/list' + qs).then(function (d) {
            S.items = d.items; S.sel.clear(); renderCrumbs(d.breadcrumb, d.search); renderRows(d); syncSel();
        }).catch(function (e) {
            $('#arBody').replaceChildren(h('tr', {}, h('td', { colspan: 6, class: 'text-center text-danger py-4', text: e.message })));
        });
    }

    function renderCrumbs(list, search) {
        var nav = $('#arCrumbs'); nav.replaceChildren();
        if (search) { nav.append(h('span', { text: 'Hasil pencarian “' + search + '” ' }), h('a', { href: '#', 'data-clear': '1', text: '(bersihkan)' })); return; }
        list.forEach(function (c, i) {
            if (i) nav.append(' / ');
            if (i === list.length - 1) nav.append(h('strong', { text: c.name }));
            else nav.append(h('a', { href: '#/' + (c.id ? 'f/' + c.id : ''), text: c.name }));
        });
    }

    function renderRows(d) {
        var tb = $('#arBody'); tb.replaceChildren();
        if (!d.items.length) { tb.append(h('tr', {}, h('td', { colspan: 6, class: 'text-center text-body-secondary py-4', text: d.search ? 'Tidak ada hasil.' : 'Folder ini kosong.' }))); return; }
        d.items.forEach(function (it) {
            var isF = it.type === 'folder';
            var cb = h('input', { type: 'checkbox', class: 'form-check-input', 'aria-label': 'Pilih ' + it.name });
            cb.addEventListener('change', function () { if (cb.checked) S.sel.add(it.id); else S.sel.delete(it.id); syncSel(); });
            var name;
            if (isF) { name = h('a', { href: '#/f/' + it.id, class: 'text-decoration-none fw-semibold', text: it.name }); }
            else name = h('span', { text: it.name });
            var badges = h('span', { class: 'ms-1' });
            if (it.private) badges.append(h('span', { class: 'badge text-bg-warning me-1', text: 'pribadi' }));
            if (it.locked) badges.append(h('span', { class: 'badge text-bg-danger me-1', text: 'terkunci' }));
            if (it.deleted) badges.append(h('span', { class: 'badge text-bg-secondary me-1', text: 'dihapus' }));
            if (!isF && !it.available) badges.append(h('span', { class: 'badge text-bg-dark me-1', text: 'isi tidak ada' }));
            var cell = h('div', {}, h('div', {}, icon(isF ? 'bi-folder-fill text-warning' : 'bi-file-earmark text-secondary'), ' ', name, badges));
            if (it.path && d.search) cell.append(h('div', { class: 'small text-body-secondary', text: it.path }));
            if (it.description) cell.append(h('div', { class: 'small text-body-secondary', text: it.description.split('\n')[0] }));
            var act = h('td', { class: 'text-end text-nowrap' });
            if (!isF && it.available) {
                if (it.inline) act.append(h('a', { class: 'btn btn-sm btn-outline-secondary me-1', href: CFG.base + '/file/' + it.id + '?inline=1', target: '_blank', rel: 'noopener', title: 'Lihat' }, icon('bi-eye')));
                act.append(h('a', { class: 'btn btn-sm btn-outline-secondary', href: CFG.base + '/file/' + it.id, title: 'Unduh dari arsip' }, icon('bi-download')));
            }
            tb.append(h('tr', it.deleted ? { class: 'text-body-secondary' } : {},
                h('td', {}, cb), h('td', {}, cell),
                h('td', { class: 'd-none d-md-table-cell small', text: it.owner_name || '—' }),
                h('td', { class: 'text-end small', text: fmtBytes(it.size) }),
                h('td', { class: 'd-none d-lg-table-cell small', text: fmtDate(it.updated_at) }), act));
        });
    }

    function syncSel() {
        var n = S.sel.size;
        $('#arSelCount').textContent = n ? '(' + n + ')' : '';
        $('#arExtract').disabled = n === 0;
        var all = $('#arAll'); all.checked = n > 0 && n === S.items.length; all.indeterminate = n > 0 && n < S.items.length;
    }

    $('#arAll').addEventListener('change', function (e) {
        S.sel.clear();
        if (e.target.checked) S.items.forEach(function (i) { S.sel.add(i.id); });
        document.querySelectorAll('#arBody input[type=checkbox]').forEach(function (c) { c.checked = e.target.checked; });
        syncSel();
    });
    $('#arTrash').addEventListener('change', function (e) { S.trash = e.target.checked; load(); });
    $('#arSearch').addEventListener('input', function (e) { clearTimeout(S.qTimer); S.qTimer = setTimeout(function () { S.q = e.target.value.trim(); load(); }, 300); });
    $('#arCrumbs').addEventListener('click', function (e) {
        if (e.target.dataset && e.target.dataset.clear) { e.preventDefault(); $('#arSearch').value = ''; S.q = ''; load(); }
    });
    function route() {
        var m = /^#\/f\/(\d+)$/.exec(location.hash);
        S.parent = m ? parseInt(m[1], 10) : null; $('#arSearch').value = ''; S.q = ''; load();
    }
    window.addEventListener('hashchange', route);

    // ---------- ekstrak ----------
    var modal = null, job = null, running = false, cancelled = false;
    function modalEl() { if (!modal) modal = bootstrap.Modal.getOrCreateInstance($('#modalExtract')); return modal; }
    function resetModal() {
        $('#exPlan').replaceChildren(); $('#exProgWrap').hidden = true; $('#exSkipped').hidden = true; $('#exErr').hidden = true;
        $('#exOpen').hidden = true; $('#exGo').hidden = true; $('#exGo').disabled = false; $('#exCancel').textContent = 'Batal'; $('#exClose').hidden = false;
        $('#exBar').style.width = '0%'; $('#exBar').classList.add('progress-bar-animated');
    }
    function showErr(msg) { var e = $('#exErr'); e.textContent = msg; e.hidden = false; }

    $('#arExtract').addEventListener('click', function () {
        resetModal(); job = null; running = false; cancelled = false;
        $('#exPlan').textContent = 'Menghitung…'; modalEl().show();
        api('POST', '/plan', { ids: Array.from(S.sel), trash: S.trash }).then(function (p) {
            var box = $('#exPlan'); box.replaceChildren(
                h('p', { class: 'mb-2' }, 'Akan menyalin ', h('strong', { text: p.files + ' berkas (' + fmtBytes(p.bytes) + ')' }), p.folders ? ' dan ' + p.folders + ' folder' : '', ' dari arsip.'),
                h('p', { class: 'mb-2' }, 'Tujuan: ', h('code', { text: p.target }), ' — folder ', h('strong', { text: 'pribadi' }), ' milik Anda. Berkas yang sedang dipakai ', h('strong', { text: 'tidak diubah atau ditimpa' }), '.'),
                h('p', { class: 'mb-1 text-body-secondary', text: 'Pemilik pada salinan menjadi Anda; kunci tidak ikut. Item yang dulunya pribadi tetap pribadi.' }));
            if (p.free != null) box.append(h('p', { class: 'mb-1 ' + (p.enough ? 'text-body-secondary' : 'text-danger fw-semibold'), text: 'Ruang disk tersedia: ' + fmtBytes(p.free) + (p.enough ? '' : ' — TIDAK CUKUP.') }));
            if (p.missing) box.append(h('p', { class: 'mb-0 text-warning', text: p.missing + ' berkas tidak punya isi di backup dan akan dilewati.' }));
            if (p.files === 0 && p.folders === 0) { showErr('Tidak ada yang dapat diekstrak dari pilihan ini.'); return; }
            if (p.enough) $('#exGo').hidden = false;
        }).catch(function (e) { $('#exPlan').textContent = ''; showErr(e.message); });
    });

    $('#exGo').addEventListener('click', function () {
        $('#exGo').disabled = true; $('#exPlan').hidden = true; $('#exProgWrap').hidden = false; $('#exClose').hidden = true;
        $('#exStatus').textContent = 'Menyiapkan…';
        api('POST', '/start', { ids: Array.from(S.sel), trash: S.trash }).then(function (r) {
            job = r.job; running = true; $('#exGo').hidden = true; $('#exOpen').href = CFG.files + '#/f/' + r.target_id;
            loop(r);
        }).catch(function (e) { showErr(e.message); $('#exClose').hidden = false; });
    });

    function loop(init) {
        api('POST', '/step', { job: job }).then(function (d) {
            var totalUnits = d.total_files + d.total_folders || 1, done = d.files_done + d.folders_done;
            $('#exBar').style.width = Math.min(100, Math.round(done / totalUnits * 100)) + '%';
            $('#exStatus').textContent = d.files_done + ' / ' + d.total_files + ' berkas · ' + fmtBytes(d.bytes_done) + ' / ' + fmtBytes(d.total_bytes);
            if (d.skipped_count) {
                var sk = $('#exSkipped'); sk.hidden = false;
                sk.textContent = d.skipped_count + ' item dilewati: ' + d.skipped.slice(0, 5).join('; ') + (d.skipped_count > 5 ? ' …' : '');
            }
            if (d.error) { running = false; showErr(d.error); $('#exCancel').textContent = 'Hentikan'; $('#exClose').hidden = false; $('#exProgWrap').hidden = false; $('#exBar').classList.remove('progress-bar-animated'); return; }
            if (d.finished) {
                running = false; job = null; $('#exBar').style.width = '100%'; $('#exBar').classList.remove('progress-bar-animated');
                $('#exStatus').textContent = 'Selesai: ' + d.files_done + ' berkas (' + fmtBytes(d.bytes_done) + ') dan ' + d.folders_done + ' folder diekstrak.';
                $('#exOpen').hidden = false; $('#exCancel').textContent = 'Tutup'; $('#exClose').hidden = false; return;
            }
            if (cancelled) return;
            loop();
        }).catch(function (e) { running = false; showErr(e.message); $('#exCancel').textContent = 'Hentikan'; $('#exClose').hidden = false; });
    }

    $('#exCancel').addEventListener('click', function () {
        if (job) { cancelled = true; api('POST', '/cancel', { job: job }).catch(function () {}); job = null; }
        modalEl().hide();
    });
    document.getElementById('modalExtract').addEventListener('hidden.bs.modal', function () { $('#exPlan').hidden = false; });

    route();
})();
