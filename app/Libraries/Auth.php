<?php

namespace App\Libraries;

use App\Models\ActivityLogModel;
use App\Models\RoleModel;
use App\Models\UserModel;
use Config\Filemanager;

/**
 * Autentikasi berbasis sesi + RBAC.
 * Peran & izin dimuat dari database di setiap request sehingga perubahan
 * peran/penonaktifan akun berlaku seketika.
 */
class Auth
{
    private ?array $user = null;
    private bool $loaded = false;

    // ------------------------------------------------------------------
    // Status & data pengguna
    // ------------------------------------------------------------------

    /** Pengguna aktif beserta 'role' dan 'permissions', atau null bila belum login. */
    public function user(): ?array
    {
        if ($this->loaded) {
            return $this->user;
        }
        $this->loaded = true;

        $uid = session()->get('uid');
        if (! $uid) {
            return $this->user = null;
        }

        $row = (new UserModel())->find((int) $uid);
        if ($row === null || (int) $row['is_active'] !== 1) {
            session()->remove('uid');

            return $this->user = null;
        }

        $roles = new RoleModel();
        $role  = $roles->find((int) $row['role_id']);
        unset($row['password_hash']);

        $row['role']        = $role ?? ['id' => 0, 'name' => '-', 'label' => '-', 'own_only' => 1];
        $row['permissions'] = $role ? $roles->permissionCodes((int) $role['id']) : [];

        return $this->user = $row;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        $u = $this->user();

        return $u ? (int) $u['id'] : null;
    }

    public function can(string $code): bool
    {
        $u = $this->user();

        return $u !== null && in_array($code, $u['permissions'], true);
    }

    /** Harus memiliki SEMUA izin. */
    public function canAll(array $codes): bool
    {
        foreach ($codes as $c) {
            if (! $this->can($c)) {
                return false;
            }
        }

        return true;
    }

    /** Hanya peran bawaan "administrator" (bukan sekadar punya izin) yang lolos. */
    public function isAdministrator(): bool
    {
        $u = $this->user();

        return $u !== null && ($u['role']['name'] ?? '') === 'administrator';
    }

    public function isOwnOnly(): bool
    {
        $u = $this->user();

        return $u === null || (int) ($u['role']['own_only'] ?? 1) === 1;
    }

    /**
     * Boleh melakukan aksi pengubahan ($perm) pada node ini?
     * Peran "own_only" hanya boleh mengubah item miliknya sendiri.
     */
    public function canModifyNode(array $node, string $perm): bool
    {
        if (! $this->can($perm)) {
            return false;
        }
        if (! $this->isOwnOnly()) {
            return true;
        }

        return isset($node['owner_id']) && (int) $node['owner_id'] === $this->id();
    }

    // ------------------------------------------------------------------
    // Login / logout
    // ------------------------------------------------------------------

    /**
     * Coba login. Mengembalikan [array|null $user, string|null $error].
     *
     * @return array{0: array|null, 1: string|null}
     */
    public function attempt(string $username, string $password): array
    {
        /** @var Filemanager $cfg */
        $cfg      = config('Filemanager');
        $username = trim($username);
        $key      = 'login_' . md5(service('request')->getIPAddress() . '|' . mb_strtolower($username));

        $state = cache($key);
        if (is_array($state) && ($state['count'] ?? 0) >= $cfg->loginMaxAttempts) {
            $wait = max(1, ($state['until'] ?? time()) - time());

            return [null, 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . ceil($wait / 60) . ' menit.'];
        }

        $user = $username !== '' ? (new UserModel())->findByUsername($username) : null;

        // Selalu jalankan verifikasi agar waktu respons tidak membocorkan keberadaan akun.
        $hash  = $user['password_hash'] ?? '$2y$10$jEHZkFaydG8dVXFoQNkreuDFUPK12b/6drco5HKoyrj95TNzJ.zza';
        $valid = password_verify($password, $hash);

        if (! $user || ! $valid) {
            $count = (int) ($state['count'] ?? 0) + 1;
            cache()->save($key, ['count' => $count, 'until' => time() + $cfg->loginLockSeconds], $cfg->loginLockSeconds);
            ActivityLogModel::record('login_failed', ['id' => null, 'username' => $username], null, $username);

            return [null, 'Username atau kata sandi salah.'];
        }

        if ((int) $user['is_active'] !== 1) {
            return [null, 'Akun Anda dinonaktifkan. Hubungi administrator.'];
        }

        cache()->delete($key);

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            (new UserModel())->update((int) $user['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        }

        $this->login($user);

        return [$this->user(), null];
    }

    public function login(array $user): void
    {
        session()->regenerate(true); // cegah session fixation
        session()->set('uid', (int) $user['id']);

        (new UserModel())->update((int) $user['id'], [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => service('request')->getIPAddress(),
        ]);

        $this->loaded = false;
        $this->user   = null;
        ActivityLogModel::record('login', $this->user() ?? ['id' => $user['id'], 'username' => $user['username']]);
    }

    public function logout(): void
    {
        $u = $this->user();
        if ($u) {
            ActivityLogModel::record('logout', $u);
        }
        session()->destroy();
        $this->user   = null;
        $this->loaded = true;
    }

    /** Muat ulang data pengguna (mis. setelah ganti peran/profil). */
    public function reload(): void
    {
        $this->loaded = false;
        $this->user   = null;
    }
}
