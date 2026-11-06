# File Manager CI4

File manager ala elFinder berbasis **CodeIgniter 4.7**, Bootstrap 5 + Bootstrap Icons, dengan RBAC, tautan berbagi publik, dan seluruh berkas disimpan di `WRITEPATH` (di luar web root).

**Murni PHP — tidak butuh Python, Node.js, Composer, maupun akses SSH/cron.** Folder `vendor/` sudah disertakan dan aset (Bootstrap, ikon) sudah lokal.

## Kebutuhan hosting
- PHP 8.2+ dengan ekstensi: `intl`, `mbstring`, `json`, `mysqlnd`, `fileinfo` (wajib); `zip` (unduh ZIP) dan `gd` (thumbnail) bersifat opsional.
- MySQL/MariaDB.
- Apache dengan `mod_rewrite` (atau lihat "Tanpa mod_rewrite").

## Pemasangan di shared hosting (tanpa SSH)
1. Unggah isi ZIP ke hosting lalu ekstrak.
2. **Opsi A (paling mudah):** biarkan semua di folder situs (mis. `public_html/`). File `.htaccess` di root otomatis mengarahkan ke `public/`, dan folder `app`, `vendor`, `writable` sudah diblokir.
   **Opsi B (paling aman):** arahkan document root domain ke folder `public/`.
3. Pastikan folder `writable/` dapat ditulis (chmod 755/775).
4. Buat database MySQL di panel hosting.
5. Buka `https://domainanda.com/install`, isi data database dan akun administrator. Installer membuat `.env`, menjalankan migrasi + seeder, lalu mengunci diri (`writable/installed.lock`). Jika halaman instalasi tidak muncul, hapus file `installed.lock` di dalam folder `writable`.
6. Login. Bila situs dibuka lewat HTTPS, cookie otomatis ditandai `Secure` (tidak perlu diatur manual; isi `cookie.secure = false` di `.env` hanya bila ingin menonaktifkan).

Pemasangan lewat SSH (opsional): salin `env` menjadi `.env`, isi database, lalu `php spark migrate` dan `ADMIN_PASSWORD=... php spark db:seed InitialSeeder`.

### Tanpa mod_rewrite
Tambahkan `app.indexPage = 'index.php'` di `.env`.

### Lupa kata sandi admin
- SSH: `php spark fm:reset-password admin`.
- Tanpa SSH: isi `fm.resetToken` (≥ 20 karakter acak) di `.env`, buka `/install/reset`, lalu hapus barisnya.

## Peran (RBAC)
| Peran | Akses |
|---|---|
| administrator | semua izin |
| manager | kelola berkas, sampah, tautan, log |
| editor | kelola berkas **miliknya sendiri** (own-only) |
| viewer | lihat & unduh saja (own-only) |

**Akun administrator dilindungi (v1.9.0):** hanya peran `administrator` yang dapat melihat, mengubah, menghapus, atau membuat akun ber-peran administrator, serta mengubah peran administrator. Pengguna non-administrator yang diberi izin `users.manage` hanya melihat dan mengelola akun non-administrator, dan pilihan peran administrator disembunyikan dari formulirnya. Percobaan akses ditolak dan dicatat di log (`user_denied`).

Izin: `files.view/download/upload/create_folder/rename/move/copy/delete/share`, `trash.manage`, `users.manage`, `roles.manage`, `shares.manage`, `logs.view`, `settings.manage`, `backup.manage/restore`. Peran dan izin dapat diubah di menu Admin. Peran *own-only* hanya boleh mengubah nama/memindah/menghapus/membagikan item miliknya.

## Halaman masuk & halaman 404
- **Halaman masuk** berupa landing page dua kolom: penjelasan singkat fitur di kiri, form masuk di kanan (di layar kecil form tampil lebih dulu). Mendukung mode terang/gelap. Teks ada di `app/Views/auth/login.php`, gaya `.lp-*` di `public/assets/css/app.css`.
- **Halaman 404 kustom** (`app/Views/errors/not_found.php`) tampil untuk alamat yang tidak dikenal, dengan status HTTP 404 yang benar. Tombol utamanya menuju **Berkas** bila sudah masuk, atau **halaman masuk** bila belum. Permintaan `/api/...` mendapat JSON, bukan HTML. Dipasang lewat `set404Override` di `app/Config/Routes.php`.

