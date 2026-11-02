<?php

namespace App\Models;

/**
 * Pengaturan key/value. Semua metode aman dipanggil sebelum migrasi dijalankan
 * (tabel belum ada) — hasilnya null / false, tidak melempar galat.
 */
class SettingModel
{
    /** @var array<string,?string> */
    private static array $cache = [];

    public static function get(string $name, ?string $default = null): ?string
    {
        if (array_key_exists($name, self::$cache)) {
            return self::$cache[$name] ?? $default;
        }
        try {
            $db = db_connect();
            if (! $db->tableExists('settings')) {
                return $default;
            }
            $row = $db->table('settings')->where('name', $name)->get()->getRowArray();
        } catch (\Throwable $e) {
            return $default;
        }
        self::$cache[$name] = $row['value'] ?? null;

        return self::$cache[$name] ?? $default;
    }

    public static function set(string $name, ?string $value): void
    {
        $db = db_connect();
        $t  = $db->table('settings');
        if ($t->where('name', $name)->countAllResults() > 0) {
            $db->table('settings')->where('name', $name)->update(['value' => $value, 'updated_at' => date('Y-m-d H:i:s')]);
        } else {
            $db->table('settings')->insert(['name' => $name, 'value' => $value, 'updated_at' => date('Y-m-d H:i:s')]);
        }
        self::$cache[$name] = $value;
    }

    public static function delete(string $name): void
    {
        db_connect()->table('settings')->where('name', $name)->delete();
        self::$cache[$name] = null;
    }

    // ---- logo ----

    /** URL logo (dengan versi untuk cache-busting) atau null bila belum diatur. */
    public static function logoUrl(): ?string
    {
        $v = self::get('logo_v');

        return ($v !== null && $v !== '' && self::get('logo_data') !== null)
            ? site_url('brand/logo') . '?v=' . rawurlencode($v)
            : null;
    }
}
