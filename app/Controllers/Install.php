<?php

namespace App\Controllers;

use App\Libraries\Installer;
use App\Models\ActivityLogModel;
use App\Models\UserModel;

/**
 * Pemasangan lewat browser (untuk shared hosting tanpa SSH/Composer) dan
 * pemulihan kata sandi darurat bertoken.
 *
 *  - /install        : hanya aktif selama belum terpasang. Setelah selesai terkunci permanen
 *                      oleh writable/installed.lock.
 *  - /install/reset  : hanya aktif bila `fm.resetToken` (>= 20 karakter) diisi di .env.
 *                      Hapus baris itu dari .env setelah selesai.
 */
class Install extends BaseController
{
    private Installer $installer;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->installer = new Installer();
    }

    public function index()
    {
        if (Installer::isInstalled()) {
            return view('install/done', ['state' => 'locked']);
        }

        return view('install/index', [
            'reqs'   => $this->installer->requirements(),
            'met'    => $this->installer->requirementsMet(),
            'upload' => $this->installer->uploadInfo(),
            'values' => $this->defaults(),
        ]);
    }

    public function run()
    {
        if (Installer::isInstalled()) {
            return $this->response->setStatusCode(403)->setBody(view('install/done', ['state' => 'locked']));
        }
        if (! $this->installer->requirementsMet()) {
            return redirect()->to(site_url('install'))->with('error', 'Persyaratan server belum terpenuhi.');
        }

        $rules = [
            'app_url'        => 'required|valid_url_strict|max_length[200]',
            'db_host'        => 'required|max_length[150]',
            'db_port'        => 'required|is_natural_no_zero|less_than[65536]',
            'db_name'        => 'required|max_length[100]',
            'db_user'        => 'required|max_length[100]',
            'db_pass'        => 'permit_empty|max_length[200]',
            'admin_name'     => 'required|min_length[2]|max_length[100]',
            'admin_username' => 'required|alpha_dash|min_length[3]|max_length[50]',
            'admin_email'    => 'permit_empty|valid_email|max_length[150]',
            'admin_password' => 'required',
            'admin_confirm'  => 'required|matches[admin_password]',
        ];
        $messages = ['admin_confirm' => ['matches' => 'Konfirmasi kata sandi admin tidak sama.']];
        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $pwErr = \App\Libraries\PasswordPolicy::check((string) $this->request->getPost('admin_password'), (string) $this->request->getPost('admin_username'), (string) $this->request->getPost('admin_name'));
        if ($pwErr !== null) {
            return redirect()->back()->withInput()->with('errors', ['admin_password' => $pwErr]);
        }

        $p      = $this->request->getPost();
        $result = $this->installer->install([
            'app_url'        => trim((string) $p['app_url']),
            'https'          => str_starts_with(strtolower(trim((string) $p['app_url'])), 'https://'),
            'db_host'        => trim((string) $p['db_host']),
            'db_port'        => (int) $p['db_port'],
            'db_name'        => trim((string) $p['db_name']),
            'db_user'        => trim((string) $p['db_user']),
            'db_pass'        => (string) ($p['db_pass'] ?? ''),
            'admin_name'     => trim((string) $p['admin_name']),
            'admin_username' => trim((string) $p['admin_username']),
            'admin_email'    => trim((string) ($p['admin_email'] ?? '')),
            'admin_password' => (string) $p['admin_password'],
        ]);

        if (! $result['ok']) {
            return redirect()->back()->withInput()->with('error', $result['message']);
        }

        return view('install/done', [
            'state'       => 'installed',
            'env_written' => $result['env_written'],
            'env'         => $result['env'],
            'login_url'   => rtrim((string) $p['app_url'], '/') . '/login',
        ]);
    }

    // ------------------------------------------------------------------
    // Pemulihan kata sandi darurat
    // ------------------------------------------------------------------

    private function resetToken(): string
    {
        $t = (string) env('fm.resetToken', '');

        return strlen($t) >= 20 ? $t : '';
    }

    public function resetForm()
    {
        if ($this->resetToken() === '') {
            return $this->response->setStatusCode(404)->setBody(view('errors/message', ['code' => 404, 'message' => 'Halaman tidak ditemukan.']));
        }

        return view('install/reset');
    }

    public function resetRun()
    {
        $token = $this->resetToken();
        if ($token === '') {
            return $this->response->setStatusCode(404)->setBody(view('errors/message', ['code' => 404, 'message' => 'Halaman tidak ditemukan.']));
        }

        $cfg = config('Filemanager');
        $key = 'fmreset_' . md5($this->request->getIPAddress());
        $st  = cache($key);
        if (is_array($st) && ($st['count'] ?? 0) >= 5) {
            return redirect()->back()->with('error', 'Terlalu banyak percobaan. Coba lagi dalam beberapa menit.');
        }

        if (! $this->validate([
            'token'    => 'required',
            'username' => 'required',
            'password' => 'required',
        ])) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }
        $pwErr = \App\Libraries\PasswordPolicy::check((string) $this->request->getPost('password'), (string) $this->request->getPost('username'));
        if ($pwErr !== null) {
            return redirect()->back()->with('errors', ['password' => $pwErr]);
        }

        if (! hash_equals($token, (string) $this->request->getPost('token'))) {
            cache()->save($key, ['count' => (int) ($st['count'] ?? 0) + 1], 600);

            return redirect()->back()->with('error', 'Token tidak valid.');
        }

        $users = new UserModel();
        $user  = $users->findByUsername(trim((string) $this->request->getPost('username')));
        if ($user === null) {
            return redirect()->back()->with('error', 'Pengguna tidak ditemukan.');
        }

        $users->update((int) $user['id'], [
            'password_hash'        => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
            'must_change_password' => 1,
            'is_active'            => 1,
        ]);
        ActivityLogModel::record('password_reset_token', ['id' => (int) $user['id'], 'username' => $user['username']], null, $user['username']);

        return redirect()->to(site_url('login'))->with('success', 'Kata sandi direset. Segera hapus "fm.resetToken" dari berkas .env.');
    }

    // ------------------------------------------------------------------

    private function defaults(): array
    {
        $old = static fn (string $k, $d = '') => old($k, $d);

        return [
            'app_url'        => $old('app_url', rtrim(config('App')->baseURL, '/') . '/'),
            'db_host'        => $old('db_host', 'localhost'),
            'db_port'        => $old('db_port', '3306'),
            'db_name'        => $old('db_name'),
            'db_user'        => $old('db_user'),
            'admin_name'     => $old('admin_name', 'Administrator'),
            'admin_username' => $old('admin_username', 'admin'),
            'admin_email'    => $old('admin_email'),
        ];
    }
}
