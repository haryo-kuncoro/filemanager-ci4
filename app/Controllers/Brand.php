<?php

namespace App\Controllers;

use App\Models\SettingModel;
use CodeIgniter\Controller;

/** Logo untuk halaman publik (login). Tidak butuh sesi. */
class Brand extends Controller
{
    public function logo()
    {
        $data = SettingModel::get('logo_data');
        $mime = SettingModel::get('logo_mime');
        $bin  = $data !== null ? base64_decode($data, true) : false;
        if ($bin === false || ! in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
            return $this->response->setStatusCode(404)->setBody('');
        }

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Content-Security-Policy', "default-src 'none'; sandbox")
            ->setHeader('Cache-Control', 'public, max-age=86400')
            ->setBody($bin);
    }
}