## PWA (dapat dipasang sebagai aplikasi)
Aplikasi dapat dipasang di HP/laptop (Chrome/Edge: tombol **Pasang** di bilah atas atau menu browser; iPhone: Bagikan → Tambahkan ke Layar Utama). Syaratnya **HTTPS** (atau `localhost`); di HTTP biasa browser tidak mengaktifkan service worker.
- Manifest dinamis di `/manifest.webmanifest` (nama mengikuti `appName`, URL mengikuti `baseURL`, aman untuk subfolder). Ikon di `public/assets/icons/` — ganti dengan logo Anda (ukuran sama).
- Service worker `public/sw.js` **hanya menyimpan aset statis** (`/assets/...`). Halaman, API, unduhan, pratinjau, dan tautan berbagi tidak pernah di-cache, jadi tidak ada data pengguna di perangkat. Saat offline tampil halaman "Tidak ada koneksi"; berkas tetap membutuhkan internet.
- Setelah memperbarui aplikasi, ubah `VERSION` di `public/sw.js` agar cache aset lama dibersihkan.

**Muncul "Tidak ada koneksi internet" padahal online?** Sejak v1.8.1 service worker mencoba ulang sekali saat permintaan halaman gagal, dan membedakan *perangkat offline* dari *server tidak merespons*. Jika pesan "Server tidak merespons" sering muncul, periksa error log hosting (batas proses/CPU/memori, LiteSpeed/Apache memutus koneksi). Untuk memaksa pembaruan: DevTools → Application → Service Workers → Unregister, lalu muat ulang.

## Pengaturan & logo
Menu **Administrasi → Pengaturan** (izin `settings.manage`, bawaan hanya `administrator`, dapat diberikan ke peran lain di Peran & Izin).
- **Logo aplikasi** tampil di atas panel login; jika belum diatur, tidak ada logo. Format PNG/JPG/GIF/WebP maks. 1 MB (disarankan PNG transparan). Bila ekstensi `gd` aktif, gambar otomatis diperkecil (maks. 600 px) dan dirender ulang ke PNG sehingga metadata/muatan tersembunyi terbuang (GIF animasi menjadi gambar diam).
- **SVG tidak didukung** karena dapat memuat skrip.
- Logo disimpan di database (tabel `settings`, base64), sehingga **ikut backup & pemulihan**. Backup lama tanpa tabel ini tidak menghapus logo yang ada.
- Dilayani publik di `/brand/logo` (dibutuhkan halaman login) dengan header `nosniff` dan CSP sandbox.
- Migrasi baru `2026-11-01-050007_CreateSettingsTable`: dijalankan otomatis saat administrator login, atau lewat Pembaruan Database. Selama belum dijalankan, halaman login tetap normal (tanpa logo).

## Daftar berkas
- **Ukuran folder** = akumulasi seluruh berkas di dalamnya (rekursif). Yang dihitung hanya berkas yang terlihat oleh Anda: isi folder pribadi milik orang lain dan berkas di sampah tidak ikut. Mengurutkan berdasarkan **Ukuran** juga memakai angka ini (tetap dalam kelompok pribadi/bersama).
- Nama panjang dipotong dengan "…" (nama lengkap tampil saat kursor diarahkan) sehingga tidak menimpa kolom ukuran; ikon kunci/pribadi/tautan tetap terlihat.

