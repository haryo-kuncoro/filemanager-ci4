<?php

namespace App\Controllers;

use App\Libraries\FileException;
use App\Libraries\StreamResponse;
use App\Models\ActivityLogModel;

/**
 * Unduh / pratinjau / thumbnail / teks / ZIP untuk pengguna yang login.
 * Berkas fisik berada di WRITEPATH dan hanya dapat dibaca lewat controller ini.
 */
class Transfer extends BaseController
{
    public function download(int $id)
    {
        return $this->serve($id, false);
    }

    public function preview(int $id)
    {
        return $this->serve($id, true);
    }

    private function serve(int $id, bool $inline)
    {
        try {
            $files = service('files');
            $node  = $files->need($id);
            $res   = $files->fileResponse($node, $inline, $this->request);
            if (! $inline) {
                ActivityLogModel::record('download', $this->auth->user(), $id, $node['name']);
            }

            return $res;
        } catch (FileException $e) {
            return $this->response->setStatusCode($e->httpCode())->setBody(view('errors/message', ['message' => $e->getMessage(), 'code' => $e->httpCode()]));
        }
    }

    public function thumb(int $id)
    {
        try {
            $files = service('files');
            $res   = $files->thumbResponse($files->need($id), $this->request);
            if ($res !== null) {
                return $res;
            }
        } catch (FileException) {
            // jatuh ke 404 di bawah
        }

        return $this->response->setStatusCode(404)->setBody('');
    }

    /** Pratinjau teks sebagai JSON (tidak pernah dirender sebagai HTML). */
    public function text(int $id)
    {
        return $this->guard(function () use ($id) {
            $cfg   = config('Filemanager');
            $files = service('files');
            $node  = $files->need($id);

            if ($node['type'] !== 'file' || ! in_array($node['ext'], $cfg->textExtensions, true)) {
                throw new FileException('Jenis berkas ini tidak dapat dipratinjau sebagai teks.', 415);
            }
            $path = service('storage')->pathFor((string) $node['storage_name']);
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

            return $this->ok(['name' => $node['name'], 'content' => $data, 'truncated' => $truncated, 'size' => (int) $node['size']]);
        });
    }

    /** GET files/zip?ids=1,2,3 */
    public function zip()
    {
        try {
            $files = service('files');
            $ids   = array_filter(array_map('intval', explode(',', (string) $this->request->getGet('ids'))));
            if ($ids === []) {
                throw new FileException('Tidak ada item yang dipilih.', 422);
            }

            $nodes = [];
            foreach (array_unique($ids) as $id) {
                $nodes[] = $files->need($id);
            }
            $path = $files->buildZip($nodes);

            $name = (count($nodes) === 1 && $nodes[0]['type'] === 'folder')
                ? $nodes[0]['name'] . '.zip'
                : 'berkas-' . date('Ymd-His') . '.zip';

            ActivityLogModel::record('download_zip', $this->auth->user(), count($nodes) === 1 ? (int) $nodes[0]['id'] : null, $name, ['items' => count($nodes)]);

            return StreamResponse::make($path, $name, 'application/zip', $this->request, ['inline' => false, 'delete_after' => true]);
        } catch (FileException $e) {
            return $this->response->setStatusCode($e->httpCode())->setBody(view('errors/message', ['message' => $e->getMessage(), 'code' => $e->httpCode()]));
        }
    }
}
