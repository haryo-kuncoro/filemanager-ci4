<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Header keamanan tambahan di atas filter 'secureheaders' bawaan CI4.
 * - CSP ringan (tanpa script-src agar skrip inline aplikasi tetap jalan): mencegah framing
 *   oleh situs lain, <base> palsu, form diarahkan keluar, dan plugin/object.
 * - HSTS hanya bila permintaan lewat HTTPS.
 * - Permissions-Policy: matikan fitur perangkat yang tidak dipakai.
 * Header yang sudah diatur sebelumnya (mis. CSP sandbox pratinjau berkas) tidak ditimpa.
 */
class HardenHeaders implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (! $response->hasHeader('Content-Security-Policy')) {
            $response->setHeader('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
        }
        if (! $response->hasHeader('Permissions-Policy')) {
            $response->setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        }
        if (\App\Libraries\Https::isSecure() && ! $response->hasHeader('Strict-Transport-Security')) {
            // Tanpa includeSubDomains/preload: aman untuk shared hosting yang berbagi domain induk.
            $response->setHeader('Strict-Transport-Security', 'max-age=15552000');
        }

        return $response;
    }
}