## Kunci folder / berkas
- Pilih item lalu klik ikon gembok (atau klik kanan → **Kunci**). **Hanya pemilik item** yang dapat mengunci dan membuka kunci — administrator pun tidak (kecuali jika pemilik asli dihapus; item otomatis dialihkan ke admin yang menghapusnya).
- Item terkunci tidak dapat diubah nama, dipindah, atau dihapus oleh siapa pun (termasuk pemiliknya, sampai dibuka kuncinya).
- Folder terkunci membekukan isinya: seluruh isi tidak dapat diubah/dipindah/dihapus, dan tidak dapat ditambah file/folder baru (unggah, folder baru, tempel, pindah ke dalamnya). Folder yang memuat item terkunci tidak dapat dihapus.
- Item terkunci tetap bisa dilihat, diunduh, disalin (salinan tidak ikut terkunci), dan dibagikan lewat tautan.

## Pribadi vs bersama
- Setiap pemilik dapat menandai folder/berkas miliknya **Pribadi** (ikon mata dicoret) atau **Bersama** (bawaan) lewat toolbar atau klik kanan.
- Item pribadi **hanya terlihat oleh pemiliknya** — pengguna lain, termasuk administrator, tidak melihatnya di daftar, pohon, pencarian, unduhan, ZIP, maupun lewat ID (dijawab "tidak ditemukan"). Folder pribadi menyembunyikan seluruh isinya.
- Urutan daftar: folder pribadi → folder bersama → berkas pribadi → berkas bersama (di dalam tiap kelompok mengikuti kolom urut yang dipilih).
- Folder yang sudah berisi item milik pengguna lain tidak dapat dijadikan pribadi, dan item milik orang lain tidak dapat dimasukkan ke folder pribadi (agar tidak ada item yang "hilang" dari pemiliknya). Folder yang memuat item pribadi orang lain tidak dapat dihapus oleh selain pemiliknya; menyalin/ZIP folder bersama tidak menyertakan item pribadi orang lain.
- Tautan publik tidak pernah menyertakan item pribadi di dalam folder yang dibagikan (folder yang sengaja dibagikan sendiri tetap dapat diakses lewat tautannya).
- Catatan: jika akun pengguna dihapus, item miliknya (termasuk yang pribadi) dialihkan ke administrator yang menghapus. Nama item pribadi dapat muncul di Log Aktivitas, dan nama yang sama dalam satu folder tetap dianggap bentrok.

## Deskripsi
Folder dan berkas dapat diberi deskripsi (maks. 500 karakter) lewat tombol **Deskripsi** / klik kanan. Deskripsi tampil di bawah nama, di Properti, dan ikut dicari. Hak ubah sama seperti mengganti nama (item terkunci harus dibuka kuncinya dulu). Deskripsi tidak ditampilkan di halaman tautan publik.

## Backup & pemulihan
Menu **Administrasi → Backup & Pemulihan**. Diatur lewat izin peran (Administrasi → Peran & Izin); secara bawaan hanya `administrator` yang memilikinya:
- `backup.manage` — buat, unduh, unggah, hapus backup.
- `backup.restore` — memulihkan (wajib disertai `backup.manage`).

> **Peringatan:** backup berisi hash kata sandi SELURUH pengguna, dan pemulihan mengganti semua data (termasuk akun & peran), sehingga pemegang izin ini praktis setara akses penuh. Berikan hanya ke orang yang sangat dipercaya; `backup.restore` sebaiknya hanya untuk administrator.

Halaman Pembaruan Database tetap khusus peran `administrator`.
- **Buat backup**: satu ZIP berisi dump database (pengguna + hash sandi, peran, folder/berkas, tautan, log) dan seluruh isi berkas. Murni PHP (ext `zip`), tanpa mysqldump/exec. Disimpan di `writable/filemanager/backups/`.
- **Unduh** salinan ke komputer Anda — ini yang dipakai bila server/hosting diganti atau diinstal ulang.
- **Unggah** backup (per potongan, aman untuk berkas besar) atau taruh lewat FTP di folder di atas.
- **Pulihkan**: mengganti SELURUH data dengan isi backup. Butuh kata sandi Anda + ketik `PULIHKAN`. Sebelum memulihkan dibuat backup pengaman otomatis (`backup-sebelum-pulih-*.zip`); bila pemulihan database gagal, data lama tetap utuh (transaksi). Setelah selesai Anda keluar dan login dengan akun dari backup.
- Alur install ulang: pasang aplikasi (`/install`), login, unggah backup, Pulihkan.
- Backup bisa dipulihkan lintas versi aplikasi (kolom yang tidak ada diabaikan / memakai nilai bawaan).

