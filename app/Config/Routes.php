<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

$routes->setAutoRoute(false); // semua rute didaftarkan eksplisit

// ---------------------------------------------------------------------
// Publik (tanpa login)
// ---------------------------------------------------------------------
$routes->get('login', 'Login::index');
$routes->post('login', 'Login::attempt');
$routes->post('logout', 'Login::logout');

// Pemasangan via web & reset darurat (terkunci otomatis / butuh token di .env)
$routes->get('install', 'Install::index');
$routes->post('install', 'Install::run');
$routes->get('install/reset', 'Install::resetForm');
$routes->post('install/reset', 'Install::resetRun');

// Tautan berbagi publik: /s/<token>
$routes->get('s/(:segment)', 'Share::view/$1');
$routes->post('s/(:segment)/unlock', 'Share::unlock/$1');
$routes->get('s/(:segment)/file/(:num)/(:segment)', 'Share::file/$1/$2/$3'); // download | preview | thumb
$routes->get('s/(:segment)/zip', 'Share::zip/$1');

// ---------------------------------------------------------------------
// Wajib login
// ---------------------------------------------------------------------
$routes->group('', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Files::home');
    $routes->get('files', 'Files::index', ['filter' => 'perm:files.view']);

    // Transfer berkas (bukan JSON)
    $routes->get('files/download/(:num)', 'Transfer::download/$1', ['filter' => 'perm:files.download']);
    $routes->get('files/zip', 'Transfer::zip', ['filter' => 'perm:files.download']);
    $routes->get('files/preview/(:num)', 'Transfer::preview/$1', ['filter' => 'perm:files.view']);
    $routes->get('files/thumb/(:num)', 'Transfer::thumb/$1', ['filter' => 'perm:files.view']);
    $routes->get('files/text/(:num)', 'Transfer::text/$1', ['filter' => 'perm:files.view']);

    // API JSON
    $routes->group('api', static function ($routes) {
        $routes->get('list', 'Api\Nodes::list', ['filter' => 'perm:files.view']);
        $routes->get('tree', 'Api\Nodes::tree', ['filter' => 'perm:files.view']);
        $routes->get('info/(:num)', 'Api\Nodes::info/$1', ['filter' => 'perm:files.view']);

        $routes->post('folders', 'Api\Nodes::createFolder', ['filter' => 'perm:files.create_folder']);
        $routes->post('rename', 'Api\Nodes::rename', ['filter' => 'perm:files.rename']);
        $routes->post('move', 'Api\Nodes::move', ['filter' => 'perm:files.move']);
        $routes->post('copy', 'Api\Nodes::copy', ['filter' => 'perm:files.copy']);
        $routes->post('lock', 'Api\Nodes::lock', ['filter' => 'perm:files.view']);
        $routes->post('delete', 'Api\Nodes::delete', ['filter' => 'perm:files.delete']);

        $routes->post('upload', 'Api\Upload::chunk', ['filter' => 'perm:files.upload']);
        $routes->post('upload/abort', 'Api\Upload::abort', ['filter' => 'perm:files.upload']);

        $routes->get('trash', 'Api\Trash::index', ['filter' => 'perm:trash.manage']);
        $routes->post('trash/restore', 'Api\Trash::restore', ['filter' => 'perm:trash.manage']);
        $routes->post('trash/purge', 'Api\Trash::purge', ['filter' => 'perm:trash.manage']);
        $routes->post('trash/empty', 'Api\Trash::empty', ['filter' => 'perm:trash.manage']);

        $routes->get('share/(:num)', 'Api\Shares::show/$1', ['filter' => 'perm:files.share']);
        $routes->post('share', 'Api\Shares::save', ['filter' => 'perm:files.share']);
        $routes->post('share/delete', 'Api\Shares::remove', ['filter' => 'perm:files.share']);
    });

    // Profil sendiri
    $routes->get('profile', 'Profile::index');
    $routes->post('profile', 'Profile::update');
    $routes->get('profile/password', 'Profile::password');
    $routes->post('profile/password', 'Profile::updatePassword');

    // Administrasi
    $routes->group('admin', static function ($routes) {
        $routes->get('users', 'Admin\Users::index', ['filter' => 'perm:users.manage']);
        $routes->get('users/new', 'Admin\Users::create', ['filter' => 'perm:users.manage']);
        $routes->post('users', 'Admin\Users::store', ['filter' => 'perm:users.manage']);
        $routes->get('users/(:num)/edit', 'Admin\Users::edit/$1', ['filter' => 'perm:users.manage']);
        $routes->post('users/(:num)', 'Admin\Users::update/$1', ['filter' => 'perm:users.manage']);
        $routes->post('users/(:num)/delete', 'Admin\Users::delete/$1', ['filter' => 'perm:users.manage']);

        $routes->get('roles', 'Admin\Roles::index', ['filter' => 'perm:roles.manage']);
        $routes->get('roles/new', 'Admin\Roles::create', ['filter' => 'perm:roles.manage']);
        $routes->post('roles', 'Admin\Roles::store', ['filter' => 'perm:roles.manage']);
        $routes->get('roles/(:num)/edit', 'Admin\Roles::edit/$1', ['filter' => 'perm:roles.manage']);
        $routes->post('roles/(:num)', 'Admin\Roles::update/$1', ['filter' => 'perm:roles.manage']);
        $routes->post('roles/(:num)/delete', 'Admin\Roles::delete/$1', ['filter' => 'perm:roles.manage']);

        $routes->get('shares', 'Admin\Shares::index', ['filter' => 'perm:shares.manage']);
        $routes->post('shares/(:num)/toggle', 'Admin\Shares::toggle/$1', ['filter' => 'perm:shares.manage']);
        $routes->post('shares/(:num)/delete', 'Admin\Shares::delete/$1', ['filter' => 'perm:shares.manage']);

        $routes->get('logs', 'Admin\Logs::index', ['filter' => 'perm:logs.view']);

        $routes->get('database', 'Admin\Database::index', ['filter' => 'admin']);
        $routes->post('database/migrate', 'Admin\Database::migrate', ['filter' => 'admin']);

        $routes->get('backups', 'Admin\Backups::index', ['filter' => 'admin']);
        $routes->post('backups', 'Admin\Backups::create', ['filter' => 'admin']);
        $routes->post('backups/upload', 'Admin\Backups::upload', ['filter' => 'admin']);
        $routes->get('backups/download/(:segment)', 'Admin\Backups::download/$1', ['filter' => 'admin']);
        $routes->post('backups/restore', 'Admin\Backups::restore', ['filter' => 'admin']);
        $routes->post('backups/delete', 'Admin\Backups::delete', ['filter' => 'admin']);
    });
});
