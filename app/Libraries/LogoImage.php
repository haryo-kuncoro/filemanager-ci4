<?php

namespace App\Libraries;

/**
 * Validasi + normalisasi gambar logo. Hanya PNG/JPEG/GIF/WebP (SVG sengaja TIDAK didukung
 * karena dapat memuat skrip). Bila GD tersedia gambar di-render ulang ke PNG (membuang metadata
 * dan muatan tersembunyi) dan diperkecil.
 */
class LogoImage
{
    public const MAX_BYTES = 1048576;   // 1 MB (berkas unggahan)
    public const MAX_SIDE  = 600;       // sisi terpanjang setelah diperkecil
    private const ALLOWED  = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'];

    /** @return array{0:string,1:string} [binary, mime]  @throws FileException */
    public static function process(string $binary): array
    {
        if ($binary === '' || strlen($binary) > self::MAX_BYTES) {
            throw new FileException('Ukuran logo maksimal 1 MB.', 422);
        }
        $info = @getimagesizefromstring($binary);
        if ($info === false || ! isset(self::ALLOWED[$info['mime'] ?? ''])) {
            throw new FileException('Berkas bukan gambar yang didukung. Gunakan PNG, JPG, GIF, atau WebP.', 422);
        }
        [$w, $h] = $info;
        if ($w < 16 || $h < 16 || $w > 5000 || $h > 5000) {
            throw new FileException('Dimensi logo harus antara 16 dan 5000 piksel.', 422);
        }

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagepng')) {
            return [$binary, $info['mime']]; // GD tidak ada: simpan apa adanya (sudah divalidasi)
        }

        $src = @imagecreatefromstring($binary);
        if ($src === false) {
            throw new FileException('Gambar tidak dapat dibaca.', 422);
        }
        $scale = min(1, self::MAX_SIDE / max($w, $h));
        $nw    = max(1, (int) round($w * $scale));
        $nh    = max(1, (int) round($h * $scale));
        $dst   = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagepng($dst, null, 6);
        $png = (string) ob_get_clean();

        return [$png, 'image/png'];
    }
}
