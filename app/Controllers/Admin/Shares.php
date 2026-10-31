<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ActivityLogModel;
use App\Models\ShareModel;

/** Daftar seluruh tautan publik; admin dapat menonaktifkan / mencabutnya. */
class Shares extends BaseController
{
    public function index()
    {
        return view('admin/shares/index', ['title' => 'Tautan Dibagikan', 'shares' => (new ShareModel())->listAll()]);
    }

    public function toggle(int $id)
    {
        $model = new ShareModel();
        $share = $model->find($id);
        if ($share === null) {
            return redirect()->to(site_url('admin/shares'))->with('error', 'Tautan tidak ditemukan.');
        }
        $model->update($id, ['is_active' => $share['is_active'] ? 0 : 1]);
        ActivityLogModel::record($share['is_active'] ? 'share_disable' : 'share_enable', $this->auth->user(), (int) $share['node_id']);

        return redirect()->to(site_url('admin/shares'))->with('success', 'Status tautan diperbarui.');
    }

    public function delete(int $id)
    {
        $model = new ShareModel();
        $share = $model->find($id);
        if ($share === null) {
            return redirect()->to(site_url('admin/shares'))->with('error', 'Tautan tidak ditemukan.');
        }
        $model->delete($id);
        ActivityLogModel::record('share_delete', $this->auth->user(), (int) $share['node_id']);

        return redirect()->to(site_url('admin/shares'))->with('success', 'Tautan dicabut.');
    }
}
