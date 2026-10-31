<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark fm:cleanup
 * Menghapus sampah yang melewati masa simpan dan berkas sementara (potongan upload / ZIP) yang basi.
 * Jadwalkan lewat cron, mis. setiap hari pukul 02:00.
 */
class FmCleanup extends BaseCommand
{
    protected $group       = 'FileManager';
    protected $name        = 'fm:cleanup';
    protected $description = 'Bersihkan sampah kedaluwarsa dan berkas sementara.';

    public function run(array $params)
    {
        $cfg   = config('Filemanager');
        $files = service('files');

        $trash = $files->purgeOlderThan($cfg->trashRetentionDays);
        $tmp   = service('storage')->cleanTmp(86400);

        CLI::write("Sampah dihapus permanen : {$trash} item (lebih tua dari {$cfg->trashRetentionDays} hari)");
        CLI::write("Berkas sementara dibuang: {$tmp}");
    }
}
