<?php

use App\Libraries\FileService;

if (! function_exists('auth_user')) {
    /** Pengguna yang sedang login (dengan 'role' dan 'permissions'). */
    function auth_user(): ?array
    {
        return service('auth')->user();
    }
}

if (! function_exists('can')) {
    /** Apakah pengguna saat ini punya izin $code? */
    function can(string $code): bool
    {
        return service('auth')->can($code);
    }
}

if (! function_exists('fm_bytes')) {
    function fm_bytes(int|string|null $bytes): string
    {
        return FileService::formatBytes((int) $bytes);
    }
}

if (! function_exists('fm_date')) {
    function fm_date(?string $date, string $format = 'd M Y H:i'): string
    {
        if (empty($date)) {
            return '-';
        }
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $ts     = strtotime($date);
        $out    = date($format, $ts);

        return str_replace(date('M', $ts), $months[(int) date('n', $ts) - 1], $out);
    }
}

if (! function_exists('fm_icon')) {
    /** Kelas Bootstrap Icons berdasarkan jenis & ekstensi. */
    function fm_icon(string $type, string $ext = '', string $mime = ''): string
    {
        if ($type === 'folder') {
            return 'bi-folder-fill';
        }
        $ext = strtolower($ext);

        return match (true) {
            str_starts_with($mime, 'image/')                                    => 'bi-file-earmark-image',
            str_starts_with($mime, 'video/')                                    => 'bi-file-earmark-play',
            str_starts_with($mime, 'audio/')                                    => 'bi-file-earmark-music',
            $ext === 'pdf'                                                      => 'bi-file-earmark-pdf',
            in_array($ext, ['doc', 'docx', 'odt', 'rtf'], true)                 => 'bi-file-earmark-word',
            in_array($ext, ['xls', 'xlsx', 'ods', 'csv'], true)                 => 'bi-file-earmark-excel',
            in_array($ext, ['ppt', 'pptx', 'odp'], true)                        => 'bi-file-earmark-ppt',
            in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'], true)      => 'bi-file-earmark-zip',
            in_array($ext, ['txt', 'md', 'log'], true)                          => 'bi-file-earmark-text',
            in_array($ext, ['js', 'ts', 'css', 'html', 'json', 'xml', 'sql', 'py', 'java', 'c', 'cpp', 'go', 'rs', 'yml', 'yaml'], true) => 'bi-file-earmark-code',
            default                                                             => 'bi-file-earmark',
        };
    }
}

if (! function_exists('fm_share_url')) {
    function fm_share_url(string $token): string
    {
        return site_url('s/' . $token);
    }
}
