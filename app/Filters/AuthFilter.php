<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Wajib login. Untuk permintaan API/AJAX balasannya JSON 401; untuk halaman biasa
 * pengguna diarahkan ke /login dan dikembalikan ke halaman tujuan setelah masuk.
 * Pengguna yang wajib ganti kata sandi hanya boleh membuka halaman ganti sandi.
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = service('auth');
        $api  = self::wantsJson($request);

        if (! $auth->check()) {
            if (! \App\Libraries\Installer::isInstalled()) {
                return redirect()->to(site_url('install'));
            }
            if ($api) {
                return service('response')->setStatusCode(401)->setJSON([
                    'ok' => false, 'message' => 'Sesi berakhir. Silakan login kembali.', 'code' => 'unauthenticated',
                ]);
            }
            if ($request->getMethod() === 'GET') {
                session()->set('redirect_url', (string) current_url());
            }

            return redirect()->to(site_url('login'));
        }

        $user = $auth->user();
        if ((int) $user['must_change_password'] === 1) {
            $path = trim($request->getUri()->getPath(), '/');
            if (! in_array($path, ['profile/password', 'logout'], true)) {
                if ($api) {
                    return service('response')->setStatusCode(403)->setJSON([
                        'ok' => false, 'message' => 'Anda harus mengganti kata sandi terlebih dahulu.', 'code' => 'password_change',
                    ]);
                }

                return redirect()->to(site_url('profile/password'));
            }
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }

    public static function wantsJson(RequestInterface $request): bool
    {
        $path = trim($request->getUri()->getPath(), '/');

        return str_starts_with($path, 'api/')
            || str_contains($request->getHeaderLine('Accept'), 'application/json')
            || strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }
}
