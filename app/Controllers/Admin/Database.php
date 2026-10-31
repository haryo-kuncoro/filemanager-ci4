<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Backup;
use App\Libraries\Installer;
use App\Models\ActivityLogModel;

/**
 * Pembaruan skema database tanpa SSH: menjalankan migrasi yang tertunda dengan satu tombol.
 * Data yang ada TIDAK diganti — hanya migrasi baru yang dieksekusi. Khusus administrator.
 */
class Database extends BaseController
{
    public function index()
    {
        try {
            $status = Installer::migrationStatus();
            $error  = null;
        } catch (\Throwable $e) {
            $status = ['applied' => [], 'pending' => []];
            $error  = $e->getMessage();
        }

        return view('admin/database/index', [
            'title'     => 'Pembaruan Database',
            'applied'   => array_reverse($status['applied']),
            'pending'   => $status['pending'],
            'error'     => $error,
            'lastError' => Installer::lastMigrationError(),
            'zipOk'     => Backup::available(),
        ]);
    }

    public function migrate()
    {
        $back = redirect()->to(site_url('admin/database'));
        try {
            $before = Installer::migrationStatus()['pending'];
        } catch (\Throwable $e) {
            return $back->with('error', 'Tidak dapat membaca status migrasi: ' . $e->getMessage());
        }
        if ($before === []) {
            @file_put_contents(WRITEPATH . 'schema.version', Installer::latestMigration());
            @unlink(WRITEPATH . 'schema.version.error');

            return $back->with('success', 'Database sudah yang terbaru.');
        }

        $note = '';
        if ($this->request->getPost('backup_first') && Backup::available()) {
            try {
                $b    = service('backup')->create($this->auth->user(), 'backup-sebelum-migrasi');
                $note = ' Backup pengaman: ' . $b['name'] . '.';
            } catch (\Throwable $e) {
                return $back->with('error', 'Backup pengaman gagal, migrasi dibatalkan: ' . $e->getMessage());
            }
        }

        $r = Installer::runMigrations();
        ActivityLogModel::record('db_migrate', $this->auth->user(), null, $r['ok'] ? 'ok' : 'gagal', ['pending' => $before, 'error' => $r['ok'] ? null : $r['message']]);

        if (! $r['ok']) {
            return $back->with('error', 'Migrasi gagal: ' . $r['message'] . $note);
        }

        return $back->with('success', count($before) . ' migrasi dijalankan.' . $note);
    }
}
