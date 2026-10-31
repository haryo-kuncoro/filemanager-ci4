<?php

namespace App\Controllers;

use App\Libraries\FileException;
use App\Libraries\StreamResponse;
use App\Models\ActivityLogModel;
use App\Models\ShareModel;

/**
 * Akses publik lewat tautan: /s/<token>. Tidak memerlukan login.
 *
 * - Tautan bisa memiliki kata sandi, tanggal kedaluwarsa, dan mode "hanya lihat".
 * - Untuk tautan folder, pengunjung hanya dapat menjelajah di dalam folder yang dibagikan
 *   (setiap id yang diminta diverifikasi berada di bawah node yang dibagikan).
 */
class Share extends BaseController
{
    protected $helpers = ['filemanager', 'form', 'url'];

    /** @return array{0: array|null, 1: array|null, 2: string|null} [share, node, pesanError] */
    private function resolve(string $token): array
    {
        $share = (new ShareModel())->findByToken($token);
        if ($share === null) {
            return [null, null, 'Tautan tidak ditemukan.'];
        }
        if (! (int) $share['is_active']) {
            return [$share, null, 'Tautan ini telah dinonaktifkan oleh pemiliknya.'];
        }
        if (ShareModel::isExpired($share)) {
            return [$share, null, 'Tautan ini sudah kedaluwarsa.'];
        }
        $node = service('files')->nodes()->findActive((int) $share['node_id']);
        if ($node === null) {
            return [$share, null, 'Konten yang dibagikan sudah tidak tersedia.'];
        }

        return [$share, $node, null];
    }

    private function unlocked(array $share): bool
    {
        if (empty($share['password_hash'])) {
            return true;
        }
        $ok = (array) session()->get('share_ok');

        return ! empty($ok[$share['token']]);
    }

    private function unavailable(string $message, int $status = 404)
    {
        return $this->response->setStatusCode($status)->setBody(view('share/unavailable', ['message' => $message]));
    }

    // ------------------------------------------------------------------

    public function view(string $token)
    {
        [$share, $node, $error] = $this->resolve($token);
        if ($error !== null) {
            return $this->unavailable($error, $share === null ? 404 : 410);
        }
        if (! $this->unlocked($share)) {
            return view('share/password', ['token' => $token, 'name' => $node['name']]);
        }

        $files = service('files');
        $nodes = $files->nodes();

        // Hitung kunjungan sekali per sesi.
        $viewed = (array) session()->get('share_viewed');
        if (empty($viewed[$token])) {
            $viewed[$token] = true;
            session()->set('share_viewed', $viewed);
            (new ShareModel())->builder()->where('id', (int) $share['id'])->set('view_count', 'view_count + 1', false)->update();
            ActivityLogModel::record('share_view', null, (int) $node['id'], $node['name'], ['token' => substr($token, 0, 8)]);
        }

        $data = [
            'share'   => $share,
            'token'   => $token,
            'node'    => $node,
            'allowDl' => (bool) $share['allow_download'],
            'kind'    => service('storage')->previewKind($node),
        ];

        if ($node['type'] === 'file') {
            return view('share/file', $data);
        }

        // ---- folder: jelajah di dalam subpohon ----
        $current = $node;
        $reqId   = (int) $this->request->getGet('f');
        if ($reqId > 0 && $reqId !== (int) $node['id']) {
            $cand = $nodes->findActive($reqId);
            if ($cand === null || $cand['type'] !== 'folder' || ! $nodes->isWithin($reqId, (int) $node['id'])) {
                return $this->unavailable('Folder tidak ditemukan pada tautan ini.');
            }
            $current = $cand;
        }

        $crumbs = [];
        foreach ($nodes->breadcrumb((int) $current['id']) as $c) {
            $crumbs[] = $c;
        }
        // Potong sehingga hanya mulai dari folder yang dibagikan.
        $start = 0;
        foreach ($crumbs as $i => $c) {
            if ((int) $c['id'] === (int) $node['id']) {
                $start = $i;
                break;
            }
        }
        $crumbs = array_slice($crumbs, $start);

        $data['current']  = $current;
        $data['crumbs']   = $crumbs;
        $data['children'] = $nodes->children((int) $current['id'], 'name', 'asc');

        return view('share/folder', $data);
    }

