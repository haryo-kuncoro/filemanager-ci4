<?php

namespace App\Controllers;

use App\Models\ActivityLogModel;
use App\Models\UserModel;

class Profile extends BaseController
{
    public function index()
    {
        return view('profile/index', ['title' => 'Profil Saya', 'user' => $this->auth->user()]);
    }

    public function update()
    {
        $rules = [
            'name'  => 'required|min_length[2]|max_length[100]',
            'email' => 'permit_empty|valid_email|max_length[150]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        (new UserModel())->update($this->auth->id(), [
            'name'  => trim((string) $this->request->getPost('name')),
            'email' => trim((string) $this->request->getPost('email')) ?: null,
        ]);

        return redirect()->to(site_url('profile'))->with('success', 'Profil diperbarui.');
    }

    public function password()
    {
        return view('profile/password', [
            'title' => 'Ganti Kata Sandi',
            'forced' => (int) $this->auth->user()['must_change_password'] === 1,
        ]);
    }

    public function updatePassword()
    {
        $rules = [
            'current_password' => 'required',
            'new_password'     => 'required',
            'confirm_password' => 'required|matches[new_password]',
        ];
        $messages = [
            'confirm_password' => ['matches' => 'Konfirmasi kata sandi tidak sama.'],
        ];
        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $pwErr = \App\Libraries\PasswordPolicy::check((string) $this->request->getPost('new_password'), (string) ($this->auth->user()['username'] ?? ''), (string) ($this->auth->user()['name'] ?? ''));
        if ($pwErr !== null) {
            return redirect()->back()->with('errors', ['new_password' => $pwErr]);
        }

        $users = new UserModel();
        $row   = $users->find($this->auth->id());
        if (! password_verify((string) $this->request->getPost('current_password'), $row['password_hash'])) {
            return redirect()->back()->with('errors', ['current_password' => 'Kata sandi saat ini salah.']);
        }
        if (password_verify((string) $this->request->getPost('new_password'), $row['password_hash'])) {
            return redirect()->back()->with('errors', ['new_password' => 'Kata sandi baru harus berbeda dari yang lama.']);
        }

        $users->update($this->auth->id(), [
            'password_hash'        => password_hash((string) $this->request->getPost('new_password'), PASSWORD_DEFAULT),
            'must_change_password' => 0,
        ]);
        ActivityLogModel::record('password_change', $this->auth->user());

        // Segarkan sesi setelah perubahan kredensial.
        session()->regenerate(true);
        $this->auth->reload();

        return redirect()->to(site_url('files'))->with('success', 'Kata sandi berhasil diganti.');
    }
}
