<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ActivityLogModel;
use App\Models\NodeModel;
use App\Models\RoleModel;
use App\Models\UserModel;

class Users extends BaseController
{
    private UserModel $users;
    private RoleModel $roles;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->users = new UserModel();
        $this->roles = new RoleModel();
    }

    public function index()
    {
        return view('admin/users/index', ['title' => 'Pengguna', 'users' => $this->users->withRoles()]);
    }

    public function create()
    {
        return view('admin/users/form', [
            'title' => 'Tambah Pengguna', 'user' => null, 'roles' => $this->roles->orderBy('id')->findAll(),
        ]);
    }

    public function store()
    {
        $min   = config('Filemanager')->minPasswordLength;
        $rules = [
            'name'     => 'required|min_length[2]|max_length[100]',
            'username' => 'required|alpha_dash|min_length[3]|max_length[50]|is_unique[users.username]',
            'email'    => 'permit_empty|valid_email|max_length[150]',
            'password' => "required|min_length[{$min}]|max_length[72]",
            'role_id'  => 'required|is_natural_no_zero|is_not_unique[roles.id]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $id = $this->users->insert([
            'name'                 => trim((string) $this->request->getPost('name')),
            'username'             => strtolower(trim((string) $this->request->getPost('username'))),
            'email'                => trim((string) $this->request->getPost('email')) ?: null,
            'password_hash'        => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
            'role_id'              => (int) $this->request->getPost('role_id'),
            'is_active'            => $this->request->getPost('is_active') ? 1 : 0,
            'must_change_password' => $this->request->getPost('must_change_password') ? 1 : 0,
        ], true);

        ActivityLogModel::record('user_create', $this->auth->user(), null, (string) $this->request->getPost('username'), ['user_id' => $id]);

        return redirect()->to(site_url('admin/users'))->with('success', 'Pengguna ditambahkan.');
    }

    public function edit(int $id)
    {
        $user = $this->users->find($id);
        if ($user === null) {
            return redirect()->to(site_url('admin/users'))->with('error', 'Pengguna tidak ditemukan.');
        }

        return view('admin/users/form', [
            'title' => 'Ubah Pengguna', 'user' => $user, 'roles' => $this->roles->orderBy('id')->findAll(),
        ]);
    }

    public function update(int $id)
    {
        $user = $this->users->find($id);
        if ($user === null) {
            return redirect()->to(site_url('admin/users'))->with('error', 'Pengguna tidak ditemukan.');
        }

        $min   = config('Filemanager')->minPasswordLength;
        $rules = [
            'name'     => 'required|min_length[2]|max_length[100]',
            'username' => "required|alpha_dash|min_length[3]|max_length[50]|is_unique[users.username,id,{$id}]",
            'email'    => 'permit_empty|valid_email|max_length[150]',
            'password' => "permit_empty|min_length[{$min}]|max_length[72]",
            'role_id'  => 'required|is_natural_no_zero|is_not_unique[roles.id]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $newRole = (int) $this->request->getPost('role_id');
        $active  = $this->request->getPost('is_active') ? 1 : 0;

        // Jangan sampai tidak ada administrator aktif yang tersisa.
        $isSelf = $id === $this->auth->id();
        if ($isSelf && $active === 0) {
            return redirect()->back()->withInput()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }
        $newRoleHasManage = in_array('users.manage', $this->roles->permissionCodes($newRole), true);
        if ((! $newRoleHasManage || $active === 0) && $this->isLastAdmin($id)) {
            return redirect()->back()->withInput()->with('error', 'Harus ada minimal satu pengguna aktif dengan izin "Mengelola pengguna".');
        }

        $data = [
            'name'                 => trim((string) $this->request->getPost('name')),
            'username'             => strtolower(trim((string) $this->request->getPost('username'))),
            'email'                => trim((string) $this->request->getPost('email')) ?: null,
            'role_id'              => $newRole,
            'is_active'            => $active,
            'must_change_password' => $this->request->getPost('must_change_password') ? 1 : 0,
        ];
        $pwd = (string) $this->request->getPost('password');
        if ($pwd !== '') {
            $data['password_hash'] = password_hash($pwd, PASSWORD_DEFAULT);
        }
        $this->users->update($id, $data);

        ActivityLogModel::record('user_update', $this->auth->user(), null, $data['username'], ['user_id' => $id, 'password_reset' => $pwd !== '']);

        return redirect()->to(site_url('admin/users'))->with('success', 'Pengguna diperbarui.');
    }

    public function delete(int $id)
    {
        $user = $this->users->find($id);
        if ($user === null) {
            return redirect()->to(site_url('admin/users'))->with('error', 'Pengguna tidak ditemukan.');
        }
        if ($id === $this->auth->id()) {
            return redirect()->to(site_url('admin/users'))->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }
        if ($this->isLastAdmin($id)) {
            return redirect()->to(site_url('admin/users'))->with('error', 'Tidak dapat menghapus administrator terakhir.');
        }

        // Berkas milik pengguna ini dialihkan ke admin yang menghapus agar tidak yatim.
        (new NodeModel())->builder()->where('owner_id', $id)->update(['owner_id' => $this->auth->id()]);
        $this->users->delete($id);

        ActivityLogModel::record('user_delete', $this->auth->user(), null, $user['username']);

        return redirect()->to(site_url('admin/users'))->with('success', 'Pengguna dihapus. Berkas miliknya dialihkan ke Anda.');
    }

    /** Apakah pengguna ini satu-satunya yang aktif dengan izin users.manage? */
    private function isLastAdmin(int $id): bool
    {
        $row = $this->users->find($id);
        if ($row === null || (int) $row['is_active'] !== 1) {
            return false;
        }
        $hasManage = in_array('users.manage', $this->roles->permissionCodes((int) $row['role_id']), true);

        return $hasManage && $this->users->countActiveAdmins($id) === 0;
    }
}
