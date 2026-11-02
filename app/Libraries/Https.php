<?php

namespace App\Libraries;

/** Deteksi HTTPS tanpa bergantung pada service CI (aman dipanggil dari Config). */
class Https
{
    public static function isSecure(): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }

        return (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? '') === '443'
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}
