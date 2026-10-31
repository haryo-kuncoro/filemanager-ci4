<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\FileException;

/**
 * Upload per-potongan (chunk) agar tidak terbentur batas upload_max_filesize / post_max_size.
 *
 * Form-data per potongan: chunk (file), upload_id (hex), name, size (total), offset, parent_id.
 */
class Upload extends BaseController
{
    public function chunk()
    {
        return $this->guard(function () {
            $file = $this->request->getFile('chunk');
            if ($file === null) {
                throw new FileException('Potongan berkas tidak ditemukan pada permintaan.', 422);
            }
            if (! $file->isValid()) {
                throw new FileException($this->uploadErrorMessage($file->getError()), 400);
            }

            $meta = [
                'upload_id' => (string) $this->request->getPost('upload_id'),
                'name'      => (string) $this->request->getPost('name'),
                'size'      => (int) $this->request->getPost('size'),
                'offset'    => (int) $this->request->getPost('offset'),
                'parent_id' => $this->folderId($this->request->getPost('parent_id')),
            ];

            $result = service('files')->acceptChunk($meta, $file->getRealPath(), (int) $file->getSize());

            return $this->ok($result);
        });
    }

    public function abort()
    {
        return $this->guard(function () {
            $in = $this->input();
            service('files')->abortUpload((string) ($in['upload_id'] ?? ''));

            return $this->ok();
        });
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Potongan melebihi batas upload server (upload_max_filesize / post_max_size).',
            UPLOAD_ERR_PARTIAL                        => 'Upload tidak lengkap, coba lagi.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'Server tidak dapat menulis berkas sementara.',
            default                                   => 'Upload gagal (kode ' . $code . ').',
        };
    }
}
