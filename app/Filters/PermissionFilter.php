<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Pemeriksaan izin RBAC tingkat-rute. Contoh: 'filter' => 'perm:files.delete'
 * Beberapa izin dipisah koma = pengguna harus memiliki SEMUANYA.
 */
class PermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = service('auth');
        if (! $auth->check()) {
            return null; // AuthFilter yang menangani
        }
        if ($arguments === null || $arguments === [] || $auth->canAll($arguments)) {
            return null;
        }

        if (AuthFilter::wantsJson($request)) {
            return service('response')->setStatusCode(403)->setJSON([
                'ok' => false, 'message' => 'Anda tidak memiliki izin untuk aksi ini.', 'code' => 'forbidden',
            ]);
        }

        return service('response')->setStatusCode(403)->setBody(view('errors/forbidden'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