## Jelajahi arsip backup (tanpa memulihkan)
Untuk melihat berkas lama dari backup **tanpa** menimpa data yang sedang dipakai: **Administrasi → Backup & Pemulihan → Jelajahi** pada backup yang diinginkan. Khusus peran `administrator` (tidak dapat didelegasikan lewat izin peran).
- **Mode baca**: isi arsip dibuka langsung dari berkas ZIP, tidak ada yang dipulihkan. Telusuri folder, cari nama/deskripsi, lihat pemilik asli, **lihat** (gambar/PDF/audio/video yang aman) atau **unduh** satu berkas dari arsip.
- Administrator melihat **seluruh** item arsip, **milik siapa pun, pribadi, maupun terkunci**. Setiap pembukaan arsip dan unduhan dicatat di Log Aktivitas. Tombol *Tampilkan yang sudah dihapus* memperlihatkan item yang saat backup berada di sampah.
- **Ekstrak**: pilih item (berkas/folder) lalu *Ekstrak*. Salinannya masuk ke folder baru `Pemulihan Arsip / Arsip <tanggal> — <nama backup>` di Beranda; **berkas dan folder yang sedang dipakai tidak disentuh sama sekali**. Mengekstrak lagi membuat subfolder baru, sehingga tidak ada yang bentrok atau bercampur.
  - Folder tujuan bersifat **pribadi** (hanya terlihat oleh administrator yang mengekstrak). Ubah ke *Bersama* atau pindahkan hasilnya bila ingin dibuka untuk pengguna lain.
  - Pemilik salinan menjadi administrator yang mengekstrak; **kunci tidak ikut**; item yang dulunya pribadi tetap pribadi; deskripsi dan tanggal ubah asli dipertahankan.
  - Berjalan **per batch** (±8 detik) dengan indikator progres, sehingga aman untuk arsip besar di shared hosting tanpa SSH. Sebelum mulai dicek ruang disk (butuh ukuran data + 50 MB cadangan). Bila disk penuh di tengah jalan, proses berhenti dengan pesan jelas dan yang sudah terekstrak tetap ada.
  - Berkas yang isinya tidak ada di backup, atau bernama tidak valid, dilewati dan dilaporkan (tidak menggagalkan seluruh proses).
- Keamanan: path berkas di dalam ZIP dihitung dari `storage_name` yang divalidasi (32 heksadesimal), tidak pernah dari nama entri arsip, sehingga arsip yang dimanipulasi tidak dapat membaca/menulis berkas di luar penyimpanan.
- Batasan: indeks pohon arsip dimuat ke memori saat dibuka (arsip dengan >100 ribu item butuh `memory_limit` PHP yang lega). Folder tidak dapat diunduh langsung dari arsip; ekstrak dulu lalu unduh dari Beranda.

## Memperbarui database tanpa SSH (migrasi)
Saat ada versi baru dengan berkas migrasi baru (`app/Database/Migrations/`):
1. Unggah berkas aplikasi baru lewat FTP/File Manager hosting (pertahankan `.env` dan folder `writable/`).
2. Login sebagai administrator. Migrasi tertunda dijalankan **otomatis sekali**. Bila berhasil tidak ada yang perlu dilakukan.
3. Jika gagal (atau ingin mengontrol sendiri), muncul peringatan kuning. Buka **Administrasi → Pembaruan Database**, lihat daftar migrasi tertunda, lalu klik **Jalankan migrasi terbaru**. Ada opsi backup pengaman otomatis sebelum menjalankan.

