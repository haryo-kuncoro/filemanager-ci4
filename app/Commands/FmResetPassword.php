<?php

namespace App\Commands;

use App\Models\UserModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark fm:reset-password <username> [password]
 * Mengatur ulang kata sandi (acak bila tidak diberikan) dan mewajibkan penggantian saat login.
 * Juga mengaktifkan kembali akun yang dinonaktifkan.
 */
class FmResetPassword extends BaseCommand
{
    protected $group       = 'FileManager';
    protected $name        = 'fm:reset-password';
    protected $description = 'Reset kata sandi pengguna dari command line.';
    protected $usage       = 'fm:reset-password <username> [password]';
    protected $arguments   = [
        'username' => 'Username yang akan direset',
        'password' => 'Kata sandi baru (opsional, default: acak)',
    ];

    public function run(array $params)
    {
        $username = $params[0] ?? CLI::prompt('Username', 'admin');
        $users    = new UserModel();
        $user     = $users->findByUsername($username);
        if ($user === null) {
            CLI::error("Pengguna '{$username}' tidak ditemukan.");

            return EXIT_ERROR;
        }

        $generated = ! isset($params[1]);
        $password  = $params[1] ?? bin2hex(random_bytes(6));

        $users->update((int) $user['id'], [
            'password_hash'        => password_hash($password, PASSWORD_DEFAULT),
            'must_change_password' => 1,
            'is_active'            => 1,
        ]);

        CLI::write("Kata sandi '{$username}' direset.", 'green');
        CLI::write('  Password baru: ' . $password . ($generated ? '  (acak — catat sekarang)' : ''));
        CLI::write('Pengguna wajib menggantinya saat login berikutnya.', 'yellow');
    }
}
