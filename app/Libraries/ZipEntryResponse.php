<?php

namespace App\Libraries;

use CodeIgniter\HTTP\Response;

/** Men-stream satu entri ZIP (mis. berkas di dalam backup) tanpa mengekstraknya ke disk. */
class ZipEntryResponse extends Response
{
    private ?\ZipArchive $zip = null;
    private string $entry     = '';

    /** @param array{inline?:bool, csp?:string|null} $opts */
    public static function make(string $zipPath, string $entry, string $downloadName, string $mime, array $opts = []): self
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::RDONLY) !== true) {
            throw new FileException('Backup tidak dapat dibuka.', 500);
        }
        $st = $zip->statName($entry);
        if ($st === false) {
            $zip->close();
            throw new FileException('Isi berkas tidak ada di dalam backup ini.', 404);
        }

        $res        = new self(config('App'));
        $res->zip   = $zip;
        $res->entry = $entry;
        $res->setStatusCode(200);
        $res->setHeader('Content-Type', $mime);
        $res->setHeader('Content-Length', (string) (int) $st['size']);
        $res->setHeader('X-Content-Type-Options', 'nosniff');
        $res->setHeader('Content-Disposition', StreamResponse::disposition($downloadName, (bool) ($opts['inline'] ?? false)));
        $res->setHeader('Referrer-Policy', 'no-referrer');
        $res->setHeader('Cache-Control', 'private, no-store');
        if (! empty($opts['csp'])) {
            $res->setHeader('Content-Security-Policy', (string) $opts['csp']);
        }

        return $res;
    }

    public function sendBody()
    {
        if ($this->zip === null) {
            return $this;
        }
        @set_time_limit(0);
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $in = $this->zip->getStream($this->entry);
        if ($in !== false) {
            while (! feof($in)) {
                $chunk = fread($in, 65536);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                echo $chunk;
                flush();
                if (connection_aborted()) {
                    break;
                }
            }
            fclose($in);
        }
        $this->zip->close();
        $this->zip = null;

        return $this;
    }
}
