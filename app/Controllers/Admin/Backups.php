<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Backup;
use App\Libraries\FileException;
use App\Libraries\FileService;
use App\Libraries\StreamResponse;
use App\Models\ActivityLogModel;
use App\Models\UserModel;

/** Backup & pemulihan seluruh data. Rute dilindungi filter `admin` (hanya administrator). */
class Backups extends BaseController
{
    private function svc(): Backup
    {
        return service('backup');
    }

    public function index()
    {
        $storage = service('storage');

        return view('admin/backups/index', [
            'title'     => 'Backup & Pemulihan',
            'zipOk'     => Backup::available(),
            'backups'   => $this->svc()->all(),
            'chunkSize' => $storage->effectiveChunkSize(),
            'maxUpload' => config('Filemanager')->maxBackupUpload,
            'csrfName'  => csrf_token(),
            'csrfHeader' => config('Security')->headerName,
        ]);
    }

    public function create()
    {
        try {
            $r = $this->svc()->create($this->auth->user());
            ActivityLogModel::record('backup_create', $this->auth->user(), null, $r['name'], ['size' => $r['size']]);

            return redirect()->to(site_url('admin/backups'))->with('success', 'Backup dibuat: ' . $r['name'] . ' (' . FileService::formatBytes($r['size']) . ').');
        } catch (FileException $e) {
            return redirect()->to(site_url('admin/backups'))->with('error', $e->getMessage());
        }
    }

    public function download(string $name)
    {
        try {
            $path = $this->svc()->path($name);
            ActivityLogModel::record('backup_download', $this->auth->user(), null, $name);

            return StreamResponse::make($path, $name, 'application/zip', $this->request);
        } catch (FileException $e) {
            return redirect()->to(site_url('admin/backups'))->with('error', $e->getMessage());
        }
    }

    public function delete()
    {
        try {
            $name = (string) $this->request->getPost('name');
            $this->svc()->delete($name);
            ActivityLogModel::record('backup_delete', $this->auth->user(), null, $name);

            return redirect()->to(site_url('admin/backups'))->with('success', 'Backup dihapus.');
        } catch (FileException $e) {
            return redirect()->to(site_url('admin/backups'))->with('error', $e->getMessage());
        }
    }

    /** Unggah backup (per potongan) dari komputer, mis. untuk memulihkan setelah install ulang. */
    public function upload()
    {
        return $this->guard(function () {
            $file = $this->request->getFile('chunk');
            if ($file === null || ! $file->isValid()) {
                throw new FileException('Potongan unggahan tidak diterima.', 422);
            }
            $res = $this->svc()->acceptChunk(
                (int) $this->auth->id(),
                (string) $this->request->getPost('upload_id'),
                (int) $this->request->getPost('offset'),
                (int) $this->request->getPost('size'),
                $file->getTempName(),
                (int) $file->getSize(),
                config('Filemanager')->maxBackupUpload,
            );
            if (! empty($res['done'])) {
                ActivityLogModel::record('backup_upload', $this->auth->user(), null, $res['name']);
            }

            return $this->ok($res);
        });
    }

    public function restore()
    {
        $back = redirect()->to(site_url('admin/backups'));
        $name = (string) $this->request->getPost('name');

        if (trim((string) $this->request->getPost('confirm')) !== 'PULIHKAN') {
            return $back->with('error', 'Ketik PULIHKAN (huruf besar) untuk mengonfirmasi.');
        }
        $row = (new UserModel())->find((int) $this->auth->id());
        if ($row === null || ! password_verify((string) $this->request->getPost('password'), (string) $row['password_hash'])) {
            return $back->with('error', 'Kata sandi Anda salah.');
        }

        try {
            $actor = $this->auth->user();
            $r     = $this->svc()->restore($name, $actor);
            ActivityLogModel::record('backup_restore', $actor, null, $name, ['rows' => $r['rows'], 'files' => $r['files'], 'safety' => $r['safety']]);
        } catch (FileException $e) {
            return $back->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', 'Restore gagal: ' . $e->getMessage());

            return $back->with('error', 'Pemulihan gagal: ' . $e->getMessage());
        }

        // Data pengguna diganti seluruhnya -> sesi lama tidak lagi bermakna.
        $this->auth->logout();

        return redirect()->to(site_url('login'))->with('success', 'Pemulihan selesai (' . $r['rows'] . ' baris, ' . $r['files'] . ' berkas). Silakan login dengan akun dari backup.'
            . ($r['safety'] ? ' Backup pengaman: ' . $r['safety'] : ''));
    }
}
