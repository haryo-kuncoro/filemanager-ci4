<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ActivityLogModel;
use App\Models\RoleModel;

class Roles extends BaseController
{
    private RoleModel $roles;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->roles = new RoleModel();
    }

    public function index()
    {
        return view('admin/roles/index', ['title' => 'Peran & Izin', 'roles' => $this->roles->withCounts()]);
    }

    public function create()
    {
        return view('admin/roles/form', [
            'title' => 'Tambah Peran', 'role' => null, 'groups' => $this->roles->groupedPermissions(), 'granted' => [],
        ]);
    }

    public function store()
    {
        $rules = [
            'name'        => 'required|alpha_dash|min_length[2]|max_length[50]|is_unique[roles.name]',
            'label'       => 'required|min_length[2]|max_length[100]',
            'description' => 'permit_empty|max_length[255]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $id = $this->roles->insert([
            'name'        => strtolower((string) $this->request->getPost('name')),
            'label'       => trim((string) $this->request->getPost('label')),
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            'own_only'    => $this->request->getPost('own_only') ? 1 : 0,
            'is_system'   => 0,
        ], true);
        $this->roles->syncPermissions((int) $id, (array) $this->request->getPost('permissions'));

        ActivityLogModel::record('role_create', $this->auth->user(), null, (string) $this->request->getPost('label'));

        return redirect()->to(site_url('admin/roles'))->with('success', 'Peran ditambahkan.');
    }

    public function edit(int $id)
    {
        $role = $this->roles->find($id);
        if ($role === null) {
            return redirect()->to(site_url('admin/roles'))->with('error', 'Peran tidak ditemukan.');
        }
        if ($role['name'] === 'administrator' && ! $this->auth->isAdministrator()) {
            return redirect()->to(site_url('admin/roles'))->with('error', 'Hanya administrator yang dapat mengubah peran administrator.');
        }

        return view('admin/roles/form', [
            'title' => 'Ubah Peran', 'role' => $role,
            'groups' => $this->roles->groupedPermissions(), 'granted' => $this->roles->permissionCodes($id),
        ]);
    }

    public function update(int $id)
    {
        $role = $this->roles->find($id);
        if ($role === null) {
            return redirect()->to(site_url('admin/roles'))->with('error', 'Peran tidak ditemukan.');
        }
        if ($role['name'] === 'administrator' && ! $this->auth->isAdministrator()) {
            return redirect()->to(site_url('admin/roles'))->with('error', 'Hanya administrator yang dapat mengubah peran administrator.');
        }

        $rules = [
            'label'       => 'required|min_length[2]|max_length[100]',
            'description' => 'permit_empty|max_length[255]',
        ];
        if (! $role['is_system']) {
            $rules['name'] = "required|alpha_dash|min_length[2]|max_length[50]|is_unique[roles.name,id,{$id}]";
        }
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $codes = array_values(array_intersect((array) $this->request->getPost('permissions'), array_keys(\App\Database\Seeds\InitialSeeder::PERMISSIONS)));

        // Peran yang dipegang akun aktif terakhir pengelola pengguna tidak boleh kehilangan izin ini.
        if (! in_array('users.manage', $codes, true) && in_array('users.manage', $this->roles->permissionCodes($id), true)) {
            $others = $this->roles->db->table('users')
                ->join('role_permissions rp', 'rp.role_id = users.role_id')
                ->join('permissions p', 'p.id = rp.permission_id')
                ->where('p.code', 'users.manage')->where('users.is_active', 1)->where('users.role_id !=', $id)
                ->countAllResults();
            if ($others === 0) {
                return redirect()->back()->withInput()->with('error', 'Izin "Mengelola pengguna" tidak dapat dicabut: tidak ada peran lain yang memilikinya.');
            }
        }
        // Administrator bawaan selalu memegang izin pengelolaan peran.
        if ($role['name'] === 'administrator') {
            $codes = array_values(array_unique(array_merge($codes, ['users.manage', 'roles.manage'])));
        }

        $data = [
            'label'       => trim((string) $this->request->getPost('label')),
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            'own_only'    => $this->request->getPost('own_only') ? 1 : 0,
        ];
        if (! $role['is_system']) {
            $data['name'] = strtolower((string) $this->request->getPost('name'));
        }
        $this->roles->update($id, $data);
        $this->roles->syncPermissions($id, $codes);

        ActivityLogModel::record('role_update', $this->auth->user(), null, $data['label'], ['permissions' => count($codes)]);

        return redirect()->to(site_url('admin/roles'))->with('success', 'Peran diperbarui.');
    }

    public function delete(int $id)
    {
        $role = $this->roles->find($id);
        if ($role === null) {
            return redirect()->to(site_url('admin/roles'))->with('error', 'Peran tidak ditemukan.');
        }
        if ($role['name'] === 'administrator' && ! $this->auth->isAdministrator()) {
            return redirect()->to(site_url('admin/roles'))->with('error', 'Hanya administrator yang dapat mengubah peran administrator.');
        }
        if ($role['is_system']) {
            return redirect()->to(site_url('admin/roles'))->with('error', 'Peran bawaan sistem tidak dapat dihapus.');
        }
        $used = $this->roles->db->table('users')->where('role_id', $id)->countAllResults();
        if ($used > 0) {
            return redirect()->to(site_url('admin/roles'))->with('error', "Peran masih dipakai oleh {$used} pengguna.");
        }

        $this->roles->delete($id);
        ActivityLogModel::record('role_delete', $this->auth->user(), null, $role['label']);

        return redirect()->to(site_url('admin/roles'))->with('success', 'Peran dihapus.');
    }
}
