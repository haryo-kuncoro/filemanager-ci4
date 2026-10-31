<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ActivityLogModel;

class Logs extends BaseController
{
    public function index()
    {
        $model  = new ActivityLogModel();
        $action = trim((string) $this->request->getGet('action'));
        $q      = trim((string) $this->request->getGet('q'));

        if ($action !== '') {
            $model->where('action', $action);
        }
        if ($q !== '') {
            $model->groupStart()->like('username', $q)->orLike('target', $q)->orLike('ip', $q)->groupEnd();
        }

        $logs = $model->orderBy('id', 'DESC')->paginate(30);

        $actions = array_column(
            $model->db->table('activity_logs')->select('action')->distinct()->orderBy('action')->get()->getResultArray(),
            'action'
        );

        return view('admin/logs/index', [
            'title'   => 'Log Aktivitas',
            'logs'    => $logs,
            'pager'   => $model->pager,
            'actions' => $actions,
            'action'  => $action,
            'q'       => $q,
        ]);
    }
}
