<?php

namespace App\Controllers;

use CodeIgniter\Controller;

/**
 * Manifest PWA dinamis: nama aplikasi dan URL mengikuti konfigurasi/baseURL,
 * sehingga tetap benar bila aplikasi dipasang di subfolder.
 */
class Pwa extends Controller
{
    public function manifest()
    {
        helper('url');
        $cfg   = config('Filemanager');
        $icons = static fn (string $f): string => base_url('assets/icons/' . $f);

        $data = [
            'id'               => site_url('files'),
            'name'             => $cfg->appName,
            'short_name'       => mb_substr($cfg->appName, 0, 12),
            'description'      => 'Kelola, bagikan, dan amankan berkas Anda.',
            'lang'             => 'id',
            'start_url'        => site_url('files'),
            'scope'            => base_url('/'),
            'display'          => 'standalone',
            'background_color' => '#ffffff',
            'theme_color'      => '#0d6efd',
            'icons'            => [
                ['src' => $icons('icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $icons('icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $icons('icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ];

        return $this->response
            ->setHeader('Content-Type', 'application/manifest+json; charset=UTF-8')
            ->setHeader('Cache-Control', 'public, max-age=3600')
            ->setBody(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
