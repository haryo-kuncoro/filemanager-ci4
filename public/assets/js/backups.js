/* Halaman Backup & Pemulihan: dialog pulihkan + unggah backup per potongan (JS murni). */
(function () {
    'use strict';
    var CFG = JSON.parse(document.getElementById('bk-config').textContent);

    document.querySelectorAll('[data-restore]').forEach(function (b) {
        b.addEventListener('click', function () {
            document.getElementById('rsName').value = b.dataset.restore;
            document.getElementById('rsLabel').textContent = b.dataset.restore;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRestore')).show();
        });
    });

    var btn = document.getElementById('bkUpload'), input = document.getElementById('bkFile');
    var prog = document.getElementById('bkProg'), msg = document.getElementById('bkMsg');
    if (!btn) return;

    function rnd() {
        var a = new Uint8Array(16); crypto.getRandomValues(a);
        return Array.prototype.map.call(a, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
    }
    function send(uid, offset, total, blob, name) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', CFG.url);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader(CFG.csrfHeader, CFG.csrfHash);
            xhr.onload = function () {
                var d = null; try { d = JSON.parse(xhr.responseText); } catch (e) { /* abaikan */ }
                if (xhr.status >= 200 && xhr.status < 300 && d && d.ok) resolve(d);
                else reject(new Error((d && d.message) || ('Gagal (HTTP ' + xhr.status + ')')));
            };
            xhr.onerror = function () { reject(new Error('Koneksi terputus.')); };
            var fd = new FormData();
            fd.append('upload_id', uid); fd.append('offset', offset); fd.append('size', total);
            fd.append('chunk', blob, name);
            xhr.send(fd);
        });
    }

    btn.addEventListener('click', async function () {
        var f = input.files[0];
        if (!f) { msg.textContent = 'Pilih berkas .zip terlebih dahulu.'; return; }
        if (f.size > CFG.max) { msg.textContent = 'Berkas melebihi batas unggahan.'; return; }
        btn.disabled = true; input.disabled = true; prog.hidden = false;
        var bar = prog.firstElementChild, uid = rnd(), offset = 0, fails = 0;
        try {
            for (;;) {
                var end = Math.min(offset + CFG.chunk, f.size), r;
                try { r = await send(uid, offset, f.size, f.slice(offset, end), f.name); fails = 0; }
                catch (e) { if (++fails >= 3) throw e; await new Promise(function (s) { setTimeout(s, 800 * fails); }); continue; }
                if (r.resync) { offset = r.received; continue; }
                offset = r.received;
                bar.style.width = Math.round(offset / f.size * 100) + '%';
                msg.textContent = 'Mengunggah… ' + Math.round(offset / f.size * 100) + '%';
                if (r.done) break;
            }
            msg.textContent = 'Selesai. Memuat ulang…';
            location.reload();
        } catch (e) {
            msg.textContent = 'Gagal: ' + e.message;
            btn.disabled = false; input.disabled = false;
        }
    });
})();
