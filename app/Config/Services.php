<?php

namespace Config;

use App\Libraries\Auth;
use App\Libraries\FileService;
use App\Libraries\Storage;
use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 */
class Services extends BaseService
{
    /** Autentikasi + RBAC. */
    public static function auth(bool $getShared = true): Auth
    {
        if ($getShared) {
            return static::getSharedInstance('auth');
        }

        return new Auth();
    }

    /** Pengelola penyimpanan fisik di WRITEPATH. */
    public static function storage(bool $getShared = true): Storage
    {
        if ($getShared) {
            return static::getSharedInstance('storage');
        }

        return new Storage(config('Filemanager'));
    }

    /** Logika bisnis file manager (folder, upload, salin, pindah, sampah, zip). */
    public static function files(bool $getShared = true): FileService
    {
        if ($getShared) {
            return static::getSharedInstance('files');
        }

        return new FileService(static::storage(), static::auth(), config('Filemanager'));
    }
}
