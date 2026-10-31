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
5. Buka `https://domainanda.com/install`, isi data database dan akun administrator. Installer membuat `.env`, menjalankan migrasi + seeder, lalu mengunci diri (`writable/installed.lock`).
6. Login. Aktifkan `cookie.secure = true` di `.env` bila memakai HTTPS.

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

Izin: `files.view/download/upload/create_folder/rename/move/copy/delete/share`, `trash.manage`, `users.manage`, `roles.manage`, `shares.manage`, `logs.view`. Peran dan izin dapat diubah di menu Admin. Peran *own-only* hanya boleh mengubah nama/memindah/menghapus/membagikan item miliknya.

## Keamanan
- Semua berkas fisik di `writable/filemanager/files/` dengan nama acak tanpa ekstensi; tidak pernah dapat diakses langsung lewat URL.
- Struktur folder bersifat virtual (database), sehingga path traversal tidak relevan.
- Ekstensi berbahaya (php, phtml, htaccess, dll.) ditolak; nama berkas disanitasi.
- Pratinjau inline hanya untuk tipe aman dengan CSP sandbox; teks tidak dirender sebagai HTML.
- CSRF, throttling login & kata sandi tautan, regenerasi sesi, wajib ganti kata sandi awal, log aktivitas.
- Tautan publik `/s/<token>`: opsional kata sandi, kedaluwarsa, mode hanya-lihat; hanya subtree yang dibagikan yang dapat diakses.

## Unggahan besar
Unggahan dipecah per potongan otomatis mengikuti `upload_max_filesize` / `post_max_size` PHP, jadi berkas besar tetap bisa diunggah di hosting dengan batas kecil. Batas total berkas ada di `app/Config/Filemanager.php` (`maxFileSize`).

## Pemeliharaan
Pembersihan potongan unggahan sementara dan sampah kedaluwarsa (30 hari) berjalan otomatis maksimal sekali per 24 jam saat ada aktivitas — tidak perlu cron. Manual: `php spark fm:cleanup`.

## Endpoint API (JSON, wajib login + header `X-CSRF-TOKEN`)
`GET api/list|tree|info/{id}` · `POST api/folders|rename|move|copy|delete` · `POST api/upload`, `api/upload/abort` · `GET api/trash`, `POST api/trash/restore|purge|empty` · `GET api/share/{id}`, `POST api/share`, `api/share/delete`.

## Pintasan papan ketik
Ctrl+A pilih semua · Ctrl+C/X/V salin/potong/tempel · F2 ubah nama · Del hapus · Enter buka · Backspace naik folder · panah navigasi.
