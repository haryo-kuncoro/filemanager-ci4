<?php

namespace App\Libraries;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Response;

/**
 * Response yang men-stream berkas dari disk (hemat memori) dengan dukungan
 * HTTP Range (seek video/audio, resume unduhan).
 */
class StreamResponse extends Response
{
    private string $filePath = '';
    private int $rangeStart  = 0;
    private int $rangeEnd    = 0;
    private bool $deleteAfterSend = false;

    /**
     * @param array{inline?:bool, delete_after?:bool, cache?:int, csp?:string|null} $opts
     */
    public static function make(string $path, string $downloadName, string $mime, ?IncomingRequest $request, array $opts = []): self
    {
        $inline = (bool) ($opts['inline'] ?? false);
        $size   = (int) filesize($path);

        $res = new self(config('App'));
        $res->filePath        = $path;
        $res->deleteAfterSend = (bool) ($opts['delete_after'] ?? false);

        $status = 200;
        $start  = 0;
        $end    = max(0, $size - 1);

        $rangeHeader = $request?->getHeaderLine('Range') ?? '';
        if ($rangeHeader !== '' && $size > 0 && preg_match('/^bytes=(\d*)-(\d*)$/', trim($rangeHeader), $m)) {
            if ($m[1] === '' && $m[2] !== '') {            // bytes=-N  (N byte terakhir)
                $start = max(0, $size - (int) $m[2]);
            } elseif ($m[1] !== '') {                       // bytes=N-  atau  N-M
                $start = (int) $m[1];
                if ($m[2] !== '') {
                    $end = min($end, (int) $m[2]);
                }
            }
            if ($start > $end || $start >= $size) {
                $res->setStatusCode(416);
                $res->setHeader('Content-Range', 'bytes */' . $size);
                $res->filePath = '';
                if ($res->deleteAfterSend) {
                    @unlink($path);
                }

                return $res;
            }
            $status = 206;
            $res->setHeader('Content-Range', "bytes {$start}-{$end}/{$size}");
        }

        $res->rangeStart = $start;
        $res->rangeEnd   = $end;
        $length          = $size > 0 ? ($end - $start + 1) : 0;

        $res->setStatusCode($status);
        $res->setHeader('Content-Type', $mime);
        $res->setHeader('Content-Length', (string) $length);
        $res->setHeader('Accept-Ranges', 'bytes');
        $res->setHeader('X-Content-Type-Options', 'nosniff');
        $res->setHeader('Content-Disposition', self::disposition($downloadName, $inline));
        $res->setHeader('Referrer-Policy', 'no-referrer');

        $cache = (int) ($opts['cache'] ?? 0);
        $res->setHeader('Cache-Control', $cache > 0 ? "private, max-age={$cache}" : 'private, no-cache, must-revalidate');

        if (! empty($opts['csp'])) {
            $res->setHeader('Content-Security-Policy', (string) $opts['csp']);
        }

        return $res;
    }

    /** Content-Disposition aman: fallback ASCII + filename* UTF-8 (RFC 5987). */
    public static function disposition(string $name, bool $inline): string
    {
        $ascii = preg_replace('/[^\x20-\x7e]/', '_', $name) ?? 'file';
        $ascii = str_replace(['"', '\\', '/', ';'], '_', $ascii);

        return ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name);
    }

    public function sendBody()
    {
        if ($this->filePath === '' || ! is_file($this->filePath)) {
            return $this;
        }

        @set_time_limit(0);
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        $fh = fopen($this->filePath, 'rb');
        if ($fh !== false) {
            if ($this->rangeStart > 0) {
                fseek($fh, $this->rangeStart);
            }
            $remaining = $this->rangeEnd - $this->rangeStart + 1;
            while ($remaining > 0 && ! feof($fh)) {
                $chunk = fread($fh, (int) min(65536, $remaining));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                echo $chunk;
                $remaining -= strlen($chunk);
                flush();
                if (connection_aborted()) {
                    break;
                }
            }
            fclose($fh);
        }

        if ($this->deleteAfterSend) {
            @unlink($this->filePath);
        }

        return $this;
    }
}
