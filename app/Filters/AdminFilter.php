<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/** Hanya peran bawaan "administrator" (tidak bisa diberikan lewat izin peran lain). */
class AdminFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = service('auth');
        if (! $auth->check() || $auth->isAdministrator()) {
            return null;
        }
        if (AuthFilter::wantsJson($request)) {
            return service('response')->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'Hanya administrator.', 'code' => 'forbidden']);
        }

        return service('response')->setStatusCode(403)->setBody(view('errors/forbidden'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