Hanya migrasi yang belum tercatat di tabel `migrations` yang dijalankan; **data yang ada tidak diganti**. Untuk membuat migrasi baru, tambahkan berkas `app/Database/Migrations/YYYY-MM-DD-HHMMSS_NamaKelas.php` (nama harus lebih baru dari yang sudah ada). Catatan: MySQL tidak dapat membatalkan perubahan struktur (DDL) di tengah jalan, jadi biarkan opsi backup pengaman tetap aktif.

## Keamanan
- Semua berkas fisik di `writable/filemanager/files/` dengan nama acak tanpa ekstensi; tidak pernah dapat diakses langsung lewat URL.
- Struktur folder bersifat virtual (database), sehingga path traversal tidak relevan.
- Ekstensi berbahaya (php, phtml, htaccess, dll.) ditolak; nama berkas disanitasi.
- Pratinjau inline hanya untuk tipe aman dengan CSP sandbox; teks tidak dirender sebagai HTML.
- CSRF, throttling login & kata sandi tautan, regenerasi sesi, wajib ganti kata sandi awal, log aktivitas.
- **Kebijakan kata sandi** (`app/Libraries/PasswordPolicy.php`, dipakai instalasi, profil, admin pengguna, reset): minimal 10 karakter (atur di `minPasswordLength`), maksimal 72 byte (batas bcrypt), memuat huruf + angka/simbol, tidak berspasi di tepi, tidak terlalu monoton/umum, dan tidak memuat username. Berlaku untuk kata sandi baru; akun lama tidak terpengaruh.
- **Header keamanan** di semua halaman: `X-Frame-Options`, `nosniff`, `Referrer-Policy` (bawaan CI4) ditambah CSP ringan (`frame-ancestors`, `base-uri`, `form-action`, `object-src`), `Permissions-Policy`, dan **HSTS** (hanya lewat HTTPS, 180 hari, tanpa includeSubDomains/preload). Pratinjau berkas tetap memakai CSP sandbox sendiri. Lihat `app/Filters/HardenHeaders.php`.
- Di belakang proxy/CDN (mis. Cloudflare), HTTPS dikenali dari header `X-Forwarded-Proto`.
- Rekomendasi server: pakai HTTPS, `CI_ENVIRONMENT = production`, ganti kata sandi admin awal, simpan backup di luar server (berisi hash kata sandi semua pengguna).
- Tautan publik `/s/<token>`: opsional kata sandi, kedaluwarsa, mode hanya-lihat; hanya subtree yang dibagikan yang dapat diakses.

## Jenis berkas yang boleh diunggah
Semua ekstensi diizinkan kecuali daftar larangan di `app/Config/Filemanager.php` (`blockedExtensions`). Daftar ini, contoh jenis umum, dan batas ukuran ditampilkan kepada pengguna di halaman Berkas (tombol **Jenis berkas** di toolbar atau tautan di bawah sidebar) dan otomatis mengikuti konfigurasi.

## Unggahan besar
Unggahan dipecah per potongan otomatis mengikuti `upload_max_filesize` / `post_max_size` PHP, jadi berkas besar tetap bisa diunggah di hosting dengan batas kecil. Batas total berkas ada di `app/Config/Filemanager.php` (`maxFileSize`).

## Pemeliharaan
Pembersihan potongan unggahan sementara dan sampah kedaluwarsa (30 hari) berjalan otomatis maksimal sekali per 24 jam saat ada aktivitas — tidak perlu cron. Manual: `php spark fm:cleanup`.

## Endpoint API (JSON, wajib login + header `X-CSRF-TOKEN`)
`GET api/list|tree|info/{id}` · `POST api/folders|rename|move|copy|delete|lock|visibility|describe` · `POST api/upload`, `api/upload/abort` · `GET api/trash`, `POST api/trash/restore|purge|empty` · `GET api/share/{id}`, `POST api/share`, `api/share/delete`.

## Pintasan papan ketik
Ctrl+A pilih semua · Ctrl+C/X/V salin/potong/tempel · F2 ubah nama · Del hapus · Enter buka · Backspace naik folder · panah navigasi.
