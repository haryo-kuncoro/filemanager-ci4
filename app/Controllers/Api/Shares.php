<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\FileException;
use App\Models\ActivityLogModel;
use App\Models\ShareModel;

/**
 * Tautan publik per item: satu tautan per node, dengan opsi kata sandi, kedaluwarsa,
 * dan izin unduh.
 */
class Shares extends BaseController
{
    public function show(int $nodeId)
    {
        return $this->guard(function () use ($nodeId) {
            $node = service('files')->need($nodeId);
            if (! $this->auth->canModifyNode($node, 'files.share')) {
                throw new FileException('Anda tidak berhak membagikan item ini.', 403);
            }
            $share = (new ShareModel())->findByNode($nodeId);

            return $this->ok(['share' => $share ? self::present($share) : null]);
        });
    }

    public function save()
    {
        return $this->guard(function () {
            $in   = $this->input();
            $node = service('files')->need((int) ($in['node_id'] ?? 0));
            if (! $this->auth->canModifyNode($node, 'files.share')) {
                throw new FileException('Anda tidak berhak membagikan item ini.', 403);
            }

            $model    = new ShareModel();
            $existing = $model->findByNode((int) $node['id']);

            $data = [
                'allow_download' => ! empty($in['allow_download']) ? 1 : 0,
                'is_active'      => array_key_exists('is_active', $in) ? (! empty($in['is_active']) ? 1 : 0) : 1,
            ];

            // Kedaluwarsa (opsional): "YYYY-MM-DDTHH:MM" atau kosong.
            $exp = trim((string) ($in['expires_at'] ?? ''));
            if ($exp === '') {
                $data['expires_at'] = null;
            } else {
                $ts = strtotime($exp);
                if ($ts === false) {
                    throw new FileException('Format tanggal kedaluwarsa tidak valid.', 422);
                }
                if ($ts <= time()) {
                    throw new FileException('Tanggal kedaluwarsa harus di masa depan.', 422);
                }
                $data['expires_at'] = date('Y-m-d H:i:s', $ts);
            }

            // Kata sandi (opsional).
            $password = (string) ($in['password'] ?? '');
            if (! empty($in['clear_password'])) {
                $data['password_hash'] = null;
            } elseif ($password !== '') {
                if (mb_strlen($password) < 4) {
                    throw new FileException('Kata sandi tautan minimal 4 karakter.', 422);
                }
                $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }

            if ($existing) {
                $model->update((int) $existing['id'], $data);
                $action = 'share_update';
            } else {
                $model->insert($data + [
                    'node_id' => (int) $node['id'], 'token' => ShareModel::newToken(),
                    'created_by' => $this->auth->id(), 'view_count' => 0, 'download_count' => 0,
                ]);
                $action = 'share_create';
            }
            ActivityLogModel::record($action, $this->auth->user(), (int) $node['id'], $node['name']);

            return $this->ok(['share' => self::present($model->findByNode((int) $node['id']))]);
        });
    }

    public function remove()
    {
        return $this->guard(function () {
            $in   = $this->input();
            $node = service('files')->need((int) ($in['node_id'] ?? 0));
            if (! $this->auth->canModifyNode($node, 'files.share')) {
                throw new FileException('Anda tidak berhak mengubah tautan ini.', 403);
            }
            (new ShareModel())->where('node_id', (int) $node['id'])->delete();
            ActivityLogModel::record('share_delete', $this->auth->user(), (int) $node['id'], $node['name']);

            return $this->ok();
        });
    }

    public static function present(array $s): array
    {
        return [
            'id'             => (int) $s['id'],
            'url'            => fm_share_url($s['token']),
            'has_password'   => ! empty($s['password_hash']),
            'expires_at'     => $s['expires_at'] ? date('Y-m-d\TH:i', strtotime($s['expires_at'])) : null,
            'expired'        => ShareModel::isExpired($s),
            'allow_download' => (bool) $s['allow_download'],
            'is_active'      => (bool) $s['is_active'],
            'view_count'     => (int) $s['view_count'],
            'download_count' => (int) $s['download_count'],
        ];
    }
}
