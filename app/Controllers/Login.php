<?php

namespace App\Controllers;

class Login extends BaseController
{
    public function index()
    {
        if (! \App\Libraries\Installer::isInstalled()) {
            return redirect()->to(site_url('install'));
        }
        if ($this->auth->check()) {
            return redirect()->to(site_url('files'));
        }

        return view('auth/login');
    }

    public function attempt()
    {
        $username = (string) $this->request->getPost('username');
        $password = (string) $this->request->getPost('password');

        [$user, $error] = $this->auth->attempt($username, $password);
        if ($user === null) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        // Kembalikan ke halaman tujuan semula (hanya URL internal).
        $next = (string) session()->get('redirect_url');
        session()->remove('redirect_url');
        if ($next !== '' && str_starts_with($next, rtrim(site_url(), '/'))) {
            return redirect()->to($next);
        }

        return redirect()->to(site_url('files'));
    }

    public function logout()
    {
        $this->auth->logout();

        return redirect()->to(site_url('login'))->with('success', 'Anda telah keluar.');
    }
}
