<?php

namespace App\Libraries;

/**
 * Kebijakan kata sandi satu pintu (dipakai instalasi, profil, admin pengguna, reset).
 * Panjang: minimal config('Filemanager')->minPasswordLength, maksimal 72 BYTE (batas bcrypt;
 * lebih dari itu dipotong diam-diam sehingga bisa menipu pengguna).
 */
class PasswordPolicy
{
    /** Kata sandi yang sangat umum (dibandingkan setelah huruf kecil). */
    private const COMMON = [
        'password', 'password1', 'password12', 'password123', 'passw0rd', 'p@ssw0rd', 'p@ssword',
        'qwertyuiop', 'qwerty123', 'qwerty12345', '1234567890', '0123456789', '12345678910',
        'admin12345', 'admin123456', 'administrator', 'letmein123', 'welcome123', 'iloveyou123',
        'abc1234567', 'abcd123456', 'katasandi', 'katasandi123', 'indonesia123', 'rahasia123',
        'filemanager', 'file-manager', 'filemanager123',
    ];

    /** @return string|null pesan galat (bahasa Indonesia), atau null bila lolos */
    public static function check(string $password, string $username = '', string $name = ''): ?string
    {
        $min = (int) config('Filemanager')->minPasswordLength;

        if (mb_strlen($password) < $min) {
            return "Kata sandi minimal {$min} karakter.";
        }
        if (strlen($password) > 72) {
            return 'Kata sandi terlalu panjang (maksimal 72 byte).';
        }
        if (trim($password) === '' || trim($password) !== $password) {
            return 'Kata sandi tidak boleh diawali atau diakhiri spasi.';
        }
        if (count(array_unique(mb_str_split($password))) < 5) {
            return 'Kata sandi terlalu monoton. Gunakan lebih banyak karakter berbeda.';
        }
        $low = mb_strtolower($password);
        if (in_array($low, self::COMMON, true)) {
            return 'Kata sandi terlalu umum dan mudah ditebak.';
        }
        $user = mb_strtolower(trim($username));
        if ($user !== '' && mb_strlen($user) >= 3 && str_contains($low, $user)) {
            return 'Kata sandi tidak boleh mengandung username.';
        }
        if (! preg_match('/\p{L}/u', $password) || ! preg_match('/[\p{N}\p{P}\p{S}]/u', $password)) {
            return 'Kata sandi harus memuat huruf dan minimal satu angka atau simbol.';
        }

        return null;
    }
}