    public function unlock(string $token)
    {
        [$share, $node, $error] = $this->resolve($token);
        if ($error !== null) {
            return $this->unavailable($error, $share === null ? 404 : 410);
        }

        $cfg = config('Filemanager');
        $key = 'share_' . md5($token . '|' . $this->request->getIPAddress());
        $st  = cache($key);
        if (is_array($st) && ($st['count'] ?? 0) >= $cfg->shareMaxAttempts) {
            return redirect()->back()->with('error', 'Terlalu banyak percobaan. Coba lagi beberapa menit lagi.');
        }

        $pwd = (string) $this->request->getPost('password');
        if (! empty($share['password_hash']) && password_verify($pwd, $share['password_hash'])) {
            cache()->delete($key);
            $ok                 = (array) session()->get('share_ok');
            $ok[$share['token']] = true;
            session()->set('share_ok', $ok);

            return redirect()->to(site_url('s/' . $token));
        }

        cache()->save($key, ['count' => (int) ($st['count'] ?? 0) + 1], $cfg->shareLockSeconds);

        return redirect()->back()->with('error', 'Kata sandi salah.');
    }

    /** s/<token>/file/<id>/(download|preview|thumb) */
    public function file(string $token, int $id, string $mode)
    {
        [$share, $node, $error] = $this->resolve($token);
        if ($error !== null) {
            return $this->unavailable($error, $share === null ? 404 : 410);
        }
        if (! $this->unlocked($share)) {
            return redirect()->to(site_url('s/' . $token));
        }

        try {
            $files = service('files');
            $nodes = $files->nodes();

            $target = $nodes->findActive($id);
            if ($target === null || $target['type'] !== 'file' || ! $nodes->isWithin($id, (int) $node['id'])) {
                throw new FileException('Berkas tidak ditemukan pada tautan ini.', 404);
            }

            if ($mode === 'thumb') {
                $res = $files->thumbResponse($target, $this->request);

                return $res ?? $this->response->setStatusCode(404)->setBody('');
            }
            if ($mode === 'preview') {
                return $files->fileResponse($target, true, $this->request);
            }
            if ($mode === 'text') {
                $cfg = config('Filemanager');
                if (! in_array($target['ext'], $cfg->textExtensions, true)) {
                    throw new FileException('Jenis berkas ini tidak dapat dipratinjau sebagai teks.', 415);
                }
                $path = service('storage')->pathFor((string) $target['storage_name']);
                if (! is_file($path)) {
                    throw new FileException('Berkas fisik tidak ditemukan.', 404);
                }
                $fh   = fopen($path, 'rb');
                $data = (string) fread($fh, $cfg->textPreviewMaxBytes + 1);
                fclose($fh);
                $truncated = strlen($data) > $cfg->textPreviewMaxBytes;
                if ($truncated) {
                    $data = substr($data, 0, $cfg->textPreviewMaxBytes);
                }
                if (! mb_check_encoding($data, 'UTF-8')) {
                    $data = mb_convert_encoding($data, 'UTF-8', 'Windows-1252');
                }

                return $this->response->setJSON(['ok' => true, 'content' => $data, 'truncated' => $truncated]);
            }
            if ($mode !== 'download') {
                throw new FileException('Permintaan tidak valid.', 400);
            }
            if (! (int) $share['allow_download']) {
                throw new FileException('Pemilik tautan hanya mengizinkan melihat, bukan mengunduh.', 403);
            }

            (new ShareModel())->builder()->where('id', (int) $share['id'])->set('download_count', 'download_count + 1', false)->update();
            ActivityLogModel::record('share_download', null, (int) $target['id'], $target['name'], ['token' => substr($token, 0, 8)]);

            return $files->fileResponse($target, false, $this->request);
        } catch (FileException $e) {
            return $this->unavailable($e->getMessage(), $e->httpCode());
        }
    }

    /** s/<token>/zip?f=<id folder opsional> */
    public function zip(string $token)
    {
        [$share, $node, $error] = $this->resolve($token);
        if ($error !== null) {
            return $this->unavailable($error, $share === null ? 404 : 410);
        }
        if (! $this->unlocked($share)) {
            return redirect()->to(site_url('s/' . $token));
        }

        try {
            if (! (int) $share['allow_download']) {
                throw new FileException('Pemilik tautan hanya mengizinkan melihat, bukan mengunduh.', 403);
            }
            $files  = service('files');
            $nodes  = $files->nodes();
            $target = $node;
            $reqId  = (int) $this->request->getGet('f');
            if ($reqId > 0 && $reqId !== (int) $node['id']) {
                $target = $nodes->findActive($reqId);
                if ($target === null || ! $nodes->isWithin($reqId, (int) $node['id'])) {
                    throw new FileException('Item tidak ditemukan pada tautan ini.', 404);
                }
            }

            $path = $files->buildZip([$target]);
            (new ShareModel())->builder()->where('id', (int) $share['id'])->set('download_count', 'download_count + 1', false)->update();
            ActivityLogModel::record('share_download', null, (int) $target['id'], $target['name'] . '.zip', ['token' => substr($token, 0, 8)]);

            return StreamResponse::make($path, $target['name'] . '.zip', 'application/zip', $this->request, ['inline' => false, 'delete_after' => true]);
        } catch (FileException $e) {
            return $this->unavailable($e->getMessage(), $e->httpCode());
        }
    }
}
