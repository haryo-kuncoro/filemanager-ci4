<?php

namespace App\Controllers;

/**
 * Halaman utama file manager (antarmuka ala elFinder). Seluruh operasi data lewat /api/*.
 */
class Files extends BaseController
{
    public function home()
    {
        return redirect()->to(site_url($this->auth->can('files.view') ? 'files' : 'profile'));
    }

    public function index()
    {
        $cfg  = config('Filemanager');
        $user = $this->auth->user();

        service('files')->housekeeping();

        return view('files/index', [
            'title'  => 'Berkas',
            'fullHeight' => true,
            'client' => [
                'base'       => rtrim(site_url(), '/'),
                'csrfName'   => csrf_token(),
                'csrfHeader' => config('Security')->headerName,
                'csrfHash'   => csrf_hash(),
                'chunkSize'  => service('storage')->effectiveChunkSize(),
                'maxFile'    => $cfg->maxFileSize,
                'perms'      => $user['permissions'],
                'ownOnly'    => $this->auth->isOwnOnly(),
                'schemaWarn' => $this->auth->isAdministrator() && \App\Libraries\Installer::schemaOutdated(),
                'me'         => ['id' => (int) $user['id'], 'name' => $user['name']],
            ],
        ]);
    }
}
