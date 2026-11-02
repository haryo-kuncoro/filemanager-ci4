<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\FileException;
use App\Libraries\LogoImage;
use App\Models\ActivityLogModel;
use App\Models\SettingModel;

/** Pengaturan aplikasi. Rute dilindungi izin settings.manage. */
class Settings extends BaseController
{
    public function index()
    {
        $ready = db_connect()->tableExists('settings');

        return view('admin/settings/index', [
            'title'   => 'Pengaturan',
            'ready'   => $ready,
            'logoUrl' => $ready ? SettingModel::logoUrl() : null,
            'maxKb'   => (int) (LogoImage::MAX_BYTES / 1024),
        ]);
    }

    public function uploadLogo()
    {
        if (! db_connect()->tableExists('settings')) {
            return redirect()->back()->with('error', 'Struktur database belum diperbarui. Buka Administrasi → Pembaruan Database.');
        }
        $file = $this->request->getFile('logo');
        if ($file === null || ! $file->isValid() || $file->hasMoved()) {
            $msg = ($file !== null && $file->getError() === UPLOAD_ERR_INI_SIZE) ? 'Berkas melebihi batas unggah server.' : 'Pilih berkas logo terlebih dahulu.';

            return redirect()->back()->with('error', $msg);
        }

        try {
            $raw = (string) file_get_contents($file->getTempName());
            [$bin, $mime] = LogoImage::process($raw);
            if (strlen($bin) > LogoImage::MAX_BYTES) {
                throw new FileException('Ukuran logo maksimal 1 MB.', 422);
            }
        } catch (FileException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        SettingModel::set('logo_data', base64_encode($bin));
        SettingModel::set('logo_mime', $mime);
        SettingModel::set('logo_v', substr(sha1($bin), 0, 12));
        ActivityLogModel::record('settings_logo_set', $this->auth->user(), null, 'logo');

        return redirect()->to(site_url('admin/settings'))->with('success', 'Logo diperbarui.');
    }

    public function deleteLogo()
    {
        if (db_connect()->tableExists('settings')) {
            foreach (['logo_data', 'logo_mime', 'logo_v'] as $k) {
                SettingModel::delete($k);
            }
            ActivityLogModel::record('settings_logo_remove', $this->auth->user(), null, 'logo');
        }

        return redirect()->to(site_url('admin/settings'))->with('success', 'Logo dihapus.');
    }
}
